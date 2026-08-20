@extends('admin.layouts.master')
@section('title', $title)
@section('content')

<!-- Start Content-->
<div class="main-body">
    <div class="page-wrapper">
        <!-- [ breadcrumb ] start -->
        <div class="page-header">
            <div class="page-block">
                <div class="row align-items-center">
                    <div class="col-md-12">
                        <div class="page-header-title">
                            <h5>{{ $title }}</h5>
                        </div>
                        <ul class="breadcrumb">
                            <li class="breadcrumb-item"><a href="{{ route('admin.dashboard.index') }}"><i class="feather icon-home"></i></a></li>
                            <li class="breadcrumb-item"><a href="#">{{ __('module_payment_verification') }}</a></li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
        <!-- [ breadcrumb ] end -->

        <!-- [ Main Content ] start -->
        <div class="row">
            <!-- Statistics Cards -->
            <div class="col-md-2 col-sm-6">
                <div class="card bg-warning text-white">
                    <div class="card-body">
                        <h6 class="text-white">{{ __('pending_receipts') }}</h6>
                        <h2 class="text-white">{{ $stats['pending'] }}</h2>
                        <p class="mb-0">{{ __('awaiting_verification') }}</p>
                    </div>
                </div>
            </div>
            <div class="col-md-2 col-sm-6">
                <div class="card bg-success text-white">
                    <div class="card-body">
                        <h6 class="text-white">{{ __('approved_receipts') }}</h6>
                        <h2 class="text-white">{{ $stats['approved'] }}</h2>
                        <p class="mb-0">{{ __('verified_approved') }}</p>
                    </div>
                </div>
            </div>
            <div class="col-md-2 col-sm-6">
                <div class="card bg-danger text-white">
                    <div class="card-body">
                        <h6 class="text-white">{{ __('rejected_receipts') }}</h6>
                        <h2 class="text-white">{{ $stats['rejected'] }}</h2>
                        <p class="mb-0">{{ __('verification_rejected') }}</p>
                    </div>
                </div>
            </div>
            <div class="col-md-2 col-sm-6">
                <div class="card bg-primary text-white">
                    <div class="card-body">
                        <h6 class="text-white">{{ __('pending_fees') }}</h6>
                        <h2 class="text-white">{{ $stats['pending_fee'] ?? 0 }}</h2>
                        <p class="mb-0">{{ __('fee_payments') }}</p>
                    </div>
                </div>
            </div>
            <div class="col-md-2 col-sm-6">
                <div class="card bg-secondary text-white">
                    <div class="card-body">
                        <h6 class="text-white">{{ __('pending_installments') }}</h6>
                        <h2 class="text-white">{{ $stats['pending_installment'] ?? 0 }}</h2>
                        <p class="mb-0">{{ __('installment_payments') }}</p>
                    </div>
                </div>
            </div>
            <div class="col-md-2 col-sm-6">
                <div class="card bg-info text-white">
                    <div class="card-body">
                        <h6 class="text-white">{{ __('total_receipts') }}</h6>
                        <h2 class="text-white">{{ $stats['total'] }}</h2>
                        <p class="mb-0">{{ __('all_submissions') }}</p>
                    </div>
                </div>
            </div>

            <!-- Payment Receipts Table -->
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header">
                        <h5>{{ __('payment_receipts_list') }}</h5>
                    </div>

                    <!-- Filter Form -->
                    <div class="card-body border-bottom">
                        <form method="GET" action="{{ route($route.'.index') }}">
                            <div class="row">
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label for="payment_type">{{ __('field_payment_type') }}</label>
                                        <select name="payment_type" id="payment_type" class="form-control">
                                            <option value="all" {{ request('payment_type') == 'all' ? 'selected' : '' }}>{{ __('all_types') }}</option>
                                            <option value="fee" {{ request('payment_type') == 'fee' ? 'selected' : '' }}>{{ __('fee_payment') }}</option>
                                            <option value="installment" {{ request('payment_type') == 'installment' ? 'selected' : '' }}>{{ __('installment_payment') }}</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-2">
                                    <div class="form-group">
                                        <label for="status">{{ __('field_status') }}</label>
                                        <select name="status" id="status" class="form-control">
                                            <option value="">{{ __('all') }}</option>
                                            <option value="pending" {{ $selected_status == 'pending' ? 'selected' : '' }}>{{ __('status_pending') }}</option>
                                            <option value="approved" {{ $selected_status == 'approved' ? 'selected' : '' }}>{{ __('status_approved') }}</option>
                                            <option value="rejected" {{ $selected_status == 'rejected' ? 'selected' : '' }}>{{ __('status_rejected') }}</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-2">
                                    <div class="form-group">
                                        <label for="date_from">{{ __('field_date_from') }}</label>
                                        <input type="date" name="date_from" id="date_from" class="form-control" value="{{ request('date_from') }}">
                                    </div>
                                </div>
                                <div class="col-md-2">
                                    <div class="form-group">
                                        <label for="date_to">{{ __('field_date_to') }}</label>
                                        <input type="date" name="date_to" id="date_to" class="form-control" value="{{ request('date_to') }}">
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label for="search">{{ __('field_search') }}</label>
                                        <input type="text" name="search" id="search" class="form-control" placeholder="{{ __('search_student_or_reference') }}" value="{{ request('search') }}">
                                    </div>
                                </div>
                                <div class="col-md-12">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-search"></i> {{ __('btn_filter') }}
                                    </button>
                                    <a href="{{ route($route.'.index') }}" class="btn btn-secondary">
                                        <i class="fas fa-redo"></i> {{ __('btn_reset') }}
                                    </a>
                                </div>
                            </div>
                        </form>
                    </div>

                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>{{ __('field_type') }}</th>
                                        <th>{{ __('field_student') }}</th>
                                        <th>{{ __('field_fees_type') }}</th>
                                        <th>{{ __('field_session') }}</th>
                                        <th>{{ __('field_amount') }}</th>
                                        <th>{{ __('field_payment_reference') }}</th>
                                        <th>{{ __('field_payment_date') }}</th>
                                        <th>{{ __('field_submitted_date') }}</th>
                                        <th>{{ __('field_status') }}</th>
                                        <th>{{ __('field_action') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                  @forelse( $rows as $key => $row )
                                    <tr>
                                        <td>{{ $rows->firstItem() + $key }}</td>
                                        <td>
                                            @if($row->payment_type == 'fee')
                                                @if(!empty($row->applicant_id) || !empty($row->fee?->applicant_id))
                                                    <span class="badge badge-warning">{{ __('Admission Fee') }}</span>
                                                @else
                                                    <span class="badge badge-primary">{{ __('fee_payment') }}</span>
                                                @endif
                                            @else
                                                <span class="badge badge-secondary">{{ __('installment_payment') }}</span>
                                            @endif
                                        </td>
                                        <td>
                                            @php
                                                $applicant = $row->applicant ?? optional($row->fee)->applicant;
                                            @endphp
                                            @if($row->student)
                                                <strong>{{ $row->student->first_name.' '.$row->student->last_name }}</strong><br>
                                                <small class="text-muted">{{ $row->student->student_id ?? '' }}</small>
                                            @elseif($applicant)
                                                <strong>{{ trim(($applicant->first_name ?? '').' '.($applicant->last_name ?? '')) ?: 'N/A' }}</strong>
                                                <span class="badge badge-light" title="{{ __('Applicant — not yet enrolled') }}">{{ __('Applicant') }}</span><br>
                                                <small class="text-muted">{{ $applicant->registration_no ?? '' }}</small>
                                            @else
                                                <strong>N/A</strong>
                                            @endif
                                        </td>
                                        <td>
                                            @if($row->payment_type == 'fee')
                                                {{ $row->fee->category->title ?? '' }}
                                            @else
                                                <span class="badge badge-info">{{ __('installment') }} #{{ $row->installment->installment_number ?? '' }}</span><br>
                                                <small>{{ $row->installment->paymentPlan->fee->category->title ?? '' }}</small>
                                            @endif
                                        </td>
                                        <td>
                                            @if($row->payment_type == 'fee')
                                                {{ $row->fee->studentEnroll->session->title ?? optional(optional($row->fee)->applicant)->session->title ?? optional($row->applicant)->session->title ?? '' }}
                                            @else
                                                {{ $row->installment->paymentPlan->fee->studentEnroll->session->title ?? '' }}
                                            @endif
                                        </td>
                                        <td>
                                            @if(isset($setting->decimal_place))
                                            {{ number_format((float)$row->amount, $setting->decimal_place, '.', '') }} 
                                            @else
                                            {{ number_format((float)$row->amount, 2, '.', '') }} 
                                            @endif 
                                            {!! $setting->currency_symbol !!}
                                        </td>
                                        <td>{{ $row->payment_reference ?? '-' }}</td>
                                        <td>
                                            @if(isset($setting->date_format))
                                            {{ date($setting->date_format, strtotime($row->payment_date)) }}
                                            @else
                                            {{ date("Y-m-d", strtotime($row->payment_date)) }}
                                            @endif
                                        </td>
                                        <td>
                                            @if(isset($setting->date_format))
                                            {{ date($setting->date_format, strtotime($row->created_at)) }}
                                            @else
                                            {{ date("Y-m-d", strtotime($row->created_at)) }}
                                            @endif
                                        </td>
                                        <td>
                                            @php
                                                $status = $row->payment_type == 'fee' ? $row->verification_status : $row->status;
                                            @endphp
                                            @if($status == 'pending')
                                                <span class="badge badge-warning">{{ __('status_pending') }}</span>
                                            @elseif($status == 'approved')
                                                <span class="badge badge-success">{{ __('status_approved') }}</span>
                                            @elseif($status == 'reversed')
                                                <span class="badge badge-dark" title="{{ __('This payment was recorded and then undone.') }}">{{ __('Reversed') }}</span>
                                            @else
                                                <span class="badge badge-danger">{{ __('status_rejected') }}</span>
                                            @endif
                                        </td>
                                        <td>
                                            <a href="{{ route($route.'.show', [$row->payment_type, $row->id]) }}" class="btn btn-sm btn-info">
                                                <i class="fas fa-eye"></i> {{ __('btn_view') }}
                                            </a>

                                            {{-- Undoing a payment moves money in the fee, the payment
                                                 account and the ledger, and can pull an application back
                                                 out of the admissions queue — so it asks for a reason and
                                                 says what it will do before doing it. --}}
                                            @if($status == 'approved')
                                                <button type="button" class="btn btn-sm btn-outline-danger"
                                                        data-bs-toggle="modal" data-bs-target="#reverseModal{{ $row->id }}">
                                                    <i class="fas fa-rotate-left"></i> {{ __('Reverse') }}
                                                </button>
                                            @endif
                                        </td>
                                    </tr>

                                    @if($status == 'approved')
                                        <tr class="d-none"><td>
                                        <div class="modal fade" id="reverseModal{{ $row->id }}" tabindex="-1" aria-hidden="true">
                                            <div class="modal-dialog">
                                                <form action="{{ route($route.'.reverse', [$row->payment_type, $row->id]) }}" method="post">
                                                    @csrf
                                                    <div class="modal-content">
                                                        <div class="modal-header">
                                                            <h5 class="modal-title">{{ __('Reverse this payment?') }}</h5>
                                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                        </div>
                                                        <div class="modal-body">
                                                            <p class="mb-2">{{ __('This will put back everything the approval changed:') }}</p>
                                                            <ul class="small mb-3">
                                                                <li>{{ __('the fee returns to what the remaining payments say it is') }}</li>
                                                                <li>{{ __('any amount credited to a payment account is debited back') }}</li>
                                                                <li>{{ __('the ledger entry is reversed by an opposing entry') }}</li>
                                                                <li>{{ __('an application submitted because of this payment returns to draft') }}</li>
                                                            </ul>
                                                            <p class="small text-muted">{{ __('Nothing is deleted — the receipt is kept and marked reversed.') }}</p>
                                                            <div class="form-group">
                                                                <label for="reverse-reason-{{ $row->id }}">{{ __('Reason') }} <span class="text-danger">*</span></label>
                                                                <textarea class="form-control" id="reverse-reason-{{ $row->id }}" name="reason" rows="2" required minlength="5"
                                                                          placeholder="{{ __('e.g. recorded against the wrong applicant') }}"></textarea>
                                                            </div>
                                                        </div>
                                                        <div class="modal-footer">
                                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ __('btn_cancel') }}</button>
                                                            <button type="submit" class="btn btn-danger">{{ __('Reverse payment') }}</button>
                                                        </div>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                        </td></tr>
                                    @endif
                                  @empty
                                    <tr>
                                        <td colspan="11" class="text-center">{{ __('no_payment_receipts_found') }}</td>
                                    </tr>
                                  @endforelse
                                </tbody>
                            </table>
                        </div>

                        <!-- Pagination -->
                        <div class="mt-3">
                            {{ $rows->links() }}
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <!-- [ Main Content ] end -->
    </div>
</div>
<!-- End Content-->

@endsection
