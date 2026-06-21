@extends('admin.accounting.reports.pdf.layout')

@section('report_title', __('Cash Flow Statement'))

@section('content')
    @if(isset($data['fiscal_year']))
    <p class="text-center" style="margin-bottom: 20px;">
        <strong>{{ __('Fiscal Year') }}:</strong> {{ $data['fiscal_year']['name'] ?? '' }}
        ({{ $data['fiscal_year']['start_date'] ?? '' }} - {{ $data['fiscal_year']['end_date'] ?? '' }})
    </p>
    @endif

    <!-- Operating Activities -->
    <div class="section-title">{{ __('Operating Activities') }}</div>
    <table>
        <tbody>
            @if(isset($data['operating']))
                @foreach($data['operating']['items'] ?? [] as $item)
                <tr>
                    <td>{{ $item['description'] ?? '' }}</td>
                    <td class="text-right">{{ number_format($item['amount'] ?? 0) }} XAF</td>
                </tr>
                @endforeach
            @endif
        </tbody>
        <tfoot>
            <tr class="totals-row">
                <td>{{ __('Net Cash from Operating Activities') }}</td>
                <td class="text-right">{{ number_format($data['operating']['total'] ?? 0) }} XAF</td>
            </tr>
        </tfoot>
    </table>

    <!-- Investing Activities -->
    <div class="section-title">{{ __('Investing Activities') }}</div>
    <table>
        <tbody>
            @if(isset($data['investing']))
                @foreach($data['investing']['items'] ?? [] as $item)
                <tr>
                    <td>{{ $item['description'] ?? '' }}</td>
                    <td class="text-right">{{ number_format($item['amount'] ?? 0) }} XAF</td>
                </tr>
                @endforeach
            @endif
        </tbody>
        <tfoot>
            <tr class="totals-row">
                <td>{{ __('Net Cash from Investing Activities') }}</td>
                <td class="text-right">{{ number_format($data['investing']['total'] ?? 0) }} XAF</td>
            </tr>
        </tfoot>
    </table>

    <!-- Financing Activities -->
    <div class="section-title">{{ __('Financing Activities') }}</div>
    <table>
        <tbody>
            @if(isset($data['financing']))
                @foreach($data['financing']['items'] ?? [] as $item)
                <tr>
                    <td>{{ $item['description'] ?? '' }}</td>
                    <td class="text-right">{{ number_format($item['amount'] ?? 0) }} XAF</td>
                </tr>
                @endforeach
            @endif
        </tbody>
        <tfoot>
            <tr class="totals-row">
                <td>{{ __('Net Cash from Financing Activities') }}</td>
                <td class="text-right">{{ number_format($data['financing']['total'] ?? 0) }} XAF</td>
            </tr>
        </tfoot>
    </table>

    <!-- Summary -->
    <div class="section-title">{{ __('Cash Flow Summary') }}</div>
    <table>
        <tbody>
            <tr>
                <td>{{ __('Net Increase/(Decrease) in Cash') }}</td>
                <td class="text-right font-bold {{ ($data['net_change'] ?? 0) >= 0 ? 'text-success' : 'text-danger' }}">
                    {{ number_format($data['net_change'] ?? 0) }} XAF
                </td>
            </tr>
            <tr>
                <td>{{ __('Beginning Cash Balance') }}</td>
                <td class="text-right">{{ number_format($data['beginning_cash'] ?? 0) }} XAF</td>
            </tr>
        </tbody>
        <tfoot>
            <tr class="totals-row">
                <td>{{ __('Ending Cash Balance') }}</td>
                <td class="text-right">{{ number_format($data['ending_cash'] ?? 0) }} XAF</td>
            </tr>
        </tfoot>
    </table>
@endsection
