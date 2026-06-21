@extends('student.layouts.master')
@section('title', $title)
@section('content')

<!-- Start Content-->
<div class="main-body">
    <div class="page-wrapper">
        <!-- [ Main Content ] start -->
        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header">
                        <h5>
                            <i class="fas fa-file-invoice"></i> {{ $title }}
                            <a href="{{ route('student.fees.index') }}" class="btn btn-sm btn-primary float-right">
                                <i class="fas fa-arrow-left"></i> Back to Fees
                            </a>
                        </h5>
                    </div>
                    <div class="card-block">
                        <!-- Payment Status Alert -->
                        <div class="alert alert-{{ $payment->status == 'approved' ? 'success' : ($payment->status == 'rejected' ? 'danger' : 'warning') }}">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h5 class="mb-1">
                                        <i class="fas fa-{{ $payment->status == 'approved' ? 'check-circle' : ($payment->status == 'rejected' ? 'times-circle' : 'clock') }}"></i>
                                        Payment Status: <strong>{{ ucfirst($payment->status) }}</strong>
                                    </h5>
                                    @if($payment->status == 'approved')
                                        <p class="mb-0">Your payment has been verified and approved by admin.</p>
                                    @elseif($payment->status == 'rejected')
                                        <p class="mb-0">Your payment was rejected. Please contact admin for details.</p>
                                    @else
                                        <p class="mb-0">Your payment is pending verification by admin.</p>
                                    @endif
                                </div>
                                <div class="text-right">
                                    <button class="btn btn-{{ $payment->status == 'approved' ? 'success' : ($payment->status == 'rejected' ? 'danger' : 'warning') }} btn-sm" 
                                            onclick="window.print()">
                                        <i class="fas fa-print"></i> Print
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Payment Information -->
                        <div class="row">
                            <div class="col-md-6">
                                <div class="card bg-light">
                                    <div class="card-header">
                                        <h6 class="mb-0"><i class="fas fa-info-circle"></i> Payment Information</h6>
                                    </div>
                                    <div class="card-body">
                                        <table class="table table-sm mb-0">
                                            <tr>
                                                <th width="40%">Payment ID:</th>
                                                <td><strong>#{{ str_pad($payment->id, 6, '0', STR_PAD_LEFT) }}</strong></td>
                                            </tr>
                                            <tr>
                                                <th>Payment Date:</th>
                                                <td>
                                                    @if(isset($setting->date_format))
                                                    {{ date($setting->date_format, strtotime($payment->payment_date)) }}
                                                    @else
                                                    {{ date('Y-m-d', strtotime($payment->payment_date)) }}
                                                    @endif
                                                </td>
                                            </tr>
                                            <tr>
                                                <th>Payment Method:</th>
                                                <td>{{ ucwords(str_replace('_', ' ', $payment->payment_method)) }}</td>
                                            </tr>
                                            @if($payment->transaction_id)
                                            <tr>
                                                <th>Transaction ID:</th>
                                                <td><code>{{ $payment->transaction_id }}</code></td>
                                            </tr>
                                            @endif
                                            <tr>
                                                <th>Number of Fees:</th>
                                                <td><span class="badge badge-info">{{ $payment->distributions->count() }}</span></td>
                                            </tr>
                                        </table>
                                    </div>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="card bg-light">
                                    <div class="card-header">
                                        <h6 class="mb-0"><i class="fas fa-money-bill-wave"></i> Payment Summary</h6>
                                    </div>
                                    <div class="card-body">
                                        <table class="table table-sm mb-0">
                                            <tr>
                                                <th width="40%">Total Balance:</th>
                                                <td>
                                                    @if(isset($setting->decimal_place))
                                                    {{ number_format((float)$payment->total_amount, $setting->decimal_place, '.', '') }}
                                                    @else
                                                    {{ number_format((float)$payment->total_amount, 2, '.', '') }}
                                                    @endif
                                                    {!! $setting->currency_symbol ?? '' !!}
                                                </td>
                                            </tr>
                                            <tr>
                                                <th>Amount Paid:</th>
                                                <td class="text-primary">
                                                    <strong>
                                                    @if(isset($setting->decimal_place))
                                                    {{ number_format((float)$payment->amount_paid, $setting->decimal_place, '.', '') }}
                                                    @else
                                                    {{ number_format((float)$payment->amount_paid, 2, '.', '') }}
                                                    @endif
                                                    {!! $setting->currency_symbol ?? '' !!}
                                                    </strong>
                                                </td>
                                            </tr>
                                            <tr>
                                                <th>Distributed Amount:</th>
                                                <td class="text-success">
                                                    @if(isset($setting->decimal_place))
                                                    {{ number_format((float)$payment->getTotalDistributedAmount(), $setting->decimal_place, '.', '') }}
                                                    @else
                                                    {{ number_format((float)$payment->getTotalDistributedAmount(), 2, '.', '') }}
                                                    @endif
                                                    {!! $setting->currency_symbol ?? '' !!}
                                                </td>
                                            </tr>
                                            @if($payment->status == 'approved')
                                            <tr>
                                                <th>Verified By:</th>
                                                <td>{{ $payment->verifiedBy->name ?? 'Admin' }}</td>
                                            </tr>
                                            <tr>
                                                <th>Verified At:</th>
                                                <td>
                                                    @if($payment->verified_at)
                                                    {{ $payment->verified_at->format('Y-m-d H:i A') }}
                                                    @endif
                                                </td>
                                            </tr>
                                            @endif
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Payment Distribution -->
                        <div class="card mt-3">
                            <div class="card-header">
                                <h6 class="mb-0"><i class="fas fa-chart-pie"></i> Payment Distribution</h6>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-bordered">
                                        <thead class="thead-light">
                                            <tr>
                                                <th>#</th>
                                                <th>Fee Type</th>
                                                <th>Session/Semester</th>
                                                <th>Balance Before</th>
                                                <th>Amount Applied</th>
                                                <th>Balance After</th>
                                                <th>Status</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach($payment->distributions as $key => $distribution)
                                            <tr>
                                                <td>{{ $key + 1 }}</td>
                                                <td>
                                                    <strong>{{ $distribution->fee->category->title ?? 'Fee' }}</strong>
                                                </td>
                                                <td>
                                                    <small class="text-muted">
                                                        {{ $distribution->fee->studentEnroll->session->title ?? '' }} -
                                                        {{ $distribution->fee->studentEnroll->semester->title ?? '' }}
                                                    </small>
                                                </td>
                                                <td>
                                                    @if(isset($setting->decimal_place))
                                                    {{ number_format((float)$distribution->balance_before, $setting->decimal_place, '.', '') }}
                                                    @else
                                                    {{ number_format((float)$distribution->balance_before, 2, '.', '') }}
                                                    @endif
                                                    {!! $setting->currency_symbol ?? '' !!}
                                                </td>
                                                <td class="text-primary">
                                                    <strong>
                                                    @if(isset($setting->decimal_place))
                                                    {{ number_format((float)$distribution->amount_applied, $setting->decimal_place, '.', '') }}
                                                    @else
                                                    {{ number_format((float)$distribution->amount_applied, 2, '.', '') }}
                                                    @endif
                                                    {!! $setting->currency_symbol ?? '' !!}
                                                    </strong>
                                                </td>
                                                <td>
                                                    @if(isset($setting->decimal_place))
                                                    {{ number_format((float)$distribution->balance_after, $setting->decimal_place, '.', '') }}
                                                    @else
                                                    {{ number_format((float)$distribution->balance_after, 2, '.', '') }}
                                                    @endif
                                                    {!! $setting->currency_symbol ?? '' !!}
                                                </td>
                                                <td>
                                                    @if($distribution->fee_status_after == 'paid')
                                                    <span class="badge badge-success">Fully Paid</span>
                                                    @else
                                                    <span class="badge badge-warning">Partially Paid</span>
                                                    @endif
                                                </td>
                                            </tr>
                                            @endforeach
                                        </tbody>
                                        <tfoot>
                                            <tr class="table-active">
                                                <th colspan="4" class="text-right">Total Distributed:</th>
                                                <th class="text-primary">
                                                    @if(isset($setting->decimal_place))
                                                    {{ number_format((float)$payment->getTotalDistributedAmount(), $setting->decimal_place, '.', '') }}
                                                    @else
                                                    {{ number_format((float)$payment->getTotalDistributedAmount(), 2, '.', '') }}
                                                    @endif
                                                    {!! $setting->currency_symbol ?? '' !!}
                                                </th>
                                                <th colspan="2"></th>
                                            </tr>
                                        </tfoot>
                                    </table>
                                </div>
                            </div>
                        </div>

                        <!-- Payment Receipt -->
                        @if($payment->receipt_path)
                        <div class="card mt-3">
                            <div class="card-header">
                                <h6 class="mb-0"><i class="fas fa-file-upload"></i> Payment Receipt</h6>
                            </div>
                            <div class="card-body">
                                @php
                                    $extension = pathinfo($payment->receipt_path, PATHINFO_EXTENSION);
                                @endphp
                                
                                @if(in_array(strtolower($extension), ['jpg', 'jpeg', 'png', 'gif']))
                                    <div class="text-center">
                                        <img src="{{ asset('storage/' . $payment->receipt_path) }}" 
                                             alt="Payment Receipt" 
                                             class="img-fluid"
                                             style="max-height: 600px;">
                                    </div>
                                @elseif(strtolower($extension) == 'pdf')
                                    <div class="text-center">
                                        <embed src="{{ asset('storage/' . $payment->receipt_path) }}" 
                                               type="application/pdf" 
                                               width="100%" 
                                               height="600px">
                                    </div>
                                @endif
                                
                                <div class="text-center mt-3">
                                    <a href="{{ asset('storage/' . $payment->receipt_path) }}" 
                                       class="btn btn-primary" 
                                       target="_blank" 
                                       download>
                                        <i class="fas fa-download"></i> Download Receipt
                                    </a>
                                </div>
                            </div>
                        </div>
                        @endif

                        <!-- Admin Note -->
                        @if($payment->admin_note && $payment->status == 'rejected')
                        <div class="alert alert-danger mt-3">
                            <h6><i class="fas fa-exclamation-triangle"></i> Admin Note:</h6>
                            <p class="mb-0">{{ $payment->admin_note }}</p>
                        </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
        <!-- [ Main Content ] end -->
    </div>
</div>
<!-- End Content-->

@endsection
