@extends('admin.accounting.reports.pdf.layout')

@section('report_title', __('Cash Flow Statement'))

@section('content')
    @if(isset($data['fiscal_year']))
    <p class="text-center" style="margin-bottom: 20px;">
        <strong>{{ __('Fiscal Year') }}:</strong> {{ $data['fiscal_year'] }}
        ({{ \Carbon\Carbon::parse($data['start_date'])->format('d/m/Y') }} - {{ \Carbon\Carbon::parse($data['end_date'])->format('d/m/Y') }})
    </p>
    @endif

    <!-- Operating Activities -->
    <div class="section-title">{{ __('Operating Activities') }}</div>
    <table>
        <tbody>
            @if(isset($data['operating_activities']))
                @foreach($data['operating_activities']['items'] ?? [] as $item)
                <tr>
                    <td>{{ $item['description'] ?? '' }}</td>
                    <td class="text-right">{{ format_amount($item['amount'] ?? 0) }} XAF</td>
                </tr>
                @endforeach
            @endif
        </tbody>
        <tfoot>
            <tr class="totals-row">
                <td>{{ __('Net Cash from Operating Activities') }}</td>
                <td class="text-right">{{ format_amount($data['operating_activities']['total'] ?? 0) }} XAF</td>
            </tr>
        </tfoot>
    </table>

    <!-- Investing Activities -->
    <div class="section-title">{{ __('Investing Activities') }}</div>
    <table>
        <tbody>
            @if(isset($data['investing_activities']))
                @foreach($data['investing_activities']['items'] ?? [] as $item)
                <tr>
                    <td>{{ $item['description'] ?? '' }}</td>
                    <td class="text-right">{{ format_amount($item['amount'] ?? 0) }} XAF</td>
                </tr>
                @endforeach
            @endif
        </tbody>
        <tfoot>
            <tr class="totals-row">
                <td>{{ __('Net Cash from Investing Activities') }}</td>
                <td class="text-right">{{ format_amount($data['investing_activities']['total'] ?? 0) }} XAF</td>
            </tr>
        </tfoot>
    </table>

    <!-- Financing Activities -->
    <div class="section-title">{{ __('Financing Activities') }}</div>
    <table>
        <tbody>
            @if(isset($data['financing_activities']))
                @foreach($data['financing_activities']['items'] ?? [] as $item)
                <tr>
                    <td>{{ $item['description'] ?? '' }}</td>
                    <td class="text-right">{{ format_amount($item['amount'] ?? 0) }} XAF</td>
                </tr>
                @endforeach
            @endif
        </tbody>
        <tfoot>
            <tr class="totals-row">
                <td>{{ __('Net Cash from Financing Activities') }}</td>
                <td class="text-right">{{ format_amount($data['financing_activities']['total'] ?? 0) }} XAF</td>
            </tr>
        </tfoot>
    </table>

    <!-- Summary -->
    <div class="section-title">{{ __('Cash Flow Summary') }}</div>
    <table>
        <tbody>
            <tr>
                <td>{{ __('Net Increase/(Decrease) in Cash') }}</td>
                <td class="text-right font-bold {{ ($data['net_change_in_cash'] ?? 0) >= 0 ? 'text-success' : 'text-danger' }}">
                    {{ format_amount($data['net_change_in_cash'] ?? 0) }} XAF
                </td>
            </tr>
            <tr>
                <td>{{ __('Beginning Cash Balance') }}</td>
                <td class="text-right">{{ format_amount($data['beginning_cash_balance'] ?? 0) }} XAF</td>
            </tr>
        </tbody>
        <tfoot>
            <tr class="totals-row">
                <td>{{ __('Ending Cash Balance') }}</td>
                <td class="text-right">{{ format_amount($data['ending_cash_balance'] ?? 0) }} XAF</td>
            </tr>
        </tfoot>
    </table>
@endsection
