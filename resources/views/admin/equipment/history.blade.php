@extends('admin.layouts.master')
@section('title', $title)
@section('content')
@include('admin.equipment._style')
<div class="main-body eq">
    <div class="page-wrapper">
        <div class="eq-head">
            <div>
                <h4 class="eq-title">{{ $title }}</h4>
                <p class="eq-sub">{{ trans_choice(':count loan, newest first.|:count loans, newest first.', count($rows), ['count' => count($rows)]) }}</p>
            </div>
            <div class="eq-head-side">
                <a class="eq-btn eq-btn-ghost eq-btn-sm" href="{{ route('admin.equipment.board') }}">{{ __('Out right now') }}</a>
            </div>
        </div>

        <div class="eq-card" style="margin-bottom:16px">
            @if($subject['kind'] === 'item')
                @php $c = $subject['card']; @endphp
                <div class="eq-ent" style="border:0;padding:0">
                    <span class="eq-av it">{{ \Illuminate\Support\Str::limit($c['code'], 3, '') }}</span>
                    <div>
                        <span class="eq-name">{{ $c['code'] }}, {{ $c['name'] }}</span>
                        <span class="eq-meta">{{ $c['accessories'] ? __('Comes with :list', ['list' => implode(', ', $c['accessories'])]) : __('Nothing else goes with it') }}{{ $c['desk'] ? ' · ' . $c['desk'] : '' }}</span>
                        <div class="eq-pills">
                            <span class="eq-pill {{ $c['students'] ? 'good' : '' }}">{{ $c['students'] ? __('Students may borrow') : __('Staff only') }}</span>
                            @if($c['state'] !== 'active')<span class="eq-pill warn">{{ $c['state_label'] }}</span>@endif
                            @if($c['loan'])<span class="eq-pill {{ $c['loan']['status']['tone'] }}">{{ __('Out with :name', ['name' => $c['loan']['borrower']['name']]) }}</span>@elseif($c['state'] === 'active')<span class="eq-pill good">{{ __('On the shelf') }}</span>@endif
                        </div>
                    </div>
                </div>
            @else
                @php $p = $subject['person']; @endphp
                <div class="eq-ent" style="border:0;padding:0">
                    <span class="eq-av">@if($p['photo'])<img src="{{ $p['photo'] }}" alt="">@else{{ $p['initials'] }}@endif</span>
                    <div>
                        <span class="eq-name">{{ $p['name'] }}</span>
                        <span class="eq-meta">{{ $p['role'] }}{{ $p['code'] ? ', ' . $p['code'] : '' }}</span>
                        @unless($p['active'])<div class="eq-pills"><span class="eq-pill bad">{{ __('No longer active') }}</span></div>@endunless
                    </div>
                </div>
            @endif
        </div>

        <div class="eq-card" style="padding:0">
            <div class="eq-table-wrap">
                <table class="eq-table">
                    <thead><tr>
                        <th>{{ $subject['kind'] === 'item' ? __('Borrowed by') : __('Item') }}</th>
                        <th>{{ __('For') }}</th><th>{{ __('Lent') }}</th><th>{{ __('Due') }}</th><th>{{ __('Back') }}</th><th>{{ __('Condition') }}</th><th>{{ __('Desk staff') }}</th>
                    </tr></thead>
                    <tbody>
                    @forelse($rows as $r)
                        @php $l = $r['loan']; @endphp
                        <tr class="{{ $l->status === 'out' && $r['late'] ? 'late' : '' }}">
                            <td>
                                @if($subject['kind'] === 'item')
                                    <a href="{{ route('admin.equipment.history.person', ['type' => $r['borrower']['type'], 'id' => $r['borrower']['id']]) }}">{{ $r['borrower']['name'] }}</a><small>{{ $r['borrower']['role'] }}</small>
                                @else
                                    <a href="{{ route('admin.equipment.history.item', $l->equipment_item_id) }}"><b>{{ $l->item->code }}</b></a><small>{{ $l->item->name }}</small>
                                @endif
                            </td>
                            <td>{{ $r['purpose'] }}</td>
                            <td class="nw">{{ $r['lent'] }}</td>
                            <td class="nw">{{ $r['due'] }}</td>
                            <td class="nw">
                                @if($l->status === 'out')<span class="eq-pill {{ $r['status']['tone'] }}">{{ $r['status']['text'] }}</span>
                                @elseif($l->status === 'lost')<span class="eq-pill bad">{{ __('Lost') }}</span>
                                @else{{ $r['returned'] }}@if($r['late'])<small style="color:var(--eq-bad)">{{ __('late') }}</small>@endif
                                    @if($r['returner'] && $r['returner']['key'] !== $r['borrower']['key'])<small>{{ __('by :name', ['name' => $r['returner']['name']]) }}</small>@endif
                                @endif
                            </td>
                            <td>
                                @if($l->return_condition === 'fine')<span class="eq-pill good">{{ __('Fine') }}</span>
                                @elseif($l->return_condition)<span class="eq-pill {{ $l->return_condition === 'damaged' ? 'bad' : 'warn' }}">{{ $l->return_condition === 'damaged' ? __('Damaged') : __('Something missing') }}</span>@endif
                                @if($r['missing'] && $l->status === 'returned')<small>{{ __('Missing: :list', ['list' => implode(', ', $r['missing'])]) }}</small>@endif
                                @if($l->return_note)<small>{{ $l->return_note }}</small>@endif
                                @if($l->follow_up_cleared_at)<small>{{ __('Settled: :note', ['note' => $l->follow_up_note]) }}</small>@endif
                            </td>
                            <td><small style="margin:0">{{ __('Out: :name', ['name' => trim(optional($l->lender)->first_name . ' ' . optional($l->lender)->last_name)]) }}</small>@if($l->receiver)<small>{{ __('In: :name', ['name' => trim($l->receiver->first_name . ' ' . $l->receiver->last_name)]) }}</small>@endif</td>
                        </tr>
                    @empty
                        <tr><td colspan="7"><div class="eq-empty">{{ __('Nothing has been lent yet.') }}</div></td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
