@extends('admin.layouts.master')
@section('title', $title)
@section('content')

@php
    $dp = $setting->decimal_place ?? 2;
    $cs = $setting->currency_symbol ?? '';
@endphp

<div class="main-body">
    <div class="page-wrapper">
        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header">
                        <h5>{{ $title }}</h5>
                    </div>
                    <div class="card-block">
                        <p class="text-muted">{{ __('Your payslips for every month you have been paid. Open one to print it, or download it as a PDF.') }}</p>
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>{{ __('Month') }}</th>
                                        <th>{{ __('Paid on') }}</th>
                                        <th class="text-right">{{ __('Gross') }}</th>
                                        <th class="text-right">{{ __('Tax') }}</th>
                                        <th class="text-right">{{ __('Net pay') }}</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($rows as $row)
                                    <tr>
                                        <td>{{ date('F Y', strtotime($row->salary_month)) }}</td>
                                        <td>{{ $row->pay_date ? date($setting->date_format ?? 'd M Y', strtotime($row->pay_date)) : '-' }}</td>
                                        <td class="text-right">{{ number_format((float) $row->gross_salary, $dp) }}</td>
                                        <td class="text-right">{{ number_format((float) $row->tax, $dp) }}</td>
                                        <td class="text-right"><strong>{{ number_format((float) $row->net_salary, $dp) }} {!! $cs !!}</strong></td>
                                        <td class="text-right">
                                            <a href="#" class="btn btn-sm btn-dark" onclick="PopupWin('{{ route('admin.my-payslips.show', $row->id) }}', '{{ __('Payslip') }}', 1000, 600); return false;"><i class="fas fa-print"></i> {{ __('Open') }}</a>
                                            <a href="{{ route('admin.my-payslips.pdf', $row->id) }}" class="btn btn-sm btn-outline-primary"><i class="fas fa-download"></i> PDF</a>
                                        </td>
                                    </tr>
                                    @empty
                                    <tr><td colspan="6" class="text-center text-muted">{{ __('No paid payslips yet.') }}</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@endsection
