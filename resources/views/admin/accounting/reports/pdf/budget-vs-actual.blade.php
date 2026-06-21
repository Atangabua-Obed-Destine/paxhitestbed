@extends('admin.accounting.reports.pdf.layout')

@section('report_title', __('Budget vs Actual Report'))

@section('content')
    @if(isset($data['fiscal_year']))
    <p class="text-center" style="margin-bottom: 20px;">
        <strong>{{ __('Fiscal Year') }}:</strong> {{ $data['fiscal_year']['name'] ?? '' }}
    </p>
    @endif

    <!-- Summary Section -->
    @if(isset($data['summary']))
    <div class="summary-section">
        <div class="summary-box">
            <div class="label">{{ __('Total Budget') }}</div>
            <div class="value">{{ number_format($data['summary']['total_budget'] ?? 0) }} XAF</div>
        </div>
        <div class="summary-box">
            <div class="label">{{ __('Total Actual') }}</div>
            <div class="value">{{ number_format($data['summary']['total_actual'] ?? 0) }} XAF</div>
        </div>
        <div class="summary-box">
            <div class="label">{{ __('Total Variance') }}</div>
            <div class="value {{ ($data['summary']['total_variance'] ?? 0) >= 0 ? 'text-success' : 'text-danger' }}">
                {{ number_format($data['summary']['total_variance'] ?? 0) }} XAF
            </div>
        </div>
        <div class="summary-box">
            <div class="label">{{ __('Utilization Rate') }}</div>
            <div class="value">{{ number_format($data['summary']['utilization_rate'] ?? 0, 1) }}%</div>
        </div>
    </div>
    @endif

    <!-- Details by Category -->
    <div class="section-title">{{ __('Budget Details by Account') }}</div>
    <table>
        <thead>
            <tr>
                <th>{{ __('Account Code') }}</th>
                <th>{{ __('Account Name') }}</th>
                <th class="text-right">{{ __('Budget') }}</th>
                <th class="text-right">{{ __('Actual') }}</th>
                <th class="text-right">{{ __('Variance') }}</th>
                <th class="text-right">{{ __('% Used') }}</th>
                <th class="text-center">{{ __('Status') }}</th>
            </tr>
        </thead>
        <tbody>
            @forelse($data['details'] ?? [] as $item)
            <tr>
                <td>{{ $item['account_code'] ?? '' }}</td>
                <td>{{ $item['account_name'] ?? '' }}</td>
                <td class="text-right">{{ number_format($item['budget'] ?? 0) }}</td>
                <td class="text-right">{{ number_format($item['actual'] ?? 0) }}</td>
                <td class="text-right {{ ($item['variance'] ?? 0) >= 0 ? 'text-success' : 'text-danger' }}">
                    {{ number_format($item['variance'] ?? 0) }}
                </td>
                <td class="text-right">{{ number_format($item['percentage_used'] ?? 0, 1) }}%</td>
                <td class="text-center">
                    @php
                        $percentUsed = $item['percentage_used'] ?? 0;
                        if ($percentUsed > 100) {
                            $statusClass = 'text-danger';
                            $status = __('Over Budget');
                        } elseif ($percentUsed >= 80) {
                            $statusClass = 'text-warning';
                            $status = __('Nearing Limit');
                        } else {
                            $statusClass = 'text-success';
                            $status = __('On Track');
                        }
                    @endphp
                    <span class="{{ $statusClass }}">{{ $status }}</span>
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="7" class="text-center">{{ __('No budget data found') }}</td>
            </tr>
            @endforelse
        </tbody>
        @if(isset($data['summary']))
        <tfoot>
            <tr class="totals-row">
                <td colspan="2">{{ __('Grand Total') }}</td>
                <td class="text-right">{{ number_format($data['summary']['total_budget'] ?? 0) }}</td>
                <td class="text-right">{{ number_format($data['summary']['total_actual'] ?? 0) }}</td>
                <td class="text-right {{ ($data['summary']['total_variance'] ?? 0) >= 0 ? 'text-success' : 'text-danger' }}">
                    {{ number_format($data['summary']['total_variance'] ?? 0) }}
                </td>
                <td class="text-right">{{ number_format($data['summary']['utilization_rate'] ?? 0, 1) }}%</td>
                <td></td>
            </tr>
        </tfoot>
        @endif
    </table>

    <!-- Variance Analysis -->
    @if(isset($data['over_budget_items']) && count($data['over_budget_items']) > 0)
    <div class="section-title text-danger">{{ __('Over Budget Items') }}</div>
    <table>
        <thead>
            <tr>
                <th>{{ __('Account') }}</th>
                <th class="text-right">{{ __('Budget') }}</th>
                <th class="text-right">{{ __('Actual') }}</th>
                <th class="text-right">{{ __('Over By') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach($data['over_budget_items'] as $item)
            <tr>
                <td>{{ $item['account_code'] ?? '' }} - {{ $item['account_name'] ?? '' }}</td>
                <td class="text-right">{{ number_format($item['budget'] ?? 0) }}</td>
                <td class="text-right">{{ number_format($item['actual'] ?? 0) }}</td>
                <td class="text-right text-danger font-bold">{{ number_format(abs($item['variance'] ?? 0)) }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
    @endif
@endsection
