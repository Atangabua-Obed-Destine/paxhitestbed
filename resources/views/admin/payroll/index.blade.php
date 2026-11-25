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
                        <form class="needs-validation" novalidate method="get" action="{{ route($route.'.index') }}">
                            <div class="row gx-2">
                                <div class="form-group col-md-2">
                                    <label for="salary_type">{{ __('field_salary_type') }} <span>*</span></label>
                                    <select class="form-control" name="salary_type" id="salary_type" required>
                                        <option value="">{{ __('select') }}</option>
                                        <option value="1" @if($selected_salary_type == 1) selected @endif>{{ __('salary_type_fixed') }}</option>
                                        <option value="2" @if($selected_salary_type == 2) selected @endif>{{ __('salary_type_hourly') }}</option>
                                    </select>

                                    <div class="invalid-feedback">
                                      {{ __('required_field') }} {{ __('field_salary_type') }}
                                    </div>
                                </div>
                                <div class="form-group col-md-2">
                                    <label for="department">{{ __('field_department') }}</label>
                                    <select class="form-control" name="department" id="department">
                                        <option value="">{{ __('all') }}</option>
                                        @foreach( $departments as $department )
                                        <option value="{{ $department->id }}" @if( $selected_department == $department->id) selected @endif>{{ $department->title }}</option>
                                        @endforeach
                                    </select>

                                    <div class="invalid-feedback">
                                      {{ __('required_field') }} {{ __('field_department') }}
                                    </div>
                                </div>
                                <div class="form-group col-md-2">
                                    <label for="designation">{{ __('field_designation') }}</label>
                                    <select class="form-control" name="designation" id="designation">
                                        <option value="">{{ __('all') }}</option>
                                        @foreach( $designations as $designation )
                                        <option value="{{ $designation->id }}" @if( $selected_designation == $designation->id) selected @endif>{{ $designation->title }}</option>
                                        @endforeach
                                    </select>

                                    <div class="invalid-feedback">
                                      {{ __('required_field') }} {{ __('field_designation') }}
                                    </div>
                                </div>
                                <div class="form-group col-md-2">
                                    <label for="month">{{ __('field_month') }} <span>*</span></label>
                                    <select class="form-control" name="month" id="month" required>
                                        <option value="1" @if($selected_month == 1) selected @endif>{{ __('month_january') }}</option>
                                        <option value="2" @if($selected_month == 2) selected @endif>{{ __('month_february') }}</option>
                                        <option value="3" @if($selected_month == 3) selected @endif>{{ __('month_march') }}</option>
                                        <option value="4" @if($selected_month == 4) selected @endif>{{ __('month_april') }}</option>
                                        <option value="5" @if($selected_month == 5) selected @endif>{{ __('month_may') }}</option>
                                        <option value="6" @if($selected_month == 6) selected @endif>{{ __('month_june') }}</option>
                                        <option value="7" @if($selected_month == 7) selected @endif>{{ __('month_july') }}</option>
                                        <option value="8" @if($selected_month == 8) selected @endif>{{ __('month_august') }}</option>
                                        <option value="9" @if($selected_month == 9) selected @endif>{{ __('month_september') }}</option>
                                        <option value="10" @if($selected_month == 10) selected @endif>{{ __('month_october') }}</option>
                                        <option value="11" @if($selected_month == 11) selected @endif>{{ __('month_november') }}</option>
                                        <option value="12" @if($selected_month == 12) selected @endif>{{ __('month_december') }}</option>
                                    </select>

                                    <div class="invalid-feedback">
                                      {{ __('required_field') }} {{ __('field_month') }}
                                    </div>
                                </div>
                                <div class="form-group col-md-2">
                                    <label for="year">{{ __('field_year') }} <span>*</span></label>
                                    <select class="form-control" name="year" id="year" required>
                                        <option value="{{ date("Y") }}" @if($selected_year == date("Y")) selected @endif>{{ date("Y") }}</option>
                                        <option value="{{ date("Y") - 1 }}" @if($selected_year == date("Y") - 1) selected @endif>{{ date("Y") - 1 }}</option>
                                        <option value="{{ date("Y") - 2 }}" @if($selected_year == date("Y") - 2) selected @endif>{{ date("Y") - 2 }}</option>
                                        <option value="{{ date("Y") - 3 }}" @if($selected_year == date("Y") - 3) selected @endif>{{ date("Y") - 3 }}</option>
                                        <option value="{{ date("Y") - 4 }}" @if($selected_year == date("Y") - 4) selected @endif>{{ date("Y") - 4 }}</option>
                                        <option value="{{ date("Y") - 5 }}" @if($selected_year == date("Y") - 5) selected @endif>{{ date("Y") - 5 }}</option>
                                        <option value="{{ date("Y") - 6 }}" @if($selected_year == date("Y") - 6) selected @endif>{{ date("Y") - 6 }}</option>
                                        <option value="{{ date("Y") - 7 }}" @if($selected_year == date("Y") - 7) selected @endif>{{ date("Y") - 7 }}</option>
                                        <option value="{{ date("Y") - 8 }}" @if($selected_year == date("Y") - 8) selected @endif>{{ date("Y") - 8 }}</option>
                                        <option value="{{ date("Y") - 9 }}" @if($selected_year == date("Y") - 9) selected @endif>{{ date("Y") - 9 }}</option>
                                    </select>

                                    <div class="invalid-feedback">
                                      {{ __('required_field') }} {{ __('field_year') }}
                                    </div>
                                </div>
                                <div class="form-group col-md-2">
                                    <button type="submit" class="btn btn-info btn-filter"><i class="fas fa-search"></i> {{ __('btn_filter') }}</button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-sm-12">
                <div class="card">
                    @if(isset($rows))
                    <div class="card-block">
                        @php
                            $bulk = session('payroll_bulk_result');
                            $listQuery = ['month' => $selected_month, 'year' => $selected_year, 'salary_type' => $selected_salary_type ?: null];
                        @endphp

                        @if($bulk)
                        <div class="alert {{ empty($bulk['skipped']) ? 'alert-success' : 'alert-warning' }} payroll-bulk-result">
                            <strong>{{ count($bulk['done']) }} {{ $bulk['verb'] }}@if(count($bulk['skipped'])), {{ count($bulk['skipped']) }} {{ __('skipped') }}@endif.</strong>
                            @if(count($bulk['skipped']))
                            <ul class="mb-0 mt-1">
                                @foreach($bulk['skipped'] as $line)<li>{{ $line }}</li>@endforeach
                            </ul>
                            @endif
                            @foreach($bulk['notes'] as $note)<div class="mt-1"><small>{{ $note }}</small></div>@endforeach
                        </div>
                        @endif

                        @can($access.'-action')
                        <form id="payrollBulkForm" action="{{ route($route.'.bulk') }}" method="post" class="d-none"
                              data-review="{{ __('I have checked each salary, the account it is paid from and the ledger entries, and confirm these payments.') }}"
                              data-review-label="{{ __('Pay and post') }}">
                            @csrf
                            <input type="hidden" name="action" value="">
                            <input type="hidden" name="month" value="{{ $selected_month }}">
                            <input type="hidden" name="year" value="{{ $selected_year }}">
                        </form>

                        <div class="d-flex flex-wrap align-items-center mb-3 payroll-toolbar" style="gap: 8px;">
                            <button type="button" class="btn btn-sm btn-primary" data-needs-pick data-bulk-action="generate"
                                data-confirm="{{ __('Generate the payroll for every ticked staff member who has none this month?') }}">
                                <i class="fas fa-plus"></i> {{ __('Generate ticked') }}
                            </button>
                            @if($settings->require_approval)
                            @can($access.'-approve')
                            <button type="button" class="btn btn-sm btn-info" data-needs-pick data-bulk-action="approve"
                                data-confirm="{{ __('Approve the generated payroll of every ticked staff member?') }}">
                                <i class="fas fa-check-double"></i> {{ __('Approve ticked') }}
                            </button>
                            @endcan
                            @endif
                            <button type="button" class="btn btn-sm btn-success" data-needs-pick data-bs-toggle="modal" data-bs-target="#payrollBulkPayModal">
                                <i class="fas fa-money-check"></i> {{ __('Pay ticked') }}
                            </button>
                            <div class="dropdown d-inline-block">
                                <button class="btn btn-sm btn-outline-dark dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                    <i class="fas fa-file-csv"></i> {{ __('Bank file') }}
                                </button>
                                <div class="dropdown-menu">
                                    <a class="dropdown-item" data-bank-file href="{{ route($route.'.bank-file', $listQuery + ['which' => 'unpaid']) }}">{{ __('Payrolls to pay (not yet paid)') }}</a>
                                    <a class="dropdown-item" data-bank-file href="{{ route($route.'.bank-file', $listQuery + ['which' => 'paid']) }}">{{ __('Payrolls already paid') }}</a>
                                    <a class="dropdown-item" data-bank-file href="{{ route($route.'.bank-file', $listQuery + ['which' => 'all']) }}">{{ __('All payrolls this month') }}</a>
                                    <div class="dropdown-divider"></div>
                                    <span class="dropdown-item-text small text-muted">{{ __('Only the ticked staff, if any are ticked.') }}</span>
                                </div>
                            </div>
                            <small class="text-muted ml-2" id="payrollPickCount"></small>
                        </div>

                        <div class="modal fade" id="payrollBulkPayModal" tabindex="-1" role="dialog" aria-hidden="true">
                            <div class="modal-dialog" role="document">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title">{{ __('Pay ticked payrolls') }}</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                                    </div>
                                    <div class="modal-body">
                                        <p class="small text-muted">{{ __('Each ticked payroll is paid into the staff member\'s default payment account. Anyone not generated, not approved or without an account is skipped and listed.') }}</p>
                                        <div class="form-group">
                                            <label class="form-label" for="bulk_pay_date">{{ __('field_pay_date') }} <span>*</span></label>
                                            <input type="date" class="form-control" name="pay_date" id="bulk_pay_date" form="payrollBulkForm" value="{{ date('Y-m-d') }}" max="{{ date('Y-m-d') }}" required>
                                        </div>
                                        <div class="form-group">
                                            <label class="form-label" for="bulk_payment_method">{{ __('field_payment_method') }} <span>*</span></label>
                                            <select class="form-control" name="payment_method" id="bulk_payment_method" form="payrollBulkForm" required>
                                                <option value="4">{{ __('payment_method_bank') }}</option>
                                                <option value="2">{{ __('payment_method_cash') }}</option>
                                                <option value="3">{{ __('payment_method_cheque') }}</option>
                                                <option value="5">{{ __('payment_method_e_wallet') }}</option>
                                            </select>
                                        </div>
                                        <div class="form-group">
                                            <label class="form-label" for="bulk_note">{{ __('field_note') }}</label>
                                            <input type="text" class="form-control" name="note" id="bulk_note" form="payrollBulkForm" maxlength="255">
                                        </div>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><i class="fas fa-times"></i> {{ __('btn_close') }}</button>
                                        <button type="button" class="btn btn-success" data-bulk-action="pay"><i class="fas fa-money-check"></i> {{ __('Pay ticked') }}</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        @endcan

                        <!-- [ Data table ] start -->
                        <div class="table-responsive">
                            <table class="display table nowrap table-striped table-hover" style="width:100%">
                                <thead>
                                    <tr>
                                        <th style="width: 28px;"><input type="checkbox" id="payrollPickAll" title="{{ __('Tick all') }}"></th>
                                        <th>{{ __('field_staff_id') }}</th>
                                        <th>{{ __('field_name') }}</th>
                                        <th>{{ __('field_department') }}</th>
                                        <th>{{ __('field_designation') }}</th>
                                        <th>{{ __('field_salary_type') }}</th>
                                        <th>{{ __('field_work_shift') }}</th>
                                        <th>{{ __('field_status') }}</th>
                                        <th>{{ __('field_action') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                  @foreach( $rows as $key => $row )
                                  @if($row->status == 1)
                                    <tr>
                                        <td><input type="checkbox" class="payroll-pick" name="users[]" value="{{ $row->id }}" form="payrollBulkForm"></td>
                                        <td>
                                            <a href="{{ route('admin.user.show', $row->id) }}">
                                                #{{ $row->staff_id }}
                                            </a>
                                        </td>
                                        <td>{{ $row->first_name }} {{ $row->last_name }}</td>
                                        <td>{{ $row->department->title ?? '' }}</td>
                                        <td>{{ $row->designation->title ?? '' }}</td>
                                        <td>
                                            @if( $row->salary_type == 1 )
                                            {{ __('salary_type_fixed') }}
                                            @elseif( $row->salary_type == 2 )
                                            {{ __('salary_type_hourly') }}
                                            @endif
                                        </td>
                                        <td>{{ $row->workShift->title ?? '' }}</td>
                                        @php
                                            $payroll_generate = 0;
                                            $payroll_status = 0;
                                        @endphp
                                        @if(isset($payrolls))
                                        @foreach( $payrolls as $payroll)
                                            @if($payroll->user_id == $row->id)
                                            @php
                                            $payroll_data = $payroll;
                                            $payroll_generate = 1;
                                            if($payroll->status == 1){
                                                $payroll_status = 1;
                                            }
                                            @endphp
                                            @endif
                                        @endforeach
                                        @endif
                                        <td>
                                            @if( $payroll_generate == 1 )
                                            @if($payroll_status == 1)
                                            <span class="badge badge-pill badge-success">{{ __('status_paid') }}</span>
                                            @if($payroll_data->payslip_emailed_at)<br><small class="text-muted" title="{{ $payroll_data->payslip_emailed_at }}"><i class="far fa-envelope"></i> {{ __('emailed') }}</small>@endif
                                            @elseif($settings->require_approval && $payroll_data->approved_at)
                                            <span class="badge badge-pill badge-info" title="{{ optional($payroll_data->approver)->first_name }} {{ optional($payroll_data->approver)->last_name }}">{{ __('Approved') }}</span>
                                            @elseif($settings->require_approval)
                                            <span class="badge badge-pill badge-warning">{{ __('Awaiting approval') }}</span>
                                            @else
                                            <span class="badge badge-pill badge-primary">{{ __('status_generated') }}</span>
                                            @endif
                                            @else
                                            <span class="badge badge-pill badge-danger">{{ __('status_not_generated') }}</span>
                                            @endif
                                        </td>
                                        <td>
                                        @if( $payroll_generate == 0 )
                                            @can($access.'-action')
                                            <a href="{{ route($route.'.generate', ['id' => $row->id, 'month' => $selected_month, 'year' => $selected_year]) }}" class="btn btn-icon btn-primary btn-sm">
                                                <i class="fas fa-plus"></i>
                                            </a>
                                            @endcan
                                        @else

                                            @if(isset($payroll_data))
                                            @if($payroll_status == 0)
                                            @if($settings->require_approval && !$payroll_data->approved_at)
                                            @can($access.'-approve')
                                            <form action="{{ route($route.'.approve', $payroll_data->id) }}" method="post" class="d-inline">
                                                @csrf
                                                <button type="submit" class="btn btn-info btn-sm"><i class="fas fa-check-double"></i> {{ __('Approve') }}</button>
                                            </form>
                                            @endcan
                                            @else
                                            @can($access.'-action')
                                            <button type="button" class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#payModal-{{ $row->id }}">
                                                <i class="fas fa-money-check"></i> {{ __('btn_pay') }}
                                            </button>
                                            <!-- Include Edit modal -->
                                            @include($view.'.pay')
                                            @endcan
                                            @endif

                                            @can($access.'-action')
                                            <a href="{{ route($route.'.generate', ['id' => $row->id, 'month' => $selected_month, 'year' => $selected_year]) }}" class="btn btn-icon btn-primary btn-sm">
                                                <i class="far fa-edit"></i>
                                            </a>
                                            @endcan

                                            @else
                                            @can($access.'-print')
                                            @if(isset($print) && isset($payroll_data))
                                            <a href="#" class="btn btn-icon btn-dark btn-sm" onclick="PopupWin('{{ route($route.'.print', ['id' => $payroll_data->id]) }}', '{{ $title }}', 1000, 600);">
                                                <i class="fas fa-print"></i>
                                            </a>
                                            @endif
                                            @endcan

                                            @can($access.'-action')
                                            <form action="{{ route($route.'.email', $payroll_data->id) }}" method="post" class="d-inline">
                                                @csrf
                                                <button type="submit" class="btn btn-icon btn-outline-primary btn-sm" title="{{ __('Email the payslip') }}"><i class="far fa-envelope"></i></button>
                                            </form>
                                            <button type="button" class="btn btn-icon btn-danger btn-sm" title="{{ __('status_unpaid') }}" data-bs-toggle="modal" data-bs-target="#unpayModal-{{ $row->id }}">
                                                <i class="fas fa-undo"></i>
                                            </button>
                                            <!-- Include Unpay modal -->
                                            @include($view.'.unpay')
                                            @endcan
                                            @endif
                                            @endif
                                            
                                        @endif
                                        </td>
                                    </tr>
                                  @endif
                                  @endforeach
                                </tbody>
                                
                                <caption>{{ date("F Y", strtotime($selected_year.'-'.$selected_month.'-01')) ?? '' }}</caption>
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
        (function () {
            var all = document.getElementById('payrollPickAll');
            var picks = function () { return Array.prototype.slice.call(document.querySelectorAll('.payroll-pick')); };
            var count = document.getElementById('payrollPickCount');
            var refresh = function () {
                var n = picks().filter(function (c) { return c.checked; }).length;
                if (count) { count.textContent = n ? n + ' {{ __('ticked') }}' : '{{ __('Tick staff to act on several at once') }}'; }
                document.querySelectorAll('[data-needs-pick]').forEach(function (b) { b.disabled = n === 0; });
            };
            if (all) { all.addEventListener('change', function () { picks().forEach(function (c) { c.checked = all.checked; }); refresh(); }); }
            picks().forEach(function (c) { c.addEventListener('change', refresh); });
            document.querySelectorAll('[data-bulk-action]').forEach(function (b) {
                b.addEventListener('click', function () {
                    var form = document.getElementById('payrollBulkForm');
                    form.querySelector('input[name="action"]').value = b.getAttribute('data-bulk-action');
                    var question = b.getAttribute('data-confirm');
                    if (question && !confirm(question)) { return; }
                    // Paying posts money, so it goes through the review dialog
                    // (requestSubmit fires the submit event the dialog listens
                    // for). Generating and approving post nothing.
                    if (b.getAttribute('data-bulk-action') === 'pay' && form.requestSubmit) {
                        form.removeAttribute('data-reviewed');
                        form.requestSubmit();
                    } else {
                        HTMLFormElement.prototype.submit.call(form);
                    }
                });
            });
            document.querySelectorAll('[data-bank-file]').forEach(function (a) {
                a.addEventListener('click', function (e) {
                    e.preventDefault();
                    var url = new URL(a.getAttribute('href'), window.location.href);
                    picks().filter(function (c) { return c.checked; }).forEach(function (c) { url.searchParams.append('users[]', c.value); });
                    window.location.href = url.toString();
                });
            });
            refresh();
        })();
    </script>
    <script type="text/javascript">
        "use strict";
        function salaryCalculator(type, id) {
          // Get Data
          var total_earning = $("input[name='total_earning'][data_id='"+type+"-"+id+"']").val();
          var bonus = $("input[name='bonus'][data_id='"+type+"-"+id+"']").val();
          var gross_salary = $("input[name='gross_salary'][data_id='"+type+"-"+id+"']").val();
          var deduction_salary = $("input[name='deduction_salary'][data_id='"+type+"-"+id+"']").val();
          var total_deduction = $("input[name='total_deduction'][data_id='"+type+"-"+id+"']").val();
          var tax_amount = $("input[name='tax'][data_id='"+type+"-"+id+"']").val();
          var net_salary = $("input[name='net_salary'][data_id='"+type+"-"+id+"']").val();

          // Pass Bonus
          if (isNaN(bonus)) bonus = 0;
          $("input[name='bonus'][data_id='"+type+"-"+id+"']").val(Math.ceil(bonus));

          // Total Gross
          var total_gross = parseFloat(total_earning) + parseFloat(bonus);

            // Calculate Tax (based on total_earning only)
            @php
            if(isset($taxs)){
            foreach($taxs as $key =>$value){
                $taxs[$key] = json_decode(json_encode($value));
            }
            @endphp

            var taxs = <?php echo json_encode($taxs); ?>;

            var i;
            for (i = 0; i < taxs.length; ++i) {
                // Use total_earning for tax bracket matching
                if(taxs[i]['min_amount'] <= parseFloat(total_earning) && taxs[i]['max_amount'] >= parseFloat(total_earning)){

                    var taxable_amount = parseFloat(total_earning) - taxs[i]['max_no_taxable_amount'];

                    // Check tax type: 1 = percentage, 2 = fixed amount
                    if(taxs[i]['tax_type'] == 2) {
                        var tax_amount = parseFloat(taxs[i]['fixed_amount']);
                    } else {
                        var tax_amount = (taxable_amount / 100) * taxs[i]['percentange'];
                    }
                }
            }
            @php } @endphp
            

          // Net Total
          var total_deduction = parseFloat(deduction_salary) + parseFloat(tax_amount);
          var net_total = parseFloat(total_gross) - parseFloat(tax_amount);

          // Pass Data
          $("input[name='gross_salary'][data_id='"+type+"-"+id+"']").val(Math.ceil(total_gross));
          $("input[name='tax'][data_id='"+type+"-"+id+"']").val(Math.ceil(tax_amount));
          $("input[name='total_deduction'][data_id='"+type+"-"+id+"']").val(Math.ceil(total_deduction));
          $("input[name='net_salary'][data_id='"+type+"-"+id+"']").val(Math.ceil(net_total));
        }
    </script>
@endsection