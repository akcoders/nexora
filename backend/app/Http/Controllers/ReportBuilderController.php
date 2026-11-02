<?php

namespace App\Http\Controllers;

use App\Models\SavedReport;
use App\Services\Reports\ReportBuilder;
use App\Services\Reports\ReportDatasets;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Self-service report builder: pick a data source, group / measure / filter it, preview the
 * chart or table, then save it, share it and pin it to the dashboard.
 */
class ReportBuilderController extends Controller
{
    public function __construct(private readonly ReportBuilder $builder) {}

    public function index(Request $request): View
    {
        $datasets = ReportDatasets::availableFor($request->user());
        $saved = SavedReport::query()->visibleTo($request->user())->with('user:id,name')->latest('updated_at')->get()
            ->filter(fn (SavedReport $report) => isset($datasets[$report->dataset]))->values();
        $selected = $request->filled('report') ? $saved->firstWhere('id', $request->integer('report')) : null;

        return view('reports.builder', [
            'catalogue' => ReportDatasets::catalogueFor($request->user()),
            'ui' => ReportBuilder::uiOptions(),
            'saved' => $saved,
            'selected' => $selected,
            'starter' => $request->only(['dataset', 'group_by', 'chart']),
        ]);
    }

    public function preview(Request $request): JsonResponse
    {
        $config = $this->builder->normalize($request->all(), $request->user());

        return response()->json(['config' => $config, 'result' => $this->builder->run($config, $request->user())]);
    }

    public function store(Request $request): JsonResponse|RedirectResponse
    {
        $details = $this->validateDetails($request);
        $config = $this->builder->normalize((array) $request->input('config', []), $request->user());
        $report = SavedReport::create($details + ['user_id' => $request->user()->id, 'dataset' => $config['dataset'], 'config' => $config]);

        return $this->respond($request, $report, 'Report "'.$report->name.'" saved.', 201);
    }

    public function update(Request $request, SavedReport $savedReport): JsonResponse|RedirectResponse
    {
        abort_unless($savedReport->isOwnedBy($request->user()), 403, 'Only the owner can change this report. Save a copy instead.');
        $details = $this->validateDetails($request);
        $config = $this->builder->normalize((array) $request->input('config', []), $request->user());
        $savedReport->update($details + ['dataset' => $config['dataset'], 'config' => $config]);

        return $this->respond($request, $savedReport, 'Report "'.$savedReport->name.'" updated.');
    }

    public function destroy(Request $request, SavedReport $savedReport): RedirectResponse
    {
        abort_unless($savedReport->isOwnedBy($request->user()), 403);
        $savedReport->delete();

        return redirect()->route('reports.builder')->with('success', 'Report deleted.');
    }

    public function pin(Request $request, SavedReport $savedReport): JsonResponse|RedirectResponse
    {
        abort_unless($savedReport->isOwnedBy($request->user()), 403);
        $savedReport->update(['pinned' => ! $savedReport->pinned]);

        return $this->respond($request, $savedReport, $savedReport->pinned ? 'Pinned to your dashboard.' : 'Removed from your dashboard.');
    }

    /** Fresh data for a saved report, used by dashboard widgets. */
    public function data(Request $request, SavedReport $savedReport): JsonResponse
    {
        abort_unless($savedReport->isOwnedBy($request->user()) || $savedReport->is_shared, 403);
        $config = $this->builder->normalize($savedReport->config, $request->user());

        return response()->json(['report' => ['id' => $savedReport->id, 'name' => $savedReport->name], 'result' => $this->builder->run($config, $request->user())]);
    }

    /** @return array<string, mixed> */
    private function validateDetails(Request $request): array
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:500'],
            'is_shared' => ['nullable', 'boolean'],
            'pinned' => ['nullable', 'boolean'],
            'config' => ['required', 'array'],
        ]);

        return [
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'is_shared' => (bool) ($validated['is_shared'] ?? false),
            'pinned' => (bool) ($validated['pinned'] ?? false),
        ];
    }

    private function respond(Request $request, SavedReport $report, string $message, int $status = 200): JsonResponse|RedirectResponse
    {
        if ($request->expectsJson()) {
            return response()->json(['message' => $message, 'report' => $report->only(['id', 'name', 'description', 'is_shared', 'pinned', 'dataset'])], $status);
        }

        return redirect()->route('reports.builder', ['report' => $report->id])->with('success', $message);
    }
}
