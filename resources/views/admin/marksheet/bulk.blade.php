<!DOCTYPE html>
<html lang="en-US">
<head>
    <meta charset="utf-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <meta name="viewport" content="width=device-width,maximum-scale=1.0">
    <title>{{ $title }}</title>

    @include('admin.marksheet.partials.styles')

    <style type="text/css">
    /* Each transcript is its own sheet of paper. Without this the next
       student's record continues down the same page as the previous one. */
    @media print {
      .tp-page { page-break-after: always; }
      .tp-page:last-of-type { page-break-after: auto; }
    }
    @media screen {
      .bulk-bar {
        position: sticky;
        top: 0;
        z-index: 10;
        background: #1a1a1a;
        color: #fff;
        padding: 10px 18px;
        font-family: 'Source Sans Pro', Arial, sans-serif;
        font-size: 13px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 16px;
      }
      .bulk-bar button {
        background: #fff;
        color: #1a1a1a;
        border: 0;
        border-radius: 4px;
        padding: 6px 14px;
        font-weight: 700;
        cursor: pointer;
      }
      .bulk-missing {
        max-width: 210mm;
        margin: 16px auto 0;
        padding: 12px 16px;
        background: #f8ece8;
        border: 1px solid #e0bfb4;
        border-radius: 6px;
        font-family: 'Source Sans Pro', Arial, sans-serif;
        font-size: 12px;
        color: #7a2e1a;
      }
    }
    @media print {
      .bulk-bar, .bulk-missing { display: none; }
    }
    </style>
</head>

<body>

<div class="bulk-bar">
    <span>
        {{ trans_choice(':count transcript ready to print|:count transcripts ready to print', count($transcripts), ['count' => count($transcripts)]) }}
        @if(!empty($skipped))
            &middot; {{ count($skipped) }} {{ __('could not be included') }}
        @endif
    </span>
    <button type="button" onclick="window.print();">{{ __('btn_print') }}</button>
</div>

@if(!empty($skipped))
{{-- Named rather than silently dropped: a bulk run that quietly prints fewer
     transcripts than were selected is how someone hands out an incomplete set
     without noticing. --}}
<div class="bulk-missing">
    <strong>{{ __('Not included') }}:</strong>
    {{ implode(', ', $skipped) }}
</div>
@endif

@foreach($transcripts as $transcript)
    @include('admin.marksheet.partials.page', [
        'row' => $transcript['student'],
        'currentEnroll' => $transcript['enroll'],
        'selectedProgramId' => $transcript['program_id'],
        'transcriptRecord' => $transcript['record'],
    ])
@endforeach

<!-- Bulk Print Js -->
<script src="{{ asset('dashboard/plugins/jquery/js/jquery.min.js') }}"></script>

</body>
</html>
