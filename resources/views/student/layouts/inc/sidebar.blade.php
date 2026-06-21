<!-- Sidemenu -->
<div class="navbar-content scroll-div ps ps--active-y">
    <ul class="nav pcoded-inner-navbar">

        @php
            function panel($slug){
                return \App\Models\Field::field($slug);
            }
        @endphp

        <li class="nav-item {{ Request::is('student/dashboard*') ? 'active' : '' }}">
            <a href="{{ route('student.dashboard.index') }}" class="nav-link">
                <span class="pcoded-micon"><i class="fas fa-desktop"></i></span>
                <span class="pcoded-mtext">{{ trans_choice('module_dashboard', 1) }}</span>
            </a>
        </li>

        @if(panel('panel_class_routine')->status == 1)
        <li class="nav-item {{ Request::is('student/class-routine*') ? 'active' : '' }}">
            <a href="{{ route('student.class-routine.index') }}" class="nav-link">
                <span class="pcoded-micon"><i class="fas fa-clock"></i></span>
                <span class="pcoded-mtext">{{ trans_choice('module_class_routine', 2) }}</span>
            </a>
        </li>
        @endif

        @if(panel('panel_exam_routine')->status == 1)
        <li class="nav-item {{ Request::is('student/exam-routine*') ? 'active' : '' }}">
            <a href="{{ route('student.exam-routine.index') }}" class="nav-link">
                <span class="pcoded-micon"><i class="fas fa-align-left"></i></span>
                <span class="pcoded-mtext">{{ trans_choice('module_exam_routine', 2) }}</span>
            </a>
        </li>
        @endif

        @php
            $examResultPanel = panel('panel_exam_result');
        @endphp
    @if(is_null($examResultPanel) || $examResultPanel->status == 1)
        <li class="nav-item {{ Request::is('student/exam-results*') ? 'active' : '' }}">
            <a href="{{ route('student.exam-results.index') }}" class="nav-link">
                <span class="pcoded-micon"><i class="fas fa-chart-line"></i></span>
                <span class="pcoded-mtext">{{ __('Exam Results') }}</span>
            </a>
        </li>
        @endif

        <li class="nav-item {{ Request::is('student/course-registration*') ? 'active' : '' }}">
            <a href="{{ route('student.course-registration.index') }}" class="nav-link">
                <span class="pcoded-micon"><i class="fas fa-book"></i></span>
                <span class="pcoded-mtext">{{ __('Course Registration') }}</span>
            </a>
        </li>

        <li class="nav-item {{ Request::is('student/form-a2*') ? 'active' : '' }}">
            <a href="{{ route('student.form-a2.index') }}" class="nav-link">
                <span class="pcoded-micon"><i class="fas fa-file-alt"></i></span>
                <span class="pcoded-mtext">{{ __('Form A2') }}</span>
            </a>
        </li>

        <li class="nav-item {{ Request::is('student/form-a3*') ? 'active' : '' }}">
            <a href="{{ route('student.form-a3.index') }}" class="nav-link">
                <span class="pcoded-micon"><i class="fas fa-file-contract"></i></span>
                <span class="pcoded-mtext">{{ __('Form A3 Docs') }}</span>
            </a>
        </li>

        @if(panel('panel_attendance')->status == 1)
        <li class="nav-item {{ Request::is('student/attendance*') ? 'active' : '' }}">
            <a href="{{ route('student.attendance.index') }}" class="nav-link">
                <span class="pcoded-micon"><i class="fas fa-calendar-check"></i></span>
                <span class="pcoded-mtext">{{ trans_choice('module_attendance', 2) }}</span>
            </a>
        </li>
        @endif

        <!-- Class Hub - Live Class Interaction -->
        <li class="nav-item pcoded-hasmenu {{ Request::is('student/class-hub*') ? 'active pcoded-trigger' : '' }}">
            <a href="#!" class="nav-link">
                <span class="pcoded-micon"><i class="fas fa-broadcast-tower"></i></span>
                <span class="pcoded-mtext">Class Hub</span>
                @php
                    // Check if there are live classes right now for this student
                    $liveClassCount = 0;
                    $enrollmentId = session('student_enrollment_id');
                    if($enrollmentId) {
                        $enrollment = \App\Models\StudentEnroll::find($enrollmentId);
                        if($enrollment) {
                            $liveClassCount = \App\Models\ClassSession::where('status', 'in_progress')
                                ->where('program_id', $enrollment->program_id)
                                ->where('semester_id', $enrollment->semester_id)
                                ->where('session_id', $enrollment->session_id)
                                ->where(function($q) use ($enrollment) {
                                    $q->whereNull('section_id')
                                      ->orWhere('section_id', $enrollment->section_id);
                                })->count();
                        }
                    }
                @endphp
                @if($liveClassCount > 0)
                <span class="pcoded-badge badge badge-success" style="animation: pulse 2s infinite;">{{ $liveClassCount }} LIVE</span>
                @endif
            </a>
            <ul class="pcoded-submenu">
                <li class="{{ Request::is('student/class-hub') && !Request::is('student/class-hub/*') ? 'active' : '' }}">
                    <a href="{{ route('student.class-hub.index') }}">
                        <i class="fas fa-calendar-day"></i> Today's Classes
                    </a>
                </li>
                <li class="{{ Request::is('student/class-hub/history*') ? 'active' : '' }}">
                    <a href="{{ route('student.class-hub.history') }}">
                        <i class="fas fa-history"></i> Class History
                    </a>
                </li>
                <li class="{{ Request::is('student/class-hub/my-notes*') ? 'active' : '' }}">
                    <a href="{{ route('student.class-hub.my-notes') }}">
                        <i class="fas fa-sticky-note"></i> My Notes
                    </a>
                </li>
            </ul>
        </li>

        @if(panel('panel_leave')->status == 1)
        <li class="nav-item {{ Request::is('student/leave*') ? 'active' : '' }}">
            <a href="{{ route('student.leave.index') }}" class="nav-link">
                <span class="pcoded-micon"><i class="far fa-edit"></i></span>
                <span class="pcoded-mtext">{{ trans_choice('module_apply_leave', 2) }}</span>
            </a>
        </li>
        @endif

        {{-- <li class="nav-item {{ Request::is('student/event-calendar*') ? 'active' : '' }}">
            <a href="{{ route('student.event.calendar') }}" class="nav-link">
                <span class="pcoded-micon"><i class="fas fa-calendar-alt"></i></span>
                <span class="pcoded-mtext">{{ trans_choice('module_calendar', 1) }}</span>
            </a>
        </li> --}}

        @if(panel('panel_fees_report')->status == 1)
        <li class="nav-item {{ Request::is('student/fees*') ? 'active' : '' }}">
            <a href="{{ route('student.fees.index') }}" class="nav-link">
                <span class="pcoded-micon"><i class="fas fa-money-bill-wave"></i></span>
                <span class="pcoded-mtext">{{ trans_choice('module_fees_report', 2) }}</span>
            </a>
        </li>
        @endif

        @if(panel('panel_fees_report')->status == 1)
        <li class="nav-item {{ Request::is('student/manual-payment*') ? 'active' : '' }}">
            <a href="{{ route('student.manual-payment.index') }}" class="nav-link">
                <span class="pcoded-micon"><i class="fas fa-receipt"></i></span>
                <span class="pcoded-mtext">{{ trans_choice('module_manual_payment', 2) }}</span>
            </a>
        </li>
        @endif

        @if(panel('panel_fees_report')->status == 1)
        <li class="nav-item {{ Request::is('student/multi-payment*') ? 'active' : '' }}">
            <a href="{{ route('student.multi-payment.index') }}" class="nav-link">
                <span class="pcoded-micon"><i class="fas fa-layer-group"></i></span>
                <span class="pcoded-mtext">Multi-Fee Payments</span>
            </a>
        </li>
        @endif

        @if(panel('panel_fees_report')->status == 1)
        <li class="nav-item {{ Request::is('student/payment-plan*') ? 'active' : '' }}">
            <a href="{{ route('student.payment-plan.index') }}" class="nav-link">
                <span class="pcoded-micon"><i class="fas fa-calendar-check"></i></span>
                <span class="pcoded-mtext">Payment Plans</span>
            </a>
        </li>
        @endif

        @if(panel('panel_library')->status == 1)
        <li class="nav-item {{ Request::is('student/library*') ? 'active' : '' }}">
            <a href="{{ route('student.library.index') }}" class="nav-link">
                <span class="pcoded-micon"><i class="fas fa-book-open"></i></span>
                <span class="pcoded-mtext">{{ trans_choice('module_library', 2) }}</span>
            </a>
        </li>
        @endif

        <li class="nav-item pcoded-hasmenu {{ Request::is('student/e-library*') ? 'active pcoded-trigger' : '' }}">
            <a href="#!" class="nav-link">
                <span class="pcoded-micon"><i class="fas fa-book-reader"></i></span>
                <span class="pcoded-mtext">E-Library</span>
            </a>
            <ul class="pcoded-submenu">
                <li class="{{ Request::is('student/e-library') ? 'active' : '' }}">
                    <a href="{{ route('student.e-library.index') }}">
                        <i class="fas fa-home"></i> Home
                    </a>
                </li>
                <li class="{{ Request::is('student/e-library/browse*') ? 'active' : '' }}">
                    <a href="{{ route('student.e-library.browse') }}">
                        <i class="fas fa-search"></i> Browse Books
                    </a>
                </li>
                <li class="{{ Request::is('student/e-library/favorites*') ? 'active' : '' }}">
                    <a href="{{ route('student.e-library.favorites') }}">
                        <i class="fas fa-heart"></i> My Favorites
                    </a>
                </li>
                <li class="{{ Request::is('student/e-library/history*') ? 'active' : '' }}">
                    <a href="{{ route('student.e-library.history') }}">
                        <i class="fas fa-history"></i> Reading History
                    </a>
                </li>
                <li class="nav-item-divider"></li>
                <li class="{{ Request::is('student/e-library/ai/recommendations*') ? 'active' : '' }}">
                    <a href="{{ route('student.e-library.ai.recommendations') }}">
                        <i class="fas fa-magic"></i> AI Recommendations
                    </a>
                </li>
                <li class="{{ Request::is('student/e-library/ai/insights*') ? 'active' : '' }}">
                    <a href="{{ route('student.e-library.ai.insights') }}">
                        <i class="fas fa-chart-line"></i> Reading Insights
                    </a>
                </li>
            </ul>
        </li>

        @if(panel('panel_notice')->status == 1)
        <li class="nav-item {{ Request::is('student/notice*') ? 'active' : '' }}">
            <a href="{{ route('student.notice.index') }}" class="nav-link">
                <span class="pcoded-micon"><i class="fas fa-bullhorn"></i></span>
                <span class="pcoded-mtext">{{ trans_choice('module_notice', 2) }}</span>
            </a>
        </li>
        @endif

        @if(panel('panel_assignment')->status == 1)
        <li class="nav-item {{ Request::is('student/assignment*') ? 'active' : '' }}">
            <a href="{{ route('student.assignment.index') }}" class="nav-link">
                <span class="pcoded-micon"><i class="fas fa-newspaper"></i></span>
                <span class="pcoded-mtext">{{ trans_choice('module_assignment', 2) }}</span>
            </a>
        </li>
        @endif

        @if(panel('panel_download')->status == 1)
        <li class="nav-item {{ Request::is('student/download*') ? 'active' : '' }}">
            <a href="{{ route('student.download.index') }}" class="nav-link">
                <span class="pcoded-micon"><i class="fas fa-download"></i></span>
                <span class="pcoded-mtext">{{ trans_choice('module_download', 2) }}</span>
            </a>
        </li>
        @endif

        @if(panel('panel_transcript')->status == 1)
        <li class="nav-item {{ Request::is('student/transcript*') ? 'active' : '' }}">
            <a href="{{ route('student.transcript.index') }}" class="nav-link">
                <span class="pcoded-micon"><i class="fas fa-pen-square"></i></span>
                <span class="pcoded-mtext">{{ trans_choice('module_transcript', 1) }}</span>
            </a>
        </li>
        @endif

        <li class="nav-item {{ Request::is('student/resit*') ? 'active' : '' }}">
            <a href="{{ route('student.resit.index') }}" class="nav-link">
                <span class="pcoded-micon"><i class="fas fa-redo-alt"></i></span>
                <span class="pcoded-mtext">{{ __('Resits') }}</span>
            </a>
        </li>

        @if(panel('panel_profile')->status == 1)
        <li class="nav-item {{ Request::is('student/profile*') ? 'active' : '' }}">
            <a href="{{ route('student.profile.index') }}" class="nav-link">
                <span class="pcoded-micon"><i class="fas fa-id-card"></i></span>
                <span class="pcoded-mtext">{{ trans_choice('module_profile', 2) }}</span>
            </a>
        </li>
        @endif

    </ul>
</div>
<!-- End Sidebar -->