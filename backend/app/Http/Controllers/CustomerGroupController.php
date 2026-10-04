<?php

namespace App\Http\Controllers;

use App\Models\CustomerGroup;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CustomerGroupController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()->can('customers.read'), 403);

        return view('customer-groups.index', [
            'groups' => CustomerGroup::withCount('customers')->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('customers.create'), 403);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:150', Rule::unique('customer_groups')->whereNull('deleted_at')],
            'description' => ['nullable', 'string', 'max:1000'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
        ]);

        $nextId = (CustomerGroup::withTrashed()->max('id') ?? 0) + 1;
        CustomerGroup::create(array_merge($data, ['code' => 'GRP'.str_pad((string) $nextId, 4, '0', STR_PAD_LEFT)]));

        return back()->with('success', 'Customer group created successfully.');
    }

    public function update(Request $request, CustomerGroup $customerGroup): RedirectResponse
    {
        abort_unless($request->user()->can('customers.update'), 403);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:150', Rule::unique('customer_groups')->ignore($customerGroup)->whereNull('deleted_at')],
            'description' => ['nullable', 'string', 'max:1000'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
        ]);
        $customerGroup->update($data);

        return back()->with('success', 'Customer group updated successfully.');
    }

    public function destroy(Request $request, CustomerGroup $customerGroup): RedirectResponse
    {
        abort_unless($request->user()->can('customers.delete'), 403);
        abort_if($customerGroup->customers()->exists(), 422, 'Move linked customers before deleting this group.');
        $customerGroup->delete();

        return back()->with('success', 'Customer group deleted successfully.');
    }
}
