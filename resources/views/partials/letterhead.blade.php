{{--
    The institution's letterhead, configured under Academic > Letterhead.

    Place as the first element of a document body. It renders on the first page
    only, by design - it is normal flow content, not a fixed page header.

    Usage:
        @include('partials.letterhead')                     for the screen
        @include('partials.letterhead', ['forPdf' => true]) for dompdf

    Do not annotate those usage lines with inline Blade comments. Blade comments
    do not nest: the first closing marker ends this whole block, whatever was
    intended. That happened here once, which left the second @include as live
    code and made this partial include itself without limit, exhausting memory
    on every document that used it.
--}}
@php
    $letterheadService = app(\App\Services\LetterheadService::class);
    $letterheadHtml = $letterheadService->render($forPdf ?? false);
@endphp

@if($letterheadHtml !== '')
    {!! $letterheadService->styles() !!}
    {!! $letterheadHtml !!}
@endif
