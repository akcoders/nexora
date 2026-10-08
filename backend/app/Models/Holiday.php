<?php

namespace App\Models;

use Database\Factories\HolidayFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Holiday extends Model
{
    /** @use HasFactory<HolidayFactory> */
    use HasFactory;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['holiday_date' => 'date', 'optional' => 'boolean'];
    }
}
