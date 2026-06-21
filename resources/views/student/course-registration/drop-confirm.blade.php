    <div class="modal fade" id="dropConfirmModal" tabindex="-1" role="dialog" aria-labelledby="CourseRegistrationDropConfirmModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-sm">
            <div class="modal-content">
                <div class="modal-body text-center">
                    <h5 class="modal-title" id="CourseRegistrationDropConfirmModalLabel">{{ __('modal_are_you_sure') }}</h5>
                    <p class="text-danger mt-2">
                        {{ __('Dropping this course will remove it from your current registration.') }}<br>
                        <strong id="drop-subject-label"></strong>
                    </p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><i class="fas fa-times"></i> {{ __('btn_close') }}</button>
                    <button type="button" class="btn btn-danger" id="confirm-drop-button"><i class="fas fa-check"></i> {{ __('btn_confirm') }}</button>
                </div>
            </div>
        </div>
    </div>
