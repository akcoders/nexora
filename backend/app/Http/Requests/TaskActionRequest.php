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
        return [
            'action' => ['required', Rule::in(['close', 'assign'])],
            'remark' => ['required', 'string', 'max:3000'],
            'assigned_to' => ['required_if:action,assign', 'nullable', Rule::exists('users', 'id')->where('status', 'active')],
            'attachments' => [Rule::requiredIf(fn (): bool => $this->input('action') === 'close' && $this->route('task')->attachment_required_on_completion), 'nullable', 'array', 'min:1', 'max:5'],
            'attachments.*' => ['required', 'file', 'mimes:jpg,jpeg,png,webp,pdf,doc,docx,xls,xlsx,txt,csv', 'max:10240'],
        ];
    }

    public function messages(): array
    {
        return ['attachments.required' => 'Attach at least one file before completing this workflow.'];
    }
}
