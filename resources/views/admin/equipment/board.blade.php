@extends('admin.layouts.master')
@section('title', $title)
@section('content')
@include('admin.equipment._style')
<div class="main-body eq">
    <div class="page-wrapper">
        <div class="eq-head">
            <div>
                <h4 class="eq-title">{{ __('Out right now') }}</h4>
                <p class="eq-sub">{{ __('Everything that is out, the most overdue first. As of :time.', ['time' => now()->format('H:i')]) }}</p>
            </div>
            <div class="eq-head-side">
                @if($desks->count() > 1)
                <form method="get" class="eq-inline">
                    <select name="desk" class="eq-select" style="width:auto;min-height:38px;font-size:14px" onchange="this.form.submit()" aria-label="{{ __('Desk') }}">
                        <option value="">{{ __('All desks') }}</option>
                        @foreach($desks as $d)<option value="{{ $d->id }}" @selected($deskId === $d->id)>{{ $d->name }}</option>@endforeach
                    </select>
                </form>
                @endif
                @can('equipment-desk')
                <a class="eq-btn eq-btn-primary eq-btn-sm" href="{{ route('admin.equipment.desk') }}"><i class="fas fa-qrcode"></i> {{ __('Open the desk') }}</a>
                @endcan
            </div>
        </div>

        <div class="eq-sum">
            <span><b>{{ $summary['items'] }}</b> {{ trans_choice('item|items', $summary['items']) }}</span>
            <span><b>{{ $summary['out'] }}</b> {{ __('out') }}</span>
            <span class="{{ $summary['overdue'] ? 'late' : '' }}"><b>{{ $summary['overdue'] }}</b> {{ __('overdue') }}</span>
            <span><b>{{ $summary['shelf'] }}</b> {{ __('on the shelf') }}</span>
            @if($summary['follow'])<span class="late"><b>{{ $summary['follow'] }}</b> {{ __('to follow up') }}</span>@endif
        </div>

        <div class="eq-card" style="padding:0">
            <div class="eq-table-wrap">
                <table class="eq-table">
                    <thead><tr><th>{{ __('Item') }}</th><th>{{ __('With') }}</th><th>{{ __('For') }}</th><th>{{ __('Since') }}</th><th>{{ __('Due') }}</th><th>{{ __('Status') }}</th>@can('equipment-desk')<th></th>@endcan</tr></thead>
                    <tbody>
                    @forelse($rows as $r)
                        @php $l = $r['loan']; @endphp
                        <tr class="{{ $r['overdue'] ? 'late' : '' }}">
                            <td><a href="{{ route('admin.equipment.history.item', $l->equipment_item_id) }}"><b>{{ $l->item->code }}</b></a><small>{{ $l->item->name }}</small></td>
                            <td><a href="{{ route('admin.equipment.history.person', ['type' => $r['borrower']['type'], 'id' => $r['borrower']['id']]) }}">{{ $r['borrower']['name'] }}</a><small>{{ $r['borrower']['role'] }}</small></td>
                            <td>{{ $r['purpose'] }}</td>
                            <td class="nw">{{ $r['lent_at'] }}</td>
                            <td class="nw">{{ $r['due_at'] }}</td>
                            <td><span class="eq-pill {{ $r['status']['tone'] }}">{{ $r['status']['text'] }}</span></td>
                            @can('equipment-desk')
                            <td>
                                @if($r['overdue'])
                                <details class="eq-act">
                                    <summary>{{ __('Mark lost') }}</summary>
                                    <form method="post" action="{{ route('admin.equipment.loans.lost', $l->id) }}" class="eq-inline">
                                        @csrf
                                        <input class="eq-input" name="note" required maxlength="1000" placeholder="{{ __('What happened') }}" aria-label="{{ __('What happened') }}">
                                        <button class="eq-btn eq-btn-bad eq-btn-sm" type="submit">{{ __('Mark lost') }}</button>
                                    </form>
                                </details>
                                @endif
                            </td>
                            @endcan
                        </tr>
                    @empty
                        <tr><td colspan="7"><div class="eq-empty">{{ __('Nothing is out. Everything is on the shelf.') }}</div></td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        @if(count($followUps))
        <div class="eq-section">
            <h5>{{ __('To follow up') }}</h5>
            <p class="eq-sub" style="margin:-4px 0 10px">{{ __('Came back damaged or with something missing. Each stays here until someone says how it was settled.') }}</p>
            <div class="eq-card" style="padding:0">
                <div class="eq-table-wrap">
                    <table class="eq-table">
                        <thead><tr><th>{{ __('Item') }}</th><th>{{ __('Borrowed by') }}</th><th>{{ __('Returned') }}</th><th>{{ __('Problem') }}</th>@can('equipment-desk')<th>{{ __('Settle') }}</th>@endcan</tr></thead>
                        <tbody>
                        @foreach($followUps as $f)
                            @php $l = $f['loan']; @endphp
                            <tr>
                                <td><a href="{{ route('admin.equipment.history.item', $l->equipment_item_id) }}"><b>{{ $l->item->code }}</b></a><small>{{ $l->item->name }}</small></td>
                                <td>{{ $f['borrower']['name'] }}<small>{{ $f['borrower']['role'] }}</small></td>
                                <td class="nw">{{ $f['returned'] }}@if($f['returner'] && $f['returner']['key'] !== $f['borrower']['key'])<small>{{ __('by :name', ['name' => $f['returner']['name']]) }}</small>@endif</td>
                                <td>
                                    <span class="eq-pill {{ $l->return_condition === 'damaged' ? 'bad' : 'warn' }}">{{ $l->return_condition === 'damaged' ? __('Damaged') : __('Something missing') }}</span>
                                    @if($f['missing'])<small>{{ __('Missing: :list', ['list' => implode(', ', $f['missing'])]) }}</small>@endif
                                    @if($l->return_note)<small>{{ $l->return_note }}</small>@endif
                                </td>
                                @can('equipment-desk')
                                <td>
                                    <form method="post" action="{{ route('admin.equipment.loans.clear', $l->id) }}" class="eq-inline">
                                        @csrf
                                        <input class="eq-input" name="note" required maxlength="1000" placeholder="{{ __('e.g. remote found, repaired') }}" aria-label="{{ __('How it was settled') }}">
                                        <button class="eq-btn eq-btn-ghost eq-btn-sm" type="submit">{{ __('Clear') }}</button>
                                    </form>
                                </td>
                                @endcan
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        @endif
    </div>
</div>
@endsection
