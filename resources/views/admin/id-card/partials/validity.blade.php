{{-- The card's validity, where the ID Card Setting screen puts it.

     Top right: white on the card's blue band, as it always was.
     Under Date Issued: the artwork has no VALIDITY label, so this row prints
     its own label and colon in the artwork's columns, and the value in the
     same column as every other value.

     $period  the text after "VALIDITY", e.g. 2026-2030
     $print   the student-card IdCardSetting (may be null) --}}
@if($print && $print->validityBelowIssued())
<div class="validity-row"><span class="validity-label">VALIDITY</span><span class="validity-colon">:</span><span class="validity-value">{{ $period }}</span></div>
@else
<div class="validity">VALIDITY: {{ $period }}</div>
@endif
