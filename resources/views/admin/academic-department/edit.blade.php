<!-- Edit modal content -->
<div id="editModal-{{ $row->id }}" class="modal fade" tabindex="-1" role="dialog" aria-labelledby="editModalLabel-{{ $row->id }}" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editModalLabel-{{ $row->id }}">{{ __('btn_edit') }} {{ $title }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form class="needs-validation" novalidate action="{{ route($route.'.update', $row->id) }}" method="post">
            @csrf
            @method('PUT')
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="faculty-{{ $row->id }}" class="form-label">{{ __('field_faculty') }} <span>*</span></label>
                                <select class="form-control" name="faculty" id="faculty-{{ $row->id }}" required>
                                    <option value="">{{ __('select') }}</option>
                                    @foreach( $faculties as $faculty )
                                    <option value="{{ $faculty->id }}" @if($row->faculty_id == $faculty->id) selected @endif>{{ $faculty->title }}</option>
                                    @endforeach
                                </select>
                                <div class="invalid-feedback">
                                  {{ __('required_field') }} {{ __('field_faculty') }}
                                </div>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="title-{{ $row->id }}" class="form-label">{{ __('field_title') }} <span>*</span></label>
                                <input type="text" class="form-control" name="title" id="title-{{ $row->id }}" value="{{ $row->title }}" required>
                                <div class="invalid-feedback">
                                  {{ __('required_field') }} {{ __('field_title') }}
                                </div>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="shortcode-{{ $row->id }}" class="form-label">{{ __('field_shortcode') }}</label>
                                <input type="text" class="form-control" name="shortcode" id="shortcode-{{ $row->id }}" value="{{ $row->shortcode }}" placeholder="e.g., CS, ENG">
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="head_of_department-{{ $row->id }}" class="form-label">Head of Department</label>
                                <select class="form-control select2" name="head_of_department" id="head_of_department-{{ $row->id }}">
                                    <option value="">{{ __('select') }}</option>
                                    @foreach( $staff as $person )
                                    <option value="{{ $person->id }}" @if($row->head_of_department_id == $person->id) selected @endif>{{ $person->first_name }} {{ $person->last_name }} ({{ $person->staff_id }})</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="email-{{ $row->id }}" class="form-label">{{ __('field_email') }}</label>
                                <input type="email" class="form-control" name="email" id="email-{{ $row->id }}" value="{{ $row->email }}" placeholder="department@example.com">
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="phone-{{ $row->id }}" class="form-label">{{ __('field_phone') }}</label>
                                <input type="text" class="form-control" name="phone" id="phone-{{ $row->id }}" value="{{ $row->phone }}" placeholder="+237 XXX XXX XXX">
                            </div>
                        </div>

                        <div class="col-md-12">
                            <div class="form-group">
                                <label for="description-{{ $row->id }}" class="form-label">{{ __('field_description') }}</label>
                                <textarea class="form-control" name="description" id="description-{{ $row->id }}" rows="3">{{ $row->description }}</textarea>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="sort_order-{{ $row->id }}" class="form-label">{{ __('field_sort_order') }}</label>
                                <input type="number" class="form-control" name="sort_order" id="sort_order-{{ $row->id }}" min="0" value="{{ $row->sort_order }}">
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="status-{{ $row->id }}" class="form-label">{{ __('select_status') }}</label>
                                <select class="form-control" name="status" id="status-{{ $row->id }}">
                                    <option value="1" @if( $row->status == 1 ) selected @endif>{{ __('status_active') }}</option>
                                    <option value="0" @if( $row->status == 0 ) selected @endif>{{ __('status_inactive') }}</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ __('btn_close') }}</button>
                    <button type="submit" class="btn btn-primary">{{ __('btn_update') }}</button>
                </div>
            </form>
        </div>
    </div>
</div>
