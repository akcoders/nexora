<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PremisesController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $premises = $request->user()->premises()
            ->where('active', true)
            ->orderBy('name')
            ->get(['premises.id', 'name', 'address', 'latitude', 'longitude', 'radius_meters']);

        return response()->json(['data' => $premises]);
    }
}
