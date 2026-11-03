{{--
    The institution's letterhead, configured under Academic > Letterhead.

    Place as the first element of a document body. On its own it renders on the
    first page only - it is normal flow content, not a fixed page header.

    To repeat it on every page, put the include inside the <thead> of a table
    that wraps the document: a table header is redrawn wherever that table
    breaks across a page. The transcript does exactly this, in
    admin/marksheet/partials/page.blade.php.

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
