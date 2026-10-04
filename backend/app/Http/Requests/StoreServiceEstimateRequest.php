<?php

namespace App\Http\Requests;

use App\Models\ServiceJob;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreServiceEstimateRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $serviceJob = $this->route('service_job');

        return $serviceJob instanceof ServiceJob
            && ($this->user()?->can('service_jobs.create_estimate') ?? false)
            && ($serviceJob->assigned_to === $this->user()?->id
                || ($this->user()?->can('service_jobs.assign') ?? false)
                || ($this->user()?->can('service_jobs.update') ?? false));
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'discount_amount' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:3000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.service_catalog_item_id' => ['nullable', 'exists:service_catalog_items,id'],
            'items.*.product_id' => ['nullable', 'exists:products,id'],
            'items.*.type' => ['required', Rule::in(['service', 'material', 'charge'])],
            'items.*.description' => ['required_without:items.*.service_catalog_item_id', 'nullable', 'string', 'max:255'],
            'items.*.quantity' => ['required', 'numeric', 'gt:0'],
            'items.*.unit' => ['nullable', 'string', 'max:30'],
            'items.*.unit_rate' => ['nullable', 'numeric', 'min:0'],
            'items.*.tax_percent' => ['nullable', 'numeric', 'between:0,100'],
        ];
    }
}
