<form id="send-verification" method="post" action="{{ route('verification.send') }}">@csrf</form>
<form method="post" action="{{ route('profile.update') }}" class="row g-3">
    @csrf
    @method('patch')
    <div class="col-12">
        <label class="form-label" for="name">Full name *</label>
        <input id="name" name="name" type="text" class="form-control @error('name') is-invalid @enderror" value="{{ old('name', $user->name) }}" required autocomplete="name" data-v="person_name" maxlength="255">
        @error('name')<div class="nx-error">{{ $message }}</div>@enderror
    </div>
    <div class="col-12">
        <label class="form-label" for="email">Email *</label>
        <input id="email" name="email" type="email" class="form-control @error('email') is-invalid @enderror" value="{{ old('email', $user->email) }}" required autocomplete="username">
        @error('email')<div class="nx-error">{{ $message }}</div>@enderror
        @if ($user instanceof \Illuminate\Contracts\Auth\MustVerifyEmail && ! $user->hasVerifiedEmail())
            <div class="form-text">Your email address is unverified. <button form="send-verification" class="btn btn-link btn-sm p-0 align-baseline">Re-send the verification email</button></div>
        @endif
    </div>
    <div class="col-12 d-flex align-items-center gap-3">
        <button class="btn btn-primary"><i data-lucide="save" style="width:16px"></i>Save details</button>
        @if (session('status') === 'profile-updated')<span class="small text-success fw-semibold">Saved.</span>@endif
    </div>
</form>
