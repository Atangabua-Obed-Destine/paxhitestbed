{{--
  Semester type + any number of its semesters.

  The senate reviews a session one semester type at a time ("First Semester"
  is Year 1 and Year 2 together), so results screens take a type and a
  multi-select of semesters. Choosing a type ticks all of its semesters;
  ticking fewer narrows the review. Figures stay per semester: the screen shows
  one block per chosen semester.

  Fed by App\Traits\ChoosesSemesters::semesterFilterData(): $semester_types,
  $semester_options, $selected_semester_type, $selected_semesters.
  Optional: $type_col, $semester_col (grid classes), $type_label,
  $type_required (default true: shows the asterisk), $semester_field_id
  (default "semester"; give another when the page already has a #semester).
--}}
@php
  $stfUid = uniqid('stf_');
  $stfSelected = array_map('intval', (array) ($selected_semesters ?? []));
  $stfType = (string) ($selected_semester_type ?? '0');
@endphp

<div class="form-group {{ $type_col ?? 'col-md-2' }}">
  <label for="semester_type">{{ $type_label ?? __('Semester type') }}@if($type_required ?? true) <span class="text-danger">*</span>@endif</label>
  <select class="form-control semester-type" name="semester_type" id="semester_type" data-stf="{{ $stfUid }}">
    <option value="0">{{ __('select') }}</option>
    @foreach(($semester_types ?? []) as $t)
    <option value="{{ $t }}" {{ $stfType === $t ? 'selected' : '' }}>{{ ucwords(strtolower($t)) }}</option>
    @endforeach
  </select>
</div>

<div class="form-group {{ $semester_col ?? 'col-md-3' }}">
  <label for="{{ $semester_field_id ?? 'semester' }}">{{ __('field_semester') }} <span class="text-muted fw-normal">({{ __('one or more') }})</span></label>
  <select class="form-control semester-multi" name="semester[]" id="{{ $semester_field_id ?? 'semester' }}" multiple data-stf="{{ $stfUid }}" data-placeholder="{{ __('All of the type') }}">
    @foreach(($semester_options ?? []) as $sem)
    <option value="{{ $sem->id }}" data-type="{{ $sem->type_name }}" data-year="{{ $sem->year }}" {{ in_array((int) $sem->id, $stfSelected, true) ? 'selected' : '' }}>{{ $sem->title }}</option>
    @endforeach
  </select>
</div>

@push('scripts')
<script type="text/javascript">
(function () {
  "use strict";
  var start = function () {
    var $ = window.jQuery;
    if (!$) { return; }
    var $type = $('select.semester-type[data-stf="{{ $stfUid }}"]');
    var $semester = $('select.semester-multi[data-stf="{{ $stfUid }}"]');
    if (!$type.length || !$semester.length) { return; }

    // The list shows the chosen type's semesters; choosing a type ticks all
    // of them, and the screen reviews every ticked one.
    var all = $semester.find('option').map(function () {
      return { id: this.value, text: $.trim($(this).text()), type: String($(this).data('type')) };
    }).get();
    var picked = $semester.val() || [];
    var fill = function (selectAll) {
      var t = $type.val();
      $semester.empty();
      all.forEach(function (o) {
        if (t === '0' || o.type === t) {
          var on = selectAll ? t !== '0' : picked.indexOf(o.id) !== -1;
          $semester.append(new Option(o.text, o.id, on, on));
        }
      });
      $semester.trigger('change');
    };
    if ($.fn.select2) { $semester.select2({ placeholder: $semester.data('placeholder'), width: '100%' }); }
    fill(false);
    $type.on('change', function () { picked = []; fill(true); });
  };
  if (document.readyState === 'complete') { start(); } else { window.addEventListener('load', start, { once: true }); }
})();
</script>
@endpush
