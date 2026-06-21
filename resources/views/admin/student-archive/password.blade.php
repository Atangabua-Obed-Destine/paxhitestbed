<!-- Password Change Modal -->
<div class="modal fade" id="passwordModal-{{ $row->id }}" tabindex="-1" role="dialog" aria-labelledby="passwordModalLabel-{{ $row->id }}" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <form class="needs-validation" novalidate action="{{ route($route.'.password-change') }}" method="post">
            @csrf
            <input type="hidden" name="id" value="{{ $row->id }}">
            
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="passwordModalLabel-{{ $row->id }}">
                        <i class="fas fa-key"></i> {{ __('change_password') }}
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-info">
                        <strong>{{ __('student') }}:</strong> {{ $row->student_id }} - {{ $row->first_name }} {{ $row->last_name }}
                    </div>

                    <div class="form-group">
                        <label for="password-{{ $row->id }}">{{ __('field_password') }} <span>*</span></label>
                        <input type="password" class="form-control" name="password" id="password-{{ $row->id }}" required>
                        <small class="form-text text-muted">{{ __('minimum_6_characters') }}</small>
                        <div class="invalid-feedback">
                            {{ __('required_field') }}
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="password_confirmation-{{ $row->id }}">{{ __('field_password_confirmation') }} <span>*</span></label>
                        <input type="password" class="form-control" name="password_confirmation" id="password_confirmation-{{ $row->id }}" required>
                        <div class="invalid-feedback">
                            {{ __('required_field') }}
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times"></i> {{ __('btn_cancel') }}
                    </button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-check"></i> {{ __('btn_change_password') }}
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>
