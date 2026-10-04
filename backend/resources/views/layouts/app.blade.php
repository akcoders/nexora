<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ isset($title) ? $title.' · ' : '' }}{{ config('app.name', 'Nexora') }}</title>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body>
        <div class="nx-shell">
            <aside class="nx-sidebar" id="nxSidebar">
                <a class="nx-brand" href="{{ route('dashboard') }}"><span class="nx-brand-mark"><i data-lucide="snowflake"></i></span><span>NEXORA</span></a>
                <nav class="nx-nav">
                    <div class="nx-nav-label">Workspace</div>
                    <a class="nx-nav-link {{ request()->routeIs('dashboard') ? 'active' : '' }}" href="{{ route('dashboard') }}"><i data-lucide="layout-dashboard"></i>Dashboard</a>
                    <div class="nx-nav-label">Operations</div>
                    <a class="nx-nav-link {{ request()->routeIs('customers.*') ? 'active' : '' }}" href="{{ route('customers.index') }}"><i data-lucide="building-2"></i>Customers</a>
                    <a class="nx-nav-link {{ request()->routeIs('products.*') || request()->routeIs('product-categories.*') ? 'active' : '' }}" href="{{ route('products.index') }}"><i data-lucide="package"></i>Products</a>
                    <a class="nx-nav-link {{ request()->routeIs('checklists.*') ? 'active' : '' }}" href="{{ route('checklists.index') }}"><i data-lucide="clipboard-check"></i>Service Checklists</a>
                    @can('checklists.read')<a class="nx-nav-link {{ request()->routeIs('service-masters.*') ? 'active' : '' }}" href="{{ route('service-masters.index') }}"><i data-lucide="sliders-horizontal"></i>Service Masters</a>@endcan
                    <a class="nx-nav-link {{ request()->routeIs('service-jobs.*') ? 'active' : '' }}" href="{{ route('service-jobs.index') }}"><i data-lucide="wrench"></i>Service Jobs</a>
                    <a class="nx-nav-link {{ request()->routeIs('attendance.*') || request()->routeIs('premises.*') ? 'active' : '' }}" href="{{ route('attendance.index') }}"><i data-lucide="map-pin-check"></i>Attendance <span class="badge bg-warning text-dark ms-auto">{{ \App\Models\Attendance::where('status', 'pending')->count() }}</span></a>
                    <a class="nx-nav-link {{ request()->routeIs('tasks.*') ? 'active' : '' }}" href="{{ route('tasks.index') }}"><i data-lucide="list-checks"></i>Tasks & Workflow</a>
                    <a class="nx-nav-link {{ request()->routeIs('reports.*') ? 'active' : '' }}" href="{{ route('reports.index') }}"><i data-lucide="chart-no-axes-combined"></i>Reports</a>
                    <div class="nx-nav-label">Administration</div>
                    <a class="nx-nav-link {{ request()->routeIs('users.*') ? 'active' : '' }}" href="{{ route('users.index') }}"><i data-lucide="users-round"></i>Users & Roles</a>
                    <a class="nx-nav-link {{ request()->routeIs('notifications.*') ? 'active' : '' }}" href="{{ route('notifications.index') }}"><i data-lucide="bell"></i>Notifications @if(auth()->user()->unreadNotifications()->count())<span class="badge bg-danger ms-auto">{{ auth()->user()->unreadNotifications()->count() }}</span>@endif</a>
                    <a class="nx-nav-link {{ request()->routeIs('settings.*') ? 'active' : '' }}" href="{{ route('settings.index') }}"><i data-lucide="settings"></i>Settings</a>
                </nav>
                <div class="nx-user-card">
                    <span class="nx-avatar">{{ strtoupper(substr(auth()->user()->name, 0, 1)) }}</span>
                    <div class="min-w-0 flex-grow-1"><div class="text-white small fw-semibold text-truncate">{{ auth()->user()->name }}</div><div class="small text-truncate" style="color:#93c5fd">{{ auth()->user()->getRoleNames()->first() ?? 'User' }}</div></div>
                    <form method="POST" action="{{ route('logout') }}">@csrf<button class="btn p-1 text-white" title="Logout"><i data-lucide="log-out" style="width:17px"></i></button></form>
                </div>
            </aside>
            <div class="nx-main">
                <header class="nx-topbar">
                    <div class="d-flex align-items-center gap-3">
                        <button class="nx-icon-btn d-lg-none" type="button" onclick="document.getElementById('nxSidebar').classList.toggle('open')"><i data-lucide="menu"></i></button>
                        <div><div class="nx-eyebrow">Nexora / {{ $breadcrumb ?? 'Workspace' }}</div><h1 class="nx-page-title">{{ $pageTitle ?? 'Dashboard' }}</h1></div>
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        <button class="nx-icon-btn d-none d-sm-grid" title="Search"><i data-lucide="search" style="width:18px"></i></button>
                        <a class="nx-icon-btn position-relative" href="{{ route('notifications.index') }}" title="Notifications"><i data-lucide="bell" style="width:18px"></i>@if(auth()->user()->unreadNotifications()->count())<span class="position-absolute top-0 end-0 translate-middle p-1 bg-danger border border-light rounded-circle"></span>@endif</a>
                        <a href="{{ route('customers.create') }}" class="btn btn-primary d-none d-sm-inline-flex align-items-center gap-2"><i data-lucide="plus" style="width:17px"></i>New</a>
                    </div>
                </header>
                <main class="nx-content">
                    @if(session('success'))<div class="alert alert-success border-0 shadow-sm">{{ session('success') }}</div>@endif
                    {{ $slot }}
                </main>
            </div>
        </div>
        @stack('scripts')
    </body>
</html>
