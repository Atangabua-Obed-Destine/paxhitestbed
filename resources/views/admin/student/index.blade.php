@extends('admin.layouts.master')
@section('title', $title)
@section('content')

<!-- Start Content-->
<div class="main-body">
    <div class="page-wrapper">
        <!-- [ Main Content ] start -->
        <div class="row">
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header">
                        <h5>{{ $title }} {{ __('list') }}</h5>
                    </div>
                    <div class="card-block">
                        @can($access.'-create')
                        <a href="{{ route($route.'.create') }}" class="btn btn-primary"><i class="fas fa-plus"></i> {{ __('btn_add_new') }}</a>
                        @endcan

                        <a href="{{ route($route.'.index') }}" class="btn btn-info"><i class="fas fa-sync-alt"></i> {{ __('btn_refresh') }}</a>

                        @can($access.'-import')
                        <a href="{{ route($route.'.import') }}" class="btn btn-dark"><i class="fas fa-upload"></i> {{ __('btn_import') }}</a>
                        @endcan

                        @isset($rows)
                        @can($access.'-password-print')
                        <form class="needs-validation d-inline" novalidate method="get" action="{{ route($route.'.password-multiprint') }}" target="_blank">
                            <input type="hidden" name="students" class="students" value="">
                            <button type="submit" class="btn btn-sm btn-dark print-btn"><i class="fas fa-print"></i> {{ __('field_password') }}</button>
                        </form>
                        @endcan
                        @endisset
                    </div>
                    
                    <div class="card-block">
                        <form class="needs-validation" novalidate method="get" action="{{ route($route.'.index') }}">
                            <input type="hidden" name="filtered" value="1">
                            <input type="hidden" name="status" value="0">
                            <div class="row gx-2">
                                                                @include('common.inc.student_search_filter', [
                                                                    'faculties' => $faculties ?? collect(),
                                                                    'programs' => $programs ?? collect(),
                                                                    'sessions' => $sessions ?? collect(),
                                                                    'semesters' => $semesters ?? collect(),
                                                                    'sections' => $sections ?? collect(),
                                                                    'semesterOptions' => $semesterOptions ?? [],
                                                                    'selected_faculty' => $selected_faculty ?? '0',
                                                                    'selected_program' => $selected_program ?? '0',
                                                                    'selected_session' => $selected_session ?? '0',
                                                                    'selected_semester' => $selected_semester ?? '0',
                                                                    'selected_semester_year' => $selected_semester_year ?? '0',
                                                                    'selected_section' => $selected_section ?? '0',
                                                                    'include_semester_all' => true,
                                                                    'include_section_all' => true,
                                                                    'current_session_id' => $current_session_id ?? null,
                                                                ])

                                <div class="form-group col-md-3">
                                    <label for="enroll_status">{{ __('field_enrollment') }} {{ __('field_status') }}</label>
                                    <select class="form-control" name="enroll_status" id="enroll_status">
                                        <option value="1" @if(($selected_enroll_status ?? '1') == '1') selected @endif>{{ __('status_active') }}</option>
                                        <option value="0" @if(($selected_enroll_status ?? '1') == '0') selected @endif>{{ __('status_inactive') }}</option>
                                        <option value="all" @if(($selected_enroll_status ?? '1') == 'all') selected @endif>{{ __('all') }}</option>
                                    </select>
                                </div>
                                <div class="form-group col-md-3">
                                    <label for="student_id">{{ __('field_student_id') }}</label>
                                    <input type="text" class="form-control" name="student_id" id="student_id" value="{{ $selected_student_id }}">

                                    <div class="invalid-feedback">
                                      {{ __('required_field') }} {{ __('field_student_id') }}
                                    </div>
                                </div>
                                <div class="form-group col-md-3">
                                    <button type="submit" class="btn btn-info btn-filter"><i class="fas fa-search"></i> {{ __('btn_search') }}</button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            @isset($rows)
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-block">
                        <!-- [ Data table ] start -->
                        <div class="table-responsive">
                            <table id="export-table" class="display table nowrap table-striped table-hover" style="width:100%">
                                @if(isset($unenrolled) && $unenrolled->count() > 0)
                                {{-- This list is built entirely from enrolments, so a student whose
                                     enrolments were erased used to vanish from it without a word.
                                     Said out loud now, whether or not the filters let them be
                                     listed below. --}}
                                <div class="alert alert-danger" role="alert">
                                    <strong>
                                        <i class="fas fa-user-slash"></i>
                                        {{ $unenrolled->count() }} {{ __('student(s) have no enrolment.') }}
                                    </strong>
                                    <div class="small mt-1">
                                        {{ __('They cannot be taught, examined or billed until they are enrolled again. This usually means a programme, session, semester or section was deleted while they were on it.') }}
                                        @if(!($unenrolled_shown ?? false))
                                            <br>{{ __('Clear the faculty and programme filters to see them listed below.') }}
                                        @endif
                                    </div>
                                </div>
                                @endif

                                <thead>
                                    <tr>
                                        <th>#
                                            <div class="checkbox checkbox-success d-inline">
                                                <input type="checkbox" id="checkbox" class="all_select">
                                                <label for="checkbox" class="cr" style="margin-bottom: 0px;"></label>
                                            </div>
                                        </th>
                                        <th>{{ __('field_matricule') }}</th>
                                        <th>{{ __('field_name') }}</th>
                                        <th>{{ __('field_program') }}</th>
                                        <th>{{ __('field_session') }}</th>
                                        <th>{{ __('field_semester') }}</th>
                                        <th>{{ __('field_section') }}</th>
                                        <th>{{ __('field_status') }}</th>
                                        {{-- <th>{{ __('field_login') }}</th> --}}
                                        <th>{{ __('field_action') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                  @foreach( $rows as $key => $row )
                                  @php
                                    // $row is now an enrollment
                                    $student = $row->student;
                                    $enroll = $row;
                                  @endphp
                                    <tr>
                                        <td>
                                            {{ $key + 1 }}
                                            <div class="checkbox checkbox-primary d-inline">
                                                <input type="checkbox" data_id="{{ $student->id }}" id="checkbox-{{ $row->id }}" value="{{ $student->id }}">
                                                <label for="checkbox-{{ $row->id }}" class="cr"></label>
                                            </div>
                                        </td>
                                        <td>
                                            <a href="{{ route($route.'.show', $student->id) }}">
                                            <strong style="color: #667eea;">#{{ $enroll->matricule ?? $student->student_id }}</strong>
                                            @if($enroll && $enroll->program)
                                                <br>
                                                <span class="badge" style="background: {{ $enroll->program->academic_level == 'M' ? '#f5576c' : ($enroll->program->academic_level == 'D' ? '#00f2fe' : '#38f9d7') }}; color: white; font-size: 9px;">
                                                    {{ $enroll->program->academic_level == 'A' ? 'UG' : ($enroll->program->academic_level == 'M' ? 'MS' : 'PhD') }}
                                                </span>
                                                @if($enroll->status == 0)
                                                <span class="badge badge-danger" style="font-size: 9px;">{{ __('status_inactive') }}</span>
                                                @endif
                                            @endif
                                            </a>
                                        </td>
                                        <td>{{ $student->first_name }} {{ $student->last_name }}</td>
                                        <td>{{ $enroll->program->shortcode ?? '' }}</td>
                                        <td>{{ $enroll->session->title ?? '' }}</td>
                                        <td>{{ $enroll->semester->title ?? '' }}</td>
                                        <td>{{ $enroll->section->title ?? '' }}</td>
                                        <td>
                                            @foreach($student->statuses as $key => $status)
                                            <span class="badge badge-primary">{{ $status->title }}</span><br>
                                            @endforeach
                                        </td>
                                        {{-- <td>
                                            @can($access.'-edit')
                                            @if( $row->login == 1 )
                                            <a href="{{ route($route.'.status', $row->id) }}" class="btn btn-icon btn-danger btn-sm"><i class="fas fa-times"></i></a>
                                            @else
                                            <a href="{{ route($route.'.status', $row->id) }}" class="btn btn-icon btn-success btn-sm"><i class="fas fa-check"></i></a>
                                            @endif
                                            @else
                                            @if( $row->login == 1 )
                                            <span class="badge badge-pill badge-success">{{ __('status_active') }}</span>
                                            @else
                                            <span class="badge badge-pill badge-danger">{{ __('status_blocked') }}</span>
                                            @endif
                                            @endcan
                                        </td> --}}
                                        <td>
                                            @can($access.'-password-print')
                                            <a href="#" class="btn btn-dark btn-sm" onclick="PopupWin('{{ route($route.'.print-password', [$student->id]) }}', '{{ $title }}', 800, 500); return false;"><i class="fas fa-print"></i> {{ __('field_password') }}</a>
                                            @endcan

                                            <form action="{{ route($route.'.send-password', [$student->id]) }}" method="post" style="display: inline;">
                                            @csrf
                                            <button type="submit" class="btn btn-info btn-sm"><i class="fas fa-envelope"></i> {{ __('field_password') }}</button>
                                            </form>
                                            <br/>
                                            
                                            <a href="{{ route($route.'.show', $student->id) }}" class="btn btn-icon btn-success btn-sm">
                                                <i class="fas fa-eye"></i>
                                            </a>

                                            @can($access.'-edit')
                                            <a href="{{ route($route.'.impersonate', $student->id) }}" class="btn btn-icon btn-dark btn-sm" title="Impersonate Student" target="_blank">
                                                <i class="fas fa-user-secret"></i>
                                            </a>

                                            <a href="{{ route($route.'.edit', $student->id) }}" class="btn btn-icon btn-primary btn-sm">
                                                <i class="far fa-edit"></i>
                                            </a>
                                            @endcan

                                            @can($access.'-card')
                                            @if(isset($print))
                                            <a href="#" class="btn btn-icon btn-warning btn-sm" onclick="PopupWin('{{ route($route.'.card', $student->id) }}', '{{ $title }}', 800, 500); return false;">
                                                <i class="fas fa-address-card"></i>
                                            </a>
                                            @endif
                                            @endcan

                                            @can($access.'-password-change')
                                            <button class="btn btn-icon btn-info btn-sm" data-bs-toggle="modal" data-bs-target="#changePasswordModal-{{ $student->id }}">
                                            <i class="fas fa-key"></i>
                                            </button>

                                            <!-- Include Password Change modal -->
                                            @include($view.'.password-change')
                                            @endcan
                                            
                                            @can($access.'-delete')
                                            <button type="button" class="btn btn-icon btn-danger btn-sm" data-bs-toggle="modal" data-bs-target="#deleteModal-{{ $student->id }}">
                                                <i class="fas fa-trash-alt"></i>
                                            </button>
                                            <!-- Include Delete modal -->
                                            @include('admin.layouts.inc.delete')
                                            @endcan

                                            @can($access.'-edit')
                                            <!-- Enrollment Status Toggle Button -->
                                            @if($enroll->status == 1)
                                            <button type="button" class="btn btn-icon btn-outline-danger btn-sm" data-bs-toggle="modal" data-bs-target="#enrollStatusModal-{{ $enroll->id }}" title="{{ __('btn_deactivate') }} {{ __('field_enrollment') }}">
                                                <i class="fas fa-user-slash"></i>
                                            </button>
                                            @else
                                            <button type="button" class="btn btn-icon btn-outline-success btn-sm" data-bs-toggle="modal" data-bs-target="#enrollStatusModal-{{ $enroll->id }}" title="{{ __('btn_activate') }} {{ __('field_enrollment') }}">
                                                <i class="fas fa-user-check"></i>
                                            </button>
                                            @endif
                                            <!-- Include Enrollment Status Toggle modal -->
                                            @include($view.'.enroll-status-modal', ['enrollment' => $enroll, 'studentData' => $student])
                                            @endcan
                                        </td>
                                    </tr>
                                  @endforeach
                                  @if(($unenrolled_shown ?? false) && isset($unenrolled))
                                  @foreach($unenrolled as $orphan)
                                    <tr class="table-danger">
                                        <td>&mdash;</td>
                                        <td>
                                            <a href="{{ route($route.'.show', $orphan->id) }}">
                                                <strong style="color: #c0392b;">#{{ $orphan->student_id }}</strong>
                                            </a>
                                            <br>
                                            <span class="badge badge-danger" style="font-size: 9px;">{{ __('No enrolment') }}</span>
                                        </td>
                                        <td>{{ $orphan->first_name }} {{ $orphan->last_name }}</td>
                                        <td colspan="4" class="text-muted">
                                            {{ __('This student has no enrolment, so they have no programme, session, semester or section.') }}
                                        </td>
                                        <td>
                                            @foreach($orphan->statuses as $status)
                                            <span class="badge badge-primary">{{ $status->title }}</span><br>
                                            @endforeach
                                        </td>
                                        <td></td>
                                        <td class="text-nowrap">
                                            @can($access.'-delete')
                                                {{-- A record like this was never really made: no enrolment, no
                                                     money, no history. Rather than leave it stranded, it goes
                                                     back to its application so the programme can be corrected
                                                     and the admission made properly. --}}
                                                <button type="button" class="btn btn-sm btn-warning btn-unconvert"
                                                        data-student="{{ $orphan->id }}"
                                                        data-name="{{ $orphan->first_name }} {{ $orphan->last_name }}"
                                                        data-matricule="{{ $orphan->student_id }}"
                                                        title="{{ __('Send back to the application') }}">
                                                    <i class="fas fa-undo"></i> {{ __('Send back') }}
                                                </button>
                                            @endcan
                                        </td>
                                    </tr>
                                  @endforeach
                                  @endif
                                </tbody>
                            </table>
                        </div>
                        <!-- [ Data table ] end -->
                    </div>
                </div>
            </div>
            @endisset
            
        </div>
        <!-- [ Main Content ] end -->
    </div>
</div>
<!-- End Content-->

@endsection

@section('page_js')
<script type="text/javascript">
    "use strict";
    $(document).ready(function() {
        $(".print-btn").on('click',function(e){

            var numberOfChecked = $("input[data_id]:checked").length;
            if(numberOfChecked <= 0){
                e.preventDefault();
                alert("{{ __('select') }} {{ __('field_student') }}");
            }

            var students = [];
            $.each($("input[data_id]:checked"), function(){
                students.push($(this).val());
            });

            $(".students").val( students.join(',') );
        });
    });

    // checkbox all-check-button selector
    $(".all_select").on('click',function(e){
        if($(this).is(":checked")){
            // check all checkbox
            $("input:checkbox").prop('checked', true);
        }
        else if($(this).is(":not(:checked)")){
            // uncheck all checkbox
            $("input:checkbox").prop('checked', false);
        }
    });
</script>

@can($access.'-delete')
{{-- Sending a record back to its application.

     This deletes a student record, so it follows the same shape as every other
     screen here that destroys something: say exactly what will happen, name the
     record, and keep the button dead until a tick box is ticked. --}}
<div class="modal fade" id="unconvertModal" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered" role="document">
    <div class="modal-content">
      <form method="POST" id="unconvert-form">
        @csrf
        <div class="modal-header bg-warning">
          <h5 class="modal-title">{{ __('Send this record back to its application') }}</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <p class="mb-2">
            {{ __('This removes the student record for') }}
            <strong id="unconvert-name">&mdash;</strong>
            (<span id="unconvert-matricule">&mdash;</span>)
            {{ __('and puts their application back in front of admissions, so the programme can be corrected and the student created again.') }}
          </p>

          <div class="alert alert-warning mb-3">
            <i class="fas fa-exclamation-triangle"></i>
            {{ __('The student record itself is deleted. Their application, and everything the applicant submitted, is kept.') }}
          </div>

          <div class="form-group">
            <label for="unconvert-reason">{{ __('Why') }}</label>
            <input type="text" class="form-control" name="reason" id="unconvert-reason" maxlength="500"
                   placeholder="{{ __('e.g. admitted onto the wrong programme') }}">
            <small class="form-text text-muted">{{ __('Written onto the application, so admissions can see what happened.') }}</small>
          </div>

          <div class="form-check">
            <input class="form-check-input" type="checkbox" id="unconvert-confirm">
            <label class="form-check-label" for="unconvert-confirm">
              {{ __('I understand this deletes the student record.') }}
            </label>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ __('btn_close') }}</button>
          <button type="submit" class="btn btn-warning" id="unconvert-submit" disabled>
            <i class="fas fa-undo"></i> {{ __('Send back to the application') }}
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

@push('scripts')
<script>
(function ($) {
  "use strict";

  $(document).on('click', '.btn-unconvert', function () {
    var button = $(this);

    $('#unconvert-name').text(button.data('name'));
    $('#unconvert-matricule').text(button.data('matricule'));
    $('#unconvert-reason').val('');
    $('#unconvert-confirm').prop('checked', false);
    $('#unconvert-submit').prop('disabled', true);
    $('#unconvert-form').attr('action',
      '{{ url('admin/admission/student') }}/' + button.data('student') + '/unconvert');

    $('#unconvertModal').modal('show');
  });

  $('#unconvert-confirm').on('change', function () {
    $('#unconvert-submit').prop('disabled', !this.checked);
  });

  $('#unconvertModal').on('hidden.bs.modal', function () {
    $('#unconvert-confirm').prop('checked', false);
    $('#unconvert-submit').prop('disabled', true);
  });
})(jQuery);
</script>
@endpush
@endcan

@endsection