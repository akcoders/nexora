<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ServiceChecklist;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function storeCategory(Request $request): RedirectResponse
    {
        ProductCategory::create($request->validate(['name' => ['required', 'string', 'max:255', 'unique:product_categories,name']]));

        return back()->with('success', 'Product category created.');
    }

    public function index(Request $request): View
    {
        $products = Product::with(['category', 'checklist'])
            ->when($request->filled('search'), fn ($query) => $query->where(fn ($query) => $query
                ->where('name', 'like', '%'.$request->string('search').'%')
                ->orWhere('code', 'like', '%'.$request->string('search').'%')
                ->orWhere('brand', 'like', '%'.$request->string('search').'%')))
            ->latest()->paginate(15)->withQueryString();

        return view('products.index', [
            'products' => $products,
            'categories' => ProductCategory::orderBy('name')->get(),
            'checklists' => ServiceChecklist::where('active', true)->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'product_category_id' => ['nullable', 'exists:product_categories,id'],
            'service_checklist_id' => ['nullable', 'exists:service_checklists,id'],
            'brand' => ['nullable', 'string', 'max:100'],
            'model' => ['nullable', 'string', 'max:100'],
            'serial_no' => ['nullable', 'string', 'max:100'],
            'warranty_months' => ['nullable', 'integer', 'min:0', 'max:240'],
            'specifications_text' => ['nullable', 'string', 'max:5000'],
        ]);
        $nextId = (Product::max('id') ?? 0) + 1;
        $data['code'] = 'PRD'.str_pad((string) $nextId, 5, '0', STR_PAD_LEFT);
        $data['specifications'] = filled($data['specifications_text'] ?? null) ? ['details' => $data['specifications_text']] : null;
        unset($data['specifications_text']);
        Product::create($data);

        return back()->with('success', 'Product created successfully.');
    }

    public function destroy(Product $product): RedirectResponse
    {
        $product->delete();

        return back()->with('success', 'Product removed.');
    }
}
