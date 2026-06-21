@extends('admin.layouts.master')
@section('title', __('general_ledger'))

@section('content')
<!-- Main content -->
<section class="content">
    <div class="container-fluid">
        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">{{ __('general_ledger') }} ({{ __('grand_livre') }})</h3>
                    </div>
                    <!-- /.card-header -->
                    <div class="card-body">
                        <div class="row">
                            <!-- Quick Links -->
                            <div class="col-md-12 mb-4">
                                <h5>{{ __('quick_access') }}</h5>
                                <div class="row">
                                    <div class="col-md-4">
                                        <div class="card bg-cyan text-white" style="border: none;">
                                            <div class="card-body">
                                                <div class="d-flex align-items-center">
                                                    <div class="mr-3">
                                                        <i class="fas fa-book fa-3x"></i>
                                                    </div>
                                                    <div class="flex-grow-1">
                                                        <h5 class="card-title mb-1">{{ __('trial_balance') }}</h5>
                                                        <p class="card-text mb-2"><small>{{ __('balance_de_verification') }}</small></p>
                                                        <a href="{{ route('admin.general-ledger.trial-balance') }}" class="btn btn-light btn-sm">
                                                            {{ __('view_report') }} <i class="fas fa-arrow-right"></i>
                                                        </a>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="card bg-success text-white" style="border: none;">
                                            <div class="card-body">
                                                <div class="d-flex align-items-center">
                                                    <div class="mr-3">
                                                        <i class="fas fa-chart-line fa-3x"></i>
                                                    </div>
                                                    <div class="flex-grow-1">
                                                        <h5 class="card-title mb-1">{{ __('income_statement') }}</h5>
                                                        <p class="card-text mb-2"><small>{{ __('compte_de_resultat') }}</small></p>
                                                        <a href="{{ route('admin.general-ledger.income-statement') }}" class="btn btn-light btn-sm">
                                                            {{ __('view_report') }} <i class="fas fa-arrow-right"></i>
                                                        </a>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="card bg-warning text-white" style="border: none;">
                                            <div class="card-body">
                                                <div class="d-flex align-items-center">
                                                    <div class="mr-3">
                                                        <i class="fas fa-balance-scale fa-3x"></i>
                                                    </div>
                                                    <div class="flex-grow-1">
                                                        <h5 class="card-title mb-1">{{ __('balance_sheet') }}</h5>
                                                        <p class="card-text mb-2"><small>{{ __('bilan') }}</small></p>
                                                        <a href="{{ route('admin.general-ledger.balance-sheet') }}" class="btn btn-light btn-sm">
                                                            {{ __('view_report') }} <i class="fas fa-arrow-right"></i>
                                                        </a>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Account Selector -->
                            <div class="col-md-12 mb-4">
                                <h5>{{ __('account_ledger') }}</h5>
                                <div class="card">
                                    <div class="card-body">
                                        <form action="{{ route('admin.general-ledger.account', ':account_id') }}" method="GET" id="account-form">
                                            <div class="row">
                                                <div class="col-md-4">
                                                    <div class="form-group">
                                                        <label for="account_id">{{ __('select_account') }}</label>
                                                        <select class="form-control select2" id="account_id" name="account_id" required>
                                                            <option value="">{{ __('select_account') }}</option>
                                                            @foreach($accounts as $account)
                                                            <option value="{{ $account->id }}">
                                                                {{ $account->account_code }} - {{ $account->account_name }}
                                                            </option>
                                                            @endforeach
                                                        </select>
                                                    </div>
                                                </div>
                                                <div class="col-md-3">
                                                    <div class="form-group">
                                                        <label for="start_date">{{ __('start_date') }}</label>
                                                        <input type="date" class="form-control" id="start_date" name="start_date">
                                                    </div>
                                                </div>
                                                <div class="col-md-3">
                                                    <div class="form-group">
                                                        <label for="end_date">{{ __('end_date') }}</label>
                                                        <input type="date" class="form-control" id="end_date" name="end_date">
                                                    </div>
                                                </div>
                                                <div class="col-md-2">
                                                    <div class="form-group">
                                                        <label>&nbsp;</label>
                                                        <button type="submit" class="btn btn-primary btn-block">
                                                            <i class="fas fa-search"></i> {{ __('view') }}
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>

                            <!-- Browse by Class -->
                            <div class="col-md-12">
                                <h5>{{ __('browse_by_class') }}</h5>
                                <div class="row">
                                    @for($i = 1; $i <= 9; $i++)
                                    <div class="col-lg-3 col-md-4 col-sm-6 mb-3">
                                        <a href="{{ route('admin.general-ledger.by-class', $i) }}" class="btn btn-outline-primary btn-block py-3" style="text-align: left;">
                                            <i class="fas fa-folder-open mr-2"></i> 
                                            <strong>{{ __('classe') }} {{ $i }}:</strong><br>
                                            <small>{{ __('class_'.$i.'_name') }}</small>
                                        </a>
                                    </div>
                                    @endfor
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- /.card-body -->
                </div>
                <!-- /.card -->
            </div>
            <!-- /.col -->
        </div>
        <!-- /.row -->
    </div>
    <!-- /.container-fluid -->
</section>
<!-- /.content -->
@endsection

@push('scripts')
<script>
    $(document).ready(function() {
        // Initialize Select2
        $('.select2').select2({
            theme: 'bootstrap4',
            placeholder: '{{ __("select_account") }}'
        });

        // Handle form submission
        $('#account-form').submit(function(e) {
            e.preventDefault();
            const accountId = $('#account_id').val();
            if (accountId) {
                let url = $(this).attr('action').replace(':account_id', accountId);
                const startDate = $('#start_date').val();
                const endDate = $('#end_date').val();
                
                if (startDate) url += '?start_date=' + startDate;
                if (endDate) url += (startDate ? '&' : '?') + 'end_date=' + endDate;
                
                window.location.href = url;
            } else {
                alert('{{ __("please_select_account") }}');
            }
        });
    });
</script>
@endpush
