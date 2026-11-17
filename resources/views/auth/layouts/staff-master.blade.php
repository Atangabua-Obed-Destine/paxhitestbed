<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex">
    <title>@yield('title') - {{ institution_name() }}</title>

    @if(isset($setting) && $setting->favicon_path)
    <link rel="icon" href="{{ asset('uploads/setting/'.$setting->favicon_path) }}" type="image/x-icon">
    @endif

    {{-- Staff sign-in. It uses the same look as EduTrust's: ink and parchment with
         one gold accent. Everything is in this file: no web fonts, icon sets or
         script libraries, so the page loads at once on a slow line. --}}
    <style>
        :root {
            --ink: #16202e; --navy: #1f3a5f; --navy-2: #13294a; --night: #0b1a2e;
            --gold: #e8b54a; --paper: #f2efe9; --line: #c8c0b3; --muted: #6f8096; --soft: #46566b;
        }
        *, *::before, *::after { box-sizing: border-box; }
        html, body { margin: 0; min-height: 100%; }
        body {
            font-family: ui-sans-serif, system-ui, -apple-system, "Segoe UI", Roboto, Arial, sans-serif;
            background: var(--paper); color: var(--ink); -webkit-font-smoothing: antialiased;
        }
        a { color: inherit; }
        svg { display: block; }
        .serif { font-family: ui-serif, Georgia, Cambria, "Times New Roman", serif; font-weight: 400; }

        .sl { display: flex; flex-direction: column; min-height: 100vh; }
        @media (min-width: 992px) { .sl { display: grid; grid-template-columns: minmax(0, 1.08fr) minmax(0, 1fr); } }

        /* The school's side */
        .sl-aside {
            position: relative; overflow: hidden; color: #fff;
            background-color: var(--night);
            background-image: linear-gradient(155deg, #0b1a2e 0%, #13294a 45%, #1f3a5f 100%);
        }
        @media (min-width: 992px) { .sl-aside { position: sticky; top: 0; height: 100vh; } }
        /* Ruled faintly, like a register page. */
        .sl-rule {
            position: absolute; inset: 0; pointer-events: none;
            background-image:
                linear-gradient(to bottom, rgba(255,255,255,.045) 1px, transparent 1px),
                linear-gradient(to right, rgba(232,181,74,.10) 1px, transparent 1px);
            background-size: 100% 34px, 100% 100%;
            background-position: 0 0, 72px 0;
            background-repeat: repeat, no-repeat;
        }
        .sl-glow { position: absolute; border-radius: 50%; filter: blur(70px); pointer-events: none; }
        .sl-glow.a { right: -96px; top: -96px; width: 320px; height: 320px; background: rgba(232,181,74,.20); }
        .sl-glow.b { left: -80px; bottom: -128px; width: 384px; height: 384px; background: rgba(59,111,176,.30); }
        .sl-aside-in { position: relative; max-width: 576px; margin: 0 auto; height: 100%; padding: 32px 24px; display: flex; flex-direction: column; }
        @media (min-width: 576px) { .sl-aside-in { padding: 32px 40px; } }
        @media (min-width: 992px) { .sl-aside-in { padding: 48px 40px; } }

        .sl-brand { display: flex; align-items: center; gap: 12px; }
        .sl-logo { width: 44px; height: 44px; flex: none; border-radius: 12px; background: #fff; padding: 4px; object-fit: contain; }
        .sl-logo-blank[hidden] { display: none; }
        .sl-logo-blank { width: 44px; height: 44px; flex: none; border-radius: 12px; display: flex; align-items: center; justify-content: center;
            border: 1px solid rgba(232,181,74,.4); background: rgba(255,255,255,.06); color: var(--gold); }
        .sl-brand-name { margin: 0; font-size: 20px; line-height: 1.2; }
        .sl-brand-sub { margin: 2px 0 0; font-size: 12px; color: rgba(255,255,255,.65); }

        .sl-intro { margin-top: 32px; }
        @media (min-width: 992px) { .sl-intro { margin-top: 56px; } }
        .sl-eyebrow { margin: 0; max-width: 28rem; font-size: 12px; font-weight: 600; text-transform: uppercase; letter-spacing: .16em; color: rgba(255,255,255,.55); }
        .sl-headline { margin: 12px 0 0; font-size: 30px; line-height: 1.2; }
        @media (min-width: 576px) { .sl-headline { font-size: 36px; } }
        .sl-headline span { color: var(--gold); }
        .sl-lede { margin: 12px 0 0; max-width: 28rem; font-size: 15px; line-height: 1.6; color: rgba(255,255,255,.75); }

        .sl-features { list-style: none; margin: 40px 0 0; padding: 0; display: none; }
        @media (min-width: 992px) { .sl-features { display: block; } }
        .sl-features li { display: flex; gap: 16px; padding: 16px; margin-bottom: 12px; border-radius: 16px;
            border: 1px solid rgba(255,255,255,.10); background: rgba(255,255,255,.06); }
        .sl-ficon { width: 40px; height: 40px; flex: none; border-radius: 12px; display: flex; align-items: center; justify-content: center; background: var(--gold); color: var(--night); }
        .sl-features b { display: block; font-size: 14px; font-weight: 600; }
        .sl-features small { display: block; margin-top: 2px; font-size: 13px; line-height: 1.4; color: rgba(255,255,255,.70); }

        /* Signing in */
        .sl-main {
            position: relative; flex: 1; display: flex; align-items: center; justify-content: center; padding: 40px 16px;
            background: radial-gradient(1100px 560px at 100% 0%, rgba(232,181,74,.09), transparent 60%),
                        radial-gradient(900px 520px at 0% 100%, rgba(31,58,95,.07), transparent 60%);
        }
        @media (min-width: 576px) { .sl-main { padding: 40px 32px; } }
        .sl-langs { position: absolute; top: 16px; right: 16px; display: flex; overflow: hidden; border: 1px solid var(--line); border-radius: 8px; font-size: 12px; font-weight: 600; }
        .sl-langs a { padding: 6px 10px; color: var(--soft); text-decoration: none; }
        .sl-langs a:hover { background: #fff; }
        .sl-langs a.on { background: var(--navy); color: #fff; }

        .sl-box { width: 100%; max-width: 448px; }
        .sl-head { text-align: center; }
        .sl-badge { margin: 0 auto; width: 64px; height: 64px; border-radius: 16px; display: flex; align-items: center; justify-content: center; color: #fff;
            background: linear-gradient(145deg, #1f3a5f, #0b1a2e); box-shadow: 0 10px 15px -3px rgba(31,58,95,.25); }
        .sl-title { margin: 20px 0 0; font-size: 30px; }
        .sl-sub { margin: 8px 0 0; font-size: 14px; color: var(--muted); }

        .sl-form { margin-top: 32px; padding: 24px; border-radius: 16px; border: 1px solid #ded8cf; background: #fff;
            box-shadow: 0 20px 25px -5px rgba(11,26,46,.06), 0 8px 10px -6px rgba(11,26,46,.06); }
        @media (min-width: 576px) { .sl-form { padding: 32px; } }
        /* A column with gaps: the hidden token takes no room. */
        .sl-form { display: flex; flex-direction: column; gap: 20px; }
        .sl-alert { display: flex; gap: 8px; padding: 10px 12px; border-radius: 8px; font-size: 14px; line-height: 1.45; }
        .sl-alert svg { flex: none; margin-top: 2px; }
        .sl-alert.err { border: 1px solid #fecdd3; background: #fff1f2; color: #9f1239; }
        .sl-alert.warn { border: 1px solid #fde68a; background: #fffbeb; color: #92400e; }
        .sl-alert.ok { border: 1px solid #a7f3d0; background: #ecfdf5; color: #065f46; }

        .sl-label { display: flex; align-items: center; gap: 6px; font-size: 14px; font-weight: 600; }
        .sl-label svg { color: var(--navy); }
        .sl-input { display: block; width: 100%; margin-top: 6px; padding: 12px 16px; font: inherit; font-size: 14px; color: var(--ink);
            background: #fff; border: 1px solid var(--line); border-radius: 12px; }
        .sl-input::placeholder { color: rgba(111,128,150,.7); }
        .sl-input:focus { outline: none; border-color: var(--navy); box-shadow: 0 0 0 3px rgba(31,58,95,.25); }
        .sl-input.bad { border-color: #e11d48; }
        .sl-pw { position: relative; margin-top: 6px; }
        .sl-pw .sl-input { margin-top: 0; padding-right: 48px; }
        .sl-eye { position: absolute; top: 0; right: 0; bottom: 0; width: 48px; display: flex; align-items: center; justify-content: center;
            border: 0; background: none; color: var(--muted); cursor: pointer; border-radius: 0 12px 12px 0; }
        .sl-eye:hover { color: var(--ink); }
        .sl-eye:focus-visible { outline: 2px solid rgba(31,58,95,.4); }

        .sl-row { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 8px; font-size: 14px; }
        .sl-row label { display: flex; align-items: center; gap: 8px; color: var(--soft); cursor: pointer; }
        .sl-row input[type=checkbox] { width: 16px; height: 16px; margin: 0; accent-color: var(--navy); }
        .sl-link { font-weight: 600; color: var(--navy); text-decoration: none; }
        .sl-link:hover { text-decoration: underline; }

        .sl-submit { display: flex; width: 100%; align-items: center; justify-content: center; gap: 8px; padding: 14px 16px;
            border: 0; border-radius: 12px; font: inherit; font-size: 14px; font-weight: 600; color: #fff; cursor: pointer;
            background: linear-gradient(100deg, #1f3a5f 0%, #13294a 100%); box-shadow: 0 10px 15px -3px rgba(31,58,95,.30); transition: filter .15s; }
        .sl-submit:hover { filter: brightness(1.1); }
        .sl-submit:focus-visible { outline: 2px solid rgba(31,58,95,.5); outline-offset: 2px; }
        .sl-submit svg { transition: transform .15s; }
        .sl-submit:hover svg { transform: translateX(2px); }
        .sl-submit[disabled] { opacity: .7; cursor: progress; }

        .sl-foot { margin: 24px 0 0; text-align: center; font-size: 12px; color: var(--muted); }
        .sl-foot + .sl-foot { margin-top: 8px; }
        .sl-foot a { font-weight: 600; color: var(--navy); text-decoration: none; }
        .sl-foot a:hover { text-decoration: underline; }

        @keyframes sl-rise { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: none; } }
        .rise { animation: sl-rise .7s ease-out both; }
        .rise-2 { animation-delay: .12s; }
        .rise-3 { animation-delay: .24s; }
        @media (prefers-reduced-motion: reduce) { .rise { animation: none; } }
    </style>
</head>
<body>

    @yield('content')

    {{-- Support chatbot, loaded only when one is configured. The host this
         pointed at unconditionally no longer resolves, so every page load
         spent a failed DNS lookup on it. This portal's own chatbot id is
         kept as the default; set CHATBOT_SCRIPT_URL to switch it back on. --}}
    @if(config('services.chatbot.script_url'))
        <script defer src="{{ config('services.chatbot.script_url') }}"
                data-chatbot-uuid="{{ config('services.chatbot.uuid', 'cb9eb69c-7a17-4bed-95b1-20cb70b0d125') }}"
                data-iframe-width="420" data-iframe-height="745" data-language="{{ app()->getLocale() }}"></script>
    @endif
</body>
</html>
