{{-- The four actions on one applicant account. Each modal is only rendered for
     someone allowed to use it, so a form never exists on the page for a user
     whose request the server would refuse anyway. --}}

@can('applicant-edit')
<div id="editApplicant-{{ $applicant->id }}" class="modal fade text-start" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="post" action="{{ route('admin.applicant.update', $applicant->id) }}">
                @csrf
                @method('PUT')
                <div class="modal-header">
                    <h5 class="modal-title">{{ __('Edit account details') }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('Close') }}"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-2">
                        <div class="col-md-6 form-group">
                            <label class="form-label">{{ __('First name') }}</label>
                            <input type="text" name="first_name" class="form-control" value="{{ $applicant->first_name }}" maxlength="191">
                        </div>
                        <div class="col-md-6 form-group">
                            <label class="form-label">{{ __('Last name') }}</label>
                            <input type="text" name="last_name" class="form-control" value="{{ $applicant->last_name }}" maxlength="191">
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">{{ __('Email') }} <span class="text-danger">*</span></label>
                        <input type="email" name="email" class="form-control" value="{{ $applicant->email }}" required maxlength="191">
                        <small class="form-text text-muted">{{ __('This is what they sign in with. Their applications that used the old address are updated too.') }}</small>
                    </div>
                    <div class="form-group">
                        <label class="form-label">{{ __('Phone') }}</label>
                        <input type="text" name="phone" class="form-control" value="{{ $applicant->phone }}" maxlength="191">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ __('btn_close') }}</button>
                    <button type="submit" class="btn btn-success"><i class="fas fa-check"></i> {{ __('btn_update') }}</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div id="statusApplicant-{{ $applicant->id }}" class="modal fade text-start" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="post" action="{{ route('admin.applicant.toggle', $applicant->id) }}">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">{{ $applicant->disabled_at ? __('Enable this account?') : __('Disable this account?') }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('Close') }}"></button>
                </div>
                <div class="modal-body">
                    @if($applicant->disabled_at)
                        <p>{{ __(':email will be able to sign in again.', ['email' => $applicant->email]) }}</p>
                    @else
                        <p>{{ __(':email will be signed out and unable to sign in. Their applications are not changed or deleted.', ['email' => $applicant->email]) }}</p>
                        <div class="form-group">
                            <label class="form-label">{{ __('Reason') }} <small class="text-muted">({{ __('optional, shown on this screen') }})</small></label>
                            <input type="text" name="disabled_reason" class="form-control" maxlength="500">
                        </div>
                    @endif
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ __('btn_close') }}</button>
                    <button type="submit" class="btn {{ $applicant->disabled_at ? 'btn-success' : 'btn-danger' }}">
                        {{ $applicant->disabled_at ? __('Enable') : __('Disable') }}
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

@unless($applicant->disabled_at)
<div id="resetApplicant-{{ $applicant->id }}" class="modal fade text-start" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="post" action="{{ route('admin.applicant.send-reset-link', $applicant->id) }}">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">{{ __('Email a reset link?') }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('Close') }}"></button>
                </div>
                <div class="modal-body">
                    <p>{{ __('A link to choose a new password will be emailed to :email. It works for 60 minutes, and their current password keeps working until they use it.', ['email' => $applicant->email]) }}</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ __('btn_close') }}</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-envelope"></i> {{ __('Send link') }}</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endunless
@endcan

@can('applicant-password-change')
<div id="passwordApplicant-{{ $applicant->id }}" class="modal fade text-start" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="post" action="{{ route('admin.applicant.password', $applicant->id) }}">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">{{ __('change_password') }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('Close') }}"></button>
                </div>
                <div class="modal-body">
                    <p class="text-muted" style="font-size:.85rem;">{{ __('For :email. Any device they are signed in on with "remember me" will be signed out.', ['email' => $applicant->email]) }}</p>
                    <div class="form-group">
                        <label class="form-label">{{ __('field_password') }} <span class="text-danger">*</span></label>
                        <input type="password" name="password" class="form-control" required minlength="8" autocomplete="new-password">
                    </div>
                    <div class="form-group">
                        <label class="form-label">{{ __('field_confirm_password') }} <span class="text-danger">*</span></label>
                        <input type="password" name="password_confirmation" class="form-control" required minlength="8" autocomplete="new-password">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ __('btn_close') }}</button>
                    <button type="submit" class="btn btn-success"><i class="fas fa-check"></i> {{ __('btn_change') }}</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endcan
