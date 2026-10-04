<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CustomerContact extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'dob' => 'date',
            'anniversary' => 'date',
            'is_primary' => 'boolean',
            'is_service' => 'boolean',
            'is_billing' => 'boolean',
            'is_escalation' => 'boolean',
        ];
    }
}
