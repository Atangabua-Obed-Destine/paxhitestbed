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
                            <li class="breadcrumb-item"><a href="{{ route('admin.payment-account.index') }}">{{ __('payment_accounts') }}</a></li>
                            <li class="breadcrumb-item"><a href="#!">{{ __('reports') }}</a></li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
        <!-- [ breadcrumb ] end -->

        <!-- [ Main Content ] start -->
        <div class="row">
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header">
                        <h5>
                            {{ __('unlinked_transactions_report') }}
                            <span class="badge badge-danger badge-pill float-right" style="font-size: 0.9rem;">
                                <i class="feather icon-alert-circle"></i> {{ $transactions->total() }} {{ __('unlinked') }}
                            </span>
                        </h5>
                        <span class="d-block m-t-5">{{ __('link_transactions_to_payment_accounts') }}</span>
                    </div>
                    <div class="card-body">
                        <!-- Filters Section -->
                        <div class="collapse show" id="collapseFilters">
                            <form method="GET" action="{{ route($route . '.unlinked') }}">
                                <div class="row">
                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label for="type">{{ __('field_transaction_type') }}</label>
                                            <select name="type" id="type" class="form-control">
                                                <option value="all" {{ $selected_type == 'all' ? 'selected' : '' }}>{{ __('all_types') }}</option>
                                                <option value="fee" {{ $selected_type == 'fee' ? 'selected' : '' }}>{{ __('fee_payment') }}</option>
                                                <option value="installment" {{ $selected_type == 'installment' ? 'selected' : '' }}>{{ __('installment_payment') }}</option>
                                                <option value="expense" {{ $selected_type == 'expense' ? 'selected' : '' }}>{{ __('expense') }}</option>
                                                <option value="income" {{ $selected_type == 'income' ? 'selected' : '' }}>{{ __('income') }}</option>
                                                <option value="payroll" {{ $selected_type == 'payroll' ? 'selected' : '' }}>{{ __('payroll') }}</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-2">
                                        <div class="form-group">
                                            <label for="date_from">{{ __('field_date_from') }}</label>
                                            <input type="date" name="date_from" id="date_from" class="form-control" value="{{ $date_from }}">
                                        </div>
                                    </div>
                                    <div class="col-md-2">
                                        <div class="form-group">
                                            <label for="date_to">{{ __('field_date_to') }}</label>
                                            <input type="date" name="date_to" id="date_to" class="form-control" value="{{ $date_to }}">
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label for="search">{{ __('search') }}</label>
                                            <input type="text" name="search" id="search" class="form-control" placeholder="{{ __('search') }}..." value="{{ $search }}">
                                        </div>
                                    </div>
                                    <div class="col-md-2">
                                        <div class="form-group">
                                            <label>&nbsp;</label>
                                            <button type="submit" class="btn btn-primary btn-block"><i class="feather icon-filter"></i> {{ __('filter') }}</button>
                                        </div>
                                    </div>
                                </div>
                            </form>

                            <!-- Summary Info -->
                            @if($transactions->total() > 0)
                            <div class="alert alert-info alert-dismissible fade show mt-3" role="alert">
                                <i class="feather icon-info"></i> 
                                <strong>{{ __('summary') }}:</strong> 
                                {{ __('showing') }} <strong>{{ $transactions->firstItem() }}</strong> {{ __('to') }} <strong>{{ $transactions->lastItem() }}</strong> 
                                {{ __('of') }} <strong>{{ $transactions->total() }}</strong> {{ __('unlinked_transactions') }}
                            </div>
                            @endif
                        </div>

                        <!-- Transactions Table -->
                        <div class="table-responsive mt-4">
                            <table class="table table-hover table-bordered">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>{{ __('field_date') }}</th>
                                        <th>{{ __('payment_ref_no') }}</th>
                                        <th>{{ __('invoice_no') }}</th>
                                        <th>{{ __('field_amount') }}</th>
                                        <th>{{ __('payment_type') }}</th>
                                        <th>{{ __('field_account') }}</th>
                                        <th>{{ __('field_description') }}</th>
                                        <th>{{ __('action') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($transactions as $transaction)
                                    <tr>
                                        <td>{{ ($transactions->currentPage() - 1) * $transactions->perPage() + $loop->iteration }}</td>
                                        <td>
                                            <div><strong>{{ date('d M Y', strtotime($transaction['date'])) }}</strong></div>
                                            <small class="text-muted"><i class="feather icon-clock"></i> {{ date('h:i A', strtotime($transaction['date'])) }}</small>
                                        </td>
                                        <td><span class="badge badge-primary">{{ $transaction['payment_ref_no'] }}</span></td>
                                        <td>
                                            @if(is_numeric($transaction['invoice_no']))
                                                <span class="badge badge-info">{{ str_pad($transaction['invoice_no'], 4, '0', STR_PAD_LEFT) }}</span>
                                            @else
                                                <span class="badge badge-info">{{ $transaction['invoice_no'] }}</span>
                                            @endif
                                        </td>
                                        <td><strong>{!! $setting->currency_symbol !!} {{ number_format($transaction['amount'], 2) }}</strong></td>
                                        <td>
                                            @if($transaction['payment_type'] == 'Fee Payment')
                                                <span class="badge badge-success">{{ $transaction['payment_type'] }}</span>
                                            @elseif($transaction['payment_type'] == 'Expense')
                                                <span class="badge badge-danger">{{ $transaction['payment_type'] }}</span>
                                            @elseif($transaction['payment_type'] == 'Income')
                                                <span class="badge badge-info">{{ $transaction['payment_type'] }}</span>
                                            @elseif($transaction['payment_type'] == 'Payroll')
                                                <span class="badge badge-warning">{{ $transaction['payment_type'] }}</span>
                                            @else
                                                <span class="badge badge-secondary">{{ $transaction['payment_type'] }}</span>
                                            @endif
                                        </td>
                                        <td>{{ $transaction['account'] }}</td>
                                        <td>{{ $transaction['description'] }}</td>
                                        <td>
                                            <button type="button" class="btn btn-sm btn-primary link-account-btn" 
                                                    data-type="{{ $transaction['type'] }}" 
                                                    data-id="{{ $transaction['id'] }}"
                                                    data-ref="{{ $transaction['payment_ref_no'] }}">
                                                <i class="feather icon-link"></i> {{ __('link_account') }}
                                            </button>
                                        </td>
                                    </tr>
                                    @empty
                                    <tr>
                                        <td colspan="9" class="text-center">{{ __('no_data_available') }}</td>
                                    </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>

                        <!-- Pagination -->
                        <div class="mt-3">
                            {{ $transactions->appends(request()->query())->links() }}
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <!-- [ Main Content ] end -->
    </div>
</div>
<!-- End Content-->

<!-- Link Account Modal -->
<div class="modal fade" id="linkAccountModal" tabindex="-1" role="dialog" aria-labelledby="linkAccountModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="linkAccountModalLabel">{{ __('link_account') }} - <span id="modal-ref-no"></span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="linkAccountForm">
                <div class="modal-body">
                    <input type="hidden" name="type" id="transaction-type">
                    <input type="hidden" name="id" id="transaction-id">
                    
                    <div class="form-group">
                        <label for="payment_account_id">{{ __('field_payment_account') }} <span class="text-danger">*</span></label>
                        <select name="payment_account_id" id="payment_account_id" class="form-control" required>
                            <option value="">{{ __('select_payment_account') }}</option>
                            @foreach($payment_accounts as $account)
                                <option value="{{ $account->id }}">
                                    {{ $account->title }} - {{ __('balance') }}: {!! $setting->currency_symbol !!} {{ number_format($account->current_balance, 2) }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="alert alert-info mt-3">
                        <i class="feather icon-info"></i> {{ __('link_account_help_text') }}
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ __('close') }}</button>
                    <button type="submit" class="btn btn-primary">{{ __('save') }}</button>
                </div>
            </form>
        </div>
    </div>
</div>

@endsection

@section('scripts')
<script>
    $(document).ready(function() {
        console.log('Document ready - Payment Account Report');
        console.log('Number of link buttons found:', $('.link-account-btn').length);
        
        // Open link account modal - using event delegation for better compatibility
        $(document).on('click', '.link-account-btn', function(e) {
            e.preventDefault();
            console.log('Link account button clicked');
            
            var type = $(this).data('type');
            var id = $(this).data('id');
            var ref = $(this).data('ref');
            
            console.log('Type:', type, 'ID:', id, 'Ref:', ref);

            $('#transaction-type').val(type);
            $('#transaction-id').val(id);
            $('#modal-ref-no').text(ref);
            $('#payment_account_id').val('');

            $('#linkAccountModal').modal('show');
        });

        // Submit link account form
        $('#linkAccountForm').on('submit', function(e) {
            e.preventDefault();
            console.log('Form submitted');

            var type = $('#transaction-type').val();
            var id = $('#transaction-id').val();
            var accountId = $('#payment_account_id').val();
            
            console.log('Submitting - Type:', type, 'ID:', id, 'Account:', accountId);
            
            if (!accountId) {
                toastr.error('Please select a payment account');
                return false;
            }

            var formData = {
                type: type,
                id: id,
                payment_account_id: accountId,
                _token: '{{ csrf_token() }}'
            };
            
            console.log('Form data:', formData);

            $.ajax({
                url: '{{ route($route . '.link') }}',
                method: 'POST',
                data: formData,
                beforeSend: function() {
                    console.log('Sending AJAX request to:', '{{ route($route . '.link') }}');
                },
                success: function(response) {
                    console.log('Success response:', response);
                    if (response.success) {
                        toastr.success(response.message);
                        $('#linkAccountModal').modal('hide');
                        setTimeout(function() {
                            location.reload();
                        }, 1500);
                    } else {
                        toastr.error(response.message);
                    }
                },
                error: function(xhr, status, error) {
                    console.log('Error response:', xhr.responseText);
                    console.log('Status:', status, 'Error:', error);
                    var message = 'Error linking transaction';
                    if (xhr.responseJSON && xhr.responseJSON.message) {
                        message = xhr.responseJSON.message;
                    } else if (xhr.responseText) {
                        message = 'Server error: ' + xhr.status;
                    }
                    toastr.error(message);
                }
            });
        });
    });
</script>
@endsection
