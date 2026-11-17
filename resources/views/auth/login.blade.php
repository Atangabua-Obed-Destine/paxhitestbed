@extends('auth.layouts.staff-master')
@section('title', __('auth_login'))
@section('content')
@php
    $logo = (isset($setting) && $setting->logo_path) ? asset('uploads/setting/'.$setting->logo_path) : null;
    $features = [
        ['M12 14l9-5-9-5-9 5 9 5z M12 14l6.16-3.42A12 12 0 0119 17.5 12 12 0 0112 20a12 12 0 01-7-2.5 12 12 0 01.84-6.92L12 14z',
         __('Teaching and examinations'), __('Courses, timetables, attendance, marks and results')],
        ['M9 7h6 M9 11h.01 M12 11h.01 M15 11h.01 M9 14h.01 M12 14h.01 M15 14h.01 M9 17h.01 M12 17h3 M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z',
         __('Finance and administration'), __('Fees, payroll, accounts and the reports behind them')],
        ['M17 20h5v-2a3 3 0 00-5.36-1.86 M17 20H7 M17 20v-2c0-.66-.13-1.28-.36-1.86 M7 20H2v-2a3 3 0 015.36-1.86 M7 20v-2c0-.66.13-1.28.36-1.86 M7.36 16.14a5 5 0 019.28 0 M15 7a3 3 0 11-6 0 3 3 0 016 0z',
         __('Admissions and communication'), __('Applications, students, staff and messages')],
    ];
    $languages = isset($user_languages) ? $user_languages : collect();
    $locale = session('locale', app()->getLocale());
@endphp

