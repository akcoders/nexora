<?php

namespace App\Http\Controllers;

use App\Models\ServiceMasterOption;
use App\Services\ComplaintOptions;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Admin-managed complaint lists: nature of complaint, fault categories, root causes and the
 * pending reasons (grouped) with their sub categories.
 */
class ComplaintMasterController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()->can('complaints.read'), 403);
        $groups = ComplaintOptions::masterGroups();

        return view('complaints.masters', [
            'groups' => $groups,
            'items' => ServiceMasterOption::query()->whereIn('type', array_keys($groups))->orderBy('type')->orderBy('sort_order')->orderBy('label')->get()->groupBy('type'),
            'pendingGroups' => ComplaintOptions::options('pending_groups'),
            'pendingReasons' => ServiceMasterOption::query()->where('type', 'pending_reason')->orderBy('label')->pluck('label', 'code'),
            'canManage' => $request->user()->can('complaints.approve'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('complaints.approve'), 403);
        $validated = $request->validate([
            'type' => ['required', Rule::in(array_keys(ComplaintOptions::masterGroups()))],
            'label' => ['required', 'string', 'max:120'],
            'group' => ['nullable', 'required_if:type,pending_reason', Rule::in(ComplaintOptions::keys('pending_groups'))],
            'reason' => ['nullable', 'required_if:type,pending_sub_reason', Rule::exists('service_master_options', 'code')->where('type', 'pending_reason')],
        ], ['group.required_if' => 'Choose the pending group this reason belongs to.', 'reason.required_if' => 'Choose the pending reason this sub category belongs to.']);

        $code = Str::limit(Str::slug($validated['label'], '_'), 50, '');
        $request->validate(['label' => [function (string $attribute, mixed $value, \Closure $fail) use ($validated, $code): void {
            if ($code === '' || ServiceMasterOption::where('type', $validated['type'])->where('code', $code)->exists()) {
                $fail('This entry already exists in the list.');
            }
        }]]);

        ServiceMasterOption::create([
            'type' => $validated['type'],
            'code' => $code,
            'label' => $validated['label'],
            'active' => true,
            'sort_order' => (int) ServiceMasterOption::where('type', $validated['type'])->max('sort_order') + 1,
            'metadata' => array_filter(['group' => $validated['group'] ?? null, 'reason' => $validated['reason'] ?? null]) ?: null,
        ]);

        return redirect()->route('complaint-masters.index', ['tab' => $validated['type']])->with('success', '"'.$validated['label'].'" added.');
    }

    public function toggle(Request $request, ServiceMasterOption $option): RedirectResponse
    {
        abort_unless($request->user()->can('complaints.approve'), 403);
        abort_unless(array_key_exists($option->type, ComplaintOptions::masterGroups()), 404);
        $option->update(['active' => ! $option->active]);

        return redirect()->route('complaint-masters.index', ['tab' => $option->type])->with('success', '"'.$option->label.'" is now '.($option->active ? 'active' : 'inactive').'.');
    }
}
