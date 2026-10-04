<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ChecklistItem extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'required' => 'boolean',
            'photo_required' => 'boolean',
            'remark_allowed' => 'boolean',
            'allowed_conditions' => 'array',
            'active' => 'boolean',
        ];
    }
}
