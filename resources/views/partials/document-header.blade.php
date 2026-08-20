{{--
    The masthead every printable document should carry.

    When a letterhead has been configured under Academic → Letterhead it is
    rendered exactly as authored — same image, same wording, same colours — so
    that what an administrator sees in the editor is what comes out of every
    document. Nothing is re-typeset around it and no institution name is written
    in the markup: documents used to each build their own header, so the school's
    name lived in seventeen views and none of them agreed with the letterhead.

    When no letterhead is configured (mode "none", or switched off) a plain
    masthead is built from Settings instead, so a document is never left with no
    heading at all.

    Usage:
        @include('partials.document-header')
        @include('partials.document-header', ['forPdf' => true])
        @include('partials.document-header', ['forPdf' => true, 'documentTitle' => __('Application for Admission')])

    Options:
        forPdf         — true when dompdf will render it (changes image paths)
        documentTitle  — printed beneath the masthead, boxed, when given
        rule           — false to omit the divider under the masthead
--}}
@php
    $letterhead = app(\App\Services\LetterheadService::class);
    $letterheadForPdf = $forPdf ?? false;
    $letterheadHtml = $letterhead->render($letterheadForPdf);
    $letterheadSetting = \App\Models\Setting::first();
    $showRule = $rule ?? true;
@endphp

<style>
    /* Scoped to the masthead so a document's own styles are never disturbed. */
    .doc-masthead { width: 100%; margin: 0 0 8px; text-align: center; }
    .doc-masthead .letterhead { width: 100%; margin: 0; }
    .doc-masthead .letterhead img { max-width: 100%; height: auto; }
    .doc-masthead .letterhead p { margin: 0 0 4px; }
    .doc-masthead .letterhead,
    .doc-masthead .letterhead-reserved { position: static; }

    .doc-masthead-fallback .logo { max-height: 70px; max-width: 90px; object-fit: contain; }
    .doc-masthead-fallback .name { font-size: 15px; font-weight: 700; margin: 4px 0 2px; letter-spacing: .02em; }
    .doc-masthead-fallback .meta { font-size: 10px; color: #444; margin: 0; }

    .doc-masthead-rule { border: 0; border-top: 1.4px solid #333; margin: 6px 0 10px; }

    .doc-masthead-title {
        display: inline-block; margin: 2px 0 10px; padding: 4px 18px;
        border: 1px solid #333; font-size: 12px; font-weight: 700;
        text-transform: uppercase; letter-spacing: .08em;
    }
</style>

<div class="doc-masthead">
    @if($letterheadHtml !== '')
        {{-- Exactly as configured. Deliberately not re-wrapped or re-styled. --}}
        {!! $letterheadHtml !!}
    @else
        {{-- No letterhead configured: compose one from Settings rather than
             leaving the document with no heading. --}}
        <div class="doc-masthead-fallback">
            @php
                $logo = optional($letterheadSetting)->logo_path;
                $logoPath = $logo ? public_path('uploads/setting/' . $logo) : null;
            @endphp
            @if($logoPath && is_file($logoPath))
                <img class="logo" src="{{ $letterheadForPdf ? $logoPath : asset('uploads/setting/' . $logo) }}" alt="">
            @endif

            <div class="name">{{ $letterhead->institutionName() }}</div>

            @if(trim((string) optional($letterheadSetting)->address) !== '')
                <p class="meta">{{ $letterheadSetting->address }}</p>
            @endif

            @php
                $contact = array_filter([
                    trim((string) optional($letterheadSetting)->phone),
                    trim((string) optional($letterheadSetting)->email),
                ]);
            @endphp
            @if($contact)
                <p class="meta">{{ implode(' · ', $contact) }}</p>
            @endif
        </div>
    @endif
</div>

@if($showRule)
    <hr class="doc-masthead-rule">
@endif

@if(!empty($documentTitle))
    <div style="text-align:center;">
        <span class="doc-masthead-title">{{ $documentTitle }}</span>
    </div>
@endif
