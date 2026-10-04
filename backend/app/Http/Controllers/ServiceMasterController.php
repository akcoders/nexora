<?php

namespace App\Http\Controllers;

use App\Models\InspectionCondition;
use App\Models\ServiceCatalogItem;
use App\Models\ServiceMasterOption;
use App\Models\ServiceType;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ServiceMasterController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()->can('checklists.read'), 403);

        return view('service-masters.index', [
            'serviceTypes' => ServiceType::query()->orderBy('name')->get(),
            'catalogItems' => ServiceCatalogItem::query()->orderBy('category')->orderBy('name')->get(),
            'conditions' => InspectionCondition::query()->orderBy('sort_order')->orderBy('name')->get(),
            'options' => ServiceMasterOption::query()->orderBy('type')->orderBy('sort_order')->orderBy('label')->get()->groupBy('type'),
        ]);
    }

    public function storeServiceType(Request $request): RedirectResponse
    {
        $this->authorizeChange($request);
        $request->merge(['code' => Str::upper(Str::slug((string) $request->input('code'), '-'))]);
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:30', 'unique:service_types,code'],
            'name' => ['required', 'string', 'max:255', 'unique:service_types,name'],
            'description' => ['nullable', 'string', 'max:2000'],
            'default_price' => ['required', 'numeric', 'min:0'],
            'tax_percent' => ['required', 'numeric', 'between:0,100'],
            'estimated_minutes' => ['nullable', 'integer', 'min:1', 'max:1440'],
        ]);
        ServiceType::create($validated + ['active' => true]);

        return back()->with('success', 'Service type added.');
    }

    public function storeCatalogItem(Request $request): RedirectResponse
    {
        $this->authorizeChange($request);
        $request->merge(['code' => Str::upper(Str::slug((string) $request->input('code'), '-'))]);
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:30', 'unique:service_catalog_items,code'],
            'name' => ['required', 'string', 'max:255'],
            'category' => ['nullable', 'string', 'max:100'],
            'equipment_type' => ['nullable', 'string', 'max:100'],
            'standard_price' => ['required', 'numeric', 'min:0'],
            'tax_percent' => ['required', 'numeric', 'between:0,100'],
            'estimated_minutes' => ['nullable', 'integer', 'min:1', 'max:1440'],
        ]);
        ServiceCatalogItem::create($validated + ['active' => true]);

        return back()->with('success', 'Service catalog item added.');
    }

    public function storeCondition(Request $request): RedirectResponse
    {
        $this->authorizeChange($request);
        $request->merge(['code' => Str::upper(Str::slug((string) $request->input('code'), '_'))]);
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:40', 'unique:inspection_conditions,code'],
            'name' => ['required', 'string', 'max:255', 'unique:inspection_conditions,name'],
            'color' => ['required', Rule::in(['success', 'warning', 'danger', 'secondary'])],
            'is_issue' => ['nullable', 'boolean'],
        ]);
        $validated['is_issue'] = $request->boolean('is_issue');
        $validated['active'] = true;
        $validated['sort_order'] = (int) InspectionCondition::max('sort_order') + 1;
        InspectionCondition::create($validated);

        return back()->with('success', 'Inspection condition added.');
    }

    public function storeOption(Request $request): RedirectResponse
    {
        $this->authorizeChange($request);
        $request->merge(['code' => Str::lower(Str::slug((string) $request->input('code'), '_'))]);
        $validated = $request->validate([
            'type' => ['required', Rule::in(['equipment_type', 'reschedule_reason', 'cancellation_reason', 'payment_method', 'unit'])],
            'code' => ['required', 'string', 'max:50'],
            'label' => ['required', 'string', 'max:255'],
        ]);
        $request->validate(['code' => [Rule::unique('service_master_options')->where('type', $validated['type'])]]);
        $validated['active'] = true;
        $validated['sort_order'] = (int) ServiceMasterOption::where('type', $validated['type'])->max('sort_order') + 1;
        ServiceMasterOption::create($validated);

        return back()->with('success', 'Service option added.');
    }

    public function toggle(Request $request, string $group, int $id): RedirectResponse
    {
        $this->authorizeChange($request);
        $model = $this->master($group, $id);
        $model->update(['active' => ! $model->active]);

        return back()->with('success', 'Master status updated.');
    }

    private function authorizeChange(Request $request): void
    {
        abort_unless($request->user()->can('checklists.update'), 403);
    }

    private function master(string $group, int $id): Model
    {
        $model = match ($group) {
            'service-types' => ServiceType::class,
            'catalog' => ServiceCatalogItem::class,
            'conditions' => InspectionCondition::class,
            'options' => ServiceMasterOption::class,
            default => abort(404),
        };

        return $model::query()->findOrFail($id);
    }
}
