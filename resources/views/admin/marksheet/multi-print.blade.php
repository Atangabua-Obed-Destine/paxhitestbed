<!DOCTYPE html>
<html lang="en-US">
<head>
    <meta charset="utf-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <meta name="viewport" content="width=device-width,maximum-scale=1.0">
    <title>{{ $title }}</title>
    
    <style type="text/css" media="print">
    @media print {
      @page { size: A4 portrait; margin: 50px auto; }   
      @page :footer { display: none }
      @page :header { display: none }
      body { margin: 15mm auto 15mm auto; }
      table, tbody, tr {page-break-before: auto;}
      .page-break { page-break-before: always; }
    }
    img, svg {
      break-inside: avoid;
    }
    .template-container {
      -webkit-transform: scale(1.0);  /* Saf3.1+, Chrome */
      -moz-transform: scale(1.0);  /* FF3.5+ */
      -ms-transform: scale(1.0);  /* IE9 */
      -o-transform: scale(1.0);  /* Opera 10.5+ */
      transform: scale(1.0);
    }
    </style>
    <link rel="stylesheet" type="text/css" href="{{ asset('dashboard/css/prints/marksheet.css') }}" media="screen, print">

    @php 
    $version = App\Models\Language::version(); 
    @endphp
    @if($version->direction == 1)
    <!-- RTL css -->
    <style type="text/css" media="screen, print">
    .template-container {
      direction: rtl;
    }
    .template-container .top-meta-table tr td,
    .template-container .top-meta-table tr th {
      float: right;
      text-align: right;
    }
    .table-no-border.marksheet td:nth-child(1), 
    .table-no-border.marksheet td:nth-child(2) {
      text-align: right;
    }
    </style>
    @endif
</head>
<body>

