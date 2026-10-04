<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CustomerCreditTerm extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'advance_required' => 'boolean',
            'tds_applicable' => 'boolean',
            'retention_applicable' => 'boolean',
            'po_mandatory' => 'boolean',
            'eway_bill_applicable' => 'boolean',
        ];
    }
}
