<x-guest-layout>
    <div class="nx-login">
        <section class="nx-login-art">
            <div class="d-flex align-items-center gap-3 position-relative" style="z-index:1"><span class="nx-logo-large p-0 overflow-hidden bg-white"><img src="{{ asset('images/brand/classic-app-icon.png') }}" class="w-100 h-100 object-fit-cover" alt="Classic"></span><div><div class="h4 mb-0 fw-bold">CLASSIC</div><small class="text-white-50">The Art of Cooling</small></div></div>
            <div class="position-relative" style="z-index:1;max-width:620px"><div class="badge rounded-pill bg-white bg-opacity-10 border border-white border-opacity-25 px-3 py-2 mb-4">Smart operations. Reliable service.</div><h1 class="display-5 fw-bold mb-3">Everything your service team needs, in one place.</h1><p class="lead text-white-50 mb-0">Manage customers, attendance and field workflows with live operational visibility.</p></div>
            <div class="small text-white-50 position-relative" style="z-index:1">Secure · Role based · Field ready</div>
        </section>
        <section class="nx-login-form">
            <div class="nx-login-panel">
                <div class="d-lg-none mb-5"><div class="d-flex align-items-center gap-2"><span class="nx-brand-mark p-0 overflow-hidden"><img src="{{ asset('images/brand/classic-app-icon.png') }}" class="w-100 h-100 object-fit-cover"></span><strong class="h5 mb-0">CLASSIC</strong></div></div>
                <div class="mb-4"><div class="text-primary fw-semibold small mb-2">WELCOME BACK</div><h2 class="h2 fw-bold mb-2">Sign in to Nexora</h2><p class="text-secondary">Use your work email to continue to the admin workspace.</p></div>
                @if(session('status'))<div class="alert alert-success">{{ session('status') }}</div>@endif
                @if($errors->any())<div class="alert alert-danger py-2">{{ $errors->first() }}</div>@endif
                <form method="POST" action="{{ route('login') }}" novalidate>@csrf
                    <div class="mb-3"><label for="email" class="form-label">Email address</label><div class="input-group"><span class="input-group-text bg-white border-end-0"><i data-lucide="mail" style="width:17px"></i></span><input id="email" class="form-control border-start-0 ps-0" type="email" name="email" value="{{ old('email') }}" placeholder="name@company.com" required autofocus autocomplete="username"></div></div>
                    <div class="mb-3"><div class="d-flex justify-content-between"><label for="password" class="form-label">Password</label>@if(Route::has('password.request'))<a class="small" href="{{ route('password.request') }}">Forgot password?</a>@endif</div><div class="input-group"><span class="input-group-text bg-white border-end-0"><i data-lucide="lock-keyhole" style="width:17px"></i></span><input id="password" class="form-control border-start-0 border-end-0 ps-0" type="password" name="password" placeholder="Enter your password" required autocomplete="current-password"><button class="input-group-text bg-white" type="button" onclick="const p=document.getElementById('password');p.type=p.type==='password'?'text':'password'"><i data-lucide="eye" style="width:17px"></i></button></div></div>
                    <div class="form-check mb-4"><input class="form-check-input" id="remember_me" type="checkbox" name="remember"><label class="form-check-label small" for="remember_me">Keep me signed in</label></div>
                    <button class="btn btn-primary w-100 py-3" type="submit">Sign in <i data-lucide="arrow-right" class="ms-2" style="width:18px"></i></button>
                </form>
                <div class="text-center text-secondary small mt-5">Protected workspace for authorised Classic Cooling staff</div>
            </div>
        </section>
    </div>
</x-guest-layout>
