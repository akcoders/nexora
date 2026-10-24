<x-guest-layout>
    <main class="nx-login">
        <section class="nx-login-art" aria-label="Nexora operations overview">
            <div class="nx-login-glow nx-login-glow-one"></div>
            <div class="nx-login-glow nx-login-glow-two"></div>

            <div class="d-flex align-items-center gap-3 position-relative z-1">
                <span class="nx-logo-large p-0 overflow-hidden bg-white"><img src="{{ asset('images/brand/classic-app-icon.png') }}" class="w-100 h-100 object-fit-cover" alt="Classic Cooling"></span>
                <div><div class="h4 mb-0 fw-bold">NEXORA</div><small class="text-white-50">by Classic Cooling</small></div>
            </div>

            <div class="position-relative z-1 nx-login-story">
                <span class="nx-login-eyebrow"><i data-lucide="sparkles"></i>Service operations, connected</span>
                <h1>From the first call to the final service report.</h1>
                <p>Keep customers, teams and field work moving together from one secure workspace.</p>
                <div class="nx-login-preview" aria-hidden="true">
                    <div class="nx-login-preview-top"><span><i data-lucide="activity"></i>Live operations</span><span class="nx-live-dot">Online</span></div>
                    <div class="nx-login-preview-grid">
                        <div><span class="nx-preview-icon blue"><i data-lucide="briefcase-business"></i></span><strong>Service jobs</strong><small>Assign and track every visit</small></div>
                        <div><span class="nx-preview-icon cyan"><i data-lucide="map-pinned"></i></span><strong>Field team</strong><small>Attendance and location proof</small></div>
                        <div><span class="nx-preview-icon green"><i data-lucide="building-2"></i></span><strong>Customer assets</strong><small>Rooms, AC units and history</small></div>
                    </div>
                </div>
            </div>

            <div class="d-flex flex-wrap gap-3 position-relative z-1 nx-login-trust"><span><i data-lucide="shield-check"></i>Role-based access</span><span><i data-lucide="lock-keyhole"></i>Secure workspace</span><span><i data-lucide="smartphone"></i>Field ready</span></div>
        </section>

        <section class="nx-login-form">
            <div class="nx-login-panel">
                <div class="d-lg-none d-flex align-items-center gap-3 mb-5"><span class="nx-brand-mark p-0 overflow-hidden"><img src="{{ asset('images/brand/classic-app-icon.png') }}" class="w-100 h-100 object-fit-cover" alt="Classic Cooling"></span><div><strong class="h5 mb-0 d-block">Nexora</strong><small class="text-secondary">Classic Cooling workspace</small></div></div>

                <div class="nx-login-welcome"><span class="nx-login-status"><span></span>Secure staff portal</span><h2>Welcome back</h2><p>Sign in with your work account to continue.</p></div>

                @if(session('status'))<div class="alert alert-success d-flex align-items-center gap-2"><i data-lucide="circle-check" style="width:18px"></i>{{ session('status') }}</div>@endif
                @if($errors->any())<div class="alert alert-danger d-flex align-items-start gap-2"><i data-lucide="circle-alert" style="width:18px;flex-shrink:0"></i><span>{{ $errors->first() }}</span></div>@endif

                <form method="POST" action="{{ route('login') }}" novalidate>
                    @csrf
                    <div class="mb-3">
                        <label for="email" class="form-label">Work email</label>
                        <div class="input-group nx-login-input"><span class="input-group-text"><i data-lucide="mail"></i></span><input id="email" class="form-control" type="email" name="email" value="{{ old('email') }}" placeholder="name@company.com" required autofocus autocomplete="username"></div>
                    </div>
                    <div class="mb-3">
                        <div class="d-flex justify-content-between gap-3"><label for="password" class="form-label">Password</label>@if(Route::has('password.request'))<a class="small fw-semibold" href="{{ route('password.request') }}">Forgot password?</a>@endif</div>
                        <div class="input-group nx-login-input"><span class="input-group-text"><i data-lucide="lock-keyhole"></i></span><input id="password" class="form-control" type="password" name="password" placeholder="Enter your password" required autocomplete="current-password"><button class="input-group-text nx-password-toggle" id="passwordToggle" type="button" aria-label="Show password" aria-pressed="false"><i data-lucide="eye"></i></button></div>
                    </div>
                    <div class="d-flex align-items-center justify-content-between gap-3 mb-4"><label class="form-check mb-0"><input class="form-check-input" id="remember_me" type="checkbox" name="remember"><span class="form-check-label small" for="remember_me">Keep me signed in</span></label><span class="small text-secondary d-none d-sm-inline">Authorised staff only</span></div>
                    <button class="btn btn-primary nx-login-submit w-100" type="submit"><span>Sign in to workspace</span><i data-lucide="arrow-right"></i></button>
                </form>

                <div class="nx-login-help"><i data-lucide="life-buoy"></i><span>Having trouble signing in? Contact your administrator.</span></div>
            </div>
        </section>
    </main>

    <script>
        document.getElementById('passwordToggle')?.addEventListener('click', event => {
            const password = document.getElementById('password');
            const button = event.currentTarget;
            const showing = password.type === 'text';
            password.type = showing ? 'password' : 'text';
            button.setAttribute('aria-label', showing ? 'Show password' : 'Hide password');
            button.setAttribute('aria-pressed', String(!showing));
            button.innerHTML = `<i data-lucide="${showing ? 'eye' : 'eye-off'}"></i>`;
            window.refreshIcons?.();
        });
    </script>
</x-guest-layout>
