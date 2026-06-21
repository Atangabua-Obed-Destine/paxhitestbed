    <!-- Edit modal content -->
    <div id="editModal-{{ $row->id }}" class="modal fade" tabindex="-1" role="dialog" aria-labelledby="myModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg" role="document">
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
                        <label for="title-{{ $row->id }}" class="form-label">{{ __('field_title') }} <span>*</span></label>
                        <input type="text" class="form-control" name="title" id="title-{{ $row->id }}" value="{{ $row->title }}" required>

                        <div class="invalid-feedback">
                          {{ __('required_field') }} {{ __('field_title') }}
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="program">{{ __('field_assign') }} {{ __('field_program') }} <span>*</span></label>

                        <!-- Hidden field to store selected combinations as JSON -->
                        <input type="hidden" name="program_semesters_json" id="program_semesters_json_{{ $row->id }}" value="">

                        <div class="alert alert-info">
                            <i class="fas fa-info-circle"></i> 
                            <strong>Selected:</strong> <span id="selected_count_{{ $row->id }}">0</span> program-semester combinations
                        </div>

                        <div class="checkbox">
                            <input type="checkbox" name="all_check_edit_{{ $row->id }}" id="all_check_edit_{{ $row->id }}" class="all_check_edit" data-section-id="{{ $row->id }}">
                            <label for="all_check_edit_{{ $row->id }}" class="cr">{{ __('all') }}</label>
                        </div>

                        @foreach($programs as $key => $program)
                        <br/><br/>
                        <span class="badge badge-pill badge-primary">{{ $key + 1 }}. {{ $program->title }}</span>
                        <hr/><br/>
                        @php
                            // Create a lookup array for faster checking
                            $selectedCombos = $row->semesterPrograms->map(function($sp) {
                                return $sp->program_id . '_' . $sp->semester_id;
                            })->toArray();
                        @endphp
                        @foreach($program->semesters->where('status', 1)->sortBy('title') as $semester)
                        <div class="checkbox d-inline">
                            <input type="checkbox" 
                                   class="semester-checkbox-{{ $row->id }}"
                                   data-section="{{ $row->id }}"
                                   id="semester-{{ $key }}-{{ $row->id }}-{{ $semester->id }}" 
                                   value="{{ $program->id }}_{{ $semester->id }}"
                                   {{ in_array($program->id . '_' . $semester->id, $selectedCombos) ? 'checked' : '' }}
                            >
                            <label for="semester-{{ $key }}-{{ $row->id }}-{{ $semester->id }}" class="cr">{{ $semester->title }}</label>
                        </div>
                        @endforeach
                        @endforeach

                        <div class="invalid-feedback">
                          {{ __('required_field') }} {{ __('field_program') }}
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="status-{{ $row->id }}" class="form-label">{{ __('select_status') }}</label>
                        <select class="form-control" name="status" id="status-{{ $row->id }}">
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