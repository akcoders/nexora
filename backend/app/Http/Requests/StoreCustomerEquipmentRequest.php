<?php

namespace App\Http\Requests;

use App\Models\CustomerFloorPlan;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreCustomerEquipmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        $customer = $this->route('customer');

        return [
            'customer_branch_id' => [
                'nullable',
                Rule::exists('customer_branches', 'id')->where('customer_id', $customer->id),
            ],
            'customer_floor_plan_id' => [
                'nullable',
                Rule::exists('customer_floor_plans', 'id')->where('customer_id', $customer->id),
            ],
            'product_id' => ['nullable', 'exists:products,id'],
            'equipment_type' => ['required', 'string', 'max:120'],
            'brand' => ['nullable', 'string', 'max:120'],
            'model' => ['nullable', 'string', 'max:120'],
            'serial_no' => [
                'nullable',
                'string',
                'max:120',
                Rule::unique('customer_equipments')->where('customer_id', $customer->id),
            ],
            'capacity' => ['nullable', 'string', 'max:80'],
            'location' => ['required', 'string', 'max:180'],
            'plan_x' => ['nullable', 'required_with:customer_floor_plan_id', 'numeric', 'between:0,100'],
            'plan_y' => ['nullable', 'required_with:customer_floor_plan_id', 'numeric', 'between:0,100'],
            'installed_at' => ['nullable', 'date'],
            'warranty_ends_at' => ['nullable', 'date', 'after_or_equal:installed_at'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            if (! $this->filled('customer_floor_plan_id')) {
                return;
            }

            $floorPlan = CustomerFloorPlan::find($this->integer('customer_floor_plan_id'));
            $branchId = $this->filled('customer_branch_id') ? $this->integer('customer_branch_id') : null;

            if ($floorPlan && $floorPlan->customer_branch_id !== $branchId) {
                $validator->errors()->add('customer_branch_id', 'Select the branch linked to this floor plan.');
            }
        }];
    }
}
