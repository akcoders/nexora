<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InspectionCondition extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['is_issue' => 'boolean', 'active' => 'boolean'];
    }
}
