    <!-- Edit modal content -->
    <div id="editModal-{{ $row->id }}" class="modal fade" tabindex="-1" role="dialog" aria-labelledby="myModalLabel" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
              <form class="needs-validation" novalidate action="{{ route($route.'.update', $row->id) }}" method="post" enctype="multipart/form-data">
                @csrf
                @method('PUT')

                <div class="modal-header">
                    <h5 class="modal-title" id="myModalLabel">{{ __('modal_edit') }} {{ $title }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
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

                    @php
                        $feeType = 'regular'; // default
                        if ($row->is_resit == 1) {
                            $feeType = 'resit';
                        } elseif ($row->is_admission == 1) {
                            $feeType = 'admission';
                        } elseif ($row->is_first_installment == 1) {
                            $feeType = 'first_installment';
                        } elseif ($row->is_second_installment == 1) {
                            $feeType = 'second_installment';
                        }
                    @endphp

                    <div class="form-group">
                        <label class="form-label">{{ __('Fee Type') }} <span>*</span></label>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="fee_type" id="fee_type_regular-{{ $row->id }}" value="regular" {{ $feeType == 'regular' ? 'checked' : '' }} required>
                            <label class="form-check-label" for="fee_type_regular-{{ $row->id }}">
                                {{ __('Regular Fee') }}
                            </label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="fee_type" id="fee_type_resit-{{ $row->id }}" value="resit" {{ $feeType == 'resit' ? 'checked' : '' }} required>
                            <label class="form-check-label" for="fee_type_resit-{{ $row->id }}">
                                {{ __('Resit Fee') }}
                            </label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="fee_type" id="fee_type_admission-{{ $row->id }}" value="admission" {{ $feeType == 'admission' ? 'checked' : '' }} required>
                            <label class="form-check-label" for="fee_type_admission-{{ $row->id }}">
                                {{ __('Admission Fee') }}
                            </label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="fee_type" id="fee_type_first-{{ $row->id }}" value="first_installment" {{ $feeType == 'first_installment' ? 'checked' : '' }} required>
                            <label class="form-check-label" for="fee_type_first-{{ $row->id }}">
                                {{ __('First Installment') }}
                            </label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="fee_type" id="fee_type_second-{{ $row->id }}" value="second_installment" {{ $feeType == 'second_installment' ? 'checked' : '' }} required>
                            <label class="form-check-label" for="fee_type_second-{{ $row->id }}">
                                {{ __('Second Installment') }}
                            </label>
                        </div>
                        <small class="form-text text-muted">{{ __('Select the type of fee category') }}</small>
                    </div>

                    <div class="form-group">
                        <label for="status" class="form-label">{{ __('select_status') }}</label>
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