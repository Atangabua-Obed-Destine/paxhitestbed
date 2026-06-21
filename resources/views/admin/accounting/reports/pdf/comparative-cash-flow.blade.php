@extends('admin.accounting.reports.pdf.layout')

@section('report_title', __('Comparative Cash Flow Statement'))

@section('content')
    @if(isset($data['periods']))
    <p class="text-center" style="margin-bottom: 20px;">
        <strong>{{ __('Comparing') }}:</strong> 
        {{ $data['periods']['current']['name'] ?? 'Current Period' }} 
        {{ __('vs') }} 
        {{ $data['periods']['previous']['name'] ?? 'Previous Period' }}
    </p>
    @endif

    <!-- Comparative Table -->
    <table>
        <thead>
            <tr>
                <th>{{ __('Description') }}</th>
                <th class="text-right">{{ $data['periods']['current']['name'] ?? __('Current') }}</th>
                <th class="text-right">{{ $data['periods']['previous']['name'] ?? __('Previous') }}</th>
                <th class="text-right">{{ __('Variance') }}</th>
                <th class="text-right">{{ __('Change %') }}</th>
            </tr>
        </thead>
        <tbody>
            <!-- Operating Activities -->
            <tr class="totals-row">
                <td colspan="5"><strong>{{ __('Operating Activities') }}</strong></td>
            </tr>
            @if(isset($data['operating']['items']))
                @foreach($data['operating']['items'] as $item)
                <tr>
                    <td class="indent-1">{{ $item['description'] ?? '' }}</td>
                    <td class="text-right">{{ number_format($item['current'] ?? 0) }}</td>
                    <td class="text-right">{{ number_format($item['previous'] ?? 0) }}</td>
                    <td class="text-right {{ ($item['variance'] ?? 0) >= 0 ? 'text-success' : 'text-danger' }}">
                        {{ number_format($item['variance'] ?? 0) }}
                    </td>
                    <td class="text-right {{ ($item['change_percent'] ?? 0) >= 0 ? 'text-success' : 'text-danger' }}">
                        {{ number_format($item['change_percent'] ?? 0, 1) }}%
                    </td>
                </tr>
                @endforeach
            @endif
            <tr style="background-color: #e9ecef;">
                <td><strong>{{ __('Net Cash from Operating') }}</strong></td>
                <td class="text-right font-bold">{{ number_format($data['operating']['current_total'] ?? 0) }}</td>
                <td class="text-right font-bold">{{ number_format($data['operating']['previous_total'] ?? 0) }}</td>
                <td class="text-right font-bold {{ ($data['operating']['variance'] ?? 0) >= 0 ? 'text-success' : 'text-danger' }}">
                    {{ number_format($data['operating']['variance'] ?? 0) }}
                </td>
                <td class="text-right font-bold {{ ($data['operating']['change_percent'] ?? 0) >= 0 ? 'text-success' : 'text-danger' }}">
                    {{ number_format($data['operating']['change_percent'] ?? 0, 1) }}%
                </td>
            </tr>

            <!-- Investing Activities -->
            <tr class="totals-row">
                <td colspan="5"><strong>{{ __('Investing Activities') }}</strong></td>
            </tr>
            @if(isset($data['investing']['items']))
                @foreach($data['investing']['items'] as $item)
                <tr>
                    <td class="indent-1">{{ $item['description'] ?? '' }}</td>
                    <td class="text-right">{{ number_format($item['current'] ?? 0) }}</td>
                    <td class="text-right">{{ number_format($item['previous'] ?? 0) }}</td>
                    <td class="text-right {{ ($item['variance'] ?? 0) >= 0 ? 'text-success' : 'text-danger' }}">
                        {{ number_format($item['variance'] ?? 0) }}
                    </td>
                    <td class="text-right {{ ($item['change_percent'] ?? 0) >= 0 ? 'text-success' : 'text-danger' }}">
                        {{ number_format($item['change_percent'] ?? 0, 1) }}%
                    </td>
                </tr>
                @endforeach
            @endif
            <tr style="background-color: #e9ecef;">
                <td><strong>{{ __('Net Cash from Investing') }}</strong></td>
                <td class="text-right font-bold">{{ number_format($data['investing']['current_total'] ?? 0) }}</td>
                <td class="text-right font-bold">{{ number_format($data['investing']['previous_total'] ?? 0) }}</td>
                <td class="text-right font-bold {{ ($data['investing']['variance'] ?? 0) >= 0 ? 'text-success' : 'text-danger' }}">
                    {{ number_format($data['investing']['variance'] ?? 0) }}
                </td>
                <td class="text-right font-bold {{ ($data['investing']['change_percent'] ?? 0) >= 0 ? 'text-success' : 'text-danger' }}">
                    {{ number_format($data['investing']['change_percent'] ?? 0, 1) }}%
                </td>
            </tr>

            <!-- Financing Activities -->
            <tr class="totals-row">
                <td colspan="5"><strong>{{ __('Financing Activities') }}</strong></td>
            </tr>
            @if(isset($data['financing']['items']))
                @foreach($data['financing']['items'] as $item)
                <tr>
                    <td class="indent-1">{{ $item['description'] ?? '' }}</td>
                    <td class="text-right">{{ number_format($item['current'] ?? 0) }}</td>
                    <td class="text-right">{{ number_format($item['previous'] ?? 0) }}</td>
                    <td class="text-right {{ ($item['variance'] ?? 0) >= 0 ? 'text-success' : 'text-danger' }}">
                        {{ number_format($item['variance'] ?? 0) }}
                    </td>
                    <td class="text-right {{ ($item['change_percent'] ?? 0) >= 0 ? 'text-success' : 'text-danger' }}">
                        {{ number_format($item['change_percent'] ?? 0, 1) }}%
                    </td>
                </tr>
                @endforeach
            @endif
            <tr style="background-color: #e9ecef;">
                <td><strong>{{ __('Net Cash from Financing') }}</strong></td>
                <td class="text-right font-bold">{{ number_format($data['financing']['current_total'] ?? 0) }}</td>
                <td class="text-right font-bold">{{ number_format($data['financing']['previous_total'] ?? 0) }}</td>
                <td class="text-right font-bold {{ ($data['financing']['variance'] ?? 0) >= 0 ? 'text-success' : 'text-danger' }}">
                    {{ number_format($data['financing']['variance'] ?? 0) }}
                </td>
                <td class="text-right font-bold {{ ($data['financing']['change_percent'] ?? 0) >= 0 ? 'text-success' : 'text-danger' }}">
                    {{ number_format($data['financing']['change_percent'] ?? 0, 1) }}%
                </td>
            </tr>
        </tbody>
        <tfoot>
            <tr class="totals-row" style="font-size: 14px;">
                <td><strong>{{ __('Net Change in Cash') }}</strong></td>
                <td class="text-right font-bold">{{ number_format($data['net_change']['current'] ?? 0) }} XAF</td>
                <td class="text-right font-bold">{{ number_format($data['net_change']['previous'] ?? 0) }} XAF</td>
                <td class="text-right font-bold {{ ($data['net_change']['variance'] ?? 0) >= 0 ? 'text-success' : 'text-danger' }}">
                    {{ number_format($data['net_change']['variance'] ?? 0) }} XAF
                </td>
                <td class="text-right font-bold {{ ($data['net_change']['change_percent'] ?? 0) >= 0 ? 'text-success' : 'text-danger' }}">
                    {{ number_format($data['net_change']['change_percent'] ?? 0, 1) }}%
                </td>
            </tr>
        </tfoot>
    </table>
@endsection
