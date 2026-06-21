@extends('student.layouts.master')
@section('title', $title)
@section('content')

<!-- Start Content-->
<div class="main-body">
    <div class="page-wrapper">
        <!-- [ Main Content ] start -->
        <div class="row">
            <div class="col-md-10 offset-md-1">
                <!-- Status Alert -->
                @if($row->verification_status == 'pending')
                <div class="alert alert-warning">
                    <h5><i class="fas fa-clock"></i> {{ __('payment_receipt_pending') }}</h5>
                    <p class="mb-0">{{ __('msg_payment_receipt_under_review') }}</p>
                </div>
                @elseif($row->verification_status == 'approved')
                <div class="alert alert-success">
                    <h5><i class="fas fa-check-circle"></i> {{ __('payment_receipt_approved') }}</h5>
                    <p class="mb-0">{{ __('msg_payment_receipt_approved') }}</p>
                </div>
                @else
                <div class="alert alert-danger">
                    <h5><i class="fas fa-times-circle"></i> {{ __('payment_receipt_rejected') }}</h5>
                    <p class="mb-0">{{ __('msg_payment_receipt_rejected') }}</p>
                </div>
                @endif

                <div class="card">
                    <div class="card-header">
                        <h5>{{ __('payment_receipt_details') }}</h5>
                    </div>
                    <div class="card-block">
                        <div class="row">
                            <!-- Left Column -->
                            <div class="col-md-6">
                                <h6 class="mb-3"><strong>{{ __('fee_information') }}</strong></h6>
                                <table class="table table-borderless">
                                    <tr>
                                        <td width="40%"><strong>{{ __('field_fees_type') }}:</strong></td>
                                        <td>{{ $row->fee->category->title ?? '' }}</td>
                                    </tr>
                                    <tr>
                                        <td><strong>{{ __('field_session') }}:</strong></td>
                                        <td>{{ $row->fee->studentEnroll->session->title ?? '' }}</td>
                                    </tr>
                                    <tr>
                                        <td><strong>{{ __('field_semester') }}:</strong></td>
                                        <td>{{ $row->fee->studentEnroll->semester->title ?? '' }}</td>
                                    </tr>
                                    <tr>
                                        <td><strong>{{ __('field_fee_amount') }}:</strong></td>
                                        <td>
                                            @if(isset($setting->decimal_place))
                                            {{ number_format((float)$row->fee->fee_amount, $setting->decimal_place, '.', '') }} 
                                            @else
                                            {{ number_format((float)$row->fee->fee_amount, 2, '.', '') }} 
                                            @endif 
                                            {!! $setting->currency_symbol !!}
                                        </td>
                                    </tr>
                                </table>

                                <h6 class="mb-3 mt-4"><strong>{{ __('payment_information') }}</strong></h6>
                                <table class="table table-borderless">
                                    <tr>
                                        <td width="40%"><strong>{{ __('field_payment_reference') }}:</strong></td>
                                        <td>{{ $row->payment_reference ?? '-' }}</td>
                                    </tr>
                                    <tr>
                                        <td><strong>{{ __('field_payment_date') }}:</strong></td>
                                        <td>
                                            @if(isset($setting->date_format))
                                            {{ date($setting->date_format, strtotime($row->payment_date)) }}
                                            @else
                                            {{ date("Y-m-d", strtotime($row->payment_date)) }}
                                            @endif
                                        </td>
                                    </tr>
                                    <tr>
                                        <td><strong>{{ __('field_amount') }}:</strong></td>
                                        <td>
                                            <strong class="text-success">
                                            @if(isset($setting->decimal_place))
                                            {{ number_format((float)$row->amount, $setting->decimal_place, '.', '') }} 
                                            @else
                                            {{ number_format((float)$row->amount, 2, '.', '') }} 
                                            @endif 
                                            {!! $setting->currency_symbol !!}
                                            </strong>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td><strong>{{ __('field_payment_method') }}:</strong></td>
                                        <td>
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
                                            {{ __('payment_method_manual') }}
                                            @endif
                                        </td>
                                    </tr>
                                    @if($row->student_note)
                                    <tr>
                                        <td><strong>{{ __('field_note') }}:</strong></td>
                                        <td>{{ $row->student_note }}</td>
                                    </tr>
                                    @endif
                                </table>
                            </div>

                            <!-- Right Column -->
                            <div class="col-md-6">
                                <h6 class="mb-3"><strong>{{ __('receipt_file') }}</strong></h6>
                                <div class="text-center mb-3">
                                    @php
                                        $fileExtension = pathinfo($row->receipt_file, PATHINFO_EXTENSION);
                                    @endphp
                                    @if(in_array(strtolower($fileExtension), ['jpg', 'jpeg', 'png']))
                                        <img src="{{ asset('uploads/'.$path.'/'.$row->receipt_file) }}" alt="Receipt" class="img-fluid" style="max-height: 400px; border: 1px solid #ddd; padding: 5px;">
                                    @elseif(strtolower($fileExtension) == 'pdf')
                                        <div class="alert alert-info">
                                            <i class="fas fa-file-pdf fa-3x mb-2"></i>
                                            <p class="mb-0">{{ __('pdf_receipt_uploaded') }}</p>
                                        </div>
                                    @else
                                        <div class="alert alert-secondary">
                                            <i class="fas fa-file fa-3x mb-2"></i>
                                            <p class="mb-0">{{ __('file_uploaded') }}</p>
                                        </div>
                                    @endif
                                </div>
                                <div class="text-center">
                                    <a href="{{ asset('uploads/'.$path.'/'.$row->receipt_file) }}" class="btn btn-sm btn-primary" target="_blank">
                                        <i class="fas fa-download"></i> {{ __('btn_download_receipt') }}
                                    </a>
                                </div>

                                <h6 class="mb-3 mt-4"><strong>{{ __('verification_status') }}</strong></h6>
                                <table class="table table-borderless">
                                    <tr>
                                        <td width="40%"><strong>{{ __('field_status') }}:</strong></td>
                                        <td>
                                            @if($row->verification_status == 'pending')
                                                <span class="badge badge-warning">{{ __('status_pending') }}</span>
                                            @elseif($row->verification_status == 'approved')
                                                <span class="badge badge-success">{{ __('status_approved') }}</span>
                                            @else
                                                <span class="badge badge-danger">{{ __('status_rejected') }}</span>
                                            @endif
                                        </td>
                                    </tr>
                                    <tr>
                                        <td><strong>{{ __('field_submitted_date') }}:</strong></td>
                                        <td>
                                            @if(isset($setting->date_format))
                                            {{ date($setting->date_format, strtotime($row->created_at)) }}
                                            @else
                                            {{ date("Y-m-d", strtotime($row->created_at)) }}
                                            @endif
                                        </td>
                                    </tr>
                                    @if($row->verified_at)
                                    <tr>
                                        <td><strong>{{ __('field_verified_date') }}:</strong></td>
                                        <td>
                                            @if(isset($setting->date_format))
                                            {{ date($setting->date_format, strtotime($row->verified_at)) }}
                                            @else
                                            {{ date("Y-m-d", strtotime($row->verified_at)) }}
                                            @endif
                                        </td>
                                    </tr>
                                    @endif
                                    @if($row->verifier)
                                    <tr>
                                        <td><strong>{{ __('field_verified_by') }}:</strong></td>
                                        <td>{{ $row->verifier->name }}</td>
                                    </tr>
                                    @endif
                                    @if($row->verification_note)
                                    <tr>
                                        <td><strong>{{ __('field_admin_note') }}:</strong></td>
                                        <td>
                                            <div class="alert alert-{{ $row->verification_status == 'approved' ? 'success' : 'danger' }} p-2">
                                                {{ $row->verification_note }}
                                            </div>
                                        </td>
                                    </tr>
                                    @endif
                                </table>
                            </div>
                        </div>

                        <div class="form-group mt-4">
                            <a href="{{ route($route.'.index') }}" class="btn btn-secondary">
                                <i class="fas fa-arrow-left"></i> {{ __('btn_back_to_list') }}
                            </a>
                            @if($row->verification_status == 'rejected')
                            <a href="{{ route($route.'.create', $row->fee_id) }}" class="btn btn-success">
                                <i class="fas fa-upload"></i> {{ __('btn_reupload_receipt') }}
                            </a>
                            @endif
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
