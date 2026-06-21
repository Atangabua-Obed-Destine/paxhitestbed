<!-- Edit modal content -->
<div id="editModal-{{ $row->id }}" class="modal fade" tabindex="-1" role="dialog" aria-labelledby="myModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <form class="needs-validation" novalidate action="{{ route($route.'.update', $row->id) }}" method="post">
            @csrf
            @method('PUT')
                <div class="modal-header">
                    <h5 class="modal-title" id="myModalLabel">{{ __('modal_edit') }} {{ $title }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                </div>
                <div class="modal-body">
                    <!-- Basic Information -->
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="title-{{ $row->id }}" class="form-label">{{ __('field_title') }} <span>*</span></label>
                                <input type="text" class="form-control" name="title" id="title-{{ $row->id }}" value="{{ $row->title }}" required>
                                <small class="form-text text-muted">e.g., Bachelor of Science, Master of Arts</small>
                                <div class="invalid-feedback">
                                    {{ __('required_field') }} {{ __('field_title') }}
                                </div>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="shortcode-{{ $row->id }}" class="form-label">{{ __('field_shortcode') }} <span>*</span></label>
                                <input type="text" class="form-control text-uppercase" name="shortcode" id="shortcode-{{ $row->id }}" value="{{ $row->shortcode }}" required>
                                <small class="form-text text-muted">e.g., BSc, MA, PhD</small>
                                <div class="invalid-feedback">
                                    {{ __('required_field') }} {{ __('field_shortcode') }}
                                </div>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="code_append_to_student_matricule-{{ $row->id }}" class="form-label">{{ __('field_matricule_code') }}</label>
                                <input type="text" class="form-control text-uppercase" name="code_append_to_student_matricule" id="code_append_to_student_matricule-{{ $row->id }}" value="{{ $row->code_append_to_student_matricule }}" maxlength="10">
                                <small class="form-text text-muted">Code to append to student matricule (e.g., HND, PG)</small>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="level-{{ $row->id }}" class="form-label">{{ __('field_level') }} <span>*</span></label>
                                <select class="form-control" name="level" id="level-{{ $row->id }}" required>
                                    <option value="">{{ __('select') }}</option>
                                    <option value="Certificate" @if($row->level == 'Certificate') selected @endif>Certificate</option>
                                    <option value="Diploma" @if($row->level == 'Diploma') selected @endif>Diploma</option>
                                    <option value="Undergraduate" @if($row->level == 'Undergraduate') selected @endif>Undergraduate</option>
                                    <option value="Postgraduate" @if($row->level == 'Postgraduate') selected @endif>Postgraduate</option>
                                </select>
                                <div class="invalid-feedback">
                                    {{ __('required_field') }} {{ __('field_level') }}
                                </div>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="status-{{ $row->id }}" class="form-label">{{ __('select_status') }}</label>
                                <select class="form-control" name="status" id="status-{{ $row->id }}">
                                    <option value="1" @if($row->status == 1) selected @endif>{{ __('status_active') }}</option>
                                    <option value="0" @if($row->status == 0) selected @endif>{{ __('status_inactive') }}</option>
                                </select>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="form-label">{{ __('field_classification') }}</label>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="is_hnd" id="is_hnd-{{ $row->id }}" value="1" {{ $row->is_hnd ? 'checked' : '' }}>
                                    <label class="form-check-label" for="is_hnd-{{ $row->id }}">
                                        Is HND (Higher National Diploma)
                                    </label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="is_postgraduate" id="is_postgraduate-{{ $row->id }}" value="1" {{ $row->is_postgraduate ? 'checked' : '' }}>
                                    <label class="form-check-label" for="is_postgraduate-{{ $row->id }}">
                                        Is Postgraduate
                                    </label>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="duration_years-{{ $row->id }}" class="form-label">{{ __('field_duration') }} (Years)</label>
                                <input type="number" class="form-control" name="duration_years" id="duration_years-{{ $row->id }}" min="1" max="10" value="{{ $row->duration_years }}">
                                <small class="form-text text-muted">Typical program duration</small>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="min_credits-{{ $row->id }}" class="form-label">{{ __('field_minimum_credits') }}</label>
                                <input type="number" class="form-control" name="min_credits" id="min_credits-{{ $row->id }}" min="0" max="500" value="{{ $row->min_credits }}">
                                <small class="form-text text-muted">Minimum credits to complete</small>
                            </div>
                        </div>

                        <div class="col-md-12">
                            <div class="form-group">
                                <label for="description-{{ $row->id }}" class="form-label">{{ __('field_description') }}</label>
                                <textarea class="form-control" name="description" id="description-{{ $row->id }}" rows="3">{{ $row->description }}</textarea>
                            </div>
                        </div>

                        <div class="col-md-12">
                            <div class="form-group">
                                <label for="requirements-{{ $row->id }}" class="form-label">{{ __('field_requirements') }}</label>
                                <textarea class="form-control" name="requirements" id="requirements-{{ $row->id }}" rows="3">{{ $row->requirements }}</textarea>
                                <small class="form-text text-muted">Entry requirements for this degree type</small>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="sort_order-{{ $row->id }}" class="form-label">{{ __('field_sort_order') }}</label>
                                <input type="number" class="form-control" name="sort_order" id="sort_order-{{ $row->id }}" min="0" value="{{ $row->sort_order }}">
                                <small class="form-text text-muted">Display order in lists</small>
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
