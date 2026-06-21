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
                            <i class="fas fa-list"></i> {{ $title }}
                            <a href="{{ route('student.fees.index') }}" class="btn btn-sm btn-primary float-right">
                                <i class="fas fa-arrow-left"></i> Back to Fees
                            </a>
                        </h5>
                    </div>
                    <div class="card-block">
                        @if($payments->count() > 0)
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Payment ID</th>
                                        <th>Payment Date</th>
                                        <th>Number of Fees</th>
                                        <th>Total Balance</th>
                                        <th>Amount Paid</th>
                                        <th>Payment Method</th>
                                        <th>Status</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($payments as $key => $payment)
                                    <tr>
                                        <td>{{ $payments->firstItem() + $key }}</td>
                                        <td><strong>#{{ str_pad($payment->id, 6, '0', STR_PAD_LEFT) }}</strong></td>
                                        <td>
                                            @if(isset($setting->date_format))
                                            {{ date($setting->date_format, strtotime($payment->payment_date)) }}
                                            @else
                                            {{ date('Y-m-d', strtotime($payment->payment_date)) }}
                                            @endif
                                        </td>
                                        <td>
                                            <span class="badge badge-info">{{ $payment->distributions->count() }} fees</span>
                                        </td>
                                        <td>
                                            @if(isset($setting->decimal_place))
                                            {{ number_format((float)$payment->total_amount, $setting->decimal_place, '.', '') }}
                                            @else
                                            {{ number_format((float)$payment->total_amount, 2, '.', '') }}
                                            @endif
                                            {!! $setting->currency_symbol ?? '' !!}
                                        </td>
                                        <td>
                                            <strong>
                                            @if(isset($setting->decimal_place))
                                            {{ number_format((float)$payment->amount_paid, $setting->decimal_place, '.', '') }}
                                            @else
                                            {{ number_format((float)$payment->amount_paid, 2, '.', '') }}
                                            @endif
                                            {!! $setting->currency_symbol ?? '' !!}
                                            </strong>
                                        </td>
                                        <td>{{ ucwords(str_replace('_', ' ', $payment->payment_method)) }}</td>
                                        <td>
                                            @if($payment->status == 'approved')
                                            <span class="badge badge-success"><i class="fas fa-check-circle"></i> Approved</span>
                                            @elseif($payment->status == 'rejected')
                                            <span class="badge badge-danger"><i class="fas fa-times-circle"></i> Rejected</span>
                                            @else
                                            <span class="badge badge-warning"><i class="fas fa-clock"></i> Pending</span>
                                            @endif
                                        </td>
                                        <td>
                                            <a href="{{ route('student.multi-payment.show', $payment->id) }}" 
                                               class="btn btn-sm btn-primary" 
                                               title="View Details">
                                                <i class="fas fa-eye"></i> View
                                            </a>
                                        </td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        <!-- Pagination -->
                        <div class="mt-3">
                            {{ $payments->links() }}
                        </div>
                        @else
                        <div class="text-center py-5">
                            <i class="fas fa-inbox fa-4x text-muted mb-3"></i>
                            <h5>No multi-payments found</h5>
                            <p class="text-muted">You haven't made any multi-fee payments yet.</p>
                            <a href="{{ route('student.fees.index') }}" class="btn btn-primary mt-3">
                                <i class="fas fa-arrow-left"></i> Go to Fees
                            </a>
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
