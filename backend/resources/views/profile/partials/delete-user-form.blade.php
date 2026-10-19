<p class="small text-secondary">Once your account is deleted, you will no longer be able to sign in to the web app or the technician app. This cannot be undone.</p>
<button type="button" class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#confirmUserDeletion"><i data-lucide="trash-2" style="width:16px"></i>Delete my account</button>

<div class="modal fade" id="confirmUserDeletion" tabindex="-1" data-nx-tone="rose" aria-labelledby="confirmUserDeletionTitle">
    <div class="modal-dialog modal-dialog-centered">
        <form method="post" action="{{ route('profile.destroy') }}" class="modal-content" data-v-nolock>
            @csrf
            @method('delete')
            <div class="modal-header"><div><h5 class="modal-title" id="confirmUserDeletionTitle">Delete your account?</h5><small class="text-secondary">Enter your password to confirm.</small></div></div>
            <div class="modal-body">
                <label class="form-label" for="delete_password">Password *</label>
                <input id="delete_password" name="password" type="password" class="form-control @if($errors->userDeletion->has('password')) is-invalid @endif" placeholder="Your current password" required autocomplete="current-password">
                @if($errors->userDeletion->has('password'))<div class="nx-error">{{ $errors->userDeletion->first('password') }}</div>@endif
            </div>
            <div class="modal-footer"><button class="btn btn-danger">Yes, delete account</button></div>
        </form>
    </div>
</div>
@if($errors->userDeletion->isNotEmpty())@push('scripts')<script>document.addEventListener('DOMContentLoaded',()=>bootstrap.Modal.getOrCreateInstance(document.getElementById('confirmUserDeletion')).show());</script>@endpush @endif
