<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <meta name="nx-locations-url" content="{{ route('locations.india') }}">

        <title>{{ isset($title) ? $title.' · ' : '' }}{{ config('app.name', 'Nexora') }}</title>
        @php
            $nxUser = auth()->user();
            $nxLanguageOptions = \App\Services\EmployeeMasterOptions::switcherLanguagesFor($nxUser);
            $nxPreferredLanguage = \App\Services\EmployeeMasterOptions::preferredLanguageFor($nxUser);
            $nxOldModal = old('_modal');
            $nxOldInput = $nxOldModal ? \Illuminate\Support\Arr::except(session()->getOldInput(), ['_token', '_method', 'password', 'password_confirmation', 'current_password', 'temporary_password']) : null;
            $nxUnread = $nxUser ? $nxUser->unreadNotifications()->count() : 0;
            $nxPendingAttendance = \App\Models\Attendance::where('status', 'pending')->count();
            $nxLanguageConfig = ['userId' => $nxUser?->id, 'preferred' => $nxPreferredLanguage, 'preferredCode' => \App\Services\EmployeeMasterOptions::languageCode($nxPreferredLanguage), 'options' => $nxLanguageOptions, 'saveUrl' => route('profile.language')];
        @endphp
        <script>
            window.nxLanguage = @json($nxLanguageConfig);
            window.nxServerErrors = @json($errors->toArray());
            window.nxOldModal = @json($nxOldModal);
            window.nxOldInput = @json($nxOldInput);
        </script>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body>
        <div class="nx-shell">
            <div class="nx-sidebar-backdrop" id="nxSidebarBackdrop" onclick="document.getElementById('nxSidebar').classList.remove('open');this.classList.remove('show')"></div>
            <aside class="nx-sidebar" id="nxSidebar">
                <a class="nx-brand notranslate" translate="no" href="{{ route('dashboard') }}"><span class="nx-brand-mark p-0 overflow-hidden bg-white"><img src="{{ asset('images/brand/classic-app-icon.png') }}" alt="Classic" class="w-100 h-100 object-fit-cover"></span><span>CLASSIC</span></a>
                <nav class="nx-nav">
                    <div class="nx-nav-label">Workspace</div>
                    <a class="nx-nav-link {{ request()->routeIs('dashboard') ? 'active' : '' }}" href="{{ route('dashboard') }}"><i data-lucide="layout-dashboard"></i>Dashboard</a>
                    <div class="nx-nav-label">Operations</div>
                    <a class="nx-nav-link {{ request()->routeIs('customers.*') ? 'active' : '' }}" href="{{ route('customers.index') }}"><i data-lucide="building-2"></i>Customers</a>
                    <a class="nx-nav-link {{ request()->routeIs('customer-groups.*') ? 'active' : '' }}" href="{{ route('customer-groups.index') }}"><i data-lucide="tags"></i>Customer Groups</a>
                    <a class="nx-nav-link {{ request()->routeIs('products.*') || request()->routeIs('product-categories.*') ? 'active' : '' }}" href="{{ route('products.index') }}"><i data-lucide="package"></i>Products</a>
                    <a class="nx-nav-link {{ request()->routeIs('checklists.*') ? 'active' : '' }}" href="{{ route('checklists.index') }}"><i data-lucide="clipboard-check"></i>Service Checklists</a>
                    @can('checklists.read')<a class="nx-nav-link {{ request()->routeIs('service-masters.*') ? 'active' : '' }}" href="{{ route('service-masters.index') }}"><i data-lucide="sliders-horizontal"></i>Service Masters</a>@endcan
                    <a class="nx-nav-link {{ request()->routeIs('service-jobs.*') ? 'active' : '' }}" href="{{ route('service-jobs.index') }}"><i data-lucide="wrench"></i>Service Jobs</a>
                    <a class="nx-nav-link {{ request()->routeIs('attendance.*') || request()->routeIs('premises.*') ? 'active' : '' }}" href="{{ route('attendance.index') }}"><i data-lucide="map-pin-check"></i>Attendance @if($nxPendingAttendance)<span class="badge bg-warning text-dark ms-auto notranslate">{{ $nxPendingAttendance }}</span>@endif</a>
                    @php $workType = request('type') ?: request()->route('task')?->task_type; @endphp
                    @php $onBoard = request()->routeIs('tasks.board'); @endphp
                    <a class="nx-nav-link {{ request()->routeIs('tasks.*') && ! $onBoard && $workType !== 'ticket' ? 'active' : '' }}" href="{{ route('tasks.index', ['scope' => 'open', 'type' => 'workflow']) }}"><i data-lucide="git-branch"></i>Internal Workflow</a>
                    <a class="nx-nav-link {{ request()->routeIs('tasks.*') && ! $onBoard && $workType === 'ticket' ? 'active' : '' }}" href="{{ route('tasks.index', ['scope' => 'open', 'type' => 'ticket']) }}"><i data-lucide="ticket-check"></i>Customer Tickets</a>
                    <a class="nx-nav-link {{ $onBoard ? 'active' : '' }}" href="{{ route('tasks.board') }}"><i data-lucide="square-kanban"></i>Kanban Board</a>
                    <a class="nx-nav-link {{ request()->routeIs('reports.*') ? 'active' : '' }}" href="{{ route('reports.index') }}"><i data-lucide="chart-no-axes-combined"></i>Reports</a>
                    <div class="nx-nav-label">HR & People</div>
                    @can('employees.read')<a class="nx-nav-link {{ request()->routeIs('employees.*') ? 'active' : '' }}" href="{{ route('employees.index') }}"><i data-lucide="contact-round"></i>Employees</a>@endcan
                    @can('hr.read')<a class="nx-nav-link {{ request()->routeIs('hr.*') ? 'active' : '' }}" href="{{ route('hr.index') }}"><i data-lucide="calendar-heart"></i>Leave & Expenses</a>@endcan
                    @can('payroll.read')<a class="nx-nav-link {{ request()->routeIs('payroll.*') ? 'active' : '' }}" href="{{ route('payroll.index') }}"><i data-lucide="wallet-cards"></i>Payroll</a>@endcan
                    <div class="nx-nav-label">Administration</div>
                    @can('companies.read')<a class="nx-nav-link {{ request()->routeIs('companies.*') ? 'active' : '' }}" href="{{ route('companies.index') }}"><i data-lucide="landmark"></i>Company Master</a>@endcan
                    <a class="nx-nav-link {{ request()->routeIs('users.*') ? 'active' : '' }}" href="{{ route('users.index') }}"><i data-lucide="users-round"></i>User Accounts</a>
                    @can('roles.read')<a class="nx-nav-link {{ request()->routeIs('roles.*') ? 'active' : '' }}" href="{{ route('roles.index') }}"><i data-lucide="shield-check"></i>Roles & Permissions</a>@endcan
                    <a class="nx-nav-link {{ request()->routeIs('notifications.*') ? 'active' : '' }}" href="{{ route('notifications.index') }}"><i data-lucide="bell"></i>Notifications @if($nxUnread)<span class="badge bg-danger ms-auto notranslate">{{ $nxUnread }}</span>@endif</a>
                    <a class="nx-nav-link {{ request()->routeIs('settings.*') ? 'active' : '' }}" href="{{ route('settings.index') }}"><i data-lucide="settings"></i>Settings</a>
                </nav>
                <div class="nx-user-card">
                    <a class="nx-avatar notranslate" href="{{ route('profile.edit') }}" title="My profile">{{ strtoupper(substr($nxUser->name, 0, 1)) }}</a>
                    <div class="min-w-0 flex-grow-1"><a class="text-white small fw-semibold text-truncate d-block notranslate" href="{{ route('profile.edit') }}">{{ $nxUser->name }}</a><div class="small text-truncate" style="color:#93c5fd">{{ $nxUser->getRoleNames()->first() ?? 'User' }}</div></div>
                    <form method="POST" action="{{ route('logout') }}">@csrf<button class="btn p-1 text-white" title="Logout"><i data-lucide="log-out" style="width:17px"></i></button></form>
                </div>
            </aside>
            <div class="nx-main">
                <header class="nx-topbar">
                    <div class="d-flex align-items-center gap-3 min-w-0">
                        <button class="nx-icon-btn d-lg-none flex-shrink-0" type="button" aria-label="Open menu" onclick="document.getElementById('nxSidebar').classList.toggle('open');document.getElementById('nxSidebarBackdrop').classList.toggle('show')"><i data-lucide="menu"></i></button>
                        <div class="min-w-0"><div class="nx-eyebrow text-truncate">Classic / {{ $breadcrumb ?? 'Workspace' }}</div><h1 class="nx-page-title text-truncate">{{ $pageTitle ?? 'Dashboard' }}</h1></div>
                    </div>
                    <div class="d-flex align-items-center gap-2 flex-shrink-0">
                        <div class="dropdown nx-language" data-language-switcher>
                            <button class="nx-icon-btn nx-language-toggle" type="button" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false" title="Change interface language"><i data-lucide="languages" style="width:18px"></i><span class="nx-language-current notranslate" data-language-label>{{ $nxPreferredLanguage }}</span></button>
                            <div class="dropdown-menu dropdown-menu-end nx-language-menu shadow">
                                <div class="nx-language-head"><strong>Interface language</strong><small class="text-secondary d-block">Languages from your employee profile</small></div>
                                <div data-language-menu></div>
                            </div>
                        </div>
                        <a class="nx-icon-btn position-relative" href="{{ route('notifications.index') }}" title="Notifications"><i data-lucide="bell" style="width:18px"></i>@if($nxUnread)<span class="position-absolute top-0 end-0 translate-middle p-1 bg-danger border border-light rounded-circle"></span>@endif</a>
                        <div class="dropdown">
                            <button class="btn btn-primary d-none d-sm-inline-flex align-items-center gap-2" type="button" data-bs-toggle="dropdown" aria-expanded="false"><i data-lucide="plus" style="width:17px"></i>New</button>
                            <div class="dropdown-menu dropdown-menu-end nx-quick-create shadow">
                                <a class="dropdown-item" href="{{ route('customers.create') }}"><i data-lucide="building-2"></i>Customer</a>
                                <a class="dropdown-item" href="{{ route('service-jobs.create') }}"><i data-lucide="wrench"></i>Service job</a>
                                @can('employees.create')<a class="dropdown-item" href="{{ route('employees.create') }}"><i data-lucide="contact-round"></i>Employee</a>@endcan
                                @can('companies.create')<a class="dropdown-item" href="{{ route('companies.create') }}"><i data-lucide="landmark"></i>Company</a>@endcan
                                <a class="dropdown-item" href="{{ route('tasks.index', ['scope' => 'open', 'type' => 'ticket']) }}"><i data-lucide="ticket-check"></i>Customer ticket</a>
                                <a class="dropdown-item" href="{{ route('tasks.board') }}"><i data-lucide="square-kanban"></i>Kanban board</a>
                            </div>
                        </div>
                    </div>
                </header>
                <main class="nx-content">
                    @if(session('success'))<div class="alert alert-success nx-flash border-0 shadow-sm d-flex align-items-start gap-2" role="status"><i data-lucide="circle-check" style="width:18px;flex:0 0 auto;margin-top:2px"></i><div>{{ session('success') }}</div><button type="button" class="btn-close ms-auto" data-bs-dismiss="alert" aria-label="Dismiss"></button></div>@endif
                    @if(session('error'))<div class="alert alert-danger nx-flash border-0 shadow-sm d-flex align-items-start gap-2" role="alert"><i data-lucide="circle-alert" style="width:18px;flex:0 0 auto;margin-top:2px"></i><div>{{ session('error') }}</div><button type="button" class="btn-close ms-auto" data-bs-dismiss="alert" aria-label="Dismiss"></button></div>@endif
                    @if(session('warning'))<div class="alert alert-warning nx-flash border-0 shadow-sm d-flex align-items-start gap-2" role="alert"><i data-lucide="triangle-alert" style="width:18px;flex:0 0 auto;margin-top:2px"></i><div>{{ session('warning') }}</div><button type="button" class="btn-close ms-auto" data-bs-dismiss="alert" aria-label="Dismiss"></button></div>@endif
                    {{ $slot }}
                </main>
            </div>
        </div>
        @stack('scripts')
    </body>
</html>
