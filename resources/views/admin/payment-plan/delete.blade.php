<!-- Delete Payment Plan Modal -->
<div id="deleteModal-{{ $row->id }}" class="modal fade" tabindex="-1" role="dialog" aria-labelledby="deleteModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <form action="{{ route($route.'.destroy', $row->id) }}" method="post">
                @csrf
                @method('DELETE')

                <div class="modal-header">
                    <h5 class="modal-title text-danger">{{ __('delete_payment_plan') }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <div class="modal-body">
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-triangle"></i> 
                        {{ __('are_you_sure_delete') }}
                    </div>

                    <p><strong>{{ __('field_student') }}:</strong> {{ $row->student->first_name }} {{ $row->student->last_name }}</p>
                    <p><strong>{{ __('field_total_amount') }}:</strong> {{ number_format($row->total_amount, 2) }} {!! $setting->currency_symbol !!}</p>
                    <p><strong>{{ __('field_installments') }}:</strong> {{ $row->installments_count }}</p>

                    <p class="text-danger"><strong>{{ __('warning') }}:</strong> {{ __('this_action_cannot_be_undone') }}</p>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times"></i> {{ __('btn_close') }}
                    </button>
                    <button type="submit" class="btn btn-danger">
                        <i class="fas fa-trash"></i> {{ __('btn_delete') }}
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
