{{--
    A plain-language guide panel for one step of the application.

    Written for applicants who have never filled in an online form before, so
    it says what to do in order, what to have beside you, and the one mistake
    that most often sends an application back. Expanded by default: someone who
    needs the help should not have to discover a toggle to get it.

    Usage:
        @include('application.partials.step-guide', [
            'guideId'    => 'step1',
            'guideIntro' => 'One sentence saying what this step is for.',
            'guideSteps' => ['Do this first.', 'Then this.'],
            'guideNeed'  => ['Your birth certificate'],          // optional
            'guideWarn'  => 'The thing that most often goes wrong.', // optional
            'guideTime'  => 'About 5 minutes',                    // optional
        ])
--}}
@php
    $guideId    = $guideId    ?? 'guide-' . uniqid();
    $guideSteps = $guideSteps ?? [];
    $guideNeed  = $guideNeed  ?? [];
    $guideIntro = $guideIntro ?? null;
    $guideWarn  = $guideWarn  ?? null;
    $guideTime  = $guideTime  ?? null;
@endphp

{{-- Styles travel with the partial so it works under either layout: the
     wizard has its own <head>, the portal pages use portal.layout. @once
     keeps a page with eight guides from emitting the stylesheet eight times. --}}
@once
<style>
    .step-guide { border: 1px solid #cfe0f7; border-radius: 10px; background: #f5f9ff; overflow: hidden; }
    .step-guide-toggle {
        display: flex; align-items: center; width: 100%;
        background: transparent; border: 0; padding: .85rem 1.1rem;
        text-align: left; color: #14396e; cursor: pointer;
    }
    .step-guide-toggle:hover { background: #eaf2ff; }
    .step-guide-heading { font-weight: 700; font-size: .96rem; flex-grow: 1; }
    .step-guide-time { font-size: .8rem; color: #5b7bab; font-weight: 600; white-space: nowrap; }
    .step-guide-chevron { transition: transform .2s ease; color: #5b7bab; font-size: .8rem; }
    .step-guide-toggle.collapsed .step-guide-chevron { transform: rotate(180deg); }
    .step-guide-body { padding: 0 1.1rem 1.1rem; }
    .step-guide-intro { color: #33507d; font-size: .93rem; margin-bottom: 1rem; }
    .step-guide-label {
        display: block; font-size: .72rem; font-weight: 800; letter-spacing: .8px;
        text-transform: uppercase; color: #7189b0; margin-bottom: .4rem;
    }
    .step-guide-need { background: #fff; border-radius: 8px; padding: .8rem 1rem; margin-bottom: 1rem; }
    .step-guide-need ul { margin: 0; padding-left: 1.1rem; }
    .step-guide-need li { color: #475569; font-size: .89rem; padding: .12rem 0; }
    .step-guide-list { margin: 0; padding-left: 1.25rem; }
    .step-guide-list li { color: #33507d; font-size: .92rem; padding: .22rem 0; }
    .step-guide-warn {
        margin-top: 1rem; background: #fff6ec; border-left: 3px solid #e08a2b;
        border-radius: 0 8px 8px 0; padding: .7rem .9rem; color: #8a5312; font-size: .88rem;
    }
</style>
@endonce

<div class="step-guide mb-4">
    <button class="step-guide-toggle" type="button" data-bs-toggle="collapse"
            data-bs-target="#{{ $guideId }}" aria-expanded="true" aria-controls="{{ $guideId }}">
        <span class="step-guide-heading">
            <i class="fas fa-lightbulb me-2"></i>{{ __('How to complete this step') }}
        </span>
        @if($guideTime)
            <span class="step-guide-time"><i class="far fa-clock me-1"></i>{{ $guideTime }}</span>
        @endif
        <i class="fas fa-chevron-up step-guide-chevron ms-2"></i>
    </button>

    <div class="collapse show" id="{{ $guideId }}">
        <div class="step-guide-body">
            @if($guideIntro)
                <p class="step-guide-intro">{{ $guideIntro }}</p>
            @endif

            @if(!empty($guideNeed))
                <div class="step-guide-need">
                    <span class="step-guide-label">{{ __('Have this beside you') }}</span>
                    <ul>
                        @foreach($guideNeed as $need)
                            <li>{{ $need }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @if(!empty($guideSteps))
                <span class="step-guide-label">{{ __('What to do') }}</span>
                <ol class="step-guide-list">
                    @foreach($guideSteps as $line)
                        <li>{{ $line }}</li>
                    @endforeach
                </ol>
            @endif

            @if($guideWarn)
                <div class="step-guide-warn">
                    <i class="fas fa-exclamation-circle me-2"></i>{{ $guideWarn }}
                </div>
            @endif
        </div>
    </div>
</div>
