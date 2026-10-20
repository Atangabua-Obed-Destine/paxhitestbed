{{--
  Heading, then category — the Income & Expenditure sheet's own order.

  A flat category dropdown gave no clue which line of the sheet a figure
  would land on, and offered categories that land on none at all, so money
  could be recorded that the sheet reports nowhere. The heading is chosen
  first and narrows the categories beneath it, exactly as the Budget Lines
  screen files them.

  Supplied by BudgetLineChoices::formData():
    $bl_headings    the headings that have at least one usable category
    $bl_categories  every usable category, with its heading and its line
    $bl_unlinked    active categories that reach no line, and why
    $bl_kept        the unusable category already on this record, or null
    $bl_no_heading  the group id for lines filed under no heading

  Also expects $bl_selected (the category id to preselect, or null) and
  $bl_manage_route (where an admin goes to fix a category's mapping).
--}}

@php
    // Built with a foreach and json_encode rather than a multi-line @json()
    // with an arrow function inside it, which Blade does not compile here.
    $blPayloadRows = [];

    foreach ($bl_categories as $blEntry) {
        $blPayloadRows[] = [
            'id' => $blEntry['id'],
            'title' => $blEntry['title'],
            'heading' => (string) $blEntry['heading_id'],
            'line' => $blEntry['line_label'],
        ];
    }

    // The record's own unusable category travels with the rest, under its own
    // pseudo-heading, so the form can show what is already saved.
    if (!empty($bl_kept)) {
        $blPayloadRows[] = [
            'id' => $bl_kept['id'],
            'title' => $bl_kept['title'],
            'heading' => 'kept',
            'line' => null,
        ];
    }

    $blPayload = json_encode($blPayloadRows, JSON_UNESCAPED_UNICODE | JSON_HEX_APOS | JSON_HEX_QUOT);

    $blSelected = $bl_selected ?? null;
    $blSelectedEntry = $bl_categories->firstWhere('id', (int) $blSelected);
    $blSelectedHeading = $blSelectedEntry['heading_id']
        ?? ($bl_kept && (int) $bl_kept['id'] === (int) $blSelected ? 'kept' : null);
@endphp

<div class="form-group col-md-4">
    <label for="budget_heading">{{ __('Budget Heading') }} <span>*</span></label>
    {{-- No name: this select steers the one beside it and is never read
         by the server, which recomputes it from the chosen category. --}}
    <select class="form-control" id="budget_heading">
        <option value="">{{ __('select') }}</option>

        @foreach($bl_headings as $heading)
        <option value="{{ $heading['id'] }}"
            @if((string) $blSelectedHeading === (string) $heading['id']) selected @endif>
            {{ $heading['label'] }}
            ({{ trans_choice('{1}:count category|[2,*]:count categories', $heading['count'], ['count' => $heading['count']]) }})
        </option>
        @endforeach

        {{-- The record's own category, when it is one this rule would no
             longer allow. Kept selectable so the record can still be
             corrected; see the note under the category dropdown. --}}
        @if($bl_kept)
        <option value="kept" @if($blSelectedHeading === 'kept') selected @endif>
            {{ __('— Not filed under a budget line —') }}
        </option>
        @endif
    </select>

    <small class="form-text text-muted">
        {{ __('Choose the heading first; the categories filed under it appear beside.') }}
    </small>

    <div class="invalid-feedback">
        {{ __('required_field') }} {{ __('Budget Heading') }}
    </div>
</div>

<div class="form-group col-md-4">
    <label for="category">{{ __('field_category') }} <span>*</span></label>
    <select class="form-control" name="category" id="category" required
            data-selected="{{ $blSelected }}">
        <option value="">{{ __('select') }}</option>
    </select>

    <small class="form-text text-muted" id="category-line-note"></small>

    <div class="invalid-feedback">
        {{ __('required_field') }} {{ __('field_category') }}
    </div>
</div>

@if($bl_kept)
<div class="col-md-12">
    <div class="alert alert-warning py-2">
        <i class="fas fa-exclamation-triangle"></i>
        <strong>{{ $bl_kept['title'] }}</strong>
        {{ __('is not filed under a budget line, so money recorded against it appears on no line of the Income & Expenditure sheet.') }}
        {{ $bl_kept['reason'] }}
        <br>
        {{ __('This record keeps it so it can still be corrected, but any other category you choose must be filed under a heading.') }}
        @can('budget-line-view')
        <a href="{{ route('admin.budget-line.index') }}" target="_blank">{{ __('Fix it in Budget Lines') }}</a>.
        @endcan
    </div>
</div>
@endif

@if($bl_unlinked->isNotEmpty())
<div class="col-md-12">
    <div class="alert alert-info py-2" style="font-size:13px">
        <i class="fas fa-info-circle"></i>
        {{ trans_choice(
            '{1}:count active category is not filed under any budget line and cannot be chosen here|[2,*]:count active categories are not filed under any budget line and cannot be chosen here',
            $bl_unlinked->count(), ['count' => $bl_unlinked->count()]
        ) }}:
        <strong>{{ $bl_unlinked->pluck('title')->take(6)->implode(', ') }}</strong>@if($bl_unlinked->count() > 6){{ __(' and :more more', ['more' => $bl_unlinked->count() - 6]) }}@endif.
        @can('budget-line-view')
        <a href="{{ route('admin.budget-line.index') }}" target="_blank">{{ __('File them under a line') }}</a>
        {{ __('to make them available.') }}
        @endcan
    </div>
</div>
@endif

@push('scripts')
<script type="text/javascript">
// ---------------------------------------------------------------------------
// Heading narrows the categories beneath it.
//
// The whole set is rendered into the page rather than fetched: there are
// seventeen categories in total, so a round trip per change would be slower
// than the page itself and would leave the form broken when it failed.
// ---------------------------------------------------------------------------
(function () {
    "use strict";

    var categories = {!! $blPayload !!};

    var heading = document.getElementById('budget_heading');
    var category = document.getElementById('category');
    var note = document.getElementById('category-line-note');

    if (!heading || !category) {
        return;
    }

    var wanted = category.getAttribute('data-selected') || '';

    var describe = function () {
        if (!note) {
            return;
        }

        var match = categories.filter(function (entry) {
            return String(entry.id) === String(category.value);
        })[0];

        if (!match) {
            note.textContent = '';
            return;
        }

        note.textContent = match.line
            ? {!! json_encode(__('Posts to')) !!} + ' ' + match.line
            : {!! json_encode(__('This category reaches no line of the sheet.')) !!};
    };

    var fill = function (keepSelection) {
        var chosen = keepSelection ? category.value : '';

        category.innerHTML = '';

        var blank = document.createElement('option');
        blank.value = '';
        blank.textContent = heading.value
            ? {!! json_encode(__('select')) !!}
            : {!! json_encode(__('Choose a heading first')) !!};
        category.appendChild(blank);

        categories.filter(function (entry) {
            return entry.heading === heading.value;
        }).forEach(function (entry) {
            var option = document.createElement('option');
            option.value = entry.id;
            option.textContent = entry.line
                ? entry.title + '  —  ' + entry.line
                : entry.title;

            if (String(entry.id) === String(chosen) || String(entry.id) === String(wanted)) {
                option.selected = true;
            }

            category.appendChild(option);
        });

        // Only honour the server's preselection once; after that the admin's
        // own choice stands, or they are returned to "select".
        wanted = '';
        describe();
    };

    heading.addEventListener('change', function () {
        fill(false);
    });

    category.addEventListener('change', describe);

    fill(true);
})();
</script>
@endpush
