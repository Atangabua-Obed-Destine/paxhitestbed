<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        * { font-family: DejaVu Sans, sans-serif; }
        body { color: #1f2733; font-size: 12.5px; line-height: 1.6; margin: 0; padding: 0; }
        .sheet { padding: 36px 48px; }
        .letterhead { text-align: center; border-bottom: 2px solid #16294a; padding-bottom: 12px; margin-bottom: 6px; }
        .letterhead img { max-height: 78px; margin-bottom: 6px; }
        .letterhead .inst { font-size: 19px; font-weight: bold; color: #16294a; letter-spacing: .3px; }
        .letterhead .contact { font-size: 10.5px; color: #6b7686; margin-top: 4px; }
        .meta { text-align: right; color: #6b7686; font-size: 11px; margin: 14px 0 22px; }
        .title { text-align: center; font-size: 15px; font-weight: bold; text-transform: uppercase; letter-spacing: 1px; color: #16294a; margin: 8px 0 22px; }
        .body { text-align: justify; }
        .body p { margin: 0 0 12px; }
        .footer-note { margin-top: 40px; font-size: 10px; color: #98a1b0; border-top: 1px solid #e3e7ee; padding-top: 8px; text-align: center; }
    </style>
</head>
<body>
    <div class="sheet">
        <div class="letterhead">
            @php $logo = $setting->logo_path ?? null; @endphp
            @if($logo && is_file(public_path('uploads/setting/'.$logo)))
                <img src="{{ public_path('uploads/setting/'.$logo) }}" alt="logo">
            @endif
            <div class="inst">{{ optional($setting)->title ?? config('app.name') }}</div>
            <div class="contact">
                @if(optional($setting)->address){{ $setting->address }}@endif
                @if(optional($setting)->phone) &nbsp;•&nbsp; {{ $setting->phone }} @endif
                @if(optional($setting)->email) &nbsp;•&nbsp; {{ $setting->email }} @endif
            </div>
        </div>

        <div class="meta">{{ date('F j, Y') }}</div>

        <div class="title">{{ __('Offer of Admission') }}</div>

        <div class="body">
            {!! $body !!}
        </div>

        <div class="footer-note">
            {{ __('This letter was generated electronically by') }} {{ optional($setting)->title ?? config('app.name') }}.
        </div>
    </div>
</body>
</html>
