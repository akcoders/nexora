<?php

namespace App\Http\Controllers;

use App\Http\Requests\Library\LibraryCategoryRequest;
use App\Http\Requests\Library\LibraryMasterRequest;
use App\Models\LibraryArticle;
use App\Models\LibraryCategory;
use App\Models\ServiceMasterOption;
use App\Services\LibraryOptions;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Knowledge library settings: categories (icon, colour, order) and the device type and
 * manufacturer lists that guides are tagged with.
 */
class LibrarySettingsController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()->can('library.manage'), 403);
        $groups = LibraryOptions::masterGroups();
        $tabs = ['categories' => 'Categories', ...array_map(fn (array $group) => $group['label'], $groups)];

        return view('library.settings', [
            'tab' => array_key_exists((string) $request->query('tab'), $tabs) ? (string) $request->query('tab') : 'categories',
            'tabs' => $tabs,
            'groups' => $groups,
            'categories' => LibraryCategory::query()->withCount('articles')->ordered()->get(),
            'masters' => ServiceMasterOption::query()->whereIn('type', array_keys($groups))->orderBy('sort_order')->orderBy('label')->get()->groupBy('type'),
            'usage' => [
                'library_device_type' => LibraryArticle::query()->whereNotNull('device_type')->selectRaw('device_type as code, count(*) as aggregate')->groupBy('device_type')->pluck('aggregate', 'code'),
                'library_manufacturer' => LibraryArticle::query()->whereNotNull('manufacturer')->selectRaw('manufacturer as code, count(*) as aggregate')->groupBy('manufacturer')->pluck('aggregate', 'code'),
            ],
            'icons' => LibraryCategoryRequest::ICONS,
            'tones' => LibraryOptions::TONES,
        ]);
    }

    public function storeCategory(LibraryCategoryRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $category = LibraryCategory::create([
            ...$this->categoryAttributes($data),
            'slug' => $this->uniqueSlug($data['name']),
            'sort_order' => $data['sort_order'] ?? ((int) LibraryCategory::query()->max('sort_order') + 1),
            'is_active' => true,
        ]);

        return redirect()->route('library.settings')->with('success', 'Category "'.$category->name.'" added.');
    }

    public function updateCategory(LibraryCategoryRequest $request, LibraryCategory $category): RedirectResponse
    {
        $data = $request->validated();
        $category->update([...$this->categoryAttributes($data), 'sort_order' => $data['sort_order'] ?? $category->sort_order]);

        return redirect()->route('library.settings')->with('success', 'Category "'.$category->name.'" updated.');
    }

    public function toggleCategory(Request $request, LibraryCategory $category): RedirectResponse
    {
        abort_unless($request->user()->can('library.manage'), 403);
        $category->update(['is_active' => ! $category->is_active]);

        return redirect()->route('library.settings')->with('success', 'Category "'.$category->name.'" is now '.($category->is_active ? 'active' : 'hidden from the library').'.');
    }

    public function storeMaster(LibraryMasterRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $label = trim($data['label']);
        ServiceMasterOption::create([
            'type' => $data['type'],
            'code' => $request->codeFor($label),
            'label' => $label,
            'active' => true,
            'sort_order' => (int) ServiceMasterOption::query()->where('type', $data['type'])->max('sort_order') + 1,
        ]);

        return redirect()->route('library.settings', ['tab' => $data['type']])->with('success', '"'.$label.'" added.');
    }

    public function updateMaster(LibraryMasterRequest $request, ServiceMasterOption $option): RedirectResponse
    {
        $this->ensureLibraryMaster($option);
        $label = trim($request->validated('label'));
        $option->update(['label' => $label]);

        return redirect()->route('library.settings', ['tab' => $option->type])->with('success', 'Renamed to "'.$label.'".');
    }

    public function toggleMaster(Request $request, ServiceMasterOption $option): RedirectResponse
    {
        abort_unless($request->user()->can('library.manage'), 403);
        $this->ensureLibraryMaster($option);
        $option->update(['active' => ! $option->active]);

        return redirect()->route('library.settings', ['tab' => $option->type])->with('success', '"'.$option->label.'" is now '.($option->active ? 'active' : 'inactive').'.');
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function categoryAttributes(array $data): array
    {
        $description = trim((string) ($data['description'] ?? ''));

        return [
            'name' => trim($data['name']),
            'description' => $description === '' ? null : $description,
            'icon' => $data['icon'],
            'color' => $data['color'],
        ];
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::limit(Str::slug($name), 130, '') ?: 'category';
        // The browse filter reads a numeric ?category= as an id, so slugs are never just digits.
        if (ctype_digit($base)) {
            $base = 'category-'.$base;
        }
        $slug = $base;
        for ($suffix = 2; LibraryCategory::query()->where('slug', $slug)->exists(); $suffix++) {
            $slug = $base.'-'.$suffix;
        }

        return $slug;
    }

    private function ensureLibraryMaster(ServiceMasterOption $option): void
    {
        abort_unless(array_key_exists($option->type, LibraryOptions::masterGroups()), 404);
    }
}
