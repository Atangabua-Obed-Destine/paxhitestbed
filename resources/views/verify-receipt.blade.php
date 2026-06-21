<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title }}</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .container {
            max-width: 800px;
            width: 100%;
            background: white;
            border-radius: 15px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            overflow: hidden;
        }
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }
        .header h1 {
            font-size: 28px;
            margin-bottom: 10px;
        }
        .header p {
            font-size: 14px;
            opacity: 0.9;
        }
        .content {
            padding: 40px;
        }
        .not-found {
            text-align: center;
            padding: 40px 20px;
        }
        .not-found .icon {
            font-size: 80px;
            color: #e74c3c;
            margin-bottom: 20px;
        }
        .not-found h2 {
            color: #2c3e50;
            margin-bottom: 15px;
        }
        .not-found p {
            color: #7f8c8d;
            margin-bottom: 30px;
        }
        .verified {
            text-align: center;
            margin-bottom: 30px;
        }
        .verified .icon {
            font-size: 80px;
            color: #27ae60;
            margin-bottom: 20px;
        }
        .verified h2 {
            color: #27ae60;
            margin-bottom: 10px;
            font-size: 28px;
        }
        .verified p {
            color: #7f8c8d;
        }
        .receipt-info {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 25px;
            margin-top: 20px;
        }
        .info-row {
            display: flex;
            justify-content: space-between;
            padding: 12px 0;
            border-bottom: 1px solid #e0e0e0;
        }
        .info-row:last-child {
            border-bottom: none;
        }
        .info-label {
            font-weight: 600;
            color: #555;
            flex: 0 0 40%;
        }
        .info-value {
            color: #333;
            flex: 0 0 60%;
            text-align: right;
        }
        .payment-status {
            display: inline-block;
            padding: 6px 15px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 600;
        }
        .status-paid {
            background-color: #d4edda;
            color: #155724;
        }
        .status-partial {
            background-color: #fff3cd;
            color: #856404;
        }
        .status-unpaid {
            background-color: #f8d7da;
            color: #721c24;
        }
        .amount-box {
            background: white;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            padding: 20px;
            margin: 20px 0;
            text-align: center;
        }
        .amount-label {
            font-size: 14px;
            color: #7f8c8d;
            margin-bottom: 5px;
        }
        .amount-value {
            font-size: 32px;
            font-weight: bold;
            color: #2c3e50;
        }
        .footer {
            background: #f8f9fa;
            padding: 20px;
            text-align: center;
            color: #7f8c8d;
            font-size: 13px;
        }
        .btn {
            display: inline-block;
            padding: 12px 30px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            text-decoration: none;
            border-radius: 25px;
            font-weight: 600;
            transition: transform 0.3s ease;
        }
        .btn:hover {
            transform: translateY(-2px);
        }
        .payment-plan-box {
            background: #e8f5e9;
            border-left: 4px solid #4caf50;
            padding: 15px;
            margin: 15px 0;
            border-radius: 5px;
        }
        .payment-plan-box h4 {
            color: #2e7d32;
            margin-bottom: 10px;
        }
        .resit-details {
            background: #fff5f5;
            border-left: 4px solid #dc3545;
            border-radius: 5px;
            padding: 20px;
            margin: 20px 0;
        }
        .resit-details h4 {
            color: #dc3545;
            margin-bottom: 15px;
            font-size: 16px;
        }
        .resit-details .info-row {
            padding: 8px 0;
        }
        .marks-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
            font-size: 13px;
        }
        .marks-table th {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            padding: 8px 6px;
            text-align: center;
            font-weight: 700;
            color: #333;
            font-size: 12px;
        }
        .marks-table td {
            border: 1px solid #dee2e6;
            padding: 8px 6px;
            text-align: center;
            color: #333;
        }
        .result-pass {
            background: #d4edda;
            color: #155724;
            font-weight: 700;
            padding: 3px 10px;
            border-radius: 12px;
            font-size: 11px;
        }
        .result-fail {
            background: #f8d7da;
            color: #721c24;
            font-weight: 700;
            padding: 3px 10px;
            border-radius: 12px;
            font-size: 11px;
        }
        @media print {
            body {
                background: white;
            }
            .container {
                box-shadow: none;
            }
            .btn {
                display: none;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🧾 {{ __('receipt_verification') }}</h1>
            <p>{{ __('verify_payment_receipt_authenticity') }}</p>
        </div>

        <div class="content">
            @if(!$receipt_found)
                <div class="not-found">
                    <div class="icon">❌</div>
                    <h2>{{ __('receipt_not_found') }}</h2>
                    <p>{{ __('receipt_number') }}: <strong>{{ $receipt_number }}</strong></p>
                    <p>{{ __('receipt_verification_failed_message') }}</p>
                </div>
            @else
                <div class="verified">
                    <div class="icon">✅</div>
                    <h2>{{ __('receipt_verified') }}</h2>
                    <p>{{ __('this_receipt_is_authentic') }}</p>
                </div>

                <div class="receipt-info">
                    <div class="info-row">
                        <span class="info-label">{{ __('field_receipt') }}:</span>
                        <span class="info-value"><strong>{{ $print->prefix ?? '' }}{{ str_pad($row->id, 6, '0', STR_PAD_LEFT) }}</strong></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">{{ __('field_matricule') }}:</span>
                        <span class="info-value">{{ $row->studentEnroll->matricule ?? $row->studentEnroll->student->student_id ?? '' }}</span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">{{ __('field_student_name') }}:</span>
                        <span class="info-value">{{ $row->studentEnroll->student->first_name ?? '' }} {{ $row->studentEnroll->student->last_name ?? '' }}</span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">{{ __('field_program') }}:</span>
                        <span class="info-value">{{ $row->studentEnroll->program->title ?? '' }}</span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">{{ __('field_fees_type') }}:</span>
                        <span class="info-value">{{ $row->category->title ?? '' }}</span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">{{ __('field_pay_date') }}:</span>
                        <span class="info-value">
                            @if(isset($setting->date_format))
                            {{ date($setting->date_format, strtotime($row->pay_date)) }}
                            @else
                            {{ date("d M Y", strtotime($row->pay_date)) }}
                            @endif
                        </span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">{{ __('field_payment_method') }}:</span>
                        <span class="info-value">
                            @if( $row->payment_method == 1 )
                            {{ __('payment_method_card') }}
                            @elseif( $row->payment_method == 2 )
                            {{ __('payment_method_cash') }}
                            @elseif( $row->payment_method == 3 )
                            {{ __('payment_method_cheque') }}
                            @elseif( $row->payment_method == 4 )
                            {{ __('payment_method_bank') }}
                            @elseif( $row->payment_method == 5 )
                            {{ __('payment_method_e_wallet') }}
                            @elseif( $row->payment_method == 6 )
                            {{ __('PayPal') }}
                            @elseif( $row->payment_method == 7 )
                            {{ __('Stripe') }}
                            @elseif( $row->payment_method == 8 )
                            {{ __('RazorPay') }}
                            @elseif( $row->payment_method == 9 )
                            {{ __('PayStack') }}
                            @elseif( $row->payment_method == 10 )
                            {{ __('Flutterwave') }}
                            @endif
                        </span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">{{ __('field_status') }}:</span>
                        <span class="info-value">
                            @if($row->status == 1)
                            <span class="payment-status status-paid">{{ __('status_paid') }}</span>
                            @elseif($row->status == 2)
                            <span class="payment-status status-partial">{{ __('status_partial_paid') }}</span>
                            @else
                            <span class="payment-status status-unpaid">{{ __('status_unpaid') }}</span>
                            @endif
                        </span>
                    </div>
                </div>

                <div class="amount-box">
                    <div class="amount-label">{{ __('field_total_amount') }}</div>
                    <div class="amount-value">
                        @if(isset($setting->decimal_place))
                        {{ number_format((float)$row->total_amount, $setting->decimal_place, '.', '') }} 
                        @else
                        {{ number_format((float)$row->total_amount, 2, '.', '') }} 
                        @endif 
                        {!! $setting->currency_symbol !!}
                    </div>
                </div>

                <div class="receipt-info">
                    <div class="info-row">
                        <span class="info-label">{{ __('field_fee') }}:</span>
                        <span class="info-value">
                            @if(isset($setting->decimal_place))
                            {{ number_format((float)$row->fee_amount, $setting->decimal_place, '.', '') }} 
                            @else
                            {{ number_format((float)$row->fee_amount, 2, '.', '') }} 
                            @endif 
                            {!! $setting->currency_symbol !!}
                        </span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">{{ __('field_discount') }}:</span>
                        <span class="info-value">
                            @if(isset($setting->decimal_place))
                            {{ number_format((float)$row->discount_amount, $setting->decimal_place, '.', '') }} 
                            @else
                            {{ number_format((float)$row->discount_amount, 2, '.', '') }} 
                            @endif 
                            {!! $setting->currency_symbol !!}
                        </span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">{{ __('field_fine_amount') }}:</span>
                        <span class="info-value">
                            @if(isset($setting->decimal_place))
                            {{ number_format((float)$row->fine_amount, $setting->decimal_place, '.', '') }} 
                            @else
                            {{ number_format((float)$row->fine_amount, 2, '.', '') }} 
                            @endif 
                            {!! $setting->currency_symbol !!}
                        </span>
                    </div>
                    <div class="info-row">
                        <span class="info-label"><strong>{{ __('field_amount_paid') }}:</strong></span>
                        <span class="info-value">
                            <strong>
                            @if(isset($setting->decimal_place))
                            {{ number_format((float)$row->paid_amount, $setting->decimal_place, '.', '') }} 
                            @else
                            {{ number_format((float)$row->paid_amount, 2, '.', '') }} 
                            @endif 
                            {!! $setting->currency_symbol !!}
                            </strong>
                        </span>
                    </div>
                    <div class="info-row">
                        <span class="info-label"><strong>{{ __('field_remaining_balance') }}:</strong></span>
                        <span class="info-value">
                            <strong style="color: {{ $row->remaining_balance > 0 ? '#856404' : '#155724' }};">
                            @if(isset($setting->decimal_place))
                            {{ number_format((float)$row->remaining_balance, $setting->decimal_place, '.', '') }} 
                            @else
                            {{ number_format((float)$row->remaining_balance, 2, '.', '') }} 
                            @endif 
                            {!! $setting->currency_symbol !!}
                            </strong>
                        </span>
                    </div>
                </div>

                @if($row->payment_plan_id && $row->paymentPlan)
                <div class="payment-plan-box">
                    <h4>📋 {{ __('payment_plan') }}</h4>
                    <p><strong>{{ __('field_status') }}:</strong> 
                        @if($row->paymentPlan->status == 'active')
                        {{ __('status_active') }}
                        @elseif($row->paymentPlan->status == 'completed')
                        {{ __('status_completed') }}
                        @elseif($row->paymentPlan->status == 'cancelled')
                        {{ __('status_cancelled') }}
                        @endif
                    </p>
                    <p><strong>{{ __('total_installments') }}:</strong> {{ $row->paymentPlan->installments->count() }}</p>
                    <p><strong>{{ __('paid_installments') }}:</strong> {{ $row->paymentPlan->installments->where('status', 'paid')->count() }}</p>
                </div>
                @endif

                @if($row->note)
                <div class="receipt-info" style="margin-top: 20px;">
                    <div class="info-row">
                        <span class="info-label">{{ __('field_note') }}:</span>
                        <span class="info-value">{{ $row->note }}</span>
                    </div>
                </div>
                @endif

                @if($row->resitRequest && $row->resitRequest->subject)
                @php
                    $rr = $row->resitRequest;
                    $subject = $rr->subject;
                    $marking = \App\Models\SubjectMarking::where('student_enroll_id', $rr->student_enroll_id)
                        ->where('subject_id', $rr->subject_id)->first();
                    $passingMarks = $subject->passing_marks ?? 50;

                    $storedAttendance = $marking ? (float)($marking->attendances ?? 0) : 0;
                    $storedAssignment = $marking ? (float)($marking->assignments ?? 0) : 0;
                    $storedActivity   = $marking ? (float)($marking->activities ?? 0) : 0;
                    $attendanceMarks  = round($storedAttendance, 2);

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
                <div class="resit-details">
                    <h4>&#8635; {{ __('Resit Course Details') }}</h4>
                    <div class="info-row">
                        <span class="info-label">{{ __('field_subject') }}:</span>
                        <span class="info-value">{{ $subject->title ?? $subject->subject_name ?? 'N/A' }} ({{ $subject->code }})</span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">{{ __('field_credit_hour') }}:</span>
                        <span class="info-value">{{ $subject->credit_hour }}</span>
                    </div>
                    @if($rr->session)
                    <div class="info-row">
                        <span class="info-label">{{ __('Original Session') }}:</span>
                        <span class="info-value">{{ $rr->session->title ?? '-' }}</span>
                    </div>
                    @endif
                    @if($rr->resitSession)
                    <div class="info-row">
                        <span class="info-label">{{ __('Resit Session') }}:</span>
                        <span class="info-value">{{ $rr->resitSession->title ?? '-' }}@if($rr->resitSemester) &mdash; {{ $rr->resitSemester->title }}@endif</span>
                    </div>
                    @endif

                    <table class="marks-table">
                        <thead>
                            <tr>
                                <th>Att</th>
                                <th>CA</th>
                                <th>Exam</th>
                                <th>Total</th>
                                <th>Grade</th>
                                <th>Pass Mark</th>
                                <th>Deficit</th>
                                <th>Result</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>{{ $attMarks }}</td>
                                <td>{{ $caMarksVal }}</td>
                                <td>{{ $exMarks }}</td>
                                <td><strong>{{ $totalMarks }}/{{ $subject->total_marks ?? 100 }}</strong></td>
                                <td><strong>{{ $resitGrade ? $resitGrade->title : '-' }}</strong></td>
                                <td>{{ $passingMarks }}</td>
                                <td><strong>{{ $deficit > 0 ? '-' . $deficit : '0' }}</strong></td>
                                <td>
                                    @if($passed)
                                    <span class="result-pass">PASS</span>
                                    @else
                                    <span class="result-fail">FAIL</span>
                                    @endif
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                @endif
            @endif
        </div>

        <div class="footer">
            <p>{{ __('generated_on') }}: {{ date('d M Y, h:i A') }}</p>
            <p style="margin-top: 10px;">
                <a href="{{ url('/') }}" class="btn">{{ __('back_to_home') }}</a>
            </p>
        </div>
    </div>
</body>
</html>
