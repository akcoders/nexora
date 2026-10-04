<?php

namespace App\Http\Controllers;

use App\Services\DemoDataService;
use Database\Seeders\DemoDataSeeder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;

class DemoDataController extends Controller
{
    public function store(Request $request, DemoDataService $demoData): RedirectResponse
    {
        abort_unless($request->user()->can('settings.update'), 403);

        $demoData->clear();
        Artisan::call('db:seed', ['--class' => DemoDataSeeder::class, '--force' => true]);

        return back()->with('success', '10 complete demo scenarios created successfully.');
    }

    public function destroy(Request $request, DemoDataService $demoData): RedirectResponse
    {
        abort_unless($request->user()->can('settings.update'), 403);

        $deleted = $demoData->clear();

        return back()->with('success', "Demo data cleared safely ({$deleted} primary records removed).");
    }
}