<div class="sl">

    {{-- The institution, before anyone is asked for anything. --}}
    <aside class="sl-aside">
        <div class="sl-rule" aria-hidden="true"></div>
        <div class="sl-glow a" aria-hidden="true"></div>
        <div class="sl-glow b" aria-hidden="true"></div>

        <div class="sl-aside-in">
            <div class="sl-brand rise">
                {{-- If the logo file is missing, the shield stands in for it. --}}
                @if($logo)
                    <img src="{{ $logo }}" alt="" class="sl-logo" onerror="this.nextElementSibling.hidden=false;this.remove()">
                @endif
                    <span class="sl-logo-blank" @if($logo) hidden @endif>
                        <svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 3l7 2.6v5.3c0 4.4-2.9 7.9-7 9.5-4.1-1.6-7-5.1-7-9.5V5.6L12 3z"/><path d="M9 12l2 2 4-4"/></svg>
                    </span>
                <div>
                    <p class="sl-brand-name serif">{{ institution_code() }}</p>
                    <p class="sl-brand-sub">{{ __('Staff portal') }}</p>
                </div>
            </div>

            <div class="sl-intro rise rise-2">
                {{-- A full name can run to three lines, so it sits above the headline, small. --}}
                <p class="sl-eyebrow">{{ institution_name() }}</p>
                <h2 class="sl-headline serif">
                    {{ __('Welcome back.') }}<br>
                    <span>{{ __('The whole institution, in one place.') }}</span>
                </h2>
                <p class="sl-lede">{{ __('Teaching, results, fees and administration, for the people who run the institution.') }}</p>
            </div>

            <ul class="sl-features rise rise-3">
                @foreach($features as [$icon, $title, $text])
                    <li>
                        <span class="sl-ficon">
                            <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="{{ $icon }}"/></svg>
                        </span>
                        <span><b>{{ $title }}</b><small>{{ $text }}</small></span>
                    </li>
                @endforeach
            </ul>
        </div>
    </aside>

    {{-- Signing in. --}}
    <main class="sl-main">
        @if($languages->count() > 1)
            <nav class="sl-langs" aria-label="{{ __('Language') }}">
                @foreach($languages as $language)
                    <a href="{{ route('version', $language->code) }}" class="{{ $locale === $language->code ? 'on' : '' }}"
                       title="{{ $language->name }}">{{ strtoupper($language->code) }}</a>
                @endforeach
            </nav>
        @endif

        <div class="sl-box">
            <div class="sl-head">
                <span class="sl-badge">
                    <svg viewBox="0 0 24 24" width="32" height="32" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <circle cx="9" cy="8" r="3.5"/>
                        <path d="M2.5 20c.6-3.6 3.3-6 6.5-6 1.4 0 2.7.4 3.7 1.2"/>
                        <path d="M18 12l3.5 1.3v2.6c0 2.2-1.5 4-3.5 4.8-2-.8-3.5-2.6-3.5-4.8v-2.6L18 12z" stroke="#e8b54a"/>
                    </svg>
                </span>
                <h1 class="sl-title serif">{{ __('auth_login_title') }}</h1>
                <p class="sl-sub">{{ __('For staff: administration, teaching staff and the bursary.') }}</p>
            </div>

            <form method="POST" action="{{ route('login') }}" class="sl-form" id="sl-form">
                @csrf

                @if(request()->get('session_expired'))
                    <div class="sl-alert warn" role="status">
                        <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>
                        <span>{{ __('Your session timed out after a while without use. Sign in again to carry on.') }}</span>
                    </div>
                @endif
                @if(session('status'))
                    <div class="sl-alert ok" role="status">
                        <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12.5l4.5 4.5L19 7.5"/></svg>
                        <span>{{ session('status') }}</span>
                    </div>
                @endif
                @if($errors->any() || session('error'))
                    <div class="sl-alert err" role="alert">
                        <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 8v5M12 16.5v.5"/></svg>
                        <span>{{ $errors->any() ? $errors->first() : session('error') }}</span>
                    </div>
                @endif

                <div>
                    <label for="email" class="sl-label">
                        <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3.5 6.5l8.5 6 8.5-6"/></svg>
                        {{ __('field_email') }}
                    </label>
                    <input id="email" name="email" type="email" required autofocus autocomplete="username" value="{{ old('email') }}"
                           placeholder="{{ __('you@example.com') }}" class="sl-input @error('email') bad @enderror">
                </div>

                <div>
                    <label for="password" class="sl-label">
                        <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="4.5" y="10.5" width="15" height="10" rx="2"/><path d="M8 10.5V7.5a4 4 0 018 0v3"/></svg>
                        {{ __('field_password') }}
                    </label>
                    <div class="sl-pw">
                        <input id="password" name="password" type="password" required autocomplete="current-password"
                               placeholder="{{ __('Your password') }}" class="sl-input @error('password') bad @enderror">
                        <button type="button" id="sl-eye" class="sl-eye" aria-controls="password" aria-pressed="false"
                                aria-label="{{ __('Show password') }}" data-show="{{ __('Show password') }}" data-hide="{{ __('Hide password') }}">
                            <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <path d="M2.5 12S6 5.5 12 5.5 21.5 12 21.5 12 18 18.5 12 18.5 2.5 12 2.5 12z"/><circle cx="12" cy="12" r="3"/>
                                <path data-slash d="M4 20L20 4" style="display:none"/>
                            </svg>
                        </button>
                    </div>
                </div>

                <div class="sl-row">
                    <label>
                        <input type="checkbox" name="remember" {{ old('remember') ? 'checked' : '' }}>
                        {{ __('field_remember') }}
                    </label>
                    @if (Route::has('password.request'))
                        <a href="{{ route('password.request') }}" class="sl-link">{{ __('auth_forgot_password') }}</a>
                    @endif
                </div>

                <button type="submit" class="sl-submit">
                    {{ __('auth_login') }}
                    <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
                </button>
            </form>

            @if (Route::has('register'))
                <p class="sl-foot">{{ __('auth_dont_have_account') }} <a href="{{ route('register') }}">{{ __('auth_register') }}</a></p>
            @endif
            <p class="sl-foot">
                {{ __('Not staff?') }}
                @if (Route::has('student.login'))
                    <a href="{{ route('student.login') }}">{{ __('Student portal') }}</a> ·
                @endif
                <a href="{{ route('home') }}">{{ __('Back to the website') }}</a>
            </p>
            <p class="sl-foot">
                @isset($setting->copyright_text)
                    &copy; {!! strip_tags($setting->copyright_text, '<a><b><br>') !!}
                @else
                    &copy; {{ now()->year }} {{ institution_name() }}
                @endisset
            </p>
        </div>
    </main>
</div>

<script>
    (function () {
        var eye = document.getElementById('sl-eye');
        var field = document.getElementById('password');
        eye.addEventListener('click', function () {
            var showing = field.type === 'text';
            field.type = showing ? 'password' : 'text';
            eye.setAttribute('aria-pressed', String(!showing));
            eye.setAttribute('aria-label', showing ? eye.dataset.show : eye.dataset.hide);
            eye.querySelector('[data-slash]').style.display = showing ? 'none' : '';
        });
        // One press signs in once: the button waits while the request is on its way.
        document.getElementById('sl-form').addEventListener('submit', function (e) {
            var b = e.target.querySelector('.sl-submit');
            setTimeout(function () { b.disabled = true; }, 0);
        });
    })();
</script>
@endsection
