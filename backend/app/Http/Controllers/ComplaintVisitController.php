<?php

namespace App\Http\Controllers;

use App\Http\Requests\Complaints\AssignComplaintVisitRequest;
use App\Http\Requests\Complaints\ComplaintVisitReportRequest;
use App\Models\Complaint;
use App\Models\ComplaintVisit;
use App\Models\Product;
use App\Models\User;
use App\Services\ComplaintOptions;
use App\Services\ComplaintService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ComplaintVisitController extends Controller
{
    public function __construct(private readonly ComplaintService $complaints) {}

    public function store(AssignComplaintVisitRequest $request, Complaint $complaint): RedirectResponse
    {
        $visit = $this->complaints->assignVisit($complaint, $request->validated(), $request->user());

        return redirect()->route('complaints.show', [$complaint, 'tab' => 'visits'])
            ->with('success', $visit->label().' assigned to '.$visit->technician->name.'.');
    }

    public function edit(Request $request, Complaint $complaint, ComplaintVisit $visit): View
    {
        Gate::authorize('report', [$complaint, $visit]);
        $visit->load(['technician', 'assistantOne', 'assistantTwo', 'parts']);

        return view('complaints.visit-report', [
            'complaint' => $complaint->load(['customer', 'branch']),
            'visit' => $visit,
            'previousVisits' => $complaint->visits()->where('visit_no', '<', $visit->visit_no)->with('technician')->get(),
            'diagnosisFields' => ComplaintOptions::diagnosisFieldsFor($complaint->equipment_type),
            'rootCauses' => ComplaintOptions::master('root_cause'),
            'faultCategories' => ComplaintOptions::master('fault_category'),
            'pendingTree' => ComplaintOptions::pendingReasonTree(),
            'products' => Product::query()->where('active', true)->with('category:id,name')->orderBy('name')->get(['id', 'code', 'name', 'brand', 'product_category_id']),
            'staff' => User::query()->active()->orderBy('name')->get(['id', 'name']),
            'options' => fn (string $list): array => ComplaintOptions::options($list),
        ]);
    }

    public function update(ComplaintVisitReportRequest $request, Complaint $complaint, ComplaintVisit $visit): RedirectResponse
    {
        $this->complaints->recordVisitReport($visit, $request->safe()->except(['attachments', 'signature_data']), $request->file('attachments', []), $request->signaturePng(), $request->user());
        $message = match ($request->input('outcome')) {
            'resolved' => $visit->label().' completed. The complaint is ready for closure checks.',
            'pending' => $visit->label().' completed. Complaint is pending: '.$request->input('pending.pending_reason').'.',
            'observation' => $visit->label().' completed. Complaint is under observation.',
            default => $visit->label().' progress saved.',
        };

        return redirect()->route('complaints.show', [$complaint, 'tab' => 'visits'])->with('success', $message);
    }

    public function cancel(Request $request, Complaint $complaint, ComplaintVisit $visit): RedirectResponse
    {
        Gate::authorize('assign', $complaint);
        abort_unless($visit->isOpen(), 422, 'Only an open visit can be cancelled.');
        $validated = $request->validate(['reason' => ['required', 'string', 'max:255']]);
        $visit->update(['status' => 'cancelled', 'technician_remarks' => trim(($visit->technician_remarks ? $visit->technician_remarks."\n" : '').'Cancelled: '.$validated['reason'])]);
        $complaint->activities()->create(['complaint_visit_id' => $visit->id, 'user_id' => $request->user()->id, 'type' => 'note', 'title' => $visit->label().' cancelled', 'remarks' => $validated['reason']]);
        if (in_array($complaint->status, ['assigned', 'scheduled', 'in_progress'], true)) {
            $complaint->update(['status' => 'open', 'updated_by' => $request->user()->id]);
        }

        return redirect()->route('complaints.show', [$complaint, 'tab' => 'visits'])->with('success', $visit->label().' cancelled. Assign a new visit when ready.');
    }
}
