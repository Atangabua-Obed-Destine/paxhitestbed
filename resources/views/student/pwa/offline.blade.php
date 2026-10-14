{{-- What a student sees with no connection.

     This is the one page the service worker keeps, so it carries nothing
     personal and nothing that goes stale: the school, how to reach them, and a
     plain account of what needs a signal and what does not.
--}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="{{ config('pwa.theme_color') }}">
    <title>{{ __('No connection') }} &mdash; {{ $setting->title ?? config('app.name') }}</title>
    <style>
        :root {
            --ink: #1f2d3d;
            --muted: #6b7a8d;
            --line: #e3e8ef;
            --brand: {{ config('pwa.theme_color') }};
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            padding: 24px 16px 48px;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            color: var(--ink);
            background: #f6f8fb;
            line-height: 1.55;
            -webkit-font-smoothing: antialiased;
        }
        .sheet {
            max-width: 480px;
            margin: 0 auto;
            background: #fff;
            border: 1px solid var(--line);
            border-radius: 14px;
            overflow: hidden;
        }
        .top { padding: 28px 24px 20px; text-align: center; border-bottom: 1px solid var(--line); }
        .logo { width: 64px; height: 64px; object-fit: contain; margin-bottom: 12px; }
        h1 { font-size: 1.25rem; margin: 0 0 6px; }
        .school { font-size: .95rem; color: var(--muted); margin: 0; }
        .body { padding: 22px 24px; }
        h2 { font-size: .95rem; margin: 0 0 8px; }
        ul { margin: 0 0 18px; padding-left: 20px; color: var(--muted); font-size: .92rem; }
        li { margin-bottom: 5px; }
        .note {
            background: #fff8e6;
            border: 1px solid #f3e2b4;
            border-radius: 10px;
            padding: 12px 14px;
            font-size: .88rem;
            color: #6b5618;
            margin-bottom: 18px;
        }
        button {
            display: block;
            width: 100%;
            padding: 13px;
            font-size: 1rem;
            font-weight: 600;
            color: #fff;
            background: var(--brand);
            border: 0;
            border-radius: 10px;
            cursor: pointer;
        }
        .contact { padding: 16px 24px 22px; border-top: 1px solid var(--line); font-size: .86rem; color: var(--muted); }
        .contact div { margin-bottom: 3px; }
        .status { text-align: center; font-size: .82rem; color: var(--muted); margin-top: 14px; }
    </style>
</head>
<body>
    <div class="sheet">
        <div class="top">
            @if($setting && $setting->logo_path && file_exists(public_path('uploads/setting/'.$setting->logo_path)))
                <img class="logo" src="{{ asset('uploads/setting/'.$setting->logo_path) }}" alt="">
            @elseif(file_exists(public_path('pwa/icon-192.png')))
                <img class="logo" src="{{ asset('pwa/icon-192.png') }}" alt="">
            @endif

            <h1>{{ __('You are offline') }}</h1>
            <p class="school">{{ $setting->title ?? config('app.name') }}</p>
        </div>

        <div class="body">
            <p style="margin-top:0">
                {{ __('Your phone has lost its connection, so the portal cannot load right now. Nothing you have already submitted is lost.') }}
            </p>

            <h2>{{ __('What needs a connection') }}</h2>
            <ul>
                <li>{{ __('Fees, payments and receipts') }}</li>
                <li>{{ __('Results, transcripts and attendance') }}</li>
                <li>{{ __('Course registration and resit requests') }}</li>
                <li>{{ __('Anything you are submitting or uploading') }}</li>
            </ul>

            <div class="note">
                {{ __('Figures like fees and results are never stored on your phone, so you always see what the school actually has on record rather than an old copy.') }}
            </div>

            <button type="button" onclick="window.location.reload()">{{ __('Try again') }}</button>
            <p class="status" id="status">{{ __('Waiting for a connection...') }}</p>
        </div>

        @if($setting && ($setting->phone || $setting->email || $setting->address))
        <div class="contact">
            <strong style="color:var(--ink)">{{ __('Need help?') }}</strong>
            @if($setting->phone)<div>{{ $setting->phone }}</div>@endif
            @if($setting->email)<div>{{ $setting->email }}</div>@endif
            @if($setting->address)<div>{{ $setting->address }}</div>@endif
        </div>
        @endif
    </div>

    <script>
        // Reload by itself the moment the phone is back, rather than leaving the
        // student tapping a button.
        window.addEventListener('online', function () {
            document.getElementById('status').textContent = {!! json_encode(__('Back online. Loading...')) !!};
            window.location.reload();
        });
    </script>
</body>
</html>
