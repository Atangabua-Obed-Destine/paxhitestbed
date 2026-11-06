{{-- The course registration deadline, on every page of the portal.

     A student held at the platform-fee page or the fees page never reaches
     the dashboard banner, and those are exactly the students most likely to
     run out of time. So the countdown rides in the layout, above whatever
     page they are on, in the colour of how little time is left.

     Shown while the deadline is still ahead, whatever else the student has
     to do first; and after it has passed only to a student who registered
     nothing, for whom it is a problem rather than a fact. --}}
@php
    $stripStudent = Auth::guard('student')->user();
    $stripEnroll = null;
    $stripDeadline = null;

    if ($stripStudent) {
        $stripSelected = session('selected_enrollment_id');

        if ($stripSelected) {
            $stripEnroll = \App\Models\StudentEnroll::with('semester')
                ->where('id', $stripSelected)
                ->where('student_id', $stripStudent->id)
                ->first();
        }

        $stripEnroll = $stripEnroll ?: $stripStudent->currentEnroll;
        $stripDeadline = \App\Models\CourseRegistrationDeadline::forEnrollment($stripEnroll);
    }

    $stripRegistered = $stripEnroll && $stripDeadline ? $stripEnroll->subjects()->count() : 0;
    $stripShow = $stripDeadline && (!$stripDeadline->isPast() || $stripRegistered === 0);
@endphp

@if($stripShow)
    @php
        $stripClosed = $stripDeadline->isPast();
        $stripColour = $stripDeadline->colour();
        $stripSemester = optional($stripEnroll->semester)->title;
    @endphp
    <div id="registration-deadline-strip" class="alert alert-{{ $stripColour }} shadow-sm mb-3 py-2"
         role="alert" style="border-left: 5px solid currentColor;">
        <div class="d-flex align-items-center flex-wrap">
            <div class="flex-grow-1 mb-1 mb-md-0">
                <i class="fas {{ $stripClosed ? 'fa-lock' : 'fa-hourglass-half' }}"></i>
                @if($stripClosed)
                    <strong>{{ __('Course registration closed on :when.', ['when' => $stripDeadline->closesAtLong()]) }}</strong>
                    {{ __('You have no courses registered for :semester. Speak to your department as soon as possible.', ['semester' => $stripSemester]) }}
                @else
                    <strong>{{ __('Course registration for :semester closes :when', ['semester' => $stripSemester, 'when' => $stripDeadline->closesAtLong()]) }}</strong>
                    <span class="badge badge-pill badge-{{ $stripColour }}">{{ $stripDeadline->countdown() }}</span>
                    @if($stripRegistered > 0)
                        <small class="d-block d-md-inline ml-md-1">{{ __('You have :count course(s) registered. Changes are possible until then.', ['count' => $stripRegistered]) }}</small>
                    @else
                        <small class="d-block d-md-inline ml-md-1">{{ __('You have not registered any course yet. After that moment the portal will not let you.') }}</small>
                    @endif
                @endif
            </div>
            @if(!$stripClosed)
                <a href="{{ route('student.course-registration.index') }}" class="btn btn-{{ $stripColour }} btn-sm ml-md-2">
                    <i class="fas fa-arrow-right"></i> {{ __('Course Registration') }}
                </a>
            @endif
        </div>
    </div>
@endif
