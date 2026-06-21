<!DOCTYPE html>
<html lang="en-US">
<head>
    <meta charset="utf-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <meta name="viewport" content="width=device-width,maximum-scale=1.0">
    <title>{{ $title }}</title>
    
    <style type="text/css" media="print">
    @media print {
      @page { size: A4 portrait; margin: 10px auto; }   
      @page :footer { display: none }
      @page :header { display: none }
      body { margin: 15mm 15mm 15mm 15mm; }
      table, tbody {page-break-before: auto;}
    }
    table, img, svg {
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
    <link rel="stylesheet" type="text/css" href="{{ asset('dashboard/css/prints/receipt.css') }}" media="screen, print">

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
    .table-no-border.receipt thead th:nth-child(1), 
    .table-no-border.receipt td:nth-child(1), 
    .table-no-border.receipt .tfoot th:nth-child(1) {
      text-align: right;
    }
    .template-container .table-no-border tr td.temp-logo {
      float: none;
    }
    .table-no-border.receipt .exam-title {
      text-align: right !important;
    }
    </style>
    @endif
</head>
<body>

<div class="template-container printable" style="width: {{ $print->width }}; height: {{ $print->height }};">
  <div class="template-inner">
    <!-- Header Section -->
    <table class="table-no-border">
        <tbody>
            <tr>
                <td class="temp-logo">
                  <div class="inner">
                    @if(is_file('uploads/'.$path.'/'.$print->logo_left))
                    <img src="{{ asset('uploads/'.$path.'/'.$print->logo_left) }}" alt="Logo">
                    @endif
                  </div>
                </td>
                <td class="temp-title">
                  <div class="inner">
                    <h2>{{ $print->title }}</h2>
                  </div>
                </td>
                <td class="temp-logo last">
                  <div class="inner">
                    @if(is_file('uploads/'.$path.'/'.$print->logo_right))
                    <img src="{{ asset('uploads/'.$path.'/'.$print->logo_right) }}" alt="Logo">
                    @endif
                  </div>
                </td>
            </tr>
        </tbody>
    </table>
    <!-- Header Section -->

    @php
        $enroll = \App\Models\Student::enroll($row->studentEnroll->student->id);

        // Payment method label
        $paymentMethods = [
            1 => __('payment_method_card'),
            2 => __('payment_method_cash'),
            3 => __('payment_method_cheque'),
            4 => __('payment_method_bank'),
            5 => __('payment_method_e_wallet'),
            6 => __('PayPal'),
            7 => __('Stripe'),
            8 => __('RazorPay'),
            9 => __('PayStack'),
            10 => __('Flutterwave'),
        ];
        $payMethodLabel = $paymentMethods[$row->payment_method] ?? '-';
        $dateFormat = $setting->date_format ?? 'Y-m-d';
    @endphp

    <!-- Receipt Sub-Title -->
    <table class="table-no-border" style="margin-top: 5px;">
        <tbody>
            <tr>
                <td style="text-align: center; padding: 6px 0 4px 0 !important; font-size: 15px; font-weight: 700; color: #101010; text-transform: uppercase; letter-spacing: 2px; border-bottom: 2px solid #101010;">
                    {{ __('Fees Receipt') }}
                </td>
            </tr>
        </tbody>
    </table>

    <!-- Receipt Number -->
    <table class="table-no-border" style="margin-top: 4px;">
        <tbody>
            <tr>
                <td style="text-align: right; padding: 3px 0 !important; font-size: 12px; font-weight: 600; color: #101010;">
                    {{ __('field_receipt') }} #: {{ $print->prefix ?? '' }}{{ str_pad($row->id, 6, '0', STR_PAD_LEFT) }}
                </td>
            </tr>
        </tbody>
    </table>

    <!-- Student Information -->
    <table class="table-no-border top-meta-table">
        <tbody>
            <tr>
                <td class="meta-data">{{ __('field_matricule') }}</td>
                <td class="meta-data value width2">: {{ $row->studentEnroll->matricule ?? $row->studentEnroll->student->student_id ?? '' }}</td>
                <td class="meta-data">{{ __('field_program') }}</td>
                <td class="meta-data value">: {{ $row->studentEnroll->program->shortcode ?? '' }}</td>
            </tr>
            <tr>
                <td class="meta-data">{{ __('field_name') }}</td>
                <td class="meta-data value width2">: {{ $row->studentEnroll->student->first_name ?? '' }} {{ $row->studentEnroll->student->last_name ?? '' }}</td>
                <td class="meta-data">{{ __('field_session') }}</td>
                <td class="meta-data value">: {{ $enroll->session->title ?? '' }}</td>
            </tr>
            <tr>
                <td class="meta-data">{{ __('field_semester') }}</td>
                <td class="meta-data value width2">: {{ $enroll->semester->title ?? '' }} ({{ $row->studentEnroll->section->title ?? '' }})</td>
                <td class="meta-data">{{ __('field_batch') }}</td>
                <td class="meta-data value">: {{ $row->studentEnroll->student->batch->title ?? '' }}</td>
            </tr>
        </tbody>
    </table>

    <!-- Payment Information -->
    <table class="table-no-border" style="margin-top: 3px; border-top: 1px solid #ccc;">
        <tbody>
            <tr>
                <td style="float: left; width: 20%; padding: 4px 0 0 !important; font-size: 12px; font-weight: 600; color: #101010;">{{ __('field_due_date') }}</td>
                <td style="float: left; width: 40%; padding: 4px 0 0 !important; font-size: 12px; font-style: italic; color: #101010;">: {{ date($dateFormat, strtotime($row->due_date)) }}</td>
                <td style="float: left; width: 20%; padding: 4px 0 0 !important; font-size: 12px; font-weight: 600; color: #101010;">{{ __('field_pay_date') }}</td>
                <td style="float: left; width: 20%; padding: 4px 0 0 !important; font-size: 12px; font-style: italic; color: #101010;">: {{ date($dateFormat, strtotime($row->pay_date)) }}</td>
            </tr>
            <tr>
                <td style="float: left; width: 20%; padding: 4px 0 0 !important; font-size: 12px; font-weight: 600; color: #101010;">{{ __('field_payment_method') }}</td>
                <td style="float: left; width: 40%; padding: 4px 0 0 !important; font-size: 12px; font-style: italic; color: #101010;">: {{ $payMethodLabel }}</td>
                <td style="float: left; width: 20%; padding: 4px 0 0 !important; font-size: 12px; font-weight: 600; color: #101010;">&nbsp;</td>
                <td style="float: left; width: 20%; padding: 4px 0 0 !important; font-size: 12px; font-style: italic; color: #101010;">&nbsp;</td>
            </tr>
            @if($row->note)
            <tr>
                <td style="float: left; width: 20%; padding: 4px 0 0 !important; font-size: 12px; font-weight: 600; color: #101010;">{{ __('field_note') }}</td>
                <td colspan="3" style="float: left; width: 80%; padding: 4px 0 0 !important; font-size: 12px; font-style: italic; color: #101010;">: {{ $row->note }}</td>
            </tr>
            @endif
        </tbody>
    </table>

    <!-- Header Section -->
    <table class="table-no-border receipt">
        <thead>
            <tr>
                <th class="width2">{{ __('field_fees_type') }}</th>
                <th>{{ __('field_amount') }}</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td colspan="2" class="exam-title">{{ $row->category->title ?? '' }}</td>
            </tr>
            <tr class="border-bottom">
                <td>{{ __('field_fee') }}</td>
                <td>
                    @if(isset($setting->decimal_place))
                    {{ number_format((float)$row->fee_amount, $setting->decimal_place, '.', '') }} 
                    @else
                    {{ number_format((float)$row->fee_amount, 2, '.', '') }} 
                    @endif 
                    {!! $setting->currency_symbol !!}
                </td>
            </tr>
            <tr class="border-bottom">
                <td>{{ __('field_discount') }}</td>
                <td>- 
                    @if(isset($setting->decimal_place))
                    {{ number_format((float)$row->discount_amount, $setting->decimal_place, '.', '') }} 
                    @else
                    {{ number_format((float)$row->discount_amount, 2, '.', '') }} 
                    @endif 
                    {!! $setting->currency_symbol !!}
                </td>
            </tr>
            <tr class="border-bottom">
                <td>{{ __('field_fine_amount') }}</td>
                <td>+ 
                    @if(isset($setting->decimal_place))
                    {{ number_format((float)$row->fine_amount, $setting->decimal_place, '.', '') }} 
                    @else
                    {{ number_format((float)$row->fine_amount, 2, '.', '') }} 
                    @endif 
                    {!! $setting->currency_symbol !!}
                </td>
            </tr>
            <tr class="tfoot">
                <th>{{ __('field_net_amount') }}:</th>
                <th>= 
                    @if(isset($setting->decimal_place))
                    {{ number_format((float)$row->total_amount, $setting->decimal_place, '.', '') }} 
                    @else
                    {{ number_format((float)$row->total_amount, 2, '.', '') }} 
                    @endif 
                    {!! $setting->currency_symbol !!}
                </th>
            </tr>
            @php
                $display_paid = min((float)$row->paid_amount, (float)$row->total_amount);
                $display_balance = max(0, $row->remaining_balance);
            @endphp
            <tr class="border-bottom">
                <td><strong>{{ __('field_amount_paid') }}</strong></td>
                <td><strong>
                    @if(isset($setting->decimal_place))
                    {{ number_format($display_paid, $setting->decimal_place, '.', '') }} 
                    @else
                    {{ number_format($display_paid, 2, '.', '') }} 
                    @endif 
                    {!! $setting->currency_symbol !!}
                </strong></td>
            </tr>
            <tr class="tfoot" style="background-color: {{ $display_balance > 0 ? '#fff3cd' : '#d4edda' }};">
                <th style="color: {{ $display_balance > 0 ? '#856404' : '#155724' }};">{{ __('field_remaining_balance') }}:</th>
                <th style="color: {{ $display_balance > 0 ? '#856404' : '#155724' }};">= 
                    @if(isset($setting->decimal_place))
                    {{ number_format((float)$display_balance, $setting->decimal_place, '.', '') }} 
                    @else
                    {{ number_format((float)$display_balance, 2, '.', '') }} 
                    @endif 
                    {!! $setting->currency_symbol !!}
                </th>
            </tr>
            <tr>
                <td colspan="2" style="text-align: center; padding-top: 15px;">
                    @if($row->status == 1)
                    <span style="background-color: #28a745; color: white; padding: 5px 15px; border-radius: 3px; font-weight: bold;">{{ __('status_paid') }}</span>
                    @elseif($row->status == 2)
                    <span style="background-color: #ffc107; color: #000; padding: 5px 15px; border-radius: 3px; font-weight: bold;">{{ __('status_partial_paid') }}</span>
                    @endif
                    
                    @if($row->payment_plan_id && $row->paymentPlan)
                    <br><span style="background-color: #17a2b8; color: white; padding: 5px 15px; border-radius: 3px; font-weight: bold; margin-top: 5px; display: inline-block;">
                        {{ __('payment_plan') }} - 
                        @if($row->paymentPlan->status == 'active')
                        {{ __('status_active') }}
                        @elseif($row->paymentPlan->status == 'completed')
                        {{ __('status_completed') }}
                        @elseif($row->paymentPlan->status == 'cancelled')
                        {{ __('status_cancelled') }}
                        @endif
                    </span>
                    @endif
                </td>
            </tr>
        </tbody>
    </table>
    <!-- Header Section -->

    @if($row->resitRequest && $row->resitRequest->subject)
    {{-- ── Resit Course Details ── --}}
    @php
        $rr = $row->resitRequest;
        $subject = $rr->subject;
        $marking = \App\Models\SubjectMarking::where('student_enroll_id', $rr->student_enroll_id)
            ->where('subject_id', $rr->subject_id)->first();
        $passingMarks = $subject->passing_marks ?? 50;

        // Stored SubjectMarking fields
        $storedAttendance = $marking ? (float)($marking->attendances ?? 0) : 0;
        $storedAssignment = $marking ? (float)($marking->assignments ?? 0) : 0;
        $storedActivity   = $marking ? (float)($marking->activities ?? 0) : 0;
        $attendanceMarks  = round($storedAttendance, 2);

        // Query Exam records and split by ExamType.is_final
        $caExamMarks = 0; $finalExamMarks = 0;
        $rawCaMarks = 0; $rawFinalMarks = 0;
        $hasZeroContribution = false;

        $allExams = \App\Models\Exam::where('student_enroll_id', $rr->student_enroll_id)
            ->where('subject_id', $rr->subject_id)->with('type')->get();

        foreach ($allExams as $exam) {
            $examContribution = (float)$exam->contribution;
            $isFinalType = $exam->type && $exam->type->is_final;
            if ($exam->attendance == 1 && $exam->achieve_marks !== null) {
                if ($isFinalType) { $rawFinalMarks += (float)$exam->achieve_marks; }
                else { $rawCaMarks += (float)$exam->achieve_marks; }
                if ($examContribution <= 0) { $hasZeroContribution = true; }
                if ($examContribution > 0 && $exam->marks > 0) {
                    $contributedMarks = (($exam->achieve_marks / $exam->marks) * 100 / 100) * $examContribution;
                    if ($isFinalType) { $finalExamMarks += $contributedMarks; }
                    else { $caExamMarks += $contributedMarks; }
                }
            }
        }

        $effectiveCaMarks = ($caExamMarks == 0 && $rawCaMarks > 0 && $hasZeroContribution) ? $rawCaMarks : $caExamMarks;
        $effectiveFinalMarks = ($finalExamMarks == 0 && $rawFinalMarks > 0 && $hasZeroContribution) ? $rawFinalMarks : $finalExamMarks;

        $totalCA      = round($attendanceMarks + $storedAssignment + $storedActivity + $effectiveCaMarks, 2);
        $examMarksVal = round($effectiveFinalMarks, 2);
        $totalMarks   = round($totalCA + $examMarksVal, 2);

        $attMarks   = round($attendanceMarks, 2);
        $caMarksVal = round($totalCA - $attendanceMarks, 2);
        $exMarks    = round($examMarksVal, 2);

        $resitGrade = null;
        $grades = \App\Models\Grade::where('status', 1)->orderBy('min_mark', 'asc')->get();
        foreach ($grades as $g) {
            if ($totalMarks >= $g->min_mark && $totalMarks <= $g->max_mark) { $resitGrade = $g; break; }
        }
        $passed = $totalMarks >= $passingMarks;
        $deficit = $passed ? 0 : round($passingMarks - $totalMarks, 1);
    @endphp

    <!-- Resit Course Details Section -->
    <table class="table-no-border receipt" style="margin-top: 15px;">
        <thead>
            <tr>
                <th colspan="2" style="text-align: center; border-top: 2px solid #101010 !important; border-bottom: 2px solid #101010 !important; padding: 6px 0 !important; font-size: 13px; text-transform: uppercase; letter-spacing: 1px;">
                    &#8635; {{ __('Resit Course Details') }}
                </th>
            </tr>
        </thead>
        <tbody>
            <tr class="border-bottom">
                <td style="text-align: left; font-weight: 600; width: 35%;">{{ __('field_subject') }}</td>
                <td style="text-align: left;">{{ $subject->title ?? $subject->subject_name ?? 'N/A' }} ({{ $subject->code }})</td>
            </tr>
            <tr class="border-bottom">
                <td style="text-align: left; font-weight: 600;">{{ __('field_credit_hour') }}</td>
                <td style="text-align: left;">{{ $subject->credit_hour }}</td>
            </tr>
            @if($rr->session)
            <tr class="border-bottom">
                <td style="text-align: left; font-weight: 600;">{{ __('Original Session') }}</td>
                <td style="text-align: left;">{{ $rr->session->title ?? '-' }}</td>
            </tr>
            @endif
            @if($rr->resitSession)
            <tr class="border-bottom">
                <td style="text-align: left; font-weight: 600;">{{ __('Resit Session') }}</td>
                <td style="text-align: left;">{{ $rr->resitSession->title ?? '-' }}@if($rr->resitSemester) &mdash; {{ $rr->resitSemester->title }}@endif</td>
            </tr>
            @endif
        </tbody>
    </table>

    <!-- Marks Breakdown -->
    <table class="table-no-border" style="margin-top: 8px; border-collapse: collapse;">
        <thead>
            <tr>
                <th style="width: 12.5%; text-align: center; padding: 5px 2px; border-top: 2px solid #101010; border-bottom: 2px solid #101010; font-size: 11px; font-weight: 700;">Att</th>
                <th style="width: 12.5%; text-align: center; padding: 5px 2px; border-top: 2px solid #101010; border-bottom: 2px solid #101010; font-size: 11px; font-weight: 700;">CA</th>
                <th style="width: 12.5%; text-align: center; padding: 5px 2px; border-top: 2px solid #101010; border-bottom: 2px solid #101010; font-size: 11px; font-weight: 700;">Exam</th>
                <th style="width: 12.5%; text-align: center; padding: 5px 2px; border-top: 2px solid #101010; border-bottom: 2px solid #101010; font-size: 11px; font-weight: 700;">Total</th>
                <th style="width: 12.5%; text-align: center; padding: 5px 2px; border-top: 2px solid #101010; border-bottom: 2px solid #101010; font-size: 11px; font-weight: 700;">Grade</th>
                <th style="width: 12.5%; text-align: center; padding: 5px 2px; border-top: 2px solid #101010; border-bottom: 2px solid #101010; font-size: 11px; font-weight: 700;">Pass Mark</th>
                <th style="width: 12.5%; text-align: center; padding: 5px 2px; border-top: 2px solid #101010; border-bottom: 2px solid #101010; font-size: 11px; font-weight: 700;">Deficit</th>
                <th style="width: 12.5%; text-align: center; padding: 5px 2px; border-top: 2px solid #101010; border-bottom: 2px solid #101010; font-size: 11px; font-weight: 700;">Result</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td style="text-align: center; padding: 6px 2px; font-size: 12px; border-bottom: 1px solid #101010;">{{ $attMarks }}</td>
                <td style="text-align: center; padding: 6px 2px; font-size: 12px; border-bottom: 1px solid #101010;">{{ $caMarksVal }}</td>
                <td style="text-align: center; padding: 6px 2px; font-size: 12px; border-bottom: 1px solid #101010;">{{ $exMarks }}</td>
                <td style="text-align: center; padding: 6px 2px; font-size: 12px; font-weight: 700; border-bottom: 1px solid #101010;">{{ $totalMarks }}/{{ $subject->total_marks ?? 100 }}</td>
                <td style="text-align: center; padding: 6px 2px; font-size: 12px; font-weight: 700; border-bottom: 1px solid #101010;">{{ $resitGrade ? $resitGrade->title : '-' }}</td>
                <td style="text-align: center; padding: 6px 2px; font-size: 12px; border-bottom: 1px solid #101010;">{{ $passingMarks }}</td>
                <td style="text-align: center; padding: 6px 2px; font-size: 12px; font-weight: 700; border-bottom: 1px solid #101010;">{{ $deficit > 0 ? '-' . $deficit : '0' }}</td>
                <td style="text-align: center; padding: 6px 2px; font-size: 12px; font-weight: 700; border-bottom: 2px solid #101010;">{{ $passed ? 'PASS' : 'FAIL' }}</td>
            </tr>
        </tbody>
    </table>
    @endif

    @if($row->payment_plan_id && $row->paymentPlan)
    <!-- Payment Plan Details Section -->
    <table class="table-no-border receipt" style="margin-top: 20px;">
        <thead>
            <tr>
                <th colspan="4" style="text-align: center; background-color: #17a2b8; color: white; padding: 10px;">
                    {{ __('payment_plan_details') }}
                </th>
            </tr>
            <tr>
                <th style="width: 10%;">{{ __('field_installment_number') }}</th>
                <th style="width: 30%;">{{ __('field_due_date') }}</th>
                <th style="width: 30%;">{{ __('field_amount') }}</th>
                <th style="width: 30%;">{{ __('field_status') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach($row->paymentPlan->installments->sortBy('due_date') as $index => $installment)
            <tr class="border-bottom">
                <td style="text-align: center;">{{ $index + 1 }}</td>
                <td>
                    @if(isset($setting->date_format))
                    {{ date($setting->date_format, strtotime($installment->due_date)) }}
                    @else
                    {{ date("Y-m-d", strtotime($installment->due_date)) }}
                    @endif
                </td>
                <td>
                    @if(isset($setting->decimal_place))
                    {{ number_format((float)$installment->amount, $setting->decimal_place, '.', '') }} 
                    @else
                    {{ number_format((float)$installment->amount, 2, '.', '') }} 
                    @endif 
                    {!! $setting->currency_symbol !!}
                </td>
                <td>
                    @if($installment->status == 'paid')
                    <span style="background-color: #28a745; color: white; padding: 2px 8px; border-radius: 3px; font-size: 11px;">{{ __('status_paid') }}</span>
                    @if($installment->payments->count() > 0)
                    <br><small style="font-size: 10px;">
                        {{ __('field_paid_on') }}: 
                        @if(isset($setting->date_format))
                        {{ date($setting->date_format, strtotime($installment->payments->first()->payment_date)) }}
                        @else
                        {{ date("Y-m-d", strtotime($installment->payments->first()->payment_date)) }}
                        @endif
                    </small>
                    @endif
                    @elseif($installment->status == 'partial')
                    <span style="background-color: #ffc107; color: #000; padding: 2px 8px; border-radius: 3px; font-size: 11px;">{{ __('status_partial') }}</span>
                    <br><small style="font-size: 10px;">
                        {{ __('field_paid') }}: 
                        @if(isset($setting->decimal_place))
                        {{ number_format((float)$installment->paid_amount, $setting->decimal_place, '.', '') }} 
                        @else
                        {{ number_format((float)$installment->paid_amount, 2, '.', '') }} 
                        @endif 
                        {!! $setting->currency_symbol !!}
                    </small>
                    @else
                    <span style="background-color: #6c757d; color: white; padding: 2px 8px; border-radius: 3px; font-size: 11px;">{{ __('status_pending') }}</span>
                    @endif
                </td>
            </tr>
            @endforeach
            <tr class="tfoot">
                <th colspan="2">{{ __('payment_plan_progress') }}:</th>
                <th colspan="2">
                    @php
                    $paidInstallments = $row->paymentPlan->installments->where('status', 'paid')->count();
                    $totalInstallments = $row->paymentPlan->installments->count();
                    @endphp
                    {{ $paidInstallments }} {{ __('of') }} {{ $totalInstallments }} {{ __('installments_paid') }}
                    ({{ $totalInstallments > 0 ? round(($paidInstallments / $totalInstallments) * 100) : 0 }}%)
                </th>
            </tr>
        </tbody>
    </table>
    <!-- Payment Plan Details Section -->
    @endif

    <!-- Header Section -->
    <table class="table-no-border">
        <tbody>
            <tr>
                <td class="temp-footer">
                  <div class="inner">
                    <p>{!! $print->footer_left !!}</p>
                  </div>
                </td>
                <td class="temp-footer">
                  <div class="inner">
                    <p>{!! $print->footer_center !!}</p>
                    <!-- QR Code for Verification -->
                    <div style="text-align: center; margin-top: 15px;">
                        {!! QrCode::size(120)->margin(1)->generate(url('verify-receipt/'.$row->id)) !!}
                        <p style="font-size: 9px; margin-top: 5px; color: #666;">{{ __('scan_to_verify') }}</p>
                    </div>
                  </div>
                </td>
                <td class="temp-footer last">
                  <div class="inner">
                    <p>{!! $print->footer_right !!}</p>
                  </div>
                </td>
            </tr>
        </tbody>
    </table>
    <!-- Header Section -->
  </div>
</div>

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