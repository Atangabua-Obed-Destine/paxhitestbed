{{--
    The institution's letterhead, configured under Academic → Letterhead.

    Place as the first element of a document body. It renders on the first page
    only, by design — it is normal flow content, not a fixed page header.

    Usage:
        @include('partials.letterhead')                  {{-- on screen --}}
        @include('partials.letterhead', ['forPdf' => true])
--}}
@php
    $letterheadService = app(\App\Services\LetterheadService::class);
    $letterheadHtml = $letterheadService->render($forPdf ?? false);
@endphp

@if($letterheadHtml !== '')
    {!! $letterheadService->styles() !!}
    {!! $letterheadHtml !!}
@endif
