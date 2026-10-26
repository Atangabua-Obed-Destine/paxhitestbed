{{--
  The income and expense lists.

  One partial, because the two files were byte-identical apart from their line
  endings and had already drifted once from the forms that feed them. The
  module's own $title/$route/$view/$path/$access do the rest, exactly as the
  controllers already pass them.

  Expects, beyond those: $rows, $categories, $selected_* (the filters as
  submitted), $window (from TransactionWindow) and $summary (from the
  controller's summarise()).

  Read top to bottom it answers, in order: what period is this, how much is it,
  how did it move, and then the records themselves.
--}}

@include('admin.account.partials.theme')

@php
    $decimals = $setting->decimal_place ?? 2;
    $currency = $setting->currency_symbol ?? '';
    $dateFormat = $setting->date_format ?? 'Y-m-d';

    $money = fn ($value) => number_format((float) $value, $decimals);

    /*
     * The split bar's segments. Shades of the module's own accent rather than
     * separate hues: these are parts of one quantity, not competing series, so
     * a sequential ramp says "share of the same money" where a categorical
     * palette would say "unrelated things".
     */
    $shades = $path === 'expense'
        ? ['#eb6834', '#f08a5f', '#f5ac8a', '#f9cdb5', '#fde6da']
        : ['#2a78d6', '#5593e0', '#7faeea', '#aac9f3', '#d4e4f9'];

    $total = max((float) $summary['total'], 0.0001);
@endphp

<div class="main-body acct">
    <div class="page-wrapper">

        {{-- What period, what it comes to, and how it moved. The screen could
             not answer any of that without an export before this. --}}
        <div class="acct-band">
            <div class="acct-band-top">
                <div class="acct-band-who">
                    <div class="acct-label">{{ __('acc_showing') }}</div>
                    <h4>{{ $window['label'] }}</h4>
                    @if($window['start'] && $window['end'])
                    <div class="acct-band-sub">
                        {{ \Carbon\Carbon::parse($window['start'])->format('j M Y') }}
                        &ndash;
                        {{ \Carbon\Carbon::parse($window['end'])->format('j M Y') }}
                    </div>
                    @endif
                </div>

                <div class="acct-band-figure">
                    <div class="acct-label">{{ $title }}</div>
                    <div class="v acct-num">
                        {{ $money($summary['total']) }}<span class="c acct-cur">{!! $currency !!}</span>
                    </div>
                    <div class="acct-band-sub">
                        {{ number_format($summary['count']) }} {{ __('acc_records') }}
                    </div>
                </div>
            </div>

            @if(count($summary['methods']))
            <div class="acct-split">
                <div class="acct-split-bar">
                    @foreach(array_values($summary['methods']) as $i => $method)
                    <span style="width: {{ round($method['total'] / $total * 100, 2) }}%;
                                 background: {{ $shades[$i % count($shades)] }};"
                          title="{{ $method['label'] }}"></span>
                    @endforeach
                </div>

                <div class="acct-split-key">
                    @foreach(array_values($summary['methods']) as $i => $method)
                    <span class="acct-split-item">
                        <span class="dot" style="background: {{ $shades[$i % count($shades)] }};"></span>
                        {{ $method['label'] }}
                        <b class="acct-num">{{ $money($method['total']) }}</b>
                        <i>{{ $method['count'] }}</i>
                    </span>
                    @endforeach
                </div>
            </div>
            @endif
        </div>

        {{-- Quick ranges carry the same start_date/end_date the form posts, so
             a bookmark, the back button and a hand-edited URL all keep working. --}}
        <div class="acct-bar">
            @foreach($window['ranges'] as $range)
            <a href="{{ route($route.'.index', array_filter([
                   'title' => $selected_title,
                   'category' => $selected_category ?: null,
                   'start_date' => $range['start'],
                   'end_date' => $range['end'],
                   'all' => $range['start'] === null ? 1 : null,
               ])) }}"
               class="acct-tab {{ $range['active'] ? 'is-on' : '' }}">
                {{ $range['label'] }}
            </a>
            @endforeach

            <div class="acct-bar-end">
                @can($access.'-create')
                <a href="{{ route($route.'.create') }}" class="acct-btn acct-btn-main">
                    <i class="fas fa-plus"></i>{{ __('btn_add_new') }}
                </a>
                @endcan

                <a href="{{ route($route.'.index') }}" class="acct-btn" title="{{ __('btn_refresh') }}">
                    <i class="fas fa-sync-alt"></i>
                </a>
            </div>
        </div>

        <div class="acct-panel">
            <form class="acct-filters" method="get" action="{{ route($route.'.index') }}">
                <div class="row gx-2 gy-2 align-items-end">
                    <div class="col-md-3">
                        <label for="title">{{ __('field_title') }}</label>
                        <input type="text" class="form-control" name="title" id="title"
                               value="{{ $selected_title }}" placeholder="{{ __('search') }}">
                    </div>

                    <div class="col-md-3">
                        <label for="category">{{ __('field_category') }}</label>
                        <select class="form-control" name="category" id="category">
                            <option value="">{{ __('all') }}</option>
                            @foreach( $categories as $category )
                            <option value="{{ $category->id }}" @if($selected_category == $category->id) selected @endif>{{ $category->title }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-2">
                        <label for="start_date">{{ __('field_from_date') }}</label>
                        <input type="date" class="form-control" name="start_date" id="start_date"
                               value="{{ $selected_start_date }}">
                    </div>

                    <div class="col-md-2">
                        <label for="end_date">{{ __('field_to_date') }}</label>
                        <input type="date" class="form-control" name="end_date" id="end_date"
                               value="{{ $selected_end_date }}">
                    </div>

                    <div class="col-md-2">
                        <button type="submit" class="acct-btn acct-btn-main w-100 justify-content-center">
                            <i class="fas fa-search"></i>{{ __('btn_filter') }}
                        </button>
                    </div>
                </div>
            </form>
        </div>

        <div class="acct-panel">
            @if($rows->isEmpty())
                <div class="acct-empty">
                    <i class="fas fa-folder-open"></i>
                    <p>{{ __('acc_no_records') }}</p>
                    <small>{{ __('acc_no_records_hint') }}</small>
                </div>
            @else
            <div class="table-responsive">
                {{-- The column count is unchanged: DataTables' export buttons
                     are configured against it in footer_script, and a mismatch
                     between thead, tbody and tfoot breaks the Excel export
                     without saying so. --}}
                <table id="export-table" class="display table nowrap acct-table" style="width:100%">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>{{ __('field_date') }}</th>
                            <th>{{ __('field_title') }}</th>
                            <th>{{ __('field_category') }}</th>
                            <th>{{ __('field_payment_method') }}</th>
                            <th>{{ __('field_invoice_id') }}</th>
                            <th class="n">{{ __('field_amount') }}</th>
                            <th class="n">{{ __('field_action') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach( $rows as $key => $row )
                        <tr>
                            <td class="acct-meta">{{ $key + 1 }}</td>
                            {{-- data-order so DataTables sorts the day and not
                                 the rendered string: the setting here is d/m/Y,
                                 under which 01/12 sorts before 26/10 and the
                                 column is useless for a report. --}}
                            <td class="acct-when" data-order="{{ \Carbon\Carbon::parse($row->date)->format('Y-m-d') }}">
                                {{ \Carbon\Carbon::parse($row->date)->format($dateFormat) }}
                            </td>
                            <td>
                                <span class="acct-what">{{ $row->title }}</span>
                                @if($row->attach && is_file('uploads/'.$path.'/'.$row->attach))
                                <i class="fas fa-paperclip acct-clip" title="{{ __('field_attach') }}"></i>
                                @endif
                            </td>
                            <td><span class="acct-tag acct-tag-flow">{{ $row->category->title ?? '—' }}</span></td>
                            <td class="acct-meta">{{ \App\Support\PaymentMethod::label($row->payment_method) }}</td>
                            <td class="acct-ref">{{ $row->invoice_id }}</td>
                            <td class="n acct-num">{{ $money($row->amount) }}</td>
                            <td class="acct-acts">
                                <button type="button" class="btn btn-icon btn-light btn-sm" data-bs-toggle="modal" data-bs-target="#showModal-{{ $row->id }}" title="{{ __('modal_view') }}">
                                    <i class="fas fa-eye"></i>
                                </button>
                                @include($view.'.show')

                                @can($access.'-edit')
                                <a href="{{ route($route.'.edit', $row->id) }}" class="btn btn-icon btn-light btn-sm" title="{{ __('btn_edit') }}">
                                    <i class="far fa-edit"></i>
                                </a>
                                @endcan

                                @can($access.'-delete')
                                <button type="button" class="btn btn-icon btn-light btn-sm" data-bs-toggle="modal" data-bs-target="#deleteModal-{{ $row->id }}" title="{{ __('btn_delete') }}">
                                    <i class="fas fa-trash-alt"></i>
                                </button>
                                @include('admin.layouts.inc.delete')
                                @endcan
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr>
                            <th></th>
                            <th></th>
                            <th></th>
                            <th></th>
                            <th></th>
                            <th>{{ __('field_total') }}</th>
                            <th class="n acct-num">{{ $money($rows->sum('amount')) }} {!! $currency !!}</th>
                            <th></th>
                        </tr>
                    </tfoot>
                </table>
            </div>
            @endif
        </div>

    </div>
</div>
