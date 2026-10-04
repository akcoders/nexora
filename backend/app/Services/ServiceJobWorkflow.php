<?php

namespace App\Services;

use App\Models\ServiceJob;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ServiceJobWorkflow
{
    public const LABELS = [
        'assigned' => 'Assigned',
        'contacted' => 'Customer Contacted',
        'confirmed' => 'Visit Confirmed',
        'rescheduled' => 'Rescheduled',
        'no_answer' => 'No Answer',
        'customer_declined' => 'Customer Declined',
        'on_the_way' => 'Going to Customer',
        'arrived' => 'Reached Customer',
        'inspection_in_progress' => 'Inspection in Progress',
        'inspection_completed' => 'Inspection Completed',
        'estimate_created' => 'Estimate Ready',
        'estimate_pending_approval' => 'Awaiting Approval',
        'customer_approved' => 'Approved',
        'service_in_progress' => 'Service in Progress',
        'service_completed' => 'Service Work Completed',
        'payment_pending' => 'Payment Pending',
        'partially_paid' => 'Partially Paid',
        'paid' => 'Paid',
        'completed' => 'Completed',
        'cancelled' => 'Cancelled',
        'on_hold' => 'On Hold',
    ];

    private const TRANSITIONS = [
        'assigned' => ['contacted', 'confirmed', 'rescheduled', 'no_answer', 'customer_declined', 'cancelled'],
        'contacted' => ['confirmed', 'rescheduled', 'no_answer', 'customer_declined', 'cancelled'],
        'no_answer' => ['contacted', 'confirmed', 'rescheduled', 'cancelled'],
        'rescheduled' => ['contacted', 'confirmed', 'cancelled'],
        'confirmed' => ['on_the_way', 'rescheduled', 'cancelled'],
        'on_the_way' => ['arrived', 'on_hold', 'cancelled'],
        'arrived' => ['inspection_in_progress', 'cancelled'],
        'inspection_in_progress' => ['inspection_completed', 'on_hold'],
        'inspection_completed' => ['estimate_created'],
        'estimate_created' => ['estimate_pending_approval', 'customer_approved', 'customer_declined'],
        'estimate_pending_approval' => ['estimate_created', 'customer_approved', 'customer_declined'],
        'customer_approved' => ['service_in_progress'],
        'service_in_progress' => ['service_completed', 'on_hold'],
        'service_completed' => ['payment_pending', 'partially_paid', 'paid'],
        'payment_pending' => ['partially_paid', 'paid', 'completed'],
        'partially_paid' => ['paid', 'completed'],
        'paid' => ['completed'],
        'on_hold' => ['confirmed', 'inspection_in_progress', 'service_in_progress', 'cancelled'],
    ];

    public function transition(ServiceJob $serviceJob, string $toStatus, User $actor, array $context = []): ServiceJob
    {
        return DB::transaction(function () use ($serviceJob, $toStatus, $actor, $context): ServiceJob {
            $lockedJob = ServiceJob::query()->lockForUpdate()->findOrFail($serviceJob->id);
            $fromStatus = $lockedJob->status;

            if (! in_array($toStatus, self::TRANSITIONS[$fromStatus] ?? [], true)) {
                throw ValidationException::withMessages([
                    'status' => "This job cannot move from {$this->label($fromStatus)} to {$this->label($toStatus)}.",
                ]);
            }

            $timestamps = match ($toStatus) {
                'confirmed' => ['confirmed_at' => now()],
                'on_the_way' => ['journey_started_at' => now()],
                'arrived' => ['arrived_at' => now()],
                'inspection_completed' => ['inspection_completed_at' => now()],
                'service_in_progress' => ['service_started_at' => now()],
                'service_completed' => ['service_completed_at' => now()],
                'completed' => ['completed_at' => now()],
                default => [],
            };

            $lockedJob->update(array_merge(['status' => $toStatus], $timestamps));
            $lockedJob->statusHistory()->create([
                'from_status' => $fromStatus,
                'to_status' => $toStatus,
                'changed_by' => $actor->id,
                'reason' => $context['reason'] ?? null,
                'remark' => $context['remark'] ?? null,
                'latitude' => $context['latitude'] ?? null,
                'longitude' => $context['longitude'] ?? null,
                'changed_at' => now(),
            ]);

            return $lockedJob->fresh();
        });
    }

    public function recordInitialStatus(ServiceJob $serviceJob, User $actor): void
    {
        $serviceJob->statusHistory()->create([
            'to_status' => $serviceJob->status,
            'changed_by' => $actor->id,
            'remark' => 'Service job created and assigned.',
            'changed_at' => now(),
        ]);
    }

    public function label(string $status): string
    {
        return self::LABELS[$status] ?? str($status)->replace('_', ' ')->headline()->toString();
    }

    public function primaryAction(ServiceJob $serviceJob): ?array
    {
        $hasPaymentRecord = $serviceJob->relationLoaded('payments')
            ? $serviceJob->payments->isNotEmpty()
            : (isset($serviceJob->payments_count)
                ? (int) $serviceJob->payments_count > 0
                : $serviceJob->payments()->exists());

        if (in_array($serviceJob->status, ['payment_pending', 'partially_paid'], true) && $hasPaymentRecord) {
            return ['key' => 'complete', 'label' => 'Complete Service'];
        }

        return match ($serviceJob->status) {
            'assigned', 'contacted', 'no_answer', 'rescheduled' => ['key' => 'contact', 'label' => 'Confirm Visit'],
            'confirmed' => ['key' => 'start_journey', 'label' => 'Start Journey'],
            'on_the_way' => ['key' => 'arrive', 'label' => 'Mark Arrived'],
            'arrived' => ['key' => 'start_inspection', 'label' => 'Begin Inspection'],
            'inspection_in_progress' => ['key' => 'complete_inspection', 'label' => 'Complete Inspection'],
            'inspection_completed' => ['key' => 'estimate', 'label' => 'Create Estimate'],
            'estimate_created', 'estimate_pending_approval' => ['key' => 'approval', 'label' => 'Customer Approval'],
            'customer_approved' => ['key' => 'start_service', 'label' => 'Begin Service'],
            'service_in_progress' => ['key' => 'complete_service_work', 'label' => 'Review Service'],
            'service_completed', 'payment_pending', 'partially_paid' => ['key' => 'payment', 'label' => 'Collect Payment'],
            'paid' => ['key' => 'complete', 'label' => 'Complete Service'],
            default => null,
        };
    }
}
