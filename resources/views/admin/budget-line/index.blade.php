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
                            <p class="mb-0">
                                {{ __('A line only fills in once a transaction category points at it. A line marked "no category maps to this line yet" will print on the sheet as a permanent zero, however much is actually spent. Map it under Accounting > Default Account Mappings, where a category is given both its ledger account and its budget line.') }}
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
