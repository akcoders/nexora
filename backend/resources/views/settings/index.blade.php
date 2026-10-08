<x-app-layout>
    <x-slot name="title">Settings</x-slot>
    <x-slot name="pageTitle">System settings</x-slot>
    <x-slot name="breadcrumb">Administration / Settings</x-slot>

    <form method="POST" action="{{ route('settings.update') }}">
        @csrf
        @method('PUT')
        <div class="row g-4">
            <div class="col-xl-8">
                <section class="nx-card p-4 mb-4">
                    <div class="d-flex align-items-center gap-3 mb-4">
                        <span class="nx-metric-icon"><i data-lucide="building-2"></i></span>
                        <div><h2 class="h6 fw-bold mb-1">Company profile</h2><div class="small text-secondary">General identity displayed across the platform.</div></div>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-8"><label class="form-label">Company name *</label><input class="form-control" name="company_name" value="{{ old('company_name', $settings['company_name'] ?? 'Classic Cooling Systems Pvt. Ltd.') }}" required></div><div class="col-md-4"><label class="form-label">App short name *</label><input class="form-control" name="company_short_name" value="{{ old('company_short_name',$settings['company_short_name']??'Classic Field Service') }}" required></div>
                        <div class="col-md-6"><label class="form-label">Company email</label><input type="email" class="form-control" name="company_email" value="{{ old('company_email', $settings['company_email'] ?? '') }}"></div>
                        <div class="col-md-6"><label class="form-label">Company phone</label><input class="form-control" name="company_phone" value="{{ old('company_phone', $settings['company_phone'] ?? '') }}"></div>
                    </div>
                </section>

                <section class="nx-card p-4">
                    <div class="d-flex align-items-center gap-3 mb-4">
                        <span class="nx-metric-icon"><i data-lucide="calendar-clock"></i></span>
                        <div><h2 class="h6 fw-bold mb-1">Attendance rules</h2><div class="small text-secondary">Control full-day, half-day and checkout classification.</div></div>
                    </div>
                    <div class="alert alert-light border small mb-4">Check-in after start time + grace is a half day. Checkout before end time − grace is also a half day.</div>
                    <div class="row g-3">
                        <div class="col-md-6"><label class="form-label">Full-day check-in time</label><input type="time" class="form-control" name="attendance_check_in_time" value="{{ old('attendance_check_in_time', $settings['attendance_check_in_time'] ?? '09:00') }}" required></div>
                        <div class="col-md-6"><label class="form-label">Check-in grace (minutes)</label><input type="number" class="form-control" name="attendance_check_in_grace_minutes" value="{{ old('attendance_check_in_grace_minutes', $settings['attendance_check_in_grace_minutes'] ?? 15) }}" min="0" max="180" required></div>
                        <div class="col-md-6"><label class="form-label">Full-day checkout time</label><input type="time" class="form-control" name="attendance_checkout_time" value="{{ old('attendance_checkout_time', $settings['attendance_checkout_time'] ?? '18:00') }}" required></div>
                        <div class="col-md-6"><label class="form-label">Checkout grace (minutes)</label><input type="number" class="form-control" name="attendance_checkout_grace_minutes" value="{{ old('attendance_checkout_grace_minutes', $settings['attendance_checkout_grace_minutes'] ?? 15) }}" min="0" max="180" required></div>
                        <div class="col-md-6"><label class="form-label">Auto checkout time</label><input type="time" class="form-control" name="auto_checkout_time" value="{{ old('auto_checkout_time', $settings['auto_checkout_time'] ?? '23:59') }}" required></div>
                        <div class="col-md-6"><label class="form-label">Default geofence radius (meters)</label><input type="number" class="form-control" name="default_geofence_radius" value="{{ old('default_geofence_radius', $settings['default_geofence_radius'] ?? 5) }}" min="5" max="5000" required></div>
                        <div class="col-12"><label class="form-check form-switch"><input type="checkbox" class="form-check-input" name="require_attendance_for_tasks" value="1" @checked(old('require_attendance_for_tasks', $settings['require_attendance_for_tasks'] ?? '1') == '1')><span class="form-check-label fw-semibold">Require attendance before technician task actions</span></label></div>
                    </div>
                </section>
                <section class="nx-card p-4 mt-4">
                    <div class="d-flex align-items-center gap-3 mb-4"><span class="nx-metric-icon"><i data-lucide="bell-ring"></i></span><div><h2 class="h6 fw-bold mb-1">OneSignal push notifications</h2><div class="small text-secondary">Mobile push credentials used for task, workflow, leave and expense alerts.</div></div></div>
                    <div class="row g-3"><div class="col-12"><label class="form-label">OneSignal App ID</label><input class="form-control" name="onesignal_app_id" value="{{ old('onesignal_app_id',$settings['onesignal_app_id']??'') }}" placeholder="00000000-0000-0000-0000-000000000000"></div><div class="col-12"><label class="form-label">REST API Key</label><input type="password" class="form-control" name="onesignal_rest_api_key" placeholder="{{ $hasOneSignalKey?'Stored securely — leave blank to keep':'Enter API key' }}"><div class="form-text">The key is encrypted before storage.</div></div></div>
                </section>
            </div>

            <div class="col-xl-4">
                <aside class="nx-card p-4 nx-summary">
                    <h3 class="h6 fw-bold mb-3">Environment</h3>
                    <div class="small border-bottom py-2 d-flex justify-content-between"><span class="text-secondary">Application</span><strong>{{ app()->environment() }}</strong></div>
                    <div class="small border-bottom py-2 d-flex justify-content-between"><span class="text-secondary">Timezone</span><strong>{{ config('app.timezone') }}</strong></div>
                    <div class="small border-bottom py-2"><span class="text-secondary d-block">Production API</span><strong class="text-break">https://nexora.webignitors.in/api/v1/</strong></div>
                    <button class="btn btn-primary w-100 mt-4">Save settings</button>
                </aside>
            </div>
        </div>
    </form>

    @can('settings.update')
        <section class="nx-card p-4 mt-4 nx-demo-data-card">
            <div class="d-flex flex-column flex-lg-row justify-content-between gap-4">
                <div class="d-flex gap-3"><span class="nx-metric-icon" style="--metric:#7c3aed;--metric-soft:#ede9fe"><i data-lucide="flask-conical"></i></span><div><h2 class="h6 fw-bold mb-1">Demo data control</h2><div class="small text-secondary">Generate 10 linked scenarios covering customers, groups, contacts, branches, AC units, technicians, attendance, workflow tasks and service-job stages.</div><div class="d-flex flex-wrap gap-2 mt-3"><span class="badge rounded-pill badge-soft-primary">{{ $demoStats['scenarios'] }} scenarios</span><span class="badge rounded-pill badge-soft-success">{{ $demoStats['customers'] }} customers</span><span class="badge rounded-pill badge-soft-warning">{{ $demoStats['service_jobs'] }} service jobs</span><span class="badge rounded-pill bg-light text-secondary">{{ $demoStats['tasks'] }} tasks</span></div></div></div>
                <div class="d-flex flex-wrap align-items-start gap-2 flex-shrink-0"><form method="POST" action="{{ route('settings.demo-data.store') }}" onsubmit="return confirm('Existing generated demo scenarios will be reset. Continue?')">@csrf<button class="btn btn-outline-primary"><i data-lucide="refresh-cw" style="width:17px"></i>Create / reset 10 demos</button></form><form method="POST" action="{{ route('settings.demo-data.destroy') }}" onsubmit="return confirm('Clear all generated demo records? Real records will not be touched.')">@csrf @method('DELETE')<button class="btn btn-outline-danger" @disabled($demoStats['scenarios'] === 0)><i data-lucide="trash-2" style="width:17px"></i>Clear demo data</button></form></div>
            </div>
        </section>
    @endcan
</x-app-layout>
