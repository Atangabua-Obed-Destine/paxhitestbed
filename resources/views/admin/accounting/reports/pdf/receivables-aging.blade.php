@extends('admin.accounting.reports.pdf.layout')

@section('report_title', __('Receivables Aging Report'))

@section('content')
    @if(isset($data['as_of_date']))
    <p class="text-center" style="margin-bottom: 20px;">
        <strong>{{ __('As of Date') }}:</strong> {{ $data['as_of_date'] }}
    </p>
    @endif

    <!-- Summary Section -->
    @if(isset($data['summary']))
    <div class="summary-section">
        <div class="summary-box">
            <div class="label">{{ __('Current (0-30 days)') }}</div>
            <div class="value text-success">{{ format_amount($data['summary']['current'] ?? 0) }} XAF</div>
        </div>
        <div class="summary-box">
            <div class="label">{{ __('31-60 Days') }}</div>
            <div class="value">{{ format_amount($data['summary']['days_31_60'] ?? 0) }} XAF</div>
        </div>
        <div class="summary-box">
            <div class="label">{{ __('61-90 Days') }}</div>
            <div class="value text-warning">{{ format_amount($data['summary']['days_61_90'] ?? 0) }} XAF</div>
        </div>
        <div class="summary-box">
            <div class="label">{{ __('91-120 Days') }}</div>
            <div class="value" style="color: #fd7e14;">{{ format_amount($data['summary']['days_91_120'] ?? 0) }} XAF</div>
        </div>
        <div class="summary-box">
            <div class="label">{{ __('Over 120 Days') }}</div>
            <div class="value text-danger">{{ format_amount($data['summary']['over_120'] ?? 0) }} XAF</div>
        </div>
        <div class="summary-box">
            <div class="label">{{ __('Total Outstanding') }}</div>
            <div class="value font-bold">{{ format_amount($data['summary']['total'] ?? 0) }} XAF</div>
        </div>
    </div>
    @endif

    <!-- Details Table -->
    <div class="section-title">{{ __('Aging Details by Customer') }}</div>
    <table>
        <thead>
            <tr>
                <th>{{ __('Customer') }}</th>
                <th class="text-right">{{ __('Current') }}</th>
                <th class="text-right">{{ __('31-60 Days') }}</th>
                <th class="text-right">{{ __('61-90 Days') }}</th>
                <th class="text-right">{{ __('91-120 Days') }}</th>
                <th class="text-right">{{ __('Over 120 Days') }}</th>
                <th class="text-right">{{ __('Total') }}</th>
            </tr>
        </thead>
        <tbody>
            @forelse($data['details'] ?? [] as $item)
            <tr>
                <td>{{ $item['customer_name'] ?? '' }}</td>
                <td class="text-right">{{ format_amount($item['current'] ?? 0) }}</td>
                <td class="text-right">{{ format_amount($item['days_31_60'] ?? 0) }}</td>
                <td class="text-right">{{ format_amount($item['days_61_90'] ?? 0) }}</td>
                <td class="text-right">{{ format_amount($item['days_91_120'] ?? 0) }}</td>
                <td class="text-right text-danger">{{ format_amount($item['over_120'] ?? 0) }}</td>
                <td class="text-right font-bold">{{ format_amount($item['total'] ?? 0) }}</td>
            </tr>
            @empty
            <tr>
                <td colspan="7" class="text-center">{{ __('No receivables data found') }}</td>
            </tr>
            @endforelse
        </tbody>
        @if(isset($data['summary']))
        <tfoot>
            <tr class="totals-row">
                <td>{{ __('Grand Total') }}</td>
                <td class="text-right">{{ format_amount($data['summary']['current'] ?? 0) }}</td>
                <td class="text-right">{{ format_amount($data['summary']['days_31_60'] ?? 0) }}</td>
                <td class="text-right">{{ format_amount($data['summary']['days_61_90'] ?? 0) }}</td>
                <td class="text-right">{{ format_amount($data['summary']['days_91_120'] ?? 0) }}</td>
                <td class="text-right text-danger">{{ format_amount($data['summary']['over_120'] ?? 0) }}</td>
                <td class="text-right">{{ format_amount($data['summary']['total'] ?? 0) }}</td>
            </tr>
        </tfoot>
        @endif
    </table>
@endsection
