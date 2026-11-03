<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <meta name="nx-locations-url" content="{{ route('locations.india') }}">
        <meta name="nx-search-url" content="{{ route('search') }}">

        <title>{{ isset($title) ? $title.' · ' : '' }}{{ config('app.name', 'Nexora') }}</title>
        @php
            $nxOldModal = old('_modal');
            $nxOldInput = $nxOldModal ? \Illuminate\Support\Arr::except(session()->getOldInput(), ['_token', '_method', 'password', 'password_confirmation', 'current_password', 'temporary_password']) : null;
            // Crumbs are separated by " / " so values that contain a slash (complaint numbers) stay whole.
            $nxCrumbs = collect(preg_split('/\s+\/\s+/', trim((string) ($breadcrumb ?? ''))))->map(fn ($part) => html_entity_decode(trim($part), ENT_QUOTES | ENT_HTML5, 'UTF-8'))->filter()->values();
        @endphp
        <script>
            window.nxLanguage = @json($nxLanguageConfig);
            window.nxServerErrors = @json($errors->toArray());
            window.nxOldModal = @json($nxOldModal);
            window.nxOldInput = @json($nxOldInput);
            try { if (localStorage.getItem('nx:sidebar') === 'collapsed' && window.innerWidth >= 992) document.documentElement.classList.add('nx-pre-collapsed'); } catch (e) {}
        </script>
        @vite(['resources/css/app.css', 'resources/css/theme.css', 'resources/js/app.js', 'resources/js/datatables.js'])
        @stack('head')
    </head>
    <body>
        <script>if (document.documentElement.classList.contains('nx-pre-collapsed')) document.body.classList.add('nx-sidebar-collapsed');</script>
        <a class="visually-hidden-focusable position-absolute top-0 start-0 m-2 btn btn-primary" href="#nxContent">Skip to content</a>
        <div class="nx-shell">
            <div class="nx-sidebar-backdrop" id="nxSidebarBackdrop" data-sidebar-close></div>
            <aside class="nx-sidebar" id="nxSidebar" aria-label="Main navigation">
                <a class="nx-brand" href="{{ route('dashboard') }}" title="Dashboard">
                    <span class="nx-brand-mark"><img src="{{ asset('images/brand/classic-app-icon.png') }}" alt="" class="w-100 h-100 object-fit-cover"></span>
                    <span class="nx-brand-text notranslate" translate="no">CLASSIC<small>Service ERP</small></span>
                </a>
                <div class="nx-sidebar-search">
                    <label><i data-lucide="search" aria-hidden="true"></i><input type="search" placeholder="Find a menu…" data-sidebar-search aria-label="Find a menu"></label>
                </div>
                <nav class="nx-nav" data-sidebar-nav>
                    @foreach($nxMenu as $section)
                        <div class="nx-nav-section" data-nav-section="{{ $section['key'] }}">
                            <button class="nx-nav-section-title" type="button" data-nav-toggle aria-expanded="true">{{ $section['label'] }}<i data-lucide="chevron-down" aria-hidden="true"></i></button>
                            <div class="nx-nav-items">
                                @foreach($section['items'] as $item)
                                    <a class="nx-nav-link {{ $item['active'] ? 'active' : '' }}" href="{{ $item['url'] }}" title="{{ $item['label'] }}" @if($item['active']) aria-current="page" @endif data-nav-item>
                                        <i data-lucide="{{ $item['icon'] }}" aria-hidden="true"></i><span class="nx-nav-label">{{ $item['label'] }}</span>
                                        @if(! empty($item['badge']))<span class="badge bg-{{ $item['tone'] ?? 'danger' }} {{ ($item['tone'] ?? '') === 'warning' ? 'text-dark' : '' }} notranslate">{{ $item['badge'] }}</span>@endif
                                    </a>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                    <div class="nx-nav-empty" data-nav-empty>No menu matches your search.</div>
                </nav>
                <div class="nx-user-card">
                    <a class="nx-avatar notranslate" href="{{ route('profile.edit') }}" title="My profile">{{ strtoupper(substr($nxUser->name, 0, 1)) }}</a>
                    <div class="min-w-0 flex-grow-1 nx-user-meta"><a class="small fw-semibold text-truncate d-block notranslate" href="{{ route('profile.edit') }}">{{ $nxUser->name }}</a><small class="d-block text-truncate">{{ $nxUser->getRoleNames()->first() ?? 'User' }}</small></div>
                    <form method="POST" action="{{ route('logout') }}">@csrf<button class="btn" title="Log out" aria-label="Log out"><i data-lucide="log-out"></i></button></form>
                </div>
            </aside>

            <div class="nx-main">
                <header class="nx-topbar">
                    <div class="nx-topbar-title">
                        <button class="nx-icon-btn d-lg-none" type="button" aria-label="Open menu" data-sidebar-open><i data-lucide="menu"></i></button>
                        <button class="nx-icon-btn d-none d-lg-grid" type="button" aria-label="Collapse menu" title="Collapse / expand menu" data-sidebar-collapse><i data-lucide="panel-left"></i></button>
                        <div class="min-w-0">
                            <ol class="nx-breadcrumb" aria-label="Breadcrumb">
                                <li><a href="{{ route('dashboard') }}">Home</a></li>
                                @foreach($nxCrumbs as $crumb)<li>{{ $crumb }}</li>@endforeach
                            </ol>
                            <h1 class="nx-page-title text-truncate">{{ $pageTitle ?? 'Dashboard' }}</h1>
                        </div>
                    </div>

                    <div class="nx-global-search" data-global-search>
                        <i data-lucide="search" class="nx-search-icon" aria-hidden="true"></i>
                        <input type="search" placeholder="Search complaints, customers, employees…" autocomplete="off" aria-label="Search everything" data-global-search-input>
                        <kbd>Ctrl K</kbd>
                        <div class="nx-search-results d-none" data-global-search-results role="listbox"></div>
                    </div>

                    <div class="d-flex align-items-center gap-2 flex-shrink-0">
                        <div class="dropdown nx-language" data-language-switcher>
                            <button class="nx-icon-btn nx-language-toggle" type="button" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false" title="Change interface language"><i data-lucide="languages"></i><span class="nx-language-current notranslate visually-hidden" data-language-label>{{ $nxPreferredLanguage }}</span></button>
                            <div class="dropdown-menu dropdown-menu-end nx-language-menu">
                                <div class="nx-language-head"><strong>Interface language</strong><small class="text-secondary d-block">Translate menus, labels and buttons</small></div>
                                <div data-language-menu></div>
                            </div>
                        </div>
                        <div class="dropdown">
                            <button class="nx-icon-btn" type="button" data-bs-toggle="dropdown" aria-expanded="false" title="Notifications"><i data-lucide="bell"></i>@if($nxUnread)<span class="nx-dot" aria-hidden="true"></span><span class="visually-hidden">{{ $nxUnread }} unread</span>@endif</button>
                            <div class="dropdown-menu dropdown-menu-end nx-notify-menu">
                                <div class="nx-notify-head"><strong>Notifications</strong>@if($nxUnread)<span class="badge badge-soft-danger rounded-pill">{{ $nxUnread }} new</span>@endif</div>
                                @forelse($nxNotifications as $notification)
                                    <a class="nx-notify-item {{ $notification->read_at ? '' : 'is-unread' }}" href="{{ $notification->data['url'] ?? (isset($notification->data['task_id']) ? route('tasks.show', $notification->data['task_id']) : route('notifications.index')) }}">
                                        <span class="nx-notify-icon"><i data-lucide="{{ $notification->data['icon'] ?? 'bell' }}"></i></span>
                                        <span class="min-w-0"><strong>{{ $notification->data['title'] ?? 'Notification' }}</strong><span class="text-truncate">{{ $notification->data['message'] ?? '' }}</span><small>{{ $notification->created_at->diffForHumans() }}</small></span>
                                    </a>
                                @empty
                                    <div class="nx-empty py-4"><i data-lucide="bell-off"></i><div>No notifications yet.</div></div>
                                @endforelse
                                <a class="nx-notify-foot" href="{{ route('notifications.index') }}">View all notifications</a>
                            </div>
                        </div>
                        <div class="dropdown">
                            <button class="btn btn-primary d-none d-sm-inline-flex" type="button" data-bs-toggle="dropdown" aria-expanded="false"><i data-lucide="plus"></i>New</button>
                            <div class="dropdown-menu dropdown-menu-end nx-quick-create">
                                <h6 class="dropdown-header">Create</h6>
                                @can('complaints.create')<a class="dropdown-item" href="{{ route('complaints.create') }}"><i data-lucide="clipboard-plus"></i>Customer complaint</a>@endcan
                                <a class="dropdown-item" href="{{ route('customers.create') }}"><i data-lucide="building-2"></i>Customer</a>
                                <a class="dropdown-item" href="{{ route('service-jobs.create') }}"><i data-lucide="wrench"></i>Service job</a>
                                <a class="dropdown-item" href="{{ route('workflows.board') }}"><i data-lucide="git-branch"></i>Internal workflow</a>
                                @can('employees.create')<a class="dropdown-item" href="{{ route('employees.create') }}"><i data-lucide="contact-round"></i>Employee</a>@endcan
                                @can('companies.create')<a class="dropdown-item" href="{{ route('companies.create') }}"><i data-lucide="landmark"></i>Company</a>@endcan
                                <div class="dropdown-divider"></div>
                                <a class="dropdown-item" href="{{ route('reports.builder') }}"><i data-lucide="table-properties"></i>Custom report</a>
                            </div>
                        </div>
                        <div class="dropdown">
                            <button class="nx-user-chip" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                <span class="nx-avatar notranslate">{{ strtoupper(substr($nxUser->name, 0, 1)) }}</span>
                                <span class="d-none d-xl-block text-start"><strong class="notranslate">{{ \Illuminate\Support\Str::limit($nxUser->name, 18) }}</strong><small>{{ $nxUser->getRoleNames()->first() ?? 'User' }}</small></span>
                                <i data-lucide="chevron-down" class="d-none d-xl-block" style="width:14px"></i>
                            </button>
                            <div class="dropdown-menu dropdown-menu-end">
                                <h6 class="dropdown-header notranslate">{{ $nxUser->email }}</h6>
                                <a class="dropdown-item" href="{{ route('profile.edit') }}"><i data-lucide="user-round"></i>My profile</a>
                                <a class="dropdown-item" href="{{ route('notifications.index') }}"><i data-lucide="bell"></i>Notifications</a>
                                <a class="dropdown-item" href="{{ route('settings.index') }}"><i data-lucide="settings"></i>Settings</a>
                                <div class="dropdown-divider"></div>
                                <form method="POST" action="{{ route('logout') }}">@csrf<button class="dropdown-item text-danger" type="submit"><i data-lucide="log-out"></i>Log out</button></form>
                            </div>
                        </div>
                    </div>
                </header>

                <main class="nx-content" id="nxContent">
                    @if(session('success'))<div class="alert alert-success nx-flash d-flex align-items-start gap-2" role="status"><i data-lucide="circle-check" style="width:18px;flex:0 0 auto;margin-top:1px"></i><div>{{ session('success') }}</div><button type="button" class="btn-close ms-auto" data-bs-dismiss="alert" aria-label="Dismiss"></button></div>@endif
                    @if(session('error'))<div class="alert alert-danger nx-flash d-flex align-items-start gap-2" role="alert"><i data-lucide="circle-alert" style="width:18px;flex:0 0 auto;margin-top:1px"></i><div>{{ session('error') }}</div><button type="button" class="btn-close ms-auto" data-bs-dismiss="alert" aria-label="Dismiss"></button></div>@endif
                    @if(session('warning'))<div class="alert alert-warning nx-flash d-flex align-items-start gap-2" role="alert"><i data-lucide="triangle-alert" style="width:18px;flex:0 0 auto;margin-top:1px"></i><div>{{ session('warning') }}</div><button type="button" class="btn-close ms-auto" data-bs-dismiss="alert" aria-label="Dismiss"></button></div>@endif
                    {{ $slot }}
                </main>
                <footer class="nx-footer d-flex flex-wrap justify-content-between gap-2"><span><span class="notranslate">CLASSIC</span> Service ERP · {{ now()->year }}</span><span>Signed in as <span class="notranslate">{{ $nxUser->name }}</span></span></footer>
            </div>
        </div>
        @stack('scripts')
    </body>
</html>
