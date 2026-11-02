<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use App\Services\DemoDataService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;

class SettingsController extends Controller
{
    public function index(Request $request, DemoDataService $demoData): View
    {
        abort_unless($request->user()->can('settings.read') || $request->user()->can('settings.update'), 403);

        return view('settings.index', [
            'settings' => Setting::pluck('value', 'key'),
            'demoStats' => $demoData->stats(),
            'hasOneSignalKey' => filled(Setting::valueFor('onesignal_rest_api_key')),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('settings.update'), 403);
        $data = $request->validate([
            'company_name' => ['required', 'string', 'max:255'],
            'company_short_name' => ['required', 'string', 'max:80'],
            'company_email' => ['nullable', 'email'],
            'company_phone' => ['nullable', 'string', 'max:30'],
            'default_geofence_radius' => ['required', 'integer', 'min:5', 'max:5000'],
            'attendance_check_in_time' => ['required', 'date_format:H:i'],
            'attendance_check_in_grace_minutes' => ['required', 'integer', 'min:0', 'max:180'],
            'attendance_checkout_time' => ['required', 'date_format:H:i'],
            'attendance_checkout_grace_minutes' => ['required', 'integer', 'min:0', 'max:180'],
            'auto_checkout_time' => ['required', 'date_format:H:i'],
            'require_attendance_for_tasks' => ['nullable', 'boolean'],
            'onesignal_app_id' => ['nullable', 'uuid'],
            'onesignal_rest_api_key' => ['nullable', 'string', 'max:500'],
            'complaint_prefix' => ['nullable', 'string', 'max:12', 'regex:/^[A-Za-z0-9-]+$/'],
            'complaint_branch_code' => ['nullable', 'string', 'max:12', 'regex:/^[A-Za-z0-9-]+$/'],
        ], [
            'complaint_prefix.regex' => 'Use letters, numbers and dashes only (for example CCSPL).',
            'complaint_branch_code.regex' => 'Use letters, numbers and dashes only (for example MUM).',
        ]);

        foreach ($data as $key => $value) {
            if ($key === 'onesignal_rest_api_key') {
                if (blank($value)) {
                    continue;
                }
                $value = Crypt::encryptString($value);
            }
            if (in_array($key, ['complaint_prefix', 'complaint_branch_code'], true)) {
                $value = strtoupper((string) $value);
            }
            Setting::updateOrCreate(['key' => $key], ['value' => $value, 'group' => str_starts_with($key, 'company_') ? 'company' : (str_starts_with($key, 'complaint_') ? 'complaints' : 'operations')]);
        }
        if (! $request->has('require_attendance_for_tasks')) {
            Setting::updateOrCreate(['key' => 'require_attendance_for_tasks'], ['value' => '0', 'group' => 'operations', 'type' => 'boolean']);
        }

        return back()->with('success', 'Settings saved successfully.');
    }
}
