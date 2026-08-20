@extends('admin.layouts.master')
@section('title', $title)
@section('content')

<style>
    .bl-table td, .bl-table th { padding: .4rem .5rem; vertical-align: middle; }
    .bl-code { font-family: ui-monospace, SFMono-Regular, Menlo, monospace; white-space: nowrap; }
    .bl-header td { background: #f3f5f7; font-weight: 700; text-transform: uppercase; letter-spacing: .3px; }
    .bl-child .bl-name { padding-left: 1.25rem; }
    .bl-account { font-size: .72rem; color: #6c757d; display: block; }
    .bl-flag { font-size: .66rem; padding: .1rem .35rem; border-radius: 3px; border: 1px solid; }
    .bl-flag-local { color: #0b6b3a; border-color: #b7e0c6; background: #eefaf2; }
    .bl-flag-standard { color: #7a5c00; border-color: #f0e0a8; background: #fdf8e6; }
    .bl-retired { opacity: .5; }
</style>

<div class="main-body">
    <div class="page-wrapper">

        <div class="row">
            <div class="col-12">
                <div class="alert alert-info">
                    {{-- Says plainly what this screen is for, because "budget line" and
                         "chart of accounts" are easy to confuse. --}}
                    <strong>{{ __('What these are') }}</strong>
                    <p class="mb-0 mt-1 small">
                        {{ __('Budget lines are the rows of the Income & Expenditure sheet, grouped by activity — Administration, Pedagogy, Transport. They are not ledger accounts: the chart of accounts is grouped by nature of expense for the statutory statements, and is coarser. One account can cover several lines. The account each line posts to is shown beneath its name.') }}
                    </p>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">{{ $title }}</h5>
                        @can('budget-line-create')
                            <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#lineModal"
                                    onclick="budgetLineForm(null)">
                                <i class="fas fa-plus me-1"></i>{{ __('Add line') }}
                            </button>
                        @endcan
                    </div>

                    <div class="card-block table-border-style">
                        <div class="table-responsive">
                            <table class="table table-sm bl-table">
                                <thead>
                                    <tr>
                                        <th style="width:90px">{{ __('Code') }}</th>
                                        <th>{{ __('Name') }}</th>
                                        <th style="width:150px">{{ __('Faculty') }}</th>
                                        <th style="width:120px">{{ __('Origin') }}</th>
                                        <th style="width:90px">{{ __('Status') }}</th>
                                        <th style="width:150px" class="text-end">{{ __('Action') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach(['income', 'expenditure', 'capital'] as $section)
                                        <tr>
                                            <td colspan="6" class="bg-dark text-white">
                                                {{ __(ucfirst($section)) }}
                                                <span class="badge bg-light text-dark ms-1">{{ count($grouped[$section] ?? []) }}</span>
                                            </td>
                                        </tr>

                                        @forelse($grouped[$section] ?? [] as $line)
                                            @php
                                                // Built here rather than inline: a multi-line array inside
                                                // @json() in an attribute is more than Blade's parser can read.
                                                $payload = $line->only([
                                                    'id', 'code', 'name', 'name_fr', 'description', 'section',
                                                    'parent_id', 'faculty_id', 'sort_order', 'is_header', 'is_local', 'status',
                                                ]);
                                            @endphp
                                            <tr class="{{ $line->is_header ? 'bl-header' : 'bl-child' }} {{ $line->status ? '' : 'bl-retired' }}">
                                                <td class="bl-code">{{ $line->code }}</td>
                                                <td class="bl-name">
                                                    {{ $line->name }}
                                                    @if(!$line->is_header && !empty($accountsByLine[$line->id]))
                                                        <span class="bl-account">{{ implode(' · ', $accountsByLine[$line->id]) }}</span>
                                                    @elseif(!$line->is_header)
                                                        <span class="bl-account text-warning">
                                                            {{ __('no category maps to this line yet') }}
                                                        </span>
                                                    @endif
                                                </td>
                                                <td class="small">{{ optional($line->faculty)->title ?? '—' }}</td>
                                                <td>
                                                    @if($line->is_local)
                                                        <span class="bl-flag bl-flag-local" title="{{ __('Added for this institution — not on the standard diocesan form.') }}">{{ __('local') }}</span>
                                                    @else
                                                        <span class="bl-flag bl-flag-standard" title="{{ __('Comes from the standard diocesan form. Renaming it breaks comparability with the form other institutions file.') }}">{{ __('diocesan') }}</span>
                                                    @endif
                                                </td>
                                                <td>
                                                    @if($line->status)
                                                        <span class="badge bg-success">{{ __('On the sheet') }}</span>
                                                    @else
                                                        <span class="badge bg-secondary">{{ __('Retired') }}</span>
                                                    @endif
                                                </td>
                                                <td class="text-end">
                                                    @can('budget-line-edit')
                                                        <button type="button" class="btn btn-sm btn-outline-primary"
                                                                data-bs-toggle="modal" data-bs-target="#lineModal"
                                                                onclick='budgetLineForm(@json($payload))'>
                                                            <i class="fas fa-pen"></i>
                                                        </button>

                                                        <form action="{{ route('admin.budget-line.toggle', $line->id) }}" method="post" class="d-inline"
                                                              onsubmit="return confirm('{{ $line->status ? __('Take this line off the sheet?') : __('Put this line back on the sheet?') }}')">
                                                            @csrf
                                                            <button type="submit" class="btn btn-sm btn-outline-secondary"
                                                                    title="{{ $line->status ? __('Retire') : __('Restore') }}">
                                                                <i class="fas {{ $line->status ? 'fa-eye-slash' : 'fa-eye' }}"></i>
                                                            </button>
                                                        </form>
                                                    @endcan

                                                    @can('budget-line-delete')
                                                        @if(empty($usage[$line->id]))
                                                            <form action="{{ route('admin.budget-line.delete', $line->id) }}" method="post" class="d-inline"
                                                                  onsubmit="return confirm('{{ __('Delete this line permanently?') }}')">
                                                                @csrf
                                                                <button type="submit" class="btn btn-sm btn-outline-danger"><i class="fas fa-trash"></i></button>
                                                            </form>
                                                        @else
                                                            {{-- Explains itself rather than showing a button that always fails. --}}
                                                            <button type="button" class="btn btn-sm btn-outline-danger" disabled
                                                                    title="{{ __('In use: :reasons. Retire it instead.', ['reasons' => implode(', ', $usage[$line->id])]) }}">
                                                                <i class="fas fa-trash"></i>
                                                            </button>
                                                        @endif
                                                    @endcan
                                                </td>
                                            </tr>
                                        @empty
                                            <tr><td colspan="6" class="text-muted small">{{ __('No lines in this section.') }}</td></tr>
                                        @endforelse
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
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

                    <div class="alert alert-warning d-none" id="diocesanWarning">
                        <i class="fas fa-exclamation-triangle me-1"></i>
                        {{ __('This line comes from the standard diocesan form. You can change it, but the sheet will no longer line up with the form other institutions file.') }}
                    </div>

                    <div class="row">
                        <div class="form-group col-md-3">
                            <label for="bl_code">{{ __('Code') }} <span>*</span></label>
                            <input type="text" class="form-control" name="code" id="bl_code" required>
                        </div>
                        <div class="form-group col-md-9">
                            <label for="bl_name">{{ __('Name') }} <span>*</span></label>
                            <input type="text" class="form-control" name="name" id="bl_name" required>
                        </div>

                        <div class="form-group col-md-6">
                            <label for="bl_name_fr">{{ __('Name (French)') }}</label>
                            <input type="text" class="form-control" name="name_fr" id="bl_name_fr">
                        </div>
                        <div class="form-group col-md-3">
                            <label for="bl_section">{{ __('Section') }} <span>*</span></label>
                            <select class="form-control" name="section" id="bl_section" required>
                                <option value="income">{{ __('Income') }}</option>
                                <option value="expenditure">{{ __('Expenditure') }}</option>
                                <option value="capital">{{ __('Capital') }}</option>
                            </select>
                        </div>
                        <div class="form-group col-md-3">
                            <label for="bl_sort_order">{{ __('Sort order') }}</label>
                            <input type="number" class="form-control" name="sort_order" id="bl_sort_order" value="0" min="0">
                        </div>

                        <div class="form-group col-md-6">
                            <label for="bl_parent">{{ __('Under heading') }}</label>
                            <select class="form-control" name="parent_id" id="bl_parent">
                                <option value="">{{ __('— none (top level) —') }}</option>
                                @foreach($headers as $header)
                                    <option value="{{ $header->id }}" data-section="{{ $header->section }}">
                                        {{ $header->code }} — {{ $header->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group col-md-6">
                            <label for="bl_faculty">{{ __('Faculty') }}</label>
                            <select class="form-control" name="faculty_id" id="bl_faculty">
                                <option value="">{{ __('— none —') }}</option>
                                @foreach($faculties as $faculty)
                                    <option value="{{ $faculty->id }}">{{ $faculty->title }}</option>
                                @endforeach
                            </select>
                            <small class="text-muted">{{ __('Only for tuition lines split by school — it is how a fee finds its line.') }}</small>
                        </div>

                        <div class="form-group col-md-12">
                            <label for="bl_description">{{ __('Description') }}</label>
                            <textarea class="form-control" name="description" id="bl_description" rows="2"></textarea>
                        </div>

                        <div class="form-group col-md-6">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="is_header" value="1" id="bl_is_header">
                                <label class="form-check-label" for="bl_is_header">
                                    {{ __('This is a heading') }}
                                    <small class="d-block text-muted">{{ __('Headings total the lines beneath them and carry no figure of their own.') }}</small>
                                </label>
                            </div>
                        </div>
                        <div class="form-group col-md-6">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="status" value="1" id="bl_status" checked>
                                <label class="form-check-label" for="bl_status">{{ __('Show on the sheet') }}</label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ __('btn_cancel') }}</button>
                    <button type="submit" class="btn btn-success">{{ __('btn_save') }}</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    "use strict";

    var BUDGET_LINE_STORE = "{{ route('admin.budget-line.store') }}";
    var BUDGET_LINE_UPDATE = "{{ route('admin.budget-line.update', ':id') }}";

    function budgetLineForm(line) {
        var form = document.getElementById('lineForm');
        var isEdit = !!line;

        document.getElementById('lineModalTitle').textContent =
            isEdit ? "{{ __('Edit line') }}" : "{{ __('Add line') }}";
        form.action = isEdit ? BUDGET_LINE_UPDATE.replace(':id', line.id) : BUDGET_LINE_STORE;

        var set = function (id, value) { document.getElementById(id).value = value == null ? '' : value; };
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

        // Warn only where it applies: a line off the diocesan form.
        document.getElementById('diocesanWarning')
            .classList.toggle('d-none', !(isEdit && !line.is_local));
    }

    // A heading has no parent, so stop offering one.
    document.getElementById('bl_is_header').addEventListener('change', function () {
        var parent = document.getElementById('bl_parent');
        parent.disabled = this.checked;
        if (this.checked) { parent.value = ''; }
    });
</script>

@endsection