@foreach($rows as $row)
<div class="printable">
<div class="template-container" style="width: {{ $marksheet->width }}; height: {{ $marksheet->height }};">
  <div class="template-inner">
    <!-- Header Section -->
    <table class="table-no-border">
        <tbody>
            <tr>
                <td class="temp-logo">
                  <div class="inner">
                    @if(is_file('uploads/'.$path.'/'.$marksheet->logo_left))
                    <img src="{{ asset('uploads/'.$path.'/'.$marksheet->logo_left) }}" alt="Logo">
                    @endif
                  </div>
                </td>
                <td class="temp-title">
                  <div class="inner">
                    <h2>{{ $setting->title }}</h2>
                    <h4>{{ $marksheet->title }}</h4>
                  </div>
                </td>
                <td class="temp-logo last">
                  <div class="inner">
                    @if(is_file('uploads/'.$path.'/'.$marksheet->logo_right))
                    <img src="{{ asset('uploads/'.$path.'/'.$marksheet->logo_right) }}" alt="Logo">
                    @endif
                  </div>
                </td>
            </tr>
        </tbody>
    </table>
    <!-- Header Section -->

    @php
        $total_credits = 0;
        $total_cgpa = 0;
    @endphp


    {{-- CGPA and Credit Cal --}}
    @foreach( $row->studentEnrolls as $key => $item )
    @if($item->session_id == $session)
    @php $session_data = $item; @endphp

        @if(isset($item->subjectMarks))
        @foreach($item->subjectMarks as $mark)

            @php
            $marks_per = round($mark->total_marks);
            @endphp

            @foreach($grades as $grade)
            @if($marks_per >= $grade->min_mark && $marks_per <= $grade->max_mark)
            @php
            // Always count all courses towards CGPA calculation, including F grades
            $total_cgpa = $total_cgpa + ($grade->point * $mark->subject->credit_hour);
            $total_credits = $total_credits + $mark->subject->credit_hour;
            @endphp
            @break
            @endif
            @endforeach

        @endforeach
        @endif

    @endif
    @endforeach

    <br/>

    <!-- Header Section -->
    <table class="table-no-border top-meta-table">
        <tbody>
            <tr>
                <td class="meta-data">{{ __('field_matricule') }}</td>
                <td class="meta-data width2">: 
                    @php
                        $studentCurrentEnroll = \App\Models\StudentEnroll::where('student_id', $row->id)
                            ->with('program')
                            ->where('id', $session_data->id ?? 0)
                            ->first();
                        if(!$studentCurrentEnroll) {
                            $studentCurrentEnroll = \App\Models\StudentEnroll::where('student_id', $row->id)
                                ->with('program')
                                ->orderBy('id', 'desc')
                                ->first();
                        }
                    @endphp
                    <strong style="color: #667eea;">{{ $studentCurrentEnroll->matricule ?? $row->student_id }}</strong>
                    @if($studentCurrentEnroll && $studentCurrentEnroll->program)
                        <span style="background: {{ $studentCurrentEnroll->program->academic_level == 'M' ? '#f5576c' : ($studentCurrentEnroll->program->academic_level == 'D' ? '#00f2fe' : '#38f9d7') }}; color: white; font-size: 9px; padding: 2px 6px; border-radius: 3px; margin-left: 5px;">
                            {{ $studentCurrentEnroll->program->academic_level == 'A' ? 'UG' : ($studentCurrentEnroll->program->academic_level == 'M' ? 'MS' : 'PhD') }}
                        </span>
                    @endif
                </td>

                <td class="meta-data">{{ __('field_session') }}</td>
                <td class="meta-data">: {{ $session_data->session->title ?? '' }}</td>
            </tr>
            <tr>
                <td class="meta-data">{{ __('field_name') }}</td>
                <td class="meta-data width2">: {{ $row->first_name }} {{ $row->last_name }}</td>
                <td class="meta-data">{{ __('field_semester') }}</td>
                <td class="meta-data">: {{ $session_data->semester->title ?? '' }}</td>
            </tr>
            <tr>
                <td class="meta-data">{{ __('field_gender') }}</td>
                <td class="meta-data width2">: 
                    @if( $row->gender == 1 )
                    {{ __('gender_male') }}
                    @elseif( $row->gender == 2 )
                    {{ __('gender_female') }}
                    @elseif( $row->gender == 3 )
                    {{ __('gender_other') }}
                    @endif
                </td>
                <td class="meta-data">{{ __('field_section') }}</td>
                <td class="meta-data">: {{ $session_data->section->title ?? '' }}</td>
            </tr>
            <tr>
                <td class="meta-data">{{ __('field_batch') }}</td>
                <td class="meta-data width2">: {{ $row->batch->title ?? '' }}</td>
                <td class="meta-data">{{ __('field_total_credit_hour') }}</td>
                <td class="meta-data">: {{ round($total_credits, 2) }}</td>
            </tr>
            <tr>
                <td class="meta-data">{{ __('field_program') }}</td>
                <td class="meta-data width2">: {{ $row->program->title ?? '' }}</td>

                @php
                    if($total_credits <= 0){
                        $total_credits = 1;
                    }
                    $com_gpa = $total_cgpa / $total_credits;
                @endphp

                <td class="meta-data">{{ __('field_cumulative_gpa') }}</td>
                <td class="meta-data">: {{ number_format((float)$com_gpa, 2, '.', '') }}</td>
            </tr>
        </tbody>
    </table>
    <!-- Header Section -->

    <br/>

    <!-- Header Section -->
  <table class="table-no-border marksheet">
    <thead>
      <tr>
        <th>{{ __('field_code') }}</th>
        <th class="width2">{{ __('field_subject') }}</th>
        <th>{{ __('field_credit_hour') }}</th>
        <th>{{ __('field_subject_type') }}</th>
        <th>{{ __('field_credits_attempted') }}</th>
        <th>{{ __('field_credits_earned') }}</th>
        <th>{{ __('field_grade_point') }}</th>
        <th>{{ __('field_grade') }}</th>
        <th>{{ __('field_point') }}</th>
      </tr>
    </thead>

        <tbody>
            @php
        $semester_credits = 0;
        $semester_cgpa = 0;
        $semester_credits_earned = 0;
            @endphp
            @foreach( $row->studentEnrolls as $key => $item )
            @if($item->session_id == $session)

            @foreach( $item->subjects as $subject )
            @php
        $creditsAttempted = (float) $subject->credit_hour;
        $semester_credits += $creditsAttempted;
        $subject_grade = null;
        $subjectGradePoint = null;
        $subjectQualityPoints = null;
        $creditsEarned = 0;
        $subjectTypeKey = $subject->subject_type == 0 ? 'subject_type_optional' : ($subject->subject_type == 2 ? 'subject_type_university_requirement' : 'subject_type_compulsory');
        if(isset($item->subjectMarks)){
          foreach($item->subjectMarks as $mark){
            if($mark->subject_id == $subject->id){
              $isPublishTimeReached = (date('Y-m-d', strtotime($mark->publish_date)) == date('Y-m-d') && date('H:i:s', strtotime($mark->publish_time)) <= date('H:i:s')) || date('Y-m-d', strtotime($mark->publish_date)) < date('Y-m-d');
              if($mark->is_visible_to_student && $isPublishTimeReached){
                $marks_per = round($mark->total_marks);
                foreach($grades as $grade){
                  if($marks_per >= $grade->min_mark && $marks_per <= $grade->max_mark){
                    $subjectGradePoint = (float) $grade->point;
                    $subjectQualityPoints = $subjectGradePoint * $creditsAttempted;
                    $semester_cgpa += $subjectQualityPoints;
                    if($subjectGradePoint > 0){
                      $semester_credits_earned += $creditsAttempted;
                      $creditsEarned = $creditsAttempted;
                    }
                    $subject_grade = $grade->title;
                    break;
                  }
                }
              }
              break;
            }
          }
        }
            @endphp
            
            <tr>
                <td>{{ $subject->code }}</td>
        <td>{{ $subject->title }}</td>
    <td>{{ number_format($creditsAttempted, 2, '.', '') }}</td>
    <td>{{ __($subjectTypeKey) }}</td>
    <td>{{ number_format($creditsAttempted, 2, '.', '') }}</td>
    <td>
          @if(!is_null($subjectGradePoint))
            {{ number_format($creditsEarned, 2, '.', '') }}
          @else
            {{ __('field_not_available') }}
          @endif
        </td>
        <td>
          @if(!is_null($subjectGradePoint))
            {{ number_format($subjectGradePoint, 2, '.', '') }}
          @else
            {{ __('field_not_available') }}
          @endif
        </td>
        <td>
          @if($subject_grade)
            {{ $subject_grade }}
          @else
            {{ __('field_not_available') }}
          @endif
        </td>
        <td>
          @if(!is_null($subjectQualityPoints))
            {{ number_format($subjectQualityPoints, 2, '.', '') }}
          @else
            {{ __('field_not_available') }}
          @endif
        </td>
            </tr>
            @endforeach

            @endif
            @endforeach

            <tr class="tfoot">
  <th colspan="2">{{ __('field_term_total') }}:</th>
  <th>{{ number_format((float)$semester_credits, 2, '.', '') }}</th>
  <th></th>
  <th>{{ number_format((float)$semester_credits, 2, '.', '') }}</th>
  <th>{{ number_format((float)$semester_credits_earned, 2, '.', '') }}</th>
  <th></th>
  <th></th>
  <th>{{ number_format((float)$semester_cgpa, 2, '.', '') }}</th>
      </tr>
      @php
        $semesterGpa = $semester_credits > 0 ? $semester_cgpa / $semester_credits : 0;
      @endphp
      <tr class="tfoot">
  <th colspan="4">{{ __('field_semester_gpa') }}:</th>
  <th colspan="2">{{ number_format((float)$semesterGpa, 2, '.', '') }}</th>
  <th></th>
  <th></th>
  <th></th>
            </tr>
        </tbody>
    </table>
    <!-- Header Section -->

    <!-- Header Section -->
    <table class="table-no-border">
        <tbody>
            <tr>
                <td class="temp-footer">
                  <div class="inner">
                    <p>{!! $marksheet->footer_left !!}</p>
                  </div>
                </td>
                <td class="temp-footer">
                  <div class="inner">
                    <p>{!! $marksheet->footer_center !!}</p>
                  </div>
                </td>
                <td class="temp-footer">
                  <div class="inner">
                    <p>{!! $marksheet->footer_right !!}</p>
                  </div>
                </td>
            </tr>
        </tbody>
    </table>
    <!-- Header Section -->
  </div>
</div>

<div class="page-break"></div>
</div>
@endforeach

    <!-- Print Js -->
    <script src="{{ asset('dashboard/plugins/jquery/js/jquery.min.js') }}"></script>
    <script src="{{ asset('dashboard/plugins/print/js/jQuery.print.min.js') }}"></script>

    <script type="text/javascript">
    $( document ).ready(function() {
      "use strict";
      $.print(".printable");
    });
    </script>

</body>
</html>