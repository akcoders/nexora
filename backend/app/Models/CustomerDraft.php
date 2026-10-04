<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CustomerDraft extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['data' => 'array'];
    }
}
