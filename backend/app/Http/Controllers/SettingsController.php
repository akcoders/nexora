<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use App\Services\DemoDataService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class SettingsController extends Controller
{
    public function index(DemoDataService $demoData): View
    {
        return view('settings.index', [
            'settings' => Setting::pluck('value', 'key'),
            'demoStats' => $demoData->stats(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'company_name' => ['required', 'string', 'max:255'],
            'company_email' => ['nullable', 'email'],
            'company_phone' => ['nullable', 'string', 'max:30'],
            'default_geofence_radius' => ['required', 'integer', 'min:25', 'max:5000'],
            'attendance_check_in_time' => ['required', 'date_format:H:i'],
            'attendance_check_in_grace_minutes' => ['required', 'integer', 'min:0', 'max:180'],
            'attendance_checkout_time' => ['required', 'date_format:H:i'],
            'attendance_checkout_grace_minutes' => ['required', 'integer', 'min:0', 'max:180'],
            'auto_checkout_time' => ['required', 'date_format:H:i'],
            'require_attendance_for_tasks' => ['nullable', 'boolean'],
        ]);

        foreach ($data as $key => $value) {
            Setting::updateOrCreate(['key' => $key], ['value' => $value, 'group' => str_starts_with($key, 'company_') ? 'company' : 'operations']);
        }
        if (! $request->has('require_attendance_for_tasks')) {
            Setting::updateOrCreate(['key' => 'require_attendance_for_tasks'], ['value' => '0', 'group' => 'operations', 'type' => 'boolean']);
        }

        return back()->with('success', 'Settings saved successfully.');
    }
}
