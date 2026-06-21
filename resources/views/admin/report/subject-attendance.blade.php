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
                        <h5>{{ $title }}</h5>
                    </div>
          <div class="card-block">
            <form class="needs-validation" novalidate method="get" action="{{ route($route .'.subject-attendance') }}">
              <div class="row gx-2">
                @include('common.inc.common_search_filter', [
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
                ])
                <div class="form-group col-md-3">
                  <label for="subject">{{ __('field_subject') }} <span>*</span></label>
                  <select class="form-control subject subject-filter" name="subject" id="subject" required>
                    <option value="">{{ __('select') }}</option>
                    @foreach(($subjects ?? collect())->sortBy('code') as $subject)
                    <option value="{{ $subject->id }}" @if($selected_subject == $subject->id) selected @endif>{{ $subject->code }} - {{ $subject->title }}</option>
                    @endforeach
                  </select>

                  <div class="invalid-feedback">
                    {{ __('required_field') }} {{ __('field_subject') }}
                  </div>
                </div>
                <div class="form-group col-md-3 align-self-end">
                  <button type="submit" class="btn btn-info btn-filter"><i class="fas fa-search"></i> {{ __('btn_search') }}</button>
                </div>
              </div>
            </form>
          </div>

                    @if(isset($attendances) && isset($rows))
                    <div class="card-header">
                        <p>{{ __('attendance_present') }}: <span class="text-primary">{{ __('P') }}</span> | {{ __('attendance_absent') }}: <span class="text-danger">{{ __('A') }}</span> | {{ __('attendance_leave') }}: <span class="text-success">{{ __('L') }}</span> | {{ __('attendance_holiday') }}: <span class="text-warning">{{ __('H') }}</span></p>
                    </div>
                    <div class="card-block">
                        <!-- [ Data table ] start -->
                        <div class="table-responsive">
                            <table class="table table-attendance table-striped table-hover table-bordered">
                                <thead>
                                    <tr>
                                        <th>{{ __('field_student_id') }}</th>
                                        <th>{{ __('field_name') }}</th>
                                        <th>{{ __('field_semester') }}</th>
                                        <th>{{ __('field_section') }}</th>
                                        <th>{{ __('field_period') }}</th>
                                        <th>{{ __('P') }}</th>
                                        <th>{{ __('A') }}</th>
                                        <th>{{ __('L') }}</th>
                                        <th>{{ __('H') }}</th>
                                        <th>{{ __('%') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                  @foreach( $rows as $key => $row )
                                    <tr>
                                        <td>
                                            @isset($row->student->student_id)
                                            <a href="{{ route('admin.student.show', $row->student->id) }}">
                                            #{{ $row->student->student_id ?? '' }}
                                            </a>
                                            @endisset
                                        </td>
                                        <td>{{ $row->student->first_name ?? '' }} {{ $row->student->last_name ?? '' }}</td>
                                        <td>{{ $row->semester->title ?? '' }}</td>
                                        <td>{{ $row->section->title ?? '' }}</td>
                                        @php
                                            $total_present = 0;
                                            $total_absent = 0;
                                            $total_leave = 0;
                                            $total_holiday = 0;
                                        @endphp
                                        @if(isset($attendances))
                                        @foreach($attendances as $user_attend)
                                        @if($user_attend->studentEnroll->student_id == $row->student_id)
                                            @if($user_attend->attendance == 1)
                                            @php
                                                $total_present = $total_present + 1;
                                            @endphp
                                            @elseif($user_attend->attendance == 2)
                                            @php
                                                $total_absent = $total_absent + 1;
                                            @endphp
                                            @elseif($user_attend->attendance == 3)
                                            @php
                                                $total_leave = $total_leave + 1;
                                            @endphp
                                            @elseif($user_attend->attendance == 4)
                                            @php
                                                $total_holiday = $total_holiday + 1;
                                            @endphp
                                            @endif
                                        @endif
                                        @endforeach
                                        @endif
                                        @php
                                            $total_working_days = $total_present + $total_absent + $total_leave;
                                        @endphp
                                        <td>{{ $total_working_days }}</td>
                                        <td>{{ $total_present }}</td>
                                        <td>{{ $total_absent }}</td>
                                        <td>{{ $total_leave }}</td>
                                        <td>{{ $total_holiday }}</td>
                                        @php
                                            if($total_working_days == 0){
                                                $total_working_days = 1;
                                            }
                                        @endphp
                                        <td>{{ round((($total_present / $total_working_days) * 100), 2) }} %</td>
                                    </tr>
                                  @endforeach
                                </tbody>

                                <caption>{{ $row->program->title ?? '' }} - {{ $row->session->title ?? '' }}</caption>
                            </table>
                        </div>
                        <!-- [ Data table ] end -->
                    </div>
                    @endif
                </div>
            </div>
        </div>
        <!-- [ Main Content ] end -->
    </div>
</div>
<!-- End Content-->

@endsection

@section('page_js')
<script type="text/javascript">
"use strict";
(function($){
  if (typeof $ === 'undefined') {
    return;
  }

  const $filterForm = $('form.needs-validation');
  if(!$filterForm.length){
    return;
  }

  const $subject = $filterForm.find('.subject-filter');
  const translationSelect = <?php echo json_encode(__('select')); ?>;
  let presetSubject = <?php echo json_encode($selected_subject ?? ''); ?>;

  const resetSubjectOptions = () => {
    if(!$subject.length){
      return;
    }
    $subject.empty();
    $subject.append($('<option/>', {
      value: '',
      text: translationSelect
    }));
  };

  const applyPreset = () => {
    if(!presetSubject){
      return;
    }
    $subject.val(String(presetSubject));
  };

  const applySubjectOptions = (items) => {
    resetSubjectOptions();
    if(Array.isArray(items)){
      items.forEach((item) => {
        if(!item){
          return;
        }
        const option = $('<option/>', {
          value: item.id,
          text: (item.code ? item.code + ' - ' : '') + item.title
        });
        if(presetSubject && String(presetSubject) === String(item.id)){
          option.attr('selected', 'selected');
        }
        $subject.append(option);
      });
    }
  };

  const fetchSubjects = () => {
    const programId = $filterForm.find('.common-program').val();
    const sessionId = $filterForm.find('.common-session').val();

    if(!programId || !sessionId){
      resetSubjectOptions();
      return;
    }

    $.post("{{ route('filter-techer-subject') }}", {
      program: programId,
      session: sessionId
    }, function(response){
      applySubjectOptions(response);
    });
  };

  const ensureInitialOptions = () => {
    if(!$subject.children('option').length){
      resetSubjectOptions();
    }

    if(!$subject.children('option').not('[value=""]').length){
      fetchSubjects();
    } else {
      applyPreset();
    }
  };

  $filterForm.on('change', '.common-program', function(){
    presetSubject = '';
    resetSubjectOptions();
  });

  $filterForm.on('change', '.common-session', function(){
    presetSubject = '';
    fetchSubjects();
  });

  $filterForm.on('change', '.common-semester, .common-semester-year, .common-section', function(){
    if(!$subject.length){
      return;
    }
    presetSubject = '';
    $subject.val('');
  });

  $subject.on('change', function(){
    presetSubject = $(this).val() || '';
  });

  ensureInitialOptions();
})(window.jQuery);
</script>
@endsection