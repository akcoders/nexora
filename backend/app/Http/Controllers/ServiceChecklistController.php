<?php

namespace App\Http\Controllers;

use App\Models\ProductCategory;
use App\Models\ServiceChecklist;
use App\Models\ServiceType;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ServiceChecklistController extends Controller
{
    public function index(): View
    {
        return view('checklists.index', [
            'checklists' => ServiceChecklist::with(['category', 'serviceType', 'items'])->latest()->paginate(12),
            'categories' => ProductCategory::orderBy('name')->get(),
            'serviceTypes' => ServiceType::query()->where('active', true)->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('checklists.create'), 403);
        $data = $this->validateChecklist($request);
        $data['phase'] ??= 'pre';

        DB::transaction(function () use ($data) {
            $checklist = ServiceChecklist::create(collect($data)->except('items')->all());
            $this->replaceItems($checklist, $data['items']);
        });

        return back()->with('success', 'Service checklist created.');
    }

    public function update(Request $request, ServiceChecklist $checklist): RedirectResponse
    {
        abort_unless($request->user()->can('checklists.update'), 403);
        $data = $this->validateChecklist($request);
        $data['phase'] ??= 'pre';

        DB::transaction(function () use ($checklist, $data): void {
            $checklist->update(collect($data)->except('items')->all());
            $this->replaceItems($checklist, $data['items']);
        });

        return back()->with('success', 'Service checklist updated.');
    }

    public function destroy(Request $request, ServiceChecklist $checklist): RedirectResponse
    {
        abort_unless($request->user()->can('checklists.delete'), 403);
        $checklist->delete();

        return back()->with('success', 'Checklist removed.');
    }

    /** @return array<string, mixed> */
    private function validateChecklist(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'product_category_id' => ['nullable', 'exists:product_categories,id'],
            'phase' => ['nullable', 'in:pre,post,both'],
            'service_type_id' => ['nullable', 'exists:service_types,id'],
            'equipment_type' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:1000'],
            'frequency' => ['nullable', 'string', 'max:100'],
            'estimated_minutes' => ['nullable', 'integer', 'min:1', 'max:1440'],
            'required_skill' => ['nullable', 'string', 'max:255'],
            'safety_notes' => ['nullable', 'string', 'max:3000'],
            'items' => ['required', 'string', 'max:20000'],
        ]);
    }

    private function replaceItems(ServiceChecklist $checklist, string $itemsText): void
    {
        $checklist->items()->delete();
        collect(preg_split('/\r\n|\r|\n/', $itemsText))
            ->map(fn (string $item): string => trim($item))
            ->filter()
            ->values()
            ->each(function (string $line, int $index) use ($checklist): void {
                $photoRequired = str_ends_with(strtolower($line), '[photo]');
                $label = trim(preg_replace('/\s*\[photo\]\s*$/i', '', $line));
                $checklist->items()->create([
                    'label' => $label,
                    'response_type' => 'condition',
                    'required' => true,
                    'photo_required' => $photoRequired,
                    'remark_allowed' => true,
                    'active' => true,
                    'sort_order' => $index,
                ]);
            });
    }
}
