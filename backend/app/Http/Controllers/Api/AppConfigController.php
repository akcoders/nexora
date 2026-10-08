<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\JsonResponse;

class AppConfigController extends Controller
{
    public function __invoke(): JsonResponse
    {
        return response()->json([
            'app_name' => Setting::valueFor('company_short_name', 'Classic Field Service'),
            'onesignal_app_id' => Setting::valueFor('onesignal_app_id'),
            'minimum_geofence_radius' => 5,
        ]);
    }
}
