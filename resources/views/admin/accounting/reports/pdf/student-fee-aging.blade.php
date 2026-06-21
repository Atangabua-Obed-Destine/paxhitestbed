@extends('admin.accounting.reports.pdf.layout')

@section('report_title', __('Student Fee Aging Report'))

@section('content')
    @if(isset($data['filters']))
    <p class="text-center" style="margin-bottom: 20px;">
        @if(isset($data['filters']['as_of_date']))
        <strong>{{ __('As of Date') }}:</strong> {{ $data['filters']['as_of_date'] }}
        @endif
        @if(isset($data['filters']['program']))
        | <strong>{{ __('Program') }}:</strong> {{ $data['filters']['program'] }}
        @endif
        @if(isset($data['filters']['batch']))
        | <strong>{{ __('Batch') }}:</strong> {{ $data['filters']['batch'] }}
        @endif
    </p>
    @endif

    <!-- Summary Section -->
    @if(isset($data['summary']))
    <div class="summary-section">
        <div class="summary-box">
            <div class="label">{{ __('Current (0-30 days)') }}</div>
            <div class="value text-success">{{ number_format($data['summary']['current'] ?? 0) }} XAF</div>
        </div>
        <div class="summary-box">
            <div class="label">{{ __('31-60 Days') }}</div>
            <div class="value">{{ number_format($data['summary']['days_31_60'] ?? 0) }} XAF</div>
        </div>
        <div class="summary-box">
            <div class="label">{{ __('61-90 Days') }}</div>
            <div class="value text-warning">{{ number_format($data['summary']['days_61_90'] ?? 0) }} XAF</div>
        </div>
        <div class="summary-box">
            <div class="label">{{ __('91-120 Days') }}</div>
            <div class="value" style="color: #fd7e14;">{{ number_format($data['summary']['days_91_120'] ?? 0) }} XAF</div>
        </div>
        <div class="summary-box">
            <div class="label">{{ __('Over 120 Days') }}</div>
            <div class="value text-danger">{{ number_format($data['summary']['over_120'] ?? 0) }} XAF</div>
        </div>
        <div class="summary-box">
            <div class="label">{{ __('Total Outstanding') }}</div>
            <div class="value font-bold">{{ number_format($data['summary']['total'] ?? 0) }} XAF</div>
        </div>
    </div>
    @endif

    <!-- Details Table -->
    <div class="section-title">{{ __('Student Fee Aging Details') }}</div>
    <table>
        <thead>
            <tr>
                <th>{{ __('Matricule') }}</th>
                <th>{{ __('Student Name') }}</th>
                <th>{{ __('Program') }}</th>
                <th class="text-right">{{ __('Current') }}</th>
                <th class="text-right">{{ __('31-60 Days') }}</th>
                <th class="text-right">{{ __('61-90 Days') }}</th>
                <th class="text-right">{{ __('91-120 Days') }}</th>
                <th class="text-right">{{ __('Over 120') }}</th>
                <th class="text-right">{{ __('Total') }}</th>
            </tr>
        </thead>
        <tbody>
            @forelse($data['details'] ?? [] as $item)
            <tr>
                <td>{{ $item['matricule'] ?? '' }}</td>
                <td>{{ $item['student_name'] ?? '' }}</td>
                <td>{{ $item['program_name'] ?? '' }}</td>
                <td class="text-right">{{ number_format($item['current'] ?? 0) }}</td>
                <td class="text-right">{{ number_format($item['days_31_60'] ?? 0) }}</td>
                <td class="text-right">{{ number_format($item['days_61_90'] ?? 0) }}</td>
                <td class="text-right">{{ number_format($item['days_91_120'] ?? 0) }}</td>
                <td class="text-right text-danger">{{ number_format($item['over_120'] ?? 0) }}</td>
                <td class="text-right font-bold">{{ number_format($item['total'] ?? 0) }}</td>
            </tr>
            @empty
            <tr>
                <td colspan="9" class="text-center">{{ __('No student fee data found') }}</td>
            </tr>
            @endforelse
        </tbody>
        @if(isset($data['summary']))
        <tfoot>
            <tr class="totals-row">
                <td colspan="3">{{ __('Grand Total') }}</td>
                <td class="text-right">{{ number_format($data['summary']['current'] ?? 0) }}</td>
                <td class="text-right">{{ number_format($data['summary']['days_31_60'] ?? 0) }}</td>
                <td class="text-right">{{ number_format($data['summary']['days_61_90'] ?? 0) }}</td>
                <td class="text-right">{{ number_format($data['summary']['days_91_120'] ?? 0) }}</td>
                <td class="text-right text-danger">{{ number_format($data['summary']['over_120'] ?? 0) }}</td>
                <td class="text-right">{{ number_format($data['summary']['total'] ?? 0) }}</td>
            </tr>
        </tfoot>
        @endif
    </table>
@endsection
