<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreServiceEstimateRequest;
use App\Models\InspectionCondition;
use App\Models\Product;
use App\Models\ServiceCatalogItem;
use App\Models\ServiceChecklist;
use App\Models\ServiceEstimate;
use App\Models\ServiceInspection;
use App\Models\ServiceInspectionItem;
use App\Models\ServiceJob;
use App\Models\ServiceMasterOption;
use App\Services\ServiceEstimateCalculator;
use App\Services\ServiceJobWorkflow;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ServiceJobController extends Controller
{
    public function index(Request $request, ServiceJobWorkflow $workflow): JsonResponse
    {
        Gate::authorize('viewAny', ServiceJob::class);
        $canViewAll = $request->user()->can('service_jobs.assign');
        $jobs = ServiceJob::query()
            ->with(['customer:id,name,code', 'equipment:id,equipment_type,brand,model,capacity,location', 'serviceType:id,name', 'technician:id,name,phone'])
            ->withCount('payments')
            ->withSum('payments', 'amount')
            ->when(! $canViewAll, fn ($query) => $query->where('assigned_to', $request->user()->id))
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->when($request->filled('scope') && $request->string('scope')->toString() === 'today', fn ($query) => $query->whereDate('scheduled_at', today()))
            ->when($request->filled('scope') && $request->string('scope')->toString() === 'upcoming', fn ($query) => $query->where('scheduled_at', '>', now()))
            ->orderByRaw("CASE WHEN priority = 'emergency' THEN 1 WHEN priority = 'very_high' THEN 2 WHEN priority = 'high' THEN 3 ELSE 4 END")
            ->orderBy('scheduled_at')
            ->paginate(20);

        $jobs->getCollection()->each(fn (ServiceJob $serviceJob) => $this->decorate($serviceJob, $workflow));

        return response()->json($jobs);
    }

    public function show(Request $request, ServiceJob $serviceJob, ServiceJobWorkflow $workflow): JsonResponse
    {
        Gate::authorize('view', $serviceJob);

        return response()->json([
            'job' => $this->loadJob($serviceJob, $workflow),
            'masters' => [
                'conditions' => InspectionCondition::query()->where('active', true)->orderBy('sort_order')->get(),
                'services' => ServiceCatalogItem::query()->where('active', true)->orderBy('name')->get(),
                'products' => Product::query()->where('active', true)->orderBy('name')->get(['id', 'code', 'name', 'brand', 'model']),
                'payment_methods' => ServiceMasterOption::query()->where('type', 'payment_method')->where('active', true)->orderBy('sort_order')->get(),
                'reschedule_reasons' => ServiceMasterOption::query()->where('type', 'reschedule_reason')->where('active', true)->orderBy('sort_order')->get(),
                'cancellation_reasons' => ServiceMasterOption::query()->where('type', 'cancellation_reason')->where('active', true)->orderBy('sort_order')->get(),
                'units' => ServiceMasterOption::query()->where('type', 'unit')->where('active', true)->orderBy('sort_order')->get(),
                'capabilities' => [
                    'cancel' => $request->user()->can('service_jobs.cancel'),
                ],
            ],
        ]);
    }

    public function contact(Request $request, ServiceJob $serviceJob, ServiceJobWorkflow $workflow): JsonResponse
    {
        $this->authorizeAction($request, $serviceJob, 'service_jobs.read');
        $validated = $request->validate([
            'outcome' => ['required', Rule::in(['confirm', 'reschedule', 'no_answer', 'customer_declined', 'cancel'])],
            'scheduled_at' => ['required_if:outcome,reschedule', 'nullable', 'date', 'after:now'],
            'reason' => ['required_if:outcome,reschedule,customer_declined,cancel', 'nullable', 'string', 'max:255'],
            'remark' => ['nullable', 'string', 'max:2000'],
        ]);

        if ($validated['outcome'] === 'cancel') {
            abort_unless($request->user()->can('service_jobs.cancel'), 403, 'You are not allowed to cancel service jobs.');
        }

        $serviceJob->visits()->create([
            'technician_id' => $request->user()->id,
            'type' => 'contact',
            'outcome' => $validated['outcome'],
            'scheduled_at' => $validated['scheduled_at'] ?? null,
            'occurred_at' => now(),
            'reason' => $validated['reason'] ?? null,
            'remark' => $validated['remark'] ?? null,
        ]);

        if ($validated['outcome'] === 'reschedule') {
            $serviceJob->update(['scheduled_at' => $validated['scheduled_at']]);
        }

        $toStatus = match ($validated['outcome']) {
            'confirm' => 'confirmed',
            'reschedule' => 'rescheduled',
            'no_answer' => 'no_answer',
            'customer_declined' => 'customer_declined',
            'cancel' => 'cancelled',
        };
        if ($serviceJob->status !== $toStatus) {
            $serviceJob = $workflow->transition($serviceJob, $toStatus, $request->user(), $validated);
        }

        return response()->json(['message' => 'Customer contact recorded.', 'job' => $this->loadJob($serviceJob, $workflow)]);
    }

    public function startJourney(Request $request, ServiceJob $serviceJob, ServiceJobWorkflow $workflow): JsonResponse
    {
        $this->authorizeAction($request, $serviceJob, 'service_jobs.read');
        $serviceJob = $workflow->transition($serviceJob, 'on_the_way', $request->user());

        return response()->json(['message' => 'Journey started.', 'job' => $this->loadJob($serviceJob, $workflow)]);
    }

    public function arrive(Request $request, ServiceJob $serviceJob, ServiceJobWorkflow $workflow): JsonResponse
    {
        $this->authorizeAction($request, $serviceJob, 'service_jobs.read');
        $validated = $request->validate([
            'latitude' => ['nullable', 'required_with:longitude', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'required_with:latitude', 'numeric', 'between:-180,180'],
            'arrival_method' => ['required', Rule::in(['auto', 'manual'])],
            'remark' => ['nullable', 'string', 'max:1000'],
        ]);
        $distance = null;
        if (isset($validated['latitude'], $validated['longitude']) && $serviceJob->latitude !== null && $serviceJob->longitude !== null) {
            $distance = round($this->distanceInMeters(
                (float) $validated['latitude'],
                (float) $validated['longitude'],
                (float) $serviceJob->latitude,
                (float) $serviceJob->longitude,
            ));
        }
        $serviceJob->visits()->create([
            'technician_id' => $request->user()->id,
            'type' => 'field',
            'outcome' => 'arrived',
            'occurred_at' => now(),
            'latitude' => $validated['latitude'] ?? null,
            'longitude' => $validated['longitude'] ?? null,
            'distance_meters' => $distance,
            'arrival_method' => $validated['arrival_method'],
            'remark' => $validated['remark'] ?? null,
        ]);
        $serviceJob = $workflow->transition($serviceJob, 'arrived', $request->user(), $validated);

        return response()->json(['message' => 'Arrival recorded.', 'job' => $this->loadJob($serviceJob, $workflow)]);
    }

    public function startInspection(Request $request, ServiceJob $serviceJob, ServiceJobWorkflow $workflow): JsonResponse
    {
        $this->authorizeAction($request, $serviceJob, 'service_jobs.inspect');
        $inspection = $this->createInspection($serviceJob, $request, 'pre');
        $serviceJob = $workflow->transition($serviceJob, 'inspection_in_progress', $request->user());

        return response()->json(['message' => 'Inspection started.', 'inspection' => $inspection->load('items.checklistItem'), 'job' => $this->loadJob($serviceJob, $workflow)]);
    }

    public function startPostInspection(Request $request, ServiceJob $serviceJob): JsonResponse
    {
        $this->authorizeAction($request, $serviceJob, 'service_jobs.inspect');
        abort_unless($serviceJob->status === 'service_in_progress', 422, 'Post-service checks are available while service is in progress.');
        $inspection = $this->createInspection($serviceJob, $request, 'post');

        return response()->json(['message' => 'Post-service checklist started.', 'inspection' => $inspection->load('items.checklistItem')]);
    }

    public function saveInspectionItem(Request $request, ServiceJob $serviceJob, ServiceInspectionItem $inspectionItem): JsonResponse
    {
        $this->authorizeAction($request, $serviceJob, 'service_jobs.inspect');
        abort_unless($inspectionItem->inspection->service_job_id === $serviceJob->id, 404);
        $validated = $request->validate([
            'inspection_condition_id' => ['required', 'exists:inspection_conditions,id'],
            'remark' => ['nullable', 'string', 'max:2000'],
        ]);
        $condition = InspectionCondition::query()->where('active', true)->findOrFail($validated['inspection_condition_id']);
        $inspectionItem->update([
            'inspection_condition_id' => $condition->id,
            'response_value' => $condition->code,
            'remark' => $validated['remark'] ?? null,
            'completed_at' => now(),
        ]);

        return response()->json(['message' => 'Inspection item saved.', 'item' => $inspectionItem->fresh()->load(['condition', 'photos'])]);
    }

    public function completeInspection(Request $request, ServiceJob $serviceJob, ServiceJobWorkflow $workflow): JsonResponse
    {
        $this->authorizeAction($request, $serviceJob, 'service_jobs.inspect');
        $inspection = $serviceJob->inspections()->where('phase', 'pre')->with(['items.checklistItem', 'items.photos'])->firstOrFail();
        $this->validateInspectionCompletion($inspection);
        abort_unless($serviceJob->photos()->where('category', 'before')->exists(), 422, 'Add at least one before-service photo.');
        $inspection->update(['status' => 'completed', 'completed_at' => now()]);
        $serviceJob = $workflow->transition($serviceJob, 'inspection_completed', $request->user());

        return response()->json(['message' => 'Inspection completed.', 'job' => $this->loadJob($serviceJob, $workflow)]);
    }

    public function completePostInspection(Request $request, ServiceJob $serviceJob): JsonResponse
    {
        $this->authorizeAction($request, $serviceJob, 'service_jobs.inspect');
        $inspection = $serviceJob->inspections()->where('phase', 'post')->with(['items.checklistItem', 'items.photos'])->firstOrFail();
        $this->validateInspectionCompletion($inspection);
        $inspection->update(['status' => 'completed', 'completed_at' => now()]);

        return response()->json(['message' => 'Post-service checklist completed.', 'inspection' => $inspection->fresh()->load(['items.condition', 'items.checklistItem'])]);
    }

    public function storeEstimate(StoreServiceEstimateRequest $request, ServiceJob $serviceJob, ServiceEstimateCalculator $calculator, ServiceJobWorkflow $workflow): JsonResponse
    {
        abort_unless(in_array($serviceJob->status, ['inspection_completed', 'estimate_created', 'estimate_pending_approval'], true), 422, 'Complete inspection before creating an estimate.');
        $calculation = $calculator->calculate($request->validated()['items'], (float) $request->input('discount_amount', 0));

        $estimate = DB::transaction(function () use ($request, $serviceJob, $calculation): ServiceEstimate {
            $version = ((int) $serviceJob->estimates()->max('version')) + 1;
            $serviceJob->estimates()->whereIn('status', ['pending_approval', 'changes_requested'])->update(['status' => 'superseded']);
            $estimate = $serviceJob->estimates()->create([
                'version' => $version,
                'status' => 'pending_approval',
                'subtotal' => $calculation['subtotal'],
                'discount_amount' => $calculation['discount_amount'],
                'tax_amount' => $calculation['tax_amount'],
                'final_amount' => $calculation['final_amount'],
                'notes' => $request->input('notes'),
                'created_by' => $request->user()->id,
            ]);
            $estimate->items()->createMany($calculation['items']);

            return $estimate;
        });

        if ($serviceJob->status === 'estimate_pending_approval') {
            $serviceJob = $workflow->transition($serviceJob, 'estimate_created', $request->user(), ['remark' => 'Estimate revised.']);
        } elseif ($serviceJob->status === 'inspection_completed') {
            $serviceJob = $workflow->transition($serviceJob, 'estimate_created', $request->user());
        }
        $serviceJob = $workflow->transition($serviceJob, 'estimate_pending_approval', $request->user());

        return response()->json(['message' => 'Service estimate created.', 'estimate' => $estimate->load('items'), 'job' => $this->loadJob($serviceJob, $workflow)], 201);
    }

    public function decideEstimate(Request $request, ServiceJob $serviceJob, ServiceEstimate $estimate, ServiceJobWorkflow $workflow): JsonResponse
    {
        $this->authorizeAction($request, $serviceJob, 'service_jobs.create_estimate');
        abort_unless($estimate->service_job_id === $serviceJob->id, 404);
        $validated = $request->validate([
            'decision' => ['required', Rule::in(['accept', 'request_change', 'decline'])],
            'customer_name' => ['required_if:decision,accept', 'nullable', 'string', 'max:255'],
            'signature' => ['required_if:decision,accept', 'nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:5120'],
            'remark' => ['required_if:decision,request_change,decline', 'nullable', 'string', 'max:2000'],
        ]);

        if ($validated['decision'] === 'accept') {
            $signaturePath = $request->file('signature')->store('service-jobs/'.$serviceJob->id.'/signatures', 'public');
            $estimate->update(['status' => 'accepted', 'customer_name' => $validated['customer_name'], 'approval_signature_path' => $signaturePath, 'decided_at' => now()]);
            $serviceJob->signatures()->updateOrCreate(['type' => 'estimate_approval'], ['signer_name' => $validated['customer_name'], 'path' => $signaturePath, 'signed_at' => now()]);
            $serviceJob = $workflow->transition($serviceJob, 'customer_approved', $request->user());
        } elseif ($validated['decision'] === 'request_change') {
            $estimate->update(['status' => 'changes_requested', 'decision_remark' => $validated['remark'], 'decided_at' => now()]);
            $serviceJob = $workflow->transition($serviceJob, 'estimate_created', $request->user(), ['remark' => $validated['remark']]);
        } else {
            $estimate->update(['status' => 'declined', 'decision_remark' => $validated['remark'], 'decided_at' => now()]);
            $serviceJob = $workflow->transition($serviceJob, 'customer_declined', $request->user(), ['remark' => $validated['remark']]);
        }

        return response()->json(['message' => 'Estimate decision recorded.', 'job' => $this->loadJob($serviceJob, $workflow)]);
    }

    public function startService(Request $request, ServiceJob $serviceJob, ServiceJobWorkflow $workflow): JsonResponse
    {
        $this->authorizeAction($request, $serviceJob, 'service_jobs.perform');
        $serviceJob = $workflow->transition($serviceJob, 'service_in_progress', $request->user());

        return response()->json(['message' => 'Service started.', 'job' => $this->loadJob($serviceJob, $workflow)]);
    }

    public function storePerformedService(Request $request, ServiceJob $serviceJob): JsonResponse
    {
        $this->authorizeAction($request, $serviceJob, 'service_jobs.perform');
        abort_unless($serviceJob->status === 'service_in_progress', 422, 'Start service before recording work performed.');
        $validated = $request->validate([
            'service_catalog_item_id' => ['required', 'exists:service_catalog_items,id'],
            'quantity' => ['required', 'numeric', 'gt:0'],
            'remark' => ['nullable', 'string', 'max:2000'],
        ]);
        $catalogItem = ServiceCatalogItem::query()->where('active', true)->findOrFail($validated['service_catalog_item_id']);
        $quantity = (float) $validated['quantity'];
        $lineTotal = round($quantity * (float) $catalogItem->standard_price * (1 + ((float) $catalogItem->tax_percent / 100)), 2);
        $performedService = $serviceJob->performedServices()->create([
            'service_catalog_item_id' => $catalogItem->id,
            'name' => $catalogItem->name,
            'quantity' => $quantity,
            'unit_rate' => $catalogItem->standard_price,
            'tax_percent' => $catalogItem->tax_percent,
            'line_total' => $lineTotal,
            'remark' => $validated['remark'] ?? null,
            'performed_by' => $request->user()->id,
            'performed_at' => now(),
        ]);

        return response()->json(['message' => 'Work performed added.', 'service' => $performedService], 201);
    }

    public function storeMaterial(Request $request, ServiceJob $serviceJob): JsonResponse
    {
        $this->authorizeAction($request, $serviceJob, 'service_jobs.perform');
        abort_unless($serviceJob->status === 'service_in_progress', 422, 'Start service before adding materials.');
        $validated = $request->validate([
            'product_id' => ['nullable', 'exists:products,id'],
            'name' => ['required_without:product_id', 'nullable', 'string', 'max:255'],
            'quantity' => ['required', 'numeric', 'gt:0'],
            'unit' => ['required', 'string', 'max:30'],
            'unit_rate' => ['required', 'numeric', 'min:0'],
            'remark' => ['nullable', 'string', 'max:2000'],
            'photo' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:5120'],
        ]);
        $product = filled($validated['product_id'] ?? null) ? Product::query()->where('active', true)->findOrFail($validated['product_id']) : null;
        $material = $serviceJob->materials()->create([
            'product_id' => $product?->id,
            'is_inventory' => $product !== null,
            'name' => $product?->name ?? $validated['name'],
            'quantity' => $validated['quantity'],
            'unit' => $validated['unit'],
            'unit_rate' => $validated['unit_rate'],
            'line_total' => round((float) $validated['quantity'] * (float) $validated['unit_rate'], 2),
            'remark' => $validated['remark'] ?? null,
            'photo_path' => $request->file('photo')?->store('service-jobs/'.$serviceJob->id.'/materials', 'public'),
            'added_by' => $request->user()->id,
        ]);

        return response()->json(['message' => 'Material added.', 'material' => $material], 201);
    }

    public function uploadPhoto(Request $request, ServiceJob $serviceJob): JsonResponse
    {
        $this->authorizeAction($request, $serviceJob, 'service_jobs.inspect');
        $validated = $request->validate([
            'photo' => ['required', 'image', 'mimes:png,jpg,jpeg,webp', 'max:8192'],
            'category' => ['required', Rule::in(['before', 'during', 'after'])],
            'caption' => ['nullable', 'string', 'max:255'],
            'service_inspection_id' => ['nullable', 'exists:service_inspections,id'],
            'service_inspection_item_id' => ['nullable', 'exists:service_inspection_items,id'],
        ]);
        if (filled($validated['service_inspection_id'] ?? null)) {
            abort_unless($serviceJob->inspections()->whereKey($validated['service_inspection_id'])->exists(), 404);
        }
        $photo = $serviceJob->photos()->create([
            'service_inspection_id' => $validated['service_inspection_id'] ?? null,
            'service_inspection_item_id' => $validated['service_inspection_item_id'] ?? null,
            'category' => $validated['category'],
            'path' => $request->file('photo')->store('service-jobs/'.$serviceJob->id.'/photos', 'public'),
            'caption' => $validated['caption'] ?? null,
            'uploaded_by' => $request->user()->id,
        ]);

        return response()->json(['message' => 'Photo uploaded.', 'photo' => $photo], 201);
    }

    public function storeSignature(Request $request, ServiceJob $serviceJob): JsonResponse
    {
        $this->authorizeAction($request, $serviceJob, 'service_jobs.perform');
        $validated = $request->validate([
            'type' => ['required', Rule::in(['completion', 'technician'])],
            'signer_name' => ['required', 'string', 'max:255'],
            'signature' => ['required', 'image', 'mimes:png,jpg,jpeg,webp', 'max:5120'],
        ]);
        $signature = $serviceJob->signatures()->updateOrCreate(
            ['type' => $validated['type']],
            ['signer_name' => $validated['signer_name'], 'path' => $request->file('signature')->store('service-jobs/'.$serviceJob->id.'/signatures', 'public'), 'signed_at' => now()],
        );

        return response()->json(['message' => 'Signature saved.', 'signature' => $signature], 201);
    }

    public function completeServiceWork(Request $request, ServiceJob $serviceJob, ServiceJobWorkflow $workflow): JsonResponse
    {
        $this->authorizeAction($request, $serviceJob, 'service_jobs.perform');
        abort_unless($serviceJob->performedServices()->exists(), 422, 'Add at least one service performed.');
        abort_unless($serviceJob->inspections()->where('phase', 'post')->where('status', 'completed')->exists(), 422, 'Complete the post-service checklist.');
        abort_unless($serviceJob->photos()->where('category', 'after')->exists(), 422, 'Add at least one after-service photo.');
        abort_unless($serviceJob->signatures()->where('type', 'completion')->exists(), 422, 'Capture customer completion signature.');
        $actualTotal = round((float) $serviceJob->performedServices()->sum('line_total') + (float) $serviceJob->materials()->sum('line_total'), 2);
        if ($actualTotal <= 0 && $serviceJob->currentEstimate) {
            $actualTotal = (float) $serviceJob->currentEstimate->final_amount;
        }
        $serviceJob->update(['final_amount' => $actualTotal]);
        $serviceJob = $workflow->transition($serviceJob, 'service_completed', $request->user());
        $serviceJob = $workflow->transition($serviceJob, 'payment_pending', $request->user());

        return response()->json(['message' => 'Service work completed.', 'job' => $this->loadJob($serviceJob, $workflow)]);
    }

    public function recordPayment(Request $request, ServiceJob $serviceJob, ServiceJobWorkflow $workflow): JsonResponse
    {
        $this->authorizeAction($request, $serviceJob, 'service_jobs.payment');
        abort_unless(in_array($serviceJob->status, ['payment_pending', 'partially_paid'], true), 422, 'Complete service work before recording payment.');
        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'min:0'],
            'method' => ['required', Rule::exists('service_master_options', 'code')->where(fn ($query) => $query->where('type', 'payment_method')->where('active', true))],
            'transaction_reference' => ['nullable', 'required_if:method,upi,card,online', 'string', 'max:255'],
            'proof' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:5120'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
        $alreadyPaid = (float) $serviceJob->payments()->where('status', 'received')->sum('amount');
        $pendingAmount = max(0, round((float) $serviceJob->final_amount - $alreadyPaid, 2));
        abort_if($validated['method'] === 'credit' && (float) $validated['amount'] !== 0.0, 422, 'Credit / pay later must be recorded with zero amount received.');
        abort_if($validated['method'] !== 'credit' && (float) $validated['amount'] <= 0, 422, 'Enter the amount received.');
        abort_if((float) $validated['amount'] > $pendingAmount, 422, 'Payment cannot be greater than the pending amount.');
        $payment = $serviceJob->payments()->create([
            'amount' => $validated['amount'],
            'method' => $validated['method'],
            'transaction_reference' => $validated['transaction_reference'] ?? null,
            'status' => 'received',
            'proof_path' => $request->file('proof')?->store('service-jobs/'.$serviceJob->id.'/payments', 'public'),
            'received_by' => $request->user()->id,
            'paid_at' => now(),
            'notes' => $validated['notes'] ?? null,
        ]);
        $paidAmount = (float) $serviceJob->payments()->where('status', 'received')->sum('amount');
        $paymentStatus = $paidAmount >= (float) $serviceJob->final_amount ? 'paid' : ($paidAmount > 0 ? 'partial' : 'pending');
        $serviceJob->update(['payment_status' => $paymentStatus]);
        if ($paymentStatus === 'paid') {
            $serviceJob = $workflow->transition($serviceJob, 'paid', $request->user());
        } elseif ($paymentStatus === 'partial' && $serviceJob->status === 'payment_pending') {
            $serviceJob = $workflow->transition($serviceJob, 'partially_paid', $request->user());
        }

        return response()->json(['message' => 'Payment recorded.', 'payment' => $payment, 'job' => $this->loadJob($serviceJob, $workflow)], 201);
    }

    public function complete(Request $request, ServiceJob $serviceJob, ServiceJobWorkflow $workflow): JsonResponse
    {
        $this->authorizeAction($request, $serviceJob, 'service_jobs.complete');
        abort_unless(in_array($serviceJob->status, ['paid', 'payment_pending', 'partially_paid'], true), 422, 'Record payment status before completing service.');
        abort_unless($serviceJob->payments()->exists(), 422, 'Record payment or credit/pay-later status before completing service.');
        abort_unless($serviceJob->signatures()->where('type', 'completion')->exists(), 422, 'Customer completion signature is required.');
        $serviceJob = $workflow->transition($serviceJob, 'completed', $request->user());
        $serviceJob->feedback()->firstOrCreate([], [
            'customer_id' => $serviceJob->customer_id,
            'technician_id' => $serviceJob->assigned_to,
            'token' => (string) Str::uuid(),
        ]);

        return response()->json(['message' => 'Service completed successfully.', 'job' => $this->loadJob($serviceJob, $workflow)]);
    }

    private function authorizeAction(Request $request, ServiceJob $serviceJob, string $permission): void
    {
        Gate::authorize('view', $serviceJob);
        abort_unless($serviceJob->assigned_to === $request->user()->id || $request->user()->can('service_jobs.update'), 403, 'Only the assigned technician can update this service job.');
        abort_unless($request->user()->can($permission) || $request->user()->can('service_jobs.update'), 403, 'You are not allowed to perform this service action.');
    }

    private function createInspection(ServiceJob $serviceJob, Request $request, string $phase): ServiceInspection
    {
        $existing = $serviceJob->inspections()->where('phase', $phase)->first();
        if ($existing) {
            return $existing;
        }
        $equipmentType = $serviceJob->equipment?->equipment_type;
        $checklist = ServiceChecklist::query()
            ->where('active', true)
            ->whereIn('phase', [$phase, 'both'])
            ->where(fn ($query) => $query->whereNull('service_type_id')->orWhere('service_type_id', $serviceJob->service_type_id))
            ->where(fn ($query) => $query->whereNull('equipment_type')->orWhere('equipment_type', $equipmentType))
            ->with(['items' => fn ($query) => $query->where('active', true)->orderBy('sort_order')])
            ->first();
        abort_unless($checklist, 422, 'No active checklist is configured for this service.');

        return DB::transaction(function () use ($serviceJob, $request, $phase, $checklist): ServiceInspection {
            $inspection = $serviceJob->inspections()->create([
                'technician_id' => $request->user()->id,
                'service_checklist_id' => $checklist->id,
                'phase' => $phase,
                'status' => 'in_progress',
                'started_at' => now(),
            ]);
            $inspection->items()->createMany($checklist->items->map(fn ($item) => [
                'checklist_item_id' => $item->id,
                'label' => $item->label,
            ])->all());

            return $inspection;
        });
    }

    private function validateInspectionCompletion(ServiceInspection $inspection): void
    {
        $missingRequired = $inspection->items->filter(fn (ServiceInspectionItem $item) => $item->checklistItem?->required && $item->completed_at === null);
        abort_if($missingRequired->isNotEmpty(), 422, 'Please complete all required inspection items.');
        $missingPhotos = $inspection->items->filter(fn (ServiceInspectionItem $item) => $item->checklistItem?->photo_required && $item->photos->isEmpty());
        abort_if($missingPhotos->isNotEmpty(), 422, 'Please add photos for all photo-required checklist items.');
    }

    private function loadJob(ServiceJob $serviceJob, ServiceJobWorkflow $workflow): ServiceJob
    {
        $serviceJob->load([
            'customer', 'branch', 'equipment', 'serviceType', 'technician',
            'statusHistory.changedBy', 'visits', 'inspections.items.condition', 'inspections.items.checklistItem',
            'estimates.items', 'performedServices', 'materials', 'photos', 'signatures', 'payments', 'feedback',
        ])->loadSum('payments', 'amount');

        return $this->decorate($serviceJob, $workflow);
    }

    private function decorate(ServiceJob $serviceJob, ServiceJobWorkflow $workflow): ServiceJob
    {
        $paid = (float) ($serviceJob->payments_sum_amount ?? 0);
        $serviceJob->setAttribute('status_label', $workflow->label($serviceJob->status));
        $serviceJob->setAttribute('primary_action', $workflow->primaryAction($serviceJob));
        $serviceJob->setAttribute('amount_paid', $paid);
        $serviceJob->setAttribute('amount_pending', max(0, round((float) $serviceJob->final_amount - $paid, 2)));
        if ($serviceJob->relationLoaded('feedback') && $serviceJob->feedback) {
            $serviceJob->setAttribute('feedback_url', route('service-feedback.show', $serviceJob->feedback->token));
        }

        return $serviceJob;
    }

    private function distanceInMeters(float $latitude, float $longitude, float $targetLatitude, float $targetLongitude): float
    {
        $earthRadius = 6371000;
        $latitudeDelta = deg2rad($targetLatitude - $latitude);
        $longitudeDelta = deg2rad($targetLongitude - $longitude);
        $haversine = sin($latitudeDelta / 2) ** 2
            + cos(deg2rad($latitude)) * cos(deg2rad($targetLatitude)) * sin($longitudeDelta / 2) ** 2;

        return $earthRadius * 2 * atan2(sqrt($haversine), sqrt(1 - $haversine));
    }
}
