<!-- Edit modal -->
<div class="modal fade" id="editModal-{{ $row->id }}" tabindex="-1" role="dialog" aria-labelledby="editModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm" role="document">
        <div class="modal-content">
            <form class="needs-validation" novalidate action="{{ route($route.'.update', $row->id) }}" method="post" enctype="multipart/form-data">
            @csrf
            @method('PUT')
            <div class="modal-header">
                <h5 class="modal-title" id="editModalLabel">{{ __('modal_edit') }} {{ $title }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <!-- Form Start -->
                <div class="form-group">
                    <label for="title" class="form-label">{{ __('field_title') }} <span>*</span></label>
                    <input type="text" class="form-control" name="title" id="title" value="{{ $row->title }}" required>

                    <div class="invalid-feedback">
                      {{ __('required_field') }} {{ __('field_title') }}
                    </div>
                </div>

                <div class="form-group">
                    <label for="description" class="form-label">{{ __('field_description') }}</label>
                    <textarea class="form-control" name="description" id="description">{{ $row->description }}</textarea>
                </div>

                <div class="form-group">
                    <label for="status" class="form-label">{{ __('field_status') }}</label>
                    <select class="form-control" name="status" id="status">
                        <option value="1" @if( $row->status == 1 ) selected @endif>{{ __('status_active') }}</option>
                        <option value="0" @if( $row->status == 0 ) selected @endif>{{ __('status_inactive') }}</option>
                    </select>
                </div>
                <!-- Form End -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><i class="fas fa-times"></i> {{ __('btn_close') }}</button>
                <button type="submit" class="btn btn-success"><i class="fas fa-check"></i> {{ __('btn_update') }}</button>
            </div>
            </form>
        </div>
    </div>
</div>
