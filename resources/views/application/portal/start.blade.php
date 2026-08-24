@extends('application.portal.layout')

@push('styles')
<style>
    .start-page { background: #f5f7fb; min-height: 100vh; display: flex; flex-direction: column; }

    .start-topbar { background: #182b49; color: #fff; padding: 1rem 0; }
    .start-topbar .brand { display: flex; align-items: center; gap: .75rem; }
    .start-topbar .brand img { height: 40px; width: auto; }
    .start-topbar .brand-text { line-height: 1.2; }
    .start-topbar .brand-text strong { display: block; font-size: 1rem; letter-spacing: .2px; }
    .start-topbar .brand-text span { font-size: .78rem; color: rgba(255,255,255,.6); }
    .start-topbar a.back-link { color: rgba(255,255,255,.8); text-decoration: none; font-size: .9rem; font-weight: 500; }
    .start-topbar a.back-link:hover { color: #fff; }

    .start-hero { padding: 3.25rem 0 2rem; }
    .start-hero h1 {
        font-size: clamp(1.9rem, 4vw, 2.9rem);
        font-weight: 800;
        color: #182b49;
        letter-spacing: -.5px;
        margin-bottom: .85rem;
        text-wrap: balance;
    }
    .start-hero .lead { color: #5a6a85; max-width: 46rem; margin: 0 auto; }

    .status-pill {
        display: inline-flex;
        align-items: center;
        gap: .5rem;
        padding: .4rem 1rem;
        border-radius: 999px;
        font-size: .85rem;
        font-weight: 600;
        margin-bottom: 1.35rem;
    }
    .status-pill.is-open { background: #e6f7ee; color: #0f7a45; }
    .status-pill.is-closed { background: #fdf0e6; color: #a2560d; }
    .status-pill .dot { width: 8px; height: 8px; border-radius: 50%; background: currentColor; }

    /* The two paths, deliberately unequal. Most people arriving here have
       never applied before, so their route is the one that carries weight. */
    .path-card {
        background: #fff;
        border: 1px solid rgba(24,43,73,.09);
        border-radius: 14px;
        padding: 2rem;
        height: 100%;
        display: flex;
        flex-direction: column;
    }
    .path-card.primary { border: 2px solid #182b49; box-shadow: 0 14px 40px rgba(24,43,73,.13); }
    .path-card .eyebrow {
        font-size: .74rem;
        font-weight: 700;
        letter-spacing: 1.1px;
        text-transform: uppercase;
        color: #8a97ab;
        margin-bottom: .6rem;
    }
    .path-card.primary .eyebrow { color: #182b49; }
    .path-card h2 { font-size: 1.3rem; font-weight: 700; color: #182b49; margin-bottom: .5rem; }
    .path-card p { color: #64748b; font-size: .94rem; flex-grow: 1; }
    .path-card .btn { padding: .8rem 1.25rem; font-weight: 600; border-radius: 9px; }

    .btn-start { background: #182b49; border-color: #182b49; color: #fff; }
    .btn-start:hover, .btn-start:focus { background: #0f1d33; border-color: #0f1d33; color: #fff; }
    .btn-continue { border: 1.5px solid #cbd5e1; color: #334155; background: #fff; }
    .btn-continue:hover, .btn-continue:focus { border-color: #182b49; color: #182b49; background: #f8fafc; }

    .section-title {
        font-size: 1.35rem;
        font-weight: 800;
        color: #182b49;
        margin-bottom: 1.25rem;
        letter-spacing: -.2px;
    }

    /* One tab per degree type, each carrying its own fee so the choice can be
       made before opening the panel. Hidden entirely when only one exists. */
    .degree-tabs { list-style: none; padding: 0; gap: .6rem; flex-wrap: wrap; }
    .degree-tab {
        background: #fff;
        border: 1px solid rgba(24,43,73,.12);
        border-radius: 11px;
        padding: .8rem 1.2rem;
        text-align: left;
        cursor: pointer;
        transition: border-color .15s ease, box-shadow .15s ease;
    }
    .degree-tab:hover { border-color: #9db4dd; }
    .degree-tab.active { border-color: #182b49; box-shadow: 0 0 0 1px #182b49 inset; }
    .degree-tab-title { display: block; font-weight: 700; font-size: .93rem; color: #182b49; }
    .degree-tab-fee { display: block; font-size: .8rem; color: #64748b; margin-top: .15rem; }

    .prep-panel { background: #fff; border: 1px solid rgba(24,43,73,.08); border-radius: 14px; padding: 2rem; }
    .prep-panel h3 { font-size: 1.08rem; font-weight: 700; color: #182b49; margin-bottom: 1.25rem; }
    .degree-name { font-size: 1.15rem !important; margin-bottom: .5rem !important; }
    .degree-intro { color: #64748b; font-size: .94rem; }
    .degree-intro p:last-child { margin-bottom: 0; }

    .prep-list { list-style: none; padding: 0; margin: 0; }
    .prep-list li { display: flex; gap: .7rem; align-items: flex-start; padding: .5rem 0; color: #475569; font-size: .93rem; }
    .prep-list li i { color: #0f7a45; margin-top: .2rem; flex-shrink: 0; }
    .doc-note { display: block; font-style: normal; font-size: .84rem; color: #94a3b8; margin-top: .1rem; }

    .optional-note { margin-top: 1rem; font-size: .87rem; color: #64748b; }
    .optional-note strong { color: #475569; }

    .fee-window {
        font-size: .88rem;
        color: #a2560d;
        background: #fdf6ee;
        border-radius: 8px;
        padding: .6rem .85rem;
    }
    .auto-submit {
        font-size: .88rem;
        color: #0f7a45;
        background: #eefaf3;
        border-radius: 8px;
        padding: .6rem .85rem;
    }
    .fee-notes { font-size: .86rem; color: #64748b; }
    .fee-notes p:last-child { margin-bottom: 0; }

    .fact-row { display: flex; flex-wrap: wrap; gap: 2.25rem; }
    .fact .label {
        font-size: .72rem;
        text-transform: uppercase;
        letter-spacing: 1px;
        color: #8a97ab;
        font-weight: 700;
        margin-bottom: .2rem;
    }
    .fact .value { font-size: 1.1rem; font-weight: 700; color: #182b49; }

    .reassure {
        background: #eef4ff;
        border-left: 3px solid #3b6fd4;
        border-radius: 0 9px 9px 0;
        padding: .95rem 1.15rem;
        color: #2f4a7d;
        font-size: .9rem;
    }

    .start-footer { margin-top: auto; padding: 2rem 0; color: #8a97ab; font-size: .85rem; }
    .start-footer a { color: #5a6a85; text-decoration: none; }
    .start-footer a:hover { text-decoration: underline; }

    @media (max-width: 767.98px) {
        .start-hero { padding: 2.25rem 0 1.5rem; }
        .path-card { padding: 1.5rem; }
    }
</style>
@endpush

@section('content')
<div class="start-page">

    <header class="start-topbar">
        <div class="container d-flex align-items-center justify-content-between">
            <div class="brand">
                @if(!empty($setting) && !empty($setting->logo_path))
                    <img src="{{ asset('uploads/setting/'.$setting->logo_path) }}" alt="{{ institution_name() }}">
                @else
                    <i class="fas fa-graduation-cap fa-2x"></i>
                @endif
                <span class="brand-text">
                    <strong>{{ institution_name() }}</strong>
                    <span>{{ __('Admissions') }}</span>
                </span>
            </div>
            <a href="{{ route('home') }}" class="back-link">
                <i class="fas fa-arrow-left me-1"></i>{{ __('Back to website') }}
            </a>
        </div>
    </header>

    <div class="container">

        <section class="start-hero text-center">
            @if($isOpen)
                <span class="status-pill is-open">
                    <span class="dot"></span>
                    @if($openSessions->count() === 1)
                        {{ __('Applications are open for') }} {{ $openSessions->first()->title }}
                    @else
                        {{ __('Applications are open') }}
                    @endif
                </span>
            @else
                <span class="status-pill is-closed">
                    <span class="dot"></span>
                    {{ __('Applications are currently closed') }}
                </span>
            @endif

            <h1>{{ __('Apply to') }} {{ institution_name() }}</h1>

            @if($isOpen)
                <p class="lead">
                    {{ __('Everything you need to apply is here. Create your account, complete the form at your own pace, and submit it when you are ready.') }}
                </p>
            @else
                <p class="lead">
                    {{ __('Applications for the next intake are not open yet. The admissions page carries the dates of the next application window.') }}
                </p>
            @endif
        </section>

        @if($isOpen)
            <section class="row g-4 mb-5 align-items-stretch">
                <div class="col-lg-7">
                    <div class="path-card primary">
                        <div class="eyebrow">{{ __('New applicant') }}</div>
                        <h2>{{ __('Start your application') }}</h2>
                        <p>
                            {{ __('Applying here for the first time? Create your applicant account and begin. It takes a minute to set up, and you can come back to finish the form whenever it suits you.') }}
                        </p>
                        <a href="{{ route('application.register') }}" class="btn btn-start btn-lg mt-3">
                            {{ __('Start my application') }} <i class="fas fa-arrow-right ms-2"></i>
                        </a>
                    </div>
                </div>

                <div class="col-lg-5">
                    <div class="path-card">
                        <div class="eyebrow">{{ __('Already started') }}</div>
                        <h2>{{ __('Continue your application') }}</h2>
                        <p>
                            {{ __('If you already created an account, sign in to pick up where you left off, upload documents or check the status of your application.') }}
                        </p>
                        <a href="{{ route('application.login') }}" class="btn btn-continue btn-lg mt-3">
                            {{ __('Sign in to continue') }}
                        </a>
                    </div>
                </div>
            </section>

            {{-- What you need and what it costs is configured per degree type
                 under Academic > Degree Type > Form Configuration, so it is
                 read from there rather than restated here. Add a degree type
                 and it appears; change its checklist and this follows. --}}
            @if($degreeTypes->isNotEmpty())
                @php $currency = optional($setting)->currency_symbol ?: optional($setting)->currency; @endphp

                <section class="mb-5">
                    <h2 class="section-title">{{ __('What you can apply for') }}</h2>

                    @if($degreeTypes->count() > 1)
                        <ul class="nav degree-tabs mb-4" role="tablist">
                            @foreach($degreeTypes as $index => $degree)
                                <li class="nav-item" role="presentation">
                                    <button class="degree-tab {{ $index === 0 ? 'active' : '' }}"
                                            id="degree-tab-{{ $degree['id'] }}"
                                            data-bs-toggle="tab"
                                            data-bs-target="#degree-pane-{{ $degree['id'] }}"
                                            type="button"
                                            role="tab"
                                            aria-controls="degree-pane-{{ $degree['id'] }}"
                                            aria-selected="{{ $index === 0 ? 'true' : 'false' }}">
                                        <span class="degree-tab-title">{{ $degree['title'] }}</span>
                                        @if($degree['fee'])
                                            <span class="degree-tab-fee">{{ number_format($degree['fee'], 0) }} {{ $currency }}</span>
                                        @endif
                                    </button>
                                </li>
                            @endforeach
                        </ul>
                    @endif

                    <div class="tab-content">
                        @foreach($degreeTypes as $index => $degree)
                            <div class="tab-pane fade {{ $index === 0 ? 'show active' : '' }}"
                                 id="degree-pane-{{ $degree['id'] }}"
                                 role="tabpanel"
                                 aria-labelledby="degree-tab-{{ $degree['id'] }}">

                                <div class="prep-panel">
                                    @if($degreeTypes->count() === 1)
                                        <h3 class="degree-name">{{ $degree['title'] }}</h3>
                                    @endif

                                    @if(!empty($degree['intro']))
                                        <div class="degree-intro">{!! $degree['intro'] !!}</div>
                                    @endif
                                    @if(!empty($degree['blurb']))
                                        <div class="degree-intro">{!! $degree['blurb'] !!}</div>
                                    @endif

                                    <div class="row g-4 mt-1">
                                        <div class="col-lg-7">
                                            <h3><i class="far fa-check-circle text-success me-2"></i>{{ __('Before you begin, have these ready') }}</h3>

                                            <ul class="prep-list">
                                                @forelse($degree['required'] as $document)
                                                    <li>
                                                        <i class="fas fa-check"></i>
                                                        <span>
                                                            {{ $document['label'] }}
                                                            @if(!empty($document['description']))
                                                                <em class="doc-note">{{ $document['description'] }}</em>
                                                            @endif
                                                        </span>
                                                    </li>
                                                @empty
                                                    <li>
                                                        <i class="fas fa-check"></i>
                                                        <span>{{ __('No documents are required upfront for this programme type.') }}</span>
                                                    </li>
                                                @endforelse
                                            </ul>

                                            @if($degree['optional']->isNotEmpty())
                                                <p class="optional-note mb-0">
                                                    <strong>{{ __('Optional, and never a reason to wait:') }}</strong>
                                                    {{ $degree['optional']->pluck('label')->implode(', ') }}.
                                                </p>
                                            @endif
                                        </div>

                                        <div class="col-lg-5">
                                            <h3><i class="far fa-clock text-primary me-2"></i>{{ __('What to expect') }}</h3>

                                            <div class="fact-row mb-4">
                                                <div class="fact">
                                                    <div class="label">{{ __('Time needed') }}</div>
                                                    <div class="value">{{ __('About 15 minutes') }}</div>
                                                </div>

                                                <div class="fact">
                                                    <div class="label">{{ __('Application fee') }}</div>
                                                    <div class="value">
                                                        @if($degree['fee'])
                                                            {{ number_format($degree['fee'], 0) }} {{ $currency }}
                                                        @else
                                                            {{ __('None') }}
                                                        @endif
                                                    </div>
                                                </div>
                                            </div>

                                            @if($degree['fee'])
                                                {{-- The fee is raised when the form is finished and the
                                                     applicant reaches the payment step, not at intake, so
                                                     the window is counted from there. Approval then submits
                                                     the application on its own. --}}
                                                <div class="fee-window mb-3">
                                                    <i class="fas fa-hourglass-half me-1"></i>
                                                    {{ __('You pay once your form is complete.') }}
                                                    @if($degree['feeDays'] > 0)
                                                        {{ __('From that point you have') }}
                                                        <strong>{{ trans_choice(':count day|:count days', $degree['feeDays'], ['count' => $degree['feeDays']]) }}</strong>
                                                        {{ __('to settle it.') }}
                                                    @endif
                                                </div>

                                                <div class="auto-submit mb-3">
                                                    <i class="fas fa-paper-plane me-1"></i>
                                                    {{ __('Once your payment is approved, your application is submitted automatically — there is nothing further for you to do.') }}
                                                </div>
                                            @endif

                                            @if(!empty($degree['feeNotes']))
                                                <div class="fee-notes mb-3">{!! $degree['feeNotes'] !!}</div>
                                            @endif

                                            <div class="reassure">
                                                <i class="fas fa-save me-1"></i>
                                                <strong>{{ __('You do not have to finish in one sitting.') }}</strong>
                                                {{ __('Your answers are saved as you go, so you can close the page and return to complete the rest later.') }}
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </section>
            @endif
        @else
            {{-- Applications are closed. Offer the admissions page, never a form
                 that would only turn the applicant away. --}}
            <section class="text-center mb-5">
                <a href="{{ route('admissions') }}" class="btn btn-start btn-lg">
                    <i class="fas fa-info-circle me-2"></i>{{ __('View admission information') }}
                </a>
                <p class="mt-4 mb-0 text-muted">
                    {{ __('Already applied?') }}
                    <a href="{{ route('application.login') }}" class="fw-semibold">{{ __('Sign in to check your application') }}</a>
                </p>
            </section>
        @endif

        <footer class="start-footer text-center">
            <p class="mb-1">
                {{ __('Need help with your application?') }}
                @if(!empty($setting) && !empty($setting->email))
                    <a href="mailto:{{ $setting->email }}">{{ $setting->email }}</a>
                @endif
                @if(!empty($setting) && !empty($setting->phone))
                    &middot; <a href="tel:{{ $setting->phone }}">{{ $setting->phone }}</a>
                @endif
            </p>
            <p class="mb-0">&copy; {{ date('Y') }} {{ institution_name() }}</p>
        </footer>

    </div>
</div>
@endsection
