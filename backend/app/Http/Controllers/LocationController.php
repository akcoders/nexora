<?php

namespace App\Http\Controllers;

use App\Services\IndiaLocations;
use Illuminate\Http\JsonResponse;

class LocationController extends Controller
{
    /** All Indian states with their districts for the address dropdowns. */
    public function india(IndiaLocations $locations): JsonResponse
    {
        return response()
            ->json(['country' => IndiaLocations::COUNTRY, 'states' => $locations->all()])
            ->setPrivate()
            ->setMaxAge(3600);
    }
}
