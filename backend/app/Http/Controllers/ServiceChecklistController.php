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
            'checklists' => ServiceChecklist::with(['category', 'items'])->latest()->paginate(12),
            'categories' => ProductCategory::orderBy('name')->get(),
            'serviceTypes' => ServiceType::query()->where('active', true)->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'product_category_id' => ['nullable', 'exists:product_categories,id'],
            'phase' => ['nullable', 'in:pre,post,both'],
            'service_type_id' => ['nullable', 'exists:service_types,id'],
            'equipment_type' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:1000'],
            'frequency' => ['nullable', 'string', 'max:100'],
            'estimated_minutes' => ['nullable', 'integer', 'min:1'],
            'required_skill' => ['nullable', 'string', 'max:255'],
            'safety_notes' => ['nullable', 'string', 'max:3000'],
            'items' => ['required', 'string'],
        ]);

        DB::transaction(function () use ($data) {
            $items = collect(preg_split('/\r\n|\r|\n/', $data['items']))->map(fn ($item) => trim($item))->filter();
            unset($data['items']);
            $data['phase'] ??= 'pre';
            $checklist = ServiceChecklist::create($data);
            $items->each(function (string $line, int $index) use ($checklist): void {
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
        });

        return back()->with('success', 'Service checklist created.');
    }

    public function destroy(ServiceChecklist $checklist): RedirectResponse
    {
        $checklist->delete();

        return back()->with('success', 'Checklist removed.');
    }
}
