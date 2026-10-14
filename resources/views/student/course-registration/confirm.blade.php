    <div class="modal fade" id="confirmModal" tabindex="-1" role="dialog" aria-labelledby="CourseRegistrationConfirmModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-sm">
            <div class="modal-content">
                <div class="modal-body text-center">
                    <h5 class="modal-title" id="CourseRegistrationConfirmModalLabel">{{ __('Register these courses?') }}</h5>
                    <p class="mt-2">{{ __('The courses you picked will be added to your semester. You can drop a course later, as long as no marks have been recorded for it yet.') }}</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><i class="fas fa-times"></i> {{ __('btn_close') }}</button>
                    <button type="submit" class="btn btn-success"><i class="fas fa-check"></i> {{ __('Yes, register them') }}</button>
                </div>
            </div>
        </div>
    </div>
