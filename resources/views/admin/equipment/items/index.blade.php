@extends('admin.layouts.master')
@section('title', $title)
@section('content')
@include('admin.equipment._style')
<div class="main-body eq">
    <div class="page-wrapper">
        <div class="eq-head">
            <div>
                <h4 class="eq-title">{{ __('Equipment list') }}</h4>
                <p class="eq-sub">{{ __('Everything the desks can lend. Each item has its own sticker code.') }}</p>
            </div>
            <div class="eq-head-side">
                @can('equipment-manage')
                <button type="button" id="eq-print" class="eq-btn eq-btn-ghost eq-btn-sm"><i class="fas fa-print"></i> <span>{{ __('Print stickers') }}</span></button>
                <a class="eq-btn eq-btn-primary eq-btn-sm" href="{{ route('admin.equipment.items.create') }}"><i class="fas fa-plus"></i> {{ __('Add equipment') }}</a>
                @endcan
            </div>
        </div>

        <form method="get" class="eq-filters">
            <input class="eq-input" type="search" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="{{ __('Code, name or kind') }}" aria-label="{{ __('Search') }}">
            @if($desks->count() > 1)
            <select class="eq-select" name="desk" aria-label="{{ __('Desk') }}">
                <option value="">{{ __('All desks') }}</option>
                @foreach($desks as $d)<option value="{{ $d->id }}" @selected(($filters['desk'] ?? '') == $d->id)>{{ $d->name }}</option>@endforeach
            </select>
            @endif
            <select class="eq-select" name="state" aria-label="{{ __('State') }}">
                <option value="">{{ __('Any state') }}</option>
                @foreach($states as $k => $v)<option value="{{ $k }}" @selected(($filters['state'] ?? '') === $k)>{{ $v }}</option>@endforeach
            </select>
            <button class="eq-btn eq-btn-ghost eq-btn-sm" type="submit">{{ __('Filter') }}</button>
        </form>

        <div class="eq-card" style="padding:0">
            <div class="eq-table-wrap">
                <table class="eq-table">
                    <thead><tr>
                        @can('equipment-manage')<th style="width:36px"><input type="checkbox" id="eq-all" aria-label="{{ __('Select all') }}"></th>@endcan
                        <th>{{ __('Code') }}</th><th>{{ __('Item') }}</th><th>{{ __('Desk') }}</th><th>{{ __('Who may borrow') }}</th><th>{{ __('Now') }}</th><th></th>
                    </tr></thead>
                    <tbody>
                    @forelse($items as $item)
                        <tr>
                            @can('equipment-manage')<td><input type="checkbox" class="eq-pick" value="{{ $item->id }}" aria-label="{{ __('Select :code', ['code' => $item->code]) }}"></td>@endcan
                            <td class="nw"><a href="{{ route('admin.equipment.history.item', $item->id) }}"><b>{{ $item->code }}</b></a></td>
                            <td>{{ $item->name }}<small>{{ collect([$item->category, $item->accessoryList() ? __('with :list', ['list' => implode(', ', $item->accessoryList())]) : null])->filter()->implode(' · ') }}</small></td>
                            <td>{{ optional($item->desk)->name }}</td>
                            <td>{{ $item->students_may_borrow ? __('Staff and students') : __('Staff only') }}</td>
                            <td>
                                @if($item->state !== 'active')<span class="eq-pill warn">{{ $item->stateLabel() }}</span>
                                @elseif($item->openLoan)<span class="eq-pill {{ $item->openLoan->isOverdue() ? 'bad' : 'acc' }}">{{ $item->openLoan->isOverdue() ? __('Overdue') : __('Out') }}</span>
                                @else<span class="eq-pill good">{{ __('On the shelf') }}</span>@endif
                            </td>
                            <td class="nw">
                                @can('equipment-manage')
                                <a class="eq-btn eq-btn-ghost eq-btn-sm" href="{{ route('admin.equipment.items.edit', $item->id) }}">{{ __('Edit') }}</a>
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7"><div class="eq-empty">{{ __('No equipment yet. Add the projectors, laptops and other items the desks lend, then print their stickers.') }}</div></td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection

@section('page_js')
<script>
{
    const all = document.getElementById('eq-all');
    const picks = () => [...document.querySelectorAll('.eq-pick')];
    if (all) all.addEventListener('change', () => picks().forEach(p => { p.checked = all.checked; }));
    const btn = document.getElementById('eq-print');
    const label = {!! json_encode(['some' => __('Print :n stickers'), 'all' => __('Print stickers')]) !!};
    const sync = () => { const n = picks().filter(p => p.checked).length; if (btn) btn.querySelector('span').textContent = n ? label.some.replace(':n', n) : label.all; };
    document.addEventListener('change', e => { if (e.target.matches('.eq-pick, #eq-all')) sync(); });
    if (btn) btn.addEventListener('click', () => {
        const ids = picks().filter(p => p.checked).map(p => p.value).join(',');
        window.open({!! json_encode(request()->getBaseUrl() . route('admin.equipment.items.stickers', [], false)) !!} + (ids ? '?ids=' + ids : ''), '_blank');
    });
}
</script>
@endsection
