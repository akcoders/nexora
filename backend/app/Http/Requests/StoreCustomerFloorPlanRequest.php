<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCustomerFloorPlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('customers.update') ?? false;
    }

    public function rules(): array
    {
        $customer = $this->route('customer');

        return [
            'building_name' => ['nullable', 'required_without:image', 'string', 'max:150'],
            'customer_branch_id' => [
                'nullable',
                Rule::exists('customer_branches', 'id')->where('customer_id', $customer->id),
            ],
            'floors' => ['nullable', 'required_without:image', 'array', 'min:1', 'max:50'],
            'floors.*.name' => ['required_with:floors', 'string', 'max:100'],
            'floors.*.rooms' => ['required_with:floors', 'array', 'min:1', 'max:100'],
            'floors.*.rooms.*' => ['required', 'string', 'max:150'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'name' => ['nullable', 'required_with:image', 'string', 'max:150'],
            'floor_label' => ['nullable', 'string', 'max:100'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:10240'],
        ];
    }
}
