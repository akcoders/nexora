<?php

namespace App\Http\Requests\Complaints;

use App\Services\ComplaintOptions;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ComplaintCommercialRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('complaint'));
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $money = ['nullable', 'numeric', 'min:0', 'max:10000000', 'decimal:0,2'];

        return [
            'service_category' => ['nullable', Rule::in(ComplaintOptions::keys('service_categories'))],
            'contract_no' => ['nullable', 'string', 'max:60'],
            'contract_expiry' => ['nullable', 'date'],
            'visit_charges' => $money,
            'labour_charges' => $money,
            'parts_charges' => $money,
            'travel_charges' => $money,
            'other_charges' => $money,
            'discount' => $money,
            'gst_percent' => ['required', 'numeric', 'between:0,28'],
            'customer_po_no' => ['nullable', 'string', 'max:60'],
            'quotation_no' => ['nullable', 'string', 'max:60'],
            'invoice_no' => ['nullable', 'string', 'max:60'],
            'payment_status' => ['required', Rule::in(ComplaintOptions::keys('payment_statuses'))],
        ];
    }
}
