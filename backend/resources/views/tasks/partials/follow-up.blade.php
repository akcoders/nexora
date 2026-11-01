<div class="mb-3" data-follow-up data-original-due="{{ $task->due_at?->format('Y-m-d\TH:i') }}" data-existing-follow-up="{{ $task->next_follow_up_at?->format('Y-m-d\TH:i') }}">
    <x-date-time-picker name="next_follow_up_at" :value="old('next_follow_up_at', $task->next_follow_up_at)" id="taskFollowUp" label="Next follow-up date & time" :allow-existing="true" />
    <div class="form-text">Original due: {{ $task->due_at?->format('d M Y, h:i A') ?? 'Not set' }}. The original deadline stays fixed.</div>
    <fieldset data-revision-fields hidden disabled class="mt-3 border-start border-warning border-3 ps-3">
        <label class="form-label" for="revisedDuePreview">Revised due date</label>
        <input class="form-control mb-2" id="revisedDuePreview" data-revision-preview readonly aria-describedby="revisionHelp">
        <div class="form-text mb-2" id="revisionHelp">Matches the next follow-up. Each revision is saved as a reason for delay.</div>
        <label class="form-label" for="taskDelayReason">Reason for delay *</label>
        <textarea class="form-control" name="delay_reason" id="taskDelayReason" rows="2" required maxlength="3000">{{ old('delay_reason') }}</textarea>
    </fieldset>
</div>
