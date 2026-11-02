<?php

namespace App\Http\Requests;

use App\Models\Task;
use App\Support\RecordVisibility;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        $parent = $this->route('task');
        if ($parent instanceof Task) {
            return RecordVisibility::allows($this->user(), $parent, 'tasks') && $this->user()->can('act', $parent);
        }

        return $this->user()->can('tasks.create') || $this->user()->can('tasks.assign');
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['task_type' => Task::TYPE_WORKFLOW, 'category' => 'Internal']);
    }

    public function messages(): array
    {
        return ['due_at.after_or_equal' => 'Choose today or a later due date.'];
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'task_type' => ['required', Rule::in([Task::TYPE_WORKFLOW])],
            'description' => ['nullable', 'string', 'max:5000'],
            'customer_id' => ['prohibited'],
            'category' => ['nullable', 'string', 'max:60'],
            'priority' => ['required', Rule::in(Task::PRIORITIES)],
            'due_at' => ['nullable', 'date', 'after_or_equal:today'],
            'assigned_to' => ['required', Rule::exists('users', 'id')->where('status', 'active')],
            'observers' => [Rule::excludeIf($this->route('task') instanceof Task), 'nullable', 'array'],
            'observers.*' => ['integer', 'distinct', Rule::exists('users', 'id')->where('status', 'active')],
            'attachment_required_on_completion' => ['sometimes', 'boolean'],
            'parent_task_id' => ['prohibited'],
            'return_to' => ['nullable', Rule::in(['board'])],
        ];
    }
}
