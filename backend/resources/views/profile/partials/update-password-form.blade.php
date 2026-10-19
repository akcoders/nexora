<form method="post" action="{{ route('password.update') }}" class="row g-3">
    @csrf
    @method('put')
    <div class="col-12">
        <label class="form-label" for="update_password_current_password">Current password *</label>
        <input id="update_password_current_password" name="current_password" type="password" class="form-control @if($errors->updatePassword->has('current_password')) is-invalid @endif" autocomplete="current-password" required>
        @if($errors->updatePassword->has('current_password'))<div class="nx-error">{{ $errors->updatePassword->first('current_password') }}</div>@endif
    </div>
    <div class="col-md-6">
        <label class="form-label" for="update_password_password">New password *</label>
        <input id="update_password_password" name="password" type="password" class="form-control @if($errors->updatePassword->has('password')) is-invalid @endif" autocomplete="new-password" required data-v="new_password|different:current_password" minlength="8">
        @if($errors->updatePassword->has('password'))<div class="nx-error">{{ $errors->updatePassword->first('password') }}</div>@endif
    </div>
    <div class="col-md-6">
        <label class="form-label" for="update_password_password_confirmation">Confirm new password *</label>
        <input id="update_password_password_confirmation" name="password_confirmation" type="password" class="form-control @if($errors->updatePassword->has('password_confirmation')) is-invalid @endif" autocomplete="new-password" required data-v="same:password">
        @if($errors->updatePassword->has('password_confirmation'))<div class="nx-error">{{ $errors->updatePassword->first('password_confirmation') }}</div>@endif
    </div>
    <div class="col-12 d-flex align-items-center gap-3">
        <button class="btn btn-primary"><i data-lucide="key-round" style="width:16px"></i>Change password</button>
        @if (session('status') === 'password-updated')<span class="small text-success fw-semibold">Saved.</span>@endif
    </div>
</form>
