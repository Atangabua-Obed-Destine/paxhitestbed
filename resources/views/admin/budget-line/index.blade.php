@extends('admin.layouts.master')
@section('title', $title)

@push('css')
<link rel="stylesheet" href="{{ asset('dashboard/plugins/nestable-master/css/nestable.min.css') }}">
@endpush

@section('content')

<style>
    /* The sheet is a two-level structure, so it is drawn as one: headings at
       the left margin, the lines they gather indented beneath them. */
    .bl-guide { border: 1px solid #cfe0f7; border-radius: 10px; background: #f5f9ff; overflow: hidden; }
    .bl-guide-toggle {
        display: flex; align-items: center; width: 100%; background: transparent; border: 0;
        padding: .85rem 1.1rem; text-align: left; color: #14396e; cursor: pointer; font-weight: 700;
    }
    .bl-guide-toggle:hover { background: #eaf2ff; }
    .bl-guide-chevron { transition: transform .2s ease; color: #5b7bab; font-size: .8rem; }
    .bl-guide-toggle.collapsed .bl-guide-chevron { transform: rotate(180deg); }
    .bl-guide-body { padding: 0 1.15rem 1.15rem; }
    .bl-guide-body h6 {
        font-size: .74rem; font-weight: 800; letter-spacing: .9px; text-transform: uppercase;
        color: #7189b0; margin: 1.1rem 0 .4rem;
    }
    .bl-guide-body p, .bl-guide-body li { color: #33507d; font-size: .92rem; line-height: 1.6; }
    .bl-guide-body ul, .bl-guide-body ol { padding-left: 1.2rem; margin-bottom: 0; }
    .bl-example {
        font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-size: .82rem;
        background: #fff; border: 1px solid #dbe7f8; border-radius: 8px; padding: .8rem 1rem;
        color: #2c4a7a; white-space: pre; overflow-x: auto;
    }

    .dd { max-width: none; }
    .dd-list .dd-list { padding-left: 2rem; }
    .dd-item > button { height: 30px; margin: 0; }

    .bl-row {
        display: flex; align-items: center; gap: .75rem;
        background: #fff; border: 1px solid #e3e7ee; border-radius: 8px;
        padding: .5rem .75rem; margin: 4px 0; cursor: default;
    }
    .dd-item.bl-is-header > .bl-row { background: #eef2f7; border-color: #cfd8e3; }
    .bl-row.bl-retired { opacity: .55; }

    .bl-grip {
        cursor: move; color: #9aa7ba; padding: .2rem .35rem; border-radius: 5px;
        flex-shrink: 0; background: transparent; border: 0;
    }
    .bl-grip:hover { background: #eef2f7; color: #47566b; }

    .bl-code {
        font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-size: .82rem;
        color: #55637a; min-width: 3.5rem; flex-shrink: 0;
    }
    .bl-main { flex-grow: 1; min-width: 0; }
    .bl-name { font-size: .92rem; color: #212529; }
    .dd-item.bl-is-header .bl-name { font-weight: 700; text-transform: uppercase; letter-spacing: .3px; }
    .bl-account { font-size: .72rem; color: #6c757d; display: block; }
    .bl-unmapped { font-size: .72rem; color: #b3701a; display: block; }
    .bl-faculty-fed { color: #2b6a4a; }

    /* Whether a mapping shipped with the system or somebody here chose it. */
    .bl-origin {
        font-size: .6rem; text-transform: uppercase; letter-spacing: .4px;
        padding: 0 .25rem; border-radius: 3px; margin-left: .2rem; border: 1px solid;
        vertical-align: middle;
    }
    .bl-origin-system { color: #64748b; border-color: #d7dee7; background: #f4f6f9; }
    .bl-origin-set { color: #0b6b3a; border-color: #b7e0c6; background: #eefaf2; }
    .bl-feeder { font-weight: 600; }

    .bl-change-btn {
        display: inline-block; margin-top: .15rem;
        background: transparent; border: 0; padding: 0;
        font-size: .72rem; color: #5b7bab; cursor: pointer;
        text-decoration: underline dotted; text-underline-offset: 2px;
    }
    .bl-change-btn:hover { color: #14396e; }

    .map-current {
        font-size: .85rem; color: #33507d; background: #f2f7ff;
        border-left: 3px solid #6d97d8; border-radius: 0 6px 6px 0;
        padding: .6rem .85rem; margin-bottom: 1rem;
    }
    .bl-unmapped-btn {
        background: transparent; border: 0; padding: 0; text-align: left;
        cursor: pointer; text-decoration: underline dotted; text-underline-offset: 2px;
    }
    .bl-unmapped-btn:hover { color: #8a5312; }
    .bl-unmapped-cta { font-weight: 700; margin-left: .35rem; text-decoration: none; }

    .map-suggestion {
        font-size: .82rem; color: #6a5a2a; background: #fdf8e6;
        border-left: 3px solid #e0b84a; border-radius: 0 6px 6px 0;
        padding: .55rem .8rem; margin-top: .35rem;
    }
    .map-warn {
        font-size: .85rem; color: #8a2020; background: #fdecec;
        border-left: 3px solid #d05353; border-radius: 0 6px 6px 0;
        padding: .6rem .85rem;
    }

    .bl-meta { display: flex; align-items: center; gap: .4rem; flex-shrink: 0; }
    .bl-flag { font-size: .66rem; padding: .1rem .35rem; border-radius: 3px; border: 1px solid; white-space: nowrap; }
    .bl-flag-local { color: #0b6b3a; border-color: #b7e0c6; background: #eefaf2; }
    .bl-flag-standard { color: #7a5c00; border-color: #f0e0a8; background: #fdf8e6; }
    .bl-actions { display: flex; gap: .25rem; flex-shrink: 0; }
    .bl-actions .btn { padding: .15rem .4rem; }

    .bl-section-head {
        display: flex; align-items: center; justify-content: space-between;
        background: #212b36; color: #fff; border-radius: 8px; padding: .55rem .9rem; margin-bottom: .6rem;
    }
    .bl-section-head .badge { font-weight: 600; }
    .bl-empty { color: #8a97ab; font-size: .88rem; padding: .5rem 0; }

    .bl-save-state {
        font-size: .82rem; font-weight: 600; padding: .25rem .7rem; border-radius: 999px;
        display: none;
    }
    .bl-save-state.is-saving { display: inline-block; background: #fff5e0; color: #8a5312; }
    .bl-save-state.is-saved { display: inline-block; background: #e6f7ee; color: #0f7a45; }
    .bl-save-state.is-error { display: inline-block; background: #fdecec; color: #a02020; }
</style>

<div class="main-body">
    <div class="page-wrapper">

        {{-- The finance director sets this up once and then lives with it for a
             year, so the screen explains itself rather than assuming the reader
             already knows what a budget line is. --}}
        <div class="row">
            <div class="col-12">
                <div class="bl-guide mb-3">
                    <button class="bl-guide-toggle" type="button" data-bs-toggle="collapse" data-bs-target="#blGuide"
                            aria-expanded="true" aria-controls="blGuide">
                        <span class="flex-grow-1"><i class="fas fa-lightbulb me-2"></i>{{ __('How the Income & Expenditure sheet is built') }}</span>
                        <i class="fas fa-chevron-up bl-guide-chevron"></i>
                    </button>

                    <div class="collapse show" id="blGuide">
                        <div class="bl-guide-body">

                            <h6>{{ __('What a budget line is') }}</h6>
                            <p class="mb-0">
                                {{ __('These are the rows of your Income & Expenditure sheet, organised the way you manage the institution — Administration, Pedagogy, Transport. They are not ledger accounts. The chart of accounts is organised by nature of expense for the statutory statements and is coarser: one account such as 605 Eau et électricité covers several lines here. The account each line posts to is printed under its name.') }}
                            </p>

                            <h6>{{ __('Headings and lines') }}</h6>
                            <p class="mb-2">
                                {{ __('A heading is a title only — ADMINISTRATION, PEDAGOGY. It holds no money of its own; it gathers the lines filed beneath it and the sheet totals them into a subtotal. The lines underneath are where money actually lands. The sheet is two levels deep and no more: a heading never sits under another heading.') }}
                            </p>
                            <div class="bl-example">400  ADMINISTRATION        ← heading, no money of its own
  401  Travelling          ← line, money lands here
  402  Meetings and Seminars
  403  Telephone / Postage
410  PEDAGOGY              ← next heading
  411  Didactic material</div>

                            <h6>{{ __('Putting them in order') }}</h6>
                            <ul class="mb-0">
                                <li>{{ __('Drag a row by the grip on its left. The new order saves on its own — there is no Save button and no number to type.') }}</li>
                                <li>{{ __('Drop a line onto a heading to file it there. That is the same action as ordering it: where a line sits is what it belongs to.') }}</li>
                                <li>{{ __('Drag a heading and the lines beneath it travel with it, so a whole group moves as one.') }}</li>
                                <li>{{ __('Sort by code renumbers a whole section from the diocesan codes, which already carry the intended order — 401 to 409 belong under 400. Use it when the sheet has drifted and you would rather not drag seventy rows.') }}</li>
                            </ul>

                            <h6>{{ __('Before a line will show any money') }}</h6>
                            <p class="mb-2">
                                {{ __('A line only fills in once a transaction category points at it. A line marked "no category maps here yet" will print on the sheet as a permanent zero, however much is actually spent.') }}
                            </p>
                            <p class="mb-2">
                                <strong>{{ __('The link runs one way:') }}</strong>
                                {{ __('a category names one budget line, not the other way round. A line can be fed by several categories; a category feeds exactly one line. Click the warning on any empty line to create a category for it — named after the line — or to point an existing one at it.') }}
                            </p>
                            <p class="mb-0">
                                <strong>{{ __('Tuition is the exception.') }}</strong>
                                {{ __('The sheet splits tuition by school, which a fee category cannot decide — there is one "First Instalment" category but four tuition lines. Those lines are tagged with a faculty instead, and each student\'s payment follows their programme to the right school\'s line. They need no category and never show the warning above.') }}
                            </p>

                            <h6>{{ __('Retiring rather than deleting') }}</h6>
                            <p class="mb-0">
                                {{ __('A line that budgeted figures, expenses or mappings already point at cannot be deleted — doing so would orphan money and break the check that proves the sheet agrees with the ledger. Retire it instead: it leaves the sheet from now on and its history stays intact. Lines marked "diocesan" come from the standard form; renaming one is allowed, but it stops your sheet lining up with the form every other institution files.') }}
                            </p>

                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">{{ $title }}</h5>
                        <div class="d-flex align-items-center gap-2">
                            <span class="bl-save-state" id="blSaveState"></span>
                            @can('budget-line-create')
                                <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#lineModal"
                                        onclick="budgetLineForm(null)">
                                    <i class="fas fa-plus me-1"></i>{{ __('Add line') }}
                                </button>
                            @endcan
                        </div>
                    </div>

                    <div class="card-block">
                        @foreach(['income', 'expenditure', 'capital'] as $section)
                            @php $tree = $grouped[$section] ?? collect(); @endphp

                            <div class="mb-4">
                                <div class="bl-section-head">
                                    <span>
                                        <strong>{{ __(ucfirst($section)) }}</strong>
                                        <span class="badge bg-light text-dark ms-2">
                                            {{ $tree->sum(fn ($node) => 1 + $node['children']->count()) }}
                                        </span>
                                    </span>

                                    @can('budget-line-edit')
                                        <form action="{{ route('admin.budget-line.auto-sort') }}" method="post" class="d-inline"
                                              onsubmit="return confirm('{{ __('Renumber this whole section from the codes? Your headings stay as they are.') }}')">
                                            @csrf
                                            <input type="hidden" name="section" value="{{ $section }}">
                                            <button type="submit" class="btn btn-sm btn-light">
                                                <i class="fas fa-sort-numeric-down me-1"></i>{{ __('Sort by code') }}
                                            </button>
                                        </form>
                                    @endcan
                                </div>

                                @if($tree->isEmpty())
                                    <p class="bl-empty">{{ __('No lines in this section yet.') }}</p>
                                @else
                                    <div class="dd bl-nest" data-section="{{ $section }}">
                                        <ol class="dd-list">
                                            @foreach($tree as $node)
                                                @include('admin.budget-line.partials.node', [
                                                    'line' => $node['line'],
                                                    'children' => $node['children'],
                                                    'accountsByLine' => $accountsByLine,
                                                    'categoriesByLine' => $categoriesByLine,
                                                    'usage' => $usage,
                                                ])
                                            @endforeach
                                        </ol>
                                    </div>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- One modal serves add and edit; budgetLineForm() repoints it. --}}
<div class="modal fade" id="lineModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="post" id="lineForm" action="{{ route('admin.budget-line.store') }}">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title" id="lineModalTitle">{{ __('Add line') }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-3 mb-3">
                            <label for="bl_code">{{ __('Code') }} <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="code" id="bl_code" required>
                            <small class="text-muted">{{ __('Follow the diocesan numbering: a heading on a round number, its lines just above it.') }}</small>
                        </div>
                        <div class="col-md-9 mb-3">
                            <label for="bl_name">{{ __('Name') }} <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="name" id="bl_name" required>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label for="bl_name_fr">{{ __('Name (French)') }}</label>
                            <input type="text" class="form-control" name="name_fr" id="bl_name_fr">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="bl_section">{{ __('Section') }} <span class="text-danger">*</span></label>
                            <select class="form-control" name="section" id="bl_section" required>
                                <option value="income">{{ __('Income') }}</option>
                                <option value="expenditure">{{ __('Expenditure') }}</option>
                                <option value="capital">{{ __('Capital') }}</option>
                            </select>
                            <small class="text-muted">{{ __('Which half of the sheet this belongs to.') }}</small>
                        </div>

                        <div class="col-12 mb-3">
                            <label for="bl_description">{{ __('Description') }}</label>
                            <textarea class="form-control" name="description" id="bl_description" rows="2"
                                      placeholder="{{ __('What belongs on this line, in your own words. Whoever codes an invoice next year will thank you.') }}"></textarea>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label for="bl_parent">{{ __('Files under which heading') }}</label>
                            <select class="form-control" name="parent_id" id="bl_parent">
                                <option value="">{{ __('None — stands on its own') }}</option>
                                @foreach($headers as $header)
                                    <option value="{{ $header->id }}">{{ $header->code }} — {{ $header->name }} ({{ ucfirst($header->section) }})</option>
                                @endforeach
                            </select>
                            <small class="text-muted">{{ __('You can also set this by dragging the line onto a heading.') }}</small>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label for="bl_profit_centre">{{ __('Trading activity') }}</label>
                            <input type="text" class="form-control" name="profit_centre" id="bl_profit_centre"
                                   placeholder="{{ __('e.g. Canteen') }}" list="blProfitCentres">
                            <datalist id="blProfitCentres">
                                @foreach($profitCentres ?? [] as $pc)
                                    <option value="{{ $pc }}"></option>
                                @endforeach
                            </datalist>
                            <small class="text-muted">
                                {{ __('Give an income line and an expenditure line the SAME name to pair them, and the Daybook reports what that activity earned, cost and made.') }}
                            </small>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label for="bl_faculty">{{ __('Faculty') }}</label>
                            <select class="form-control" name="faculty_id" id="bl_faculty">
                                <option value="">{{ __('All faculties') }}</option>
                                @foreach($faculties as $faculty)
                                    <option value="{{ $faculty->id }}">{{ $faculty->title }}</option>
                                @endforeach
                            </select>
                            <small class="text-muted">{{ __('Leave empty unless this line belongs to one faculty only.') }}</small>
                        </div>

                        {{-- sort_order is no longer typed in: position comes from
                             dragging, which is the only way to be sure a line
                             lands inside the heading it belongs to. --}}
                        <input type="hidden" name="sort_order" id="bl_sort_order" value="0">

                        <div class="col-12">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="is_header" value="1" id="bl_is_header">
                                <label class="form-check-label" for="bl_is_header">
                                    {{ __('This is a heading') }}
                                    <small class="d-block text-muted">
                                        {{ __('A title that gathers other lines and is subtotalled. It holds no money of its own, so leave this unticked for anything you will spend against.') }}
                                    </small>
                                </label>
                            </div>

                            <div class="form-check mt-2">
                                <input class="form-check-input" type="checkbox" name="status" value="1" id="bl_status" checked>
                                <label class="form-check-label" for="bl_status">
                                    {{ __('On the sheet') }}
                                    <small class="d-block text-muted">{{ __('Untick to retire the line without losing its history.') }}</small>
                                </label>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
                    <button type="submit" class="btn btn-primary">{{ __('Save') }}</button>
                </div>
            </form>
        </div>
    </div>
</div>


{{-- Mapping a line to a category, without leaving this screen. --}}
<div class="modal fade" id="mapModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    {{ __('Give this line a category') }}
                    <small class="d-block text-muted" id="mapLineLabel"></small>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body">
                <p class="text-muted small">
                    {{ __('Money reaches a budget line through a category. Create one named after this line, or point an existing category at it.') }}
                </p>

                {{-- Shown when the line already has categories. A mapping marked
                     "supplied" is not fixed — it shipped with the system and can
                     be changed like any other, which the badge on its own failed
                     to convey. --}}
                <div class="map-current d-none" id="mapCurrent"></div>

                <ul class="nav nav-tabs mb-3" role="tablist">
                    <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#mapCreate" type="button">{{ __('Create a new category') }}</button></li>
                    <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#mapLink" type="button">{{ __('Link an existing one') }}</button></li>
                </ul>

                <div class="tab-content">
                    {{-- CREATE ------------------------------------------------ --}}
                    <div class="tab-pane fade show active" id="mapCreate" role="tabpanel">
                        <form method="post" id="mapCreateForm">
                            @csrf
                            <div class="row">
                                <div class="col-md-12 mb-3">
                                    <label for="map_title">{{ __('Category name') }} <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" name="title" id="map_title" required>
                                    <small class="text-muted" id="mapKindNote"></small>
                                </div>

                                <div class="col-md-6 mb-3">
                                    <label for="map_debit">{{ __('Debit account') }} <span class="text-danger">*</span></label>
                                    <select class="form-control" name="debit_account_id" id="map_debit" required></select>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="map_credit">{{ __('Credit account') }} <span class="text-danger">*</span></label>
                                    <select class="form-control" name="credit_account_id" id="map_credit" required></select>
                                </div>

                                <div class="col-12">
                                    {{-- Shown only when a guess was borrowed from a sibling, and
                                         it names the sibling. A suggestion that hides its source
                                         gets accepted; one that shows its working gets checked. --}}
                                    <div class="map-suggestion d-none" id="mapSuggestion"></div>
                                </div>

                                <div class="col-12 mt-3">
                                    <label for="map_description">{{ __('Description') }}</label>
                                    <textarea class="form-control" name="description" id="map_description" rows="2"
                                              placeholder="{{ __('What belongs in this category, in your own words.') }}"></textarea>
                                </div>
                            </div>

                            <div class="text-end mt-3">
                                <button type="button" class="btn btn-light" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
                                <button type="submit" class="btn btn-primary">{{ __('Create and link') }}</button>
                            </div>
                        </form>
                    </div>

                    {{-- LINK -------------------------------------------------- --}}
                    <div class="tab-pane fade" id="mapLink" role="tabpanel">
                        <form method="post" id="mapLinkForm">
                            @csrf
                            <div class="mb-3">
                                <label for="map_existing">{{ __('Category') }} <span class="text-danger">*</span></label>
                                <select class="form-control" name="mapping_id" id="map_existing" required>
                                    <option value="">{{ __('Choose a category') }}</option>
                                    @foreach($linkableMappings as $m)
                                        <option value="{{ $m['mapping_id'] }}"
                                                data-current="{{ $m['current_line'] }}">
                                            {{ $m['name'] }}
                                            @if($m['current_line'])
                                                — {{ __('now feeds') }} {{ $m['current_line'] }}
                                            @else
                                                — {{ __('not on the sheet yet') }}
                                            @endif
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            {{-- Every category here is already mapped, so this warning is the
                                 ordinary case. Linking is a MOVE, and the line it leaves drops
                                 to zero on the next sheet without anything else saying so. --}}
                            <div class="map-warn d-none" id="mapLinkWarn"></div>

                            <div class="text-end mt-3">
                                <button type="button" class="btn btn-light" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
                                <button type="submit" class="btn btn-primary">{{ __('Point it at this line') }}</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@endsection

@push('scripts')
<script src="{{ asset('dashboard/plugins/nestable-master/js/jquery.nestable.js') }}"></script>
<script>
(function () {
    'use strict';

    function set(id, value) {
        var el = document.getElementById(id);
        if (el) { el.value = value === null || value === undefined ? '' : value; }
    }

    window.budgetLineForm = function (line) {
        var isEdit = !!line;

        document.getElementById('lineModalTitle').textContent =
            isEdit ? '{{ __('Edit line') }}' : '{{ __('Add line') }}';
        document.getElementById('lineForm').action = isEdit
            ? '{{ url('admin/budget-line') }}/' + line.id + '/update'
            : '{{ route('admin.budget-line.store') }}';

        set('bl_code', isEdit ? line.code : '');
        set('bl_name', isEdit ? line.name : '');
        set('bl_name_fr', isEdit ? line.name_fr : '');
        set('bl_description', isEdit ? line.description : '');
        set('bl_section', isEdit ? line.section : 'expenditure');
        set('bl_sort_order', isEdit ? line.sort_order : 0);
        set('bl_parent', isEdit ? (line.parent_id || '') : '');
        set('bl_faculty', isEdit ? (line.faculty_id || '') : '');
        set('bl_profit_centre', isEdit ? (line.profit_centre || '') : '');

        document.getElementById('bl_is_header').checked = isEdit ? !!line.is_header : false;
        document.getElementById('bl_status').checked = isEdit ? !!line.status : true;

        toggleHeaderFields();
    };

    // A heading gathers other lines, so it cannot itself file under one.
    function toggleHeaderFields() {
        var isHeader = document.getElementById('bl_is_header').checked;
        var parent = document.getElementById('bl_parent');
        if (!parent) { return; }
        parent.disabled = isHeader;
        if (isHeader) { parent.value = ''; }
    }


    /* ---- Mapping a line to a category ------------------------------- */

    var ACCOUNTS = @json($accountOptions);
    var SUGGESTIONS = @json($suggestions);

    function fillAccounts(select, classNumber, selectedId) {
        select.innerHTML = '';

        var blank = document.createElement('option');
        blank.value = '';
        blank.textContent = '{{ __('Choose an account') }}';
        select.appendChild(blank);

        (ACCOUNTS[classNumber] || []).forEach(function (account) {
            var option = document.createElement('option');
            option.value = account.id;
            option.textContent = account.label;
            if (selectedId && String(account.id) === String(selectedId)) {
                option.selected = true;
            }
            select.appendChild(option);
        });
    }

    window.budgetLineMap = function (line) {
        var isIncome = line.section === 'income';
        var isCapital = line.section === 'capital';
        var varyingClass = isIncome ? 7 : (isCapital ? 2 : 6);

        document.getElementById('mapLineLabel').textContent = line.code + ' — ' + line.name;
        document.getElementById('map_title').value = line.name;

        document.getElementById('mapCreateForm').action =
            '{{ url('admin/budget-line') }}/' + line.id + '/category';
        document.getElementById('mapLinkForm').action =
            '{{ url('admin/budget-line') }}/' + line.id + '/link';

        document.getElementById('mapKindNote').textContent = isIncome
            ? '{{ __('Creates an income category. Fee categories are billable to students and are created on the Fees screen — use "Link an existing one" for those.') }}'
            : '{{ __('Creates an expense category.') }}';

        var suggestion = SUGGESTIONS[line.id] || {};

        // One side of the entry is always cash; only the other is a real choice.
        fillAccounts(document.getElementById('map_debit'),
                     isIncome ? 5 : varyingClass,
                     suggestion.debit_account_id);
        fillAccounts(document.getElementById('map_credit'),
                     isIncome ? varyingClass : 5,
                     suggestion.credit_account_id);

        var note = document.getElementById('mapSuggestion');
        if (suggestion.from_sibling && suggestion.sibling_label) {
            note.classList.remove('d-none');
            note.innerHTML = '<strong>{{ __('Suggested from') }} '
                + suggestion.sibling_label
                + '</strong>, {{ __('which sits under the same heading. It is a starting point, not an answer — check it names the right account before you save.') }}';
        } else {
            note.classList.add('d-none');
            note.textContent = '';
        }

        var warn = document.getElementById('mapLinkWarn');
        warn.classList.add('d-none');
        document.getElementById('map_existing').value = '';

        var current = document.getElementById('mapCurrent');
        if (line.fed && line.fed.length) {
            current.classList.remove('d-none');
            current.innerHTML = '<strong>{{ __('This line is already fed by') }} '
                + line.fed.join(', ')
                + '.</strong> '
                + '{{ __('Adding another category here means both feed this line. A mapping marked "supplied" came with the system and can be changed like any other — to move one somewhere else, open the line you want it on and use "Link an existing one".') }}';
        } else {
            current.classList.add('d-none');
            current.innerHTML = '';
        }
    };

    document.addEventListener('DOMContentLoaded', function () {
        var existing = document.getElementById('map_existing');
        if (!existing) { return; }

        existing.addEventListener('change', function () {
            var option = this.options[this.selectedIndex];
            var current = option ? option.getAttribute('data-current') : '';
            var warn = document.getElementById('mapLinkWarn');

            if (current) {
                warn.classList.remove('d-none');
                warn.innerHTML = '<strong>{{ __('This moves money.') }}</strong> '
                    + '{{ __('That category currently feeds') }} <strong>' + current + '</strong>. '
                    + '{{ __('Pointing it here takes its figures off that line, which will then read zero.') }}';
            } else {
                warn.classList.add('d-none');
            }
        });
    });

    document.addEventListener('DOMContentLoaded', function () {
        var headerBox = document.getElementById('bl_is_header');
        if (headerBox) { headerBox.addEventListener('change', toggleHeaderFields); }

        if (typeof jQuery === 'undefined' || !jQuery.fn.nestable) {
            return;
        }

        var $ = jQuery;
        var $state = $('#blSaveState');
        var saveTimer = null;

        function showState(kind, text) {
            $state.removeClass('is-saving is-saved is-error').addClass(kind).text(text);
            if (kind === 'is-saved') {
                window.setTimeout(function () { $state.removeClass('is-saved').text(''); }, 2500);
            }
        }

        /**
         * Flatten nestable's tree into the running order, each entry carrying
         * the heading it now sits under. Order and parentage are one fact —
         * where a line sits IS what it belongs to — so they are sent together.
         */
        function flatten(nodes, parentId, out) {
            $.each(nodes, function (_, node) {
                out.push({ id: node.id, parent_id: parentId });
                if (node.children && node.children.length) {
                    out.push.apply(out, flatten(node.children, node.id, []));
                }
            });
            return out;
        }

        $('.bl-nest').each(function () {
            var $nest = $(this);
            var section = $nest.data('section');

            $nest.nestable({
                handleClass: 'bl-grip',
                // Two levels: headings, and the lines filed under them.
                maxDepth: 2
            });

            $nest.on('change', function () {
                window.clearTimeout(saveTimer);

                // Nestable fires change during a drag as well as at the end;
                // one save per settled drop is enough.
                saveTimer = window.setTimeout(function () {
                    var order = flatten($nest.nestable('serialize'), null, []);
                    if (!order.length) { return; }

                    showState('is-saving', '{{ __('Saving order…') }}');

                    $.ajax({
                        url: '{{ route('admin.budget-line.reorder') }}',
                        method: 'POST',
                        data: {
                            _token: '{{ csrf_token() }}',
                            section: section,
                            order: order
                        }
                    }).done(function (response) {
                        if (response && response.success) {
                            showState('is-saved', '{{ __('Order saved') }}');
                        } else {
                            showState('is-error', '{{ __('Could not save the order') }}');
                        }
                    }).fail(function () {
                        // Left visible, not auto-cleared: the screen now shows an
                        // order the database does not have, and a reload is the
                        // only way to find out the real one.
                        showState('is-error', '{{ __('Order not saved — reload the page') }}');
                    });
                }, 250);
            });
        });
    });
})();
</script>
@endpush
