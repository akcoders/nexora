<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ServiceMasterOption extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['active' => 'boolean', 'metadata' => 'array'];
    }
}
