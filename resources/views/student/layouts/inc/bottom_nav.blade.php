{{--
  The tab bar.

  Four destinations a student opens daily, plus More. Each tab stays lit for
  everything beneath it — a student three screens into the class hub still sees
  Classes — so the bar never loses their place.

  Which tabs exist is App\Support\StudentNav's answer, not this file's: a
  module switched off in the panels is not offered here either.
--}}

@php
    $appTabs = \App\Support\StudentNav::tabs();
    $appGroups = \App\Support\StudentNav::moreGroups();
    /*
     * More lights only when nothing else does.
     *
     * Attendance is under the Classes tab AND listed in the sheet, so marking
     * More whenever a sheet item is current lit two tabs at once — which reads
     * as the bar not knowing where the student is. A tab wins; More is the
     * answer for everywhere a tab does not reach.
     */
    $appTabOn = collect($appTabs)->contains(fn ($t) => $t['active']);
    $appMoreOn = !$appTabOn && collect($appGroups)->flatten(1)->contains(fn ($i) => $i['active']);
@endphp

<nav class="app-nav" aria-label="{{ __('nav_more') }}">
    @foreach($appTabs as $tab)
    <a href="{{ $tab['url'] }}"
       class="app-tab {{ $tab['active'] ? 'is-on' : '' }}"
       @if($tab['active']) aria-current="page" @endif>
        <span class="app-tab-ico">
            <i class="{{ $tab['icon'] }}" aria-hidden="true"></i>
            @if($tab['badge'])
            {{-- Something is owed. A dot, not a figure: the figure belongs on
                 the Fees screen, and it carries a label for a reader who
                 cannot see it. --}}
            <span class="app-tab-dot" role="img" aria-label="{{ __('nav_fees_due') }}"></span>
            @endif
        </span>
        <span>{{ $tab['label'] }}</span>
    </a>
    @endforeach

    <button type="button"
            class="app-tab {{ $appMoreOn ? 'is-on' : '' }}"
            data-bs-toggle="modal" data-bs-target="#appMore">
        <span class="app-tab-ico"><i class="fas fa-ellipsis-h" aria-hidden="true"></i></span>
        <span>{{ __('nav_more') }}</span>
    </button>
</nav>

@include('student.layouts.inc.more_sheet', ['appGroups' => $appGroups])
