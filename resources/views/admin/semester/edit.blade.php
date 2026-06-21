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

                    <div class="form-group">
                        <label for="year" class="form-label">{{ __('field_year') }} <span>*</span></label>
                        <select class="form-control" name="year" id="year" required>
                            <option value="">{{ __('select') }}</option>
                            <option value="1" @if( $row->year == 1 ) selected @endif>{{ __('1st_year') }}</option>
                            <option value="2" @if( $row->year == 2 ) selected @endif>{{ __('2nd_year') }}</option>
                            <option value="3" @if( $row->year == 3 ) selected @endif>{{ __('3rd_year') }}</option>
                            <option value="4" @if( $row->year == 4 ) selected @endif>{{ __('4th_year') }}</option>
                            <option value="5" @if( $row->year == 5 ) selected @endif>{{ __('5th_year') }}</option>
                            <option value="6" @if( $row->year == 6 ) selected @endif>{{ __('6th_year') }}</option>
                            <option value="7" @if( $row->year == 7 ) selected @endif>{{ __('7th_year') }}</option>
                            <option value="8" @if( $row->year == 8 ) selected @endif>{{ __('8th_year') }}</option>
                        </select>

                        <div class="invalid-feedback">
                          {{ __('required_field') }} {{ __('field_year') }}
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="semester_type" class="form-label">{{ __('Semester Type') }} <span>*</span></label>
                        <select class="form-control" name="semester_type" id="semester_type" required>
                            <option value="">{{ __('select') }}</option>
                            <option value="1" @if( $row->semester_type == 1 ) selected @endif>{{ __('First Semester') }}</option>
                            <option value="2" @if( $row->semester_type == 2 ) selected @endif>{{ __('Second Semester') }}</option>
                        </select>

                        <div class="invalid-feedback">
                          {{ __('required_field') }} {{ __('Semester Type') }}
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="program">{{ __('field_assign') }} {{ __('field_program') }} <span>*</span></label><br/>

                        @foreach($programs as $key => $program)
                        <br/>
                        <div class="checkbox d-inline">
                            <input type="checkbox" name="programs[]" id="program-{{ $key }}-{{ $row->id }}" value="{{ $program->id }}"

                            @foreach($row->programs as $selected_program)
                                @if($selected_program->id == $program->id) checked @endif 
                            @endforeach

                            >
                            <label for="program-{{ $key }}-{{ $row->id }}" class="cr">{{ $program->title }}</label>
                        </div>
                        @endforeach

                        <div class="invalid-feedback">
                          {{ __('required_field') }} {{ __('field_program') }}
                        </div>
                    </div>

                    <div class="form-group">
                        <div class="checkbox d-inline">
                            <input type="checkbox" name="is_resit" id="is_resit_edit_{{ $row->id }}" value="1" @if($row->is_resit) checked @endif onchange="toggleParentSemester('edit_{{ $row->id }}')">
                            <label for="is_resit_edit_{{ $row->id }}" class="cr">{{ __('Resit Semester') }}</label>
                        </div>
                        <small class="form-text text-muted">{{ __('Mark this semester as dedicated to resit activities.') }}</small>
                    </div>

                    <div class="form-group" id="parent_semester_edit_{{ $row->id }}" style="display: {{ $row->is_resit ? 'block' : 'none' }};">
                        <label for="parent_semester_id_edit_{{ $row->id }}" class="form-label">{{ __('Parent Semester') }}</label>
                        <select class="form-control" name="parent_semester_id" id="parent_semester_id_edit_{{ $row->id }}">
                            <option value="">{{ __('select') }}</option>
                            @foreach($regular_semesters as $regular_semester)
                            <option value="{{ $regular_semester->id }}" @if($row->parent_semester_id == $regular_semester->id) selected @endif>
                                {{ $regular_semester->title }}
                            </option>
                            @endforeach
                        </select>
                        <small class="form-text text-muted">{{ __('Select the main semester that this resit semester is associated with.') }}</small>
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