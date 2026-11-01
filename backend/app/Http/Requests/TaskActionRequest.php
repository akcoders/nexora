<?php

namespace App\Http\Requests;

use App\Models\Task;
use App\Support\RecordVisibility;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TaskActionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $task = $this->route('task');

        return $task instanceof Task
            && RecordVisibility::allows($this->user(), $task, 'tasks')
            && ($this->is('api/*') ? $task->assigned_to === $this->user()->id : $this->user()->can('act', $task));
    }

    public function rules(): array
    {
        $updating = $this->input('action') === 'update';
        $reassigning = $this->input('action') === 'assign' || ($updating && $this->boolean('reassign'));
        $rescheduling = $updating && $this->boolean('change_due_date');
        $completing = $this->input('action') === 'close' || ($updating && $this->input('status') === Task::STATUS_CLOSED);

        return [
            'action' => ['required', Rule::in(['update', 'close', 'assign'])],
            'remark' => ['required', 'string', 'max:3000'],
            'status' => [Rule::excludeIf(! $updating), 'required', Rule::in(['pending', 'in_progress', Task::STATUS_CLOSED])],
            'reassign' => [Rule::excludeIf(! $updating), 'sometimes', 'boolean', Rule::prohibitedIf($completing && $this->boolean('reassign'))],
            'assigned_to' => [Rule::excludeIf(! $reassigning), 'required', Rule::exists('users', 'id')->where('status', 'active'), Rule::when($updating, [Rule::notIn([$this->route('task')->assigned_to])])],
            'change_due_date' => [Rule::excludeIf(! $updating), 'sometimes', 'boolean'],
            'due_at' => [Rule::excludeIf(! $rescheduling), 'present', 'nullable', 'date', 'after_or_equal:today'],
            'reason' => [Rule::excludeIf(! $rescheduling), 'required', 'string', 'max:3000'],
            'attachments' => [Rule::requiredIf($completing && $this->route('task')->attachment_required_on_completion), 'nullable', 'array', 'min:1', 'max:5'],
            'attachments.*' => ['required', 'file', 'mimes:jpg,jpeg,png,webp,pdf,doc,docx,xls,xlsx,txt,csv', 'max:10240'],
        ];
    }

    public function messages(): array
    {
        return [
            'attachments.required' => 'Attach at least one file before completing this workflow.',
            'due_at.after_or_equal' => 'Choose today or a later due date.',
            'reason.required' => 'Please specify why you are changing the due date.',
            'reassign.prohibited' => 'Complete the workflow or assign it to another owner, one at a time.',
        ];
    }
}
