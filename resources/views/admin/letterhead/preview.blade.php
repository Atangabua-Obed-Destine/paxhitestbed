<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 12mm 15mm; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #222; }
        .sample { margin-top: 10px; }
        .sample h2 { font-size: 14px; margin: 0 0 6px; }
        .muted { color: #777; font-size: 10.5px; }
        .filler { margin-top: 8px; line-height: 1.7; text-align: justify; }
    </style>
    {!! $styles !!}
</head>
<body>
    {!! $letterhead !!}

    {{-- Enough content to run past one page, so it is obvious the letterhead
         appears on the first page only rather than repeating. --}}
    <div class="sample">
        <h2>{{ __('Letterhead preview') }}</h2>
        <p class="muted">
            {{ __('This is how the letterhead will appear at the top of a document. It renders on the first page only — scroll to page two to confirm.') }}
        </p>
        @for($i = 0; $i < 22; $i++)
            <p class="filler">
                {{ __('Sample document text.') }}
                {{ str_repeat(__('This paragraph exists only to push the preview onto a second page so the first-page behaviour can be checked. '), 3) }}
            </p>
        @endfor
    </div>
</body>
</html>
