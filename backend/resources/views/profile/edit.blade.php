<x-app-layout>
    <x-slot name="title">My profile</x-slot>
    <x-slot name="pageTitle">My account</x-slot>
    <x-slot name="breadcrumb">Workspace / My account</x-slot>
    @php $employeeProfile = $user->employeeProfile; @endphp
    @if(session('status') === 'profile-updated')<div class="alert alert-success nx-flash border-0 shadow-sm d-flex align-items-center gap-2"><i data-lucide="circle-check" style="width:18px"></i>Profile details saved.</div>@endif
    @if(session('status') === 'password-updated')<div class="alert alert-success nx-flash border-0 shadow-sm d-flex align-items-center gap-2"><i data-lucide="circle-check" style="width:18px"></i>Password changed. Use the new password next time you sign in.</div>@endif
    @if(session('status') === 'verification-link-sent')<div class="alert alert-success nx-flash border-0 shadow-sm d-flex align-items-center gap-2"><i data-lucide="mail-check" style="width:18px"></i>A new verification link has been sent to your email address.</div>@endif

    <section class="nx-profile-hero mb-4">
        <span class="nx-avatar notranslate">{{ strtoupper(substr($user->name, 0, 1)) }}</span>
        <div class="flex-grow-1 min-w-0">
            <h2 class="text-truncate">{{ $user->name }}</h2>
            <p>{{ $user->designation ?: 'Team member' }}@if($user->department) · {{ $user->department }}@endif · {{ $user->getRoleNames()->first() ?? 'User' }}@if($user->employee_code) · <span class="notranslate">{{ $user->employee_code }}</span>@endif</p>
        </div>
        @if($employeeProfile)<a class="btn btn-light" href="{{ route('employees.show', $user) }}"><i data-lucide="contact-round" style="width:16px"></i>Employee master</a>@endif
    </section>

    <div class="row g-4">
        <div class="col-lg-6">
            <section class="nx-card nx-profile-card p-4 tone-sky">
                <div class="nx-field-group-head"><span class="nx-field-group-icon"><i data-lucide="user-round-pen"></i></span><div><strong>Profile information</strong><small>Your name and the email you sign in with.</small></div></div>
                @include('profile.partials.update-profile-information-form')
            </section>
        </div>
        <div class="col-lg-6">
            <section class="nx-card nx-profile-card p-4 tone-violet">
                <div class="nx-field-group-head"><span class="nx-field-group-icon"><i data-lucide="languages"></i></span><div><strong>Interface language</strong><small>The web app and the technician app open in this language. Switch anytime from the language menu at the top.</small></div></div>
                <form method="POST" action="{{ route('profile.language') }}" class="d-flex flex-wrap gap-2 align-items-end">
                    @csrf @method('PATCH')
                    <div class="flex-grow-1"><label class="form-label" for="profileLanguage">Preferred language</label><select class="form-select" id="profileLanguage" name="language">@foreach($languages as $name=>$code)<option value="{{ $name }}" @selected($preferredLanguage===$name)>{{ $name }} <span class="notranslate">({{ $code }})</span></option>@endforeach</select></div>
                    <button class="btn btn-primary">Save language</button>
                </form>
                @if($employeeProfile && count($employeeProfile->languages ?? []))<div class="d-flex flex-wrap gap-2 mt-3">@foreach($employeeProfile->languages as $language)<span class="nx-lang-pill {{ $language === $preferredLanguage ? 'is-preferred' : '' }}"><i data-lucide="languages"></i>{{ $language }}</span>@endforeach</div><div class="form-text mt-2">Languages come from your employee master. Ask HR to add more.</div>@else<div class="form-text mt-2">The app opens in the language you choose here.</div>@endif
            </section>
        </div>
        <div class="col-lg-6">
            <section class="nx-card nx-profile-card p-4 tone-emerald">
                <div class="nx-field-group-head"><span class="nx-field-group-icon"><i data-lucide="lock-keyhole"></i></span><div><strong>Password</strong><small>Use a long, unique password to keep your account secure.</small></div></div>
                @include('profile.partials.update-password-form')
            </section>
        </div>
        <div class="col-lg-6">
            <section class="nx-card nx-profile-card nx-danger-zone p-4 tone-rose">
                <div class="nx-field-group-head"><span class="nx-field-group-icon"><i data-lucide="user-round-x"></i></span><div><strong>Delete account</strong><small>Permanently removes your login. Employee records are kept for HR and payroll.</small></div></div>
                @include('profile.partials.delete-user-form')
            </section>
        </div>
    </div>
</x-app-layout>
