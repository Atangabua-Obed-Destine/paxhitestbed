<!-- Sidemenu -->
<div class="navbar-content scroll-div ps ps--active-y">
    <ul class="nav pcoded-inner-navbar">

        @can('dashboard-view')
        <li class="nav-item {{ Request::is('admin/dashboard*') ? 'active' : '' }}">
            <a href="{{ route('admin.dashboard.index') }}" class="nav-link">
                <span class="pcoded-micon"><i class="fas fa-home"></i></span>
                <span class="pcoded-mtext">{{ trans_choice('module_dashboard', 1) }}</span>
            </a>
        </li>
        @endcan

        @canany(['application-create', 'application-view', 'applicant-view', 'student-create', 'student-view', 'student-import', 'student-password-print', 'student-password-change', 'student-card', 'student-transfer-in-create', 'student-transfer-in-view', 'student-transfer-out-create', 'student-transfer-out-view', 'status-type-create', 'status-type-view', 'id-card-setting-view', 'student-form-a2-view', 'student-form-a3-view', 'student-exam-code-view'])
        <li class="nav-item pcoded-hasmenu {{ Request::is('admin/admission*') ? 'pcoded-trigger active' : '' }}">
            <a href="#!" class="nav-link">
                <span class="pcoded-micon"><i class="fas fa-university"></i></span>
                <span class="pcoded-mtext">{{ trans_choice('module_admission', 2) }}</span>
            </a>
            <ul class="pcoded-submenu">
                @canany(['application-create', 'application-view'])
                <li class="{{ Request::is('admin/admission/application*') && !Request::is('admin/admission/fee-config*') ? 'active' : '' }}"><a href="{{ route('admin.application.index') }}" class="">{{ trans_choice('module_application', 2) }}</a></li>
                @endcanany

                @can('applicant-view')
                <li class="{{ Request::is('admin/admission/applicant*') ? 'active' : '' }}"><a href="{{ route('admin.applicant.index') }}" class="">{{ __('Applicants') }}</a></li>
                @endcan
                
                @can('admission-fee-config-view')
                <li class="{{ Request::is('admin/admission/fee-config*') ? 'active' : '' }}"><a href="{{ route('admin.admission-fee-config.index') }}" class="">{{ __('Admission Fee Config') }}</a></li>
                @endcan

                @can('form-a2-setting-view')
                <li class="{{ Request::is('setting/form-a2-setting*') ? 'active' : '' }}"><a href="{{ route('admin.form-a2-setting.index') }}" class="">{{ __('Form A2 Config') }}</a></li>
                @endcan

                @can('form-a3-setting-view')
                <li class="{{ Request::is('setting/form-a3-setting*') ? 'active' : '' }}"><a href="{{ route('admin.form-a3-setting.index') }}" class="">{{ __('Form A3 Config') }}</a></li>
                @endcan

                @canany(['student-create'])
                <li class="{{ Request::is('admin/admission/student/create') ? 'active' : '' }}"><a href="{{ route('admin.student.create') }}" class="">{{ trans_choice('module_registration', 1) }}</a></li>
                @endcanany

                @canany(['student-view', 'student-password-print', 'student-password-change', 'student-card', 'student-import'])
                <li class="{{ Request::is('admin/admission/student') ? 'active' : '' }}"><a href="{{ route('admin.student.index') }}" class="">{{ trans_choice('module_student', 1) }} {{ __('list') }}</a></li>
                @endcanany

                @canany(['student-transfer-in-create', 'student-transfer-in-view', 'student-transfer-out-create', 'student-transfer-out-view'])
                <li class="nav-item pcoded-hasmenu {{ Request::is('admin/admission/student-transfer*') ? 'pcoded-trigger active' : '' }}">
                    <a href="#!" class="nav-link">
                        <span class="pcoded-mtext">{{ trans_choice('module_student_transfer', 2) }}</span>
                    </a>

                    <ul class="pcoded-submenu">
                        @canany(['student-transfer-in-create', 'student-transfer-in-view'])
                        <li class="{{ Request::is('admin/admission/student-transfer-in*') ? 'active' : '' }}"><a href="{{ route('admin.student-transfer-in.index') }}" class="">{{ trans_choice('module_transfer_in', 1) }}</a></li>
                        @endcanany

                        @canany(['student-transfer-out-create', 'student-transfer-out-view'])
                        <li class="{{ Request::is('admin/admission/student-transfer-out*') ? 'active' : '' }}"><a href="{{ route('admin.student-transfer-out.index') }}" class="">{{ trans_choice('module_transfer_out', 1) }}</a></li>
                        @endcanany
                    </ul>
                </li>
                @endcanany

                @canany(['status-type-create', 'status-type-view'])
                <li class="{{ Request::is('admin/admission/status-type*') ? 'active' : '' }}"><a href="{{ route('admin.status-type.index') }}" class="">{{ trans_choice('module_status_type', 2) }}</a></li>
                @endcanany

                @canany(['student-card'])
                <li class="{{ Request::is('admin/admission/id-card') ? 'active' : '' }}"><a href="{{ route('admin.id-card.index') }}" class="">{{ trans_choice('module_id_card', 2) }}</a></li>
                @endcanany

                @canany(['id-card-setting-view'])
                <li class="nav-item pcoded-hasmenu {{ Request::is('admin/admission/id-card-setting*') ? 'pcoded-trigger active' : '' }}">
                    <a href="#!" class="nav-link">
                        <span class="pcoded-mtext">{{ trans_choice('module_setting', 2) }}</span>
                    </a>

                    <ul class="pcoded-submenu">
                        @can('id-card-setting-view')
                        <li class="{{ Request::is('admin/admission/id-card-setting*') ? 'active' : '' }}"><a href="{{ route('admin.id-card-setting.index') }}" class="">{{ trans_choice('module_id_card_setting', 1) }}</a></li>
                        @endcan
                    </ul>
                </li>
                @endcanany

                @can('form-a2-access-view')
                <li class="{{ Request::is('admin/form-a2-access*') ? 'active' : '' }}"><a href="{{ route('admin.form-a2-access.index') }}" class="">{{ __('Form A2 Access') }}</a></li>
                @endcan

                @can('student-exam-code-view')
                <li class="{{ Request::is('admin/admission/student-exam-codes*') ? 'active' : '' }}"><a href="{{ route('admin.student-exam-codes.index') }}" class=""><i class="fas fa-id-badge"></i> {{ __('HND Exam Codes') }}</a></li>
                @endcan
                @can('student-form-a2-view')
                <li class="{{ Request::is('admin/admission/student-form-a2*') ? 'active' : '' }}"><a href="{{ route('admin.student-form-a2.index') }}" class=""><i class="fas fa-file-alt"></i> {{ __('Student Form A2') }}</a></li>
                @endcan

                @can('student-form-a3-view')
                <li class="{{ Request::is('admin/admission/student-form-a3*') ? 'active' : '' }}"><a href="{{ route('admin.student-form-a3.index') }}" class=""><i class="fas fa-list-alt"></i> {{ __('Student Form A3') }}</a></li>
                @endcan
            </ul>
        </li>
        @endcanany

        @can('student-archive-view')
        <li class="nav-item {{ Request::is('admin/student-archive*') ? 'active' : '' }}">
            <a href="{{ route('admin.student-archive.index') }}" class="nav-link">
                <span class="pcoded-micon"><i class="fas fa-archive"></i></span>
                <span class="pcoded-mtext">{{ __('Student Archives') }}</span>
            </a>
        </li>
        @endcan

        @canany(['student-attendance-action', 'student-attendance-report', 'student-leave-manage-view', 'student-leave-manage-edit', 'student-note-create', 'student-note-view', 'student-enroll-single', 'student-enroll-group', 'student-enroll-adddrop', 'student-enroll-complete', 'student-enroll-alumni'])
        <li class="nav-item pcoded-hasmenu {{ Request::is('admin/student*') ? 'pcoded-trigger active' : '' }}">
            <a href="#!" class="nav-link">
                <span class="pcoded-micon"><i class="fas fa-user-graduate"></i></span>
                <span class="pcoded-mtext">{{ trans_choice('module_student', 2) }}</span>
            </a>
            <ul class="pcoded-submenu">

                @canany(['student-attendance-action', 'student-attendance-report', 'attendance-setting-view', 'class-hub-reports-view'])
                <li class="nav-item pcoded-hasmenu {{ Request::is('admin/student-attendance*') ? 'pcoded-trigger active' : '' }} {{ Request::is('admin/class-session*') ? 'pcoded-trigger active' : '' }} {{ Request::is('admin/attendance-settings*') ? 'pcoded-trigger active' : '' }} {{ Request::is('admin/class-hub-reports*') ? 'pcoded-trigger active' : '' }}">
                    <a href="#!" class="nav-link">
                        <span class="pcoded-mtext">{{ trans_choice('module_attendance', 2) }}</span>
                    </a>

                    <ul class="pcoded-submenu">
                        @can('student-attendance-action')
                        <li class="{{ Request::is('admin/student-attendance') ? 'active' : '' }}"><a href="{{ route('admin.student-attendance.index') }}" class="">{{ trans_choice('module_student_subject_attendance', 2) }}</a></li>
                        @endcan

                        @can('student-attendance-action')
                        <li class="{{ Request::is('admin/student-attendance-bulk-migration*') ? 'active' : '' }}"><a href="{{ route('admin.student-attendance.bulk-migration') }}" class=""><i class="fas fa-calendar-alt mr-1"></i> Bulk Migration</a></li>
                        @endcan

                        @can('student-attendance-report')
                        <li class="{{ Request::is('admin/student-attendance-report*') ? 'active' : '' }}"><a href="{{ route('admin.student-attendance.report') }}" class="">{{ trans_choice('module_student_subject_report', 2) }}</a></li>
                        @endcan

                        @can('class-session-view')
                        <li class="{{ Request::is('admin/class-session') ? 'active' : '' }}"><a href="{{ route('admin.class-session.index') }}" class=""><i class="fas fa-chalkboard-teacher mr-1"></i> Class Sessions & Logbook</a></li>
                        @endcan

                        @can('class-session-create')
                        <li class="{{ Request::is('admin/class-session/kiosk*') ? 'active' : '' }}"><a href="{{ route('admin.class-session.kiosk') }}" class=""><i class="fas fa-qrcode mr-1"></i> Open Class Kiosk</a></li>
                        @endcan

                        @can('attendance-setting-view')
                        <li class="{{ Request::is('admin/attendance-settings*') ? 'active' : '' }}"><a href="{{ route('admin.attendance-settings.index') }}" class=""><i class="fas fa-cogs mr-1"></i> Kiosk Settings</a></li>
                        @endcan

                        @can('class-hub-reports-view')
                        <li class="{{ Request::is('admin/class-hub-reports*') ? 'active' : '' }}"><a href="{{ route('admin.class-hub-reports.index') }}" class=""><i class="fas fa-chart-line mr-1"></i> Class Hub Reports</a></li>
                        @endcan
                    </ul>
                </li>
                @endcanany

                @canany(['student-leave-manage-view', 'student-leave-manage-edit'])
                <li class="{{ Request::is('admin/student-leave-manage*') ? 'active' : '' }}"><a href="{{ route('admin.student-leave-manage.index') }}" class="">{{ trans_choice('module_leave_manage', 1) }}</a></li>
                @endcanany

                @canany(['student-note-create', 'student-note-view'])
                <li class="{{ Request::is('admin/student/student-note*') ? 'active' : '' }}"><a href="{{ route('admin.student-note.index') }}" class="">{{ trans_choice('module_student_note', 2) }}</a></li>
                @endcanany

                @canany(['student-enroll-single', 'student-enroll-group', 'student-enroll-adddrop', 'student-enroll-complete'])
                <li class="nav-item pcoded-hasmenu {{ Request::is('admin/student/single-enroll*') ? 'pcoded-trigger active' : '' }} {{ Request::is('admin/student/group-enroll*') ? 'pcoded-trigger active' : '' }} {{ Request::is('admin/student/subject-adddrop*') ? 'pcoded-trigger active' : '' }} {{ Request::is('admin/student/course-complete*') ? 'pcoded-trigger active' : '' }}">
                    <a href="#!" class="nav-link">
                        <span class="pcoded-mtext">{{ trans_choice('module_student_enroll', 2) }}</span>
                    </a>

                    <ul class="pcoded-submenu">
                        @canany(['student-enroll-single'])
                        <li class="{{ Request::is('admin/student/single-enroll*') ? 'active' : '' }}"><a href="{{ route('admin.single-enroll.index') }}" class="">{{ trans_choice('module_single_enroll', 1) }}</a></li>
                        @endcanany

                        @canany(['student-enroll-group'])
                        <li class="{{ Request::is('admin/student/group-enroll*') ? 'active' : '' }}"><a href="{{ route('admin.group-enroll.index') }}" class="">{{ trans_choice('module_group_enroll', 2) }}</a></li>
                        @endcanany

                        @canany(['student-enroll-adddrop'])
                        <li class="{{ Request::is('admin/student/subject-adddrop*') ? 'active' : '' }}"><a href="{{ route('admin.subject-adddrop.index') }}" class="">{{ trans_choice('module_subject_adddrop', 2) }}</a></li>
                        @endcanany

                        @canany(['student-enroll-complete'])
                        <li class="{{ Request::is('admin/student/course-complete*') ? 'active' : '' }}"><a href="{{ route('admin.course-complete.index') }}" class="">{{ trans_choice('module_course_complete', 2) }}</a></li>
                        @endcanany
                    </ul>
                </li>
                @endcanany

                @can('max-credit-config-view')
                <li class="{{ Request::is('admin/student/max-credit-config*') ? 'active' : '' }}">
                    <a href="{{ route('admin.max-credit-config.index') }}" class="">
                        <span class="pcoded-mtext">{{ __('Max Credits Config') }}</span>
                    </a>
                </li>
                @endcan

                @canany(['student-enroll-alumni'])
                <li class="{{ Request::is('admin/student/student-alumni*') ? 'active' : '' }}"><a href="{{ route('admin.student-alumni.index') }}" class="">{{ trans_choice('module_student_alumni', 1) }} {{ __('list') }}</a></li>
                @endcanany

                @canany(['catholic-student-view', 'catholic-student-edit'])
                <li class="{{ Request::is('admin/catholic-student*') ? 'active' : '' }}">
                    <a href="{{ route('admin.catholic-student.index') }}" class="">
                        <span class="pcoded-mtext"><i class="fas fa-cross"></i> {{ trans_choice('module_catholic_student', 2) }}</span>
                    </a>
                </li>
                @endcanany
            </ul>
        </li>
        @endcanany

        @canany(['faculty-create', 'faculty-view', 'program-create', 'program-view', 'batch-create', 'batch-view', 'session-create', 'session-view', 'semester-create', 'semester-view', 'section-create', 'section-view', 'class-room-create', 'class-room-view', 'subject-create', 'subject-view', 'enroll-subject-create', 'enroll-subject-view', 'sector-create', 'sector-view'])
        <li class="nav-item pcoded-hasmenu {{ Request::is('admin/academic*') ? 'pcoded-trigger active' : '' }}">
            <a href="#!" class="nav-link">
                <span class="pcoded-micon"><i class="fab fa-accusoft"></i></span>
                <span class="pcoded-mtext">{{ trans_choice('module_academic', 2) }}</span>
            </a>
            <ul class="pcoded-submenu">
                <li class="{{ Request::is('admin/academic/health-status*') ? 'active' : '' }}"><a href="{{ route('admin.academic-health.index') }}" class=""><i class="fas fa-heartbeat mr-2"></i>{{ __('Health Status') }}</a></li>
                @canany(['sector-create', 'sector-view'])
                <li class="{{ Request::is('admin/academic/sector*') ? 'active' : '' }}"><a href="{{ route('admin.sector.index') }}" class="">{{ __('Sector') }}</a></li>
                @endcanany

                @canany(['faculty-create', 'faculty-view'])
                <li class="{{ Request::is('admin/academic/faculty*') ? 'active' : '' }}"><a href="{{ route('admin.faculty.index') }}" class="">{{ trans_choice('module_faculty', 2) }}</a></li>
                @endcanany
                
                @canany(['academic-department-create', 'academic-department-view'])
                <li class="{{ Request::is('admin/academic/academic-department*') ? 'active' : '' }}"><a href="{{ route('admin.academic-department.index') }}" class="">Academic Departments</a></li>
                @endcanany
                
                @canany(['degree-type-create', 'degree-type-view'])
                <li class="{{ Request::is('admin/academic/degree-type*') ? 'active' : '' }}"><a href="{{ route('admin.degree-type.index') }}" class="">Degree Types</a></li>
                @can('letterhead-view')
                <li class="{{ Request::is('admin/academic/letterhead*') ? 'active' : '' }}"><a href="{{ route('admin.letterhead.index') }}" class="">{{ __('Letterhead') }}</a></li>
                @endcan
                @endcanany
                
                @canany(['program-create', 'program-view'])
                <li class="{{ Request::is('admin/academic/program*') ? 'active' : '' }}"><a href="{{ route('admin.program.index') }}" class="">{{ trans_choice('module_program', 2) }}</a></li>
                @endcanany

                @canany(['batch-create', 'batch-view'])
                <li class="{{ Request::is('admin/academic/batch*') ? 'active' : '' }}"><a href="{{ route('admin.batch.index') }}" class="">{{ trans_choice('module_batch', 2) }}</a></li>
                @endcanany

                @canany(['session-create', 'session-view'])
                <li class="{{ Request::is('admin/academic/session*') ? 'active' : '' }}"><a href="{{ route('admin.session.index') }}" class="">{{ trans_choice('module_session', 2) }}</a></li>
                @endcanany

                @canany(['semester-create', 'semester-view'])
                <li class="{{ Request::is('admin/academic/semester*') ? 'active' : '' }}"><a href="{{ route('admin.semester.index') }}" class="">{{ trans_choice('module_semester', 2) }}</a></li>
                @endcanany
                
                @canany(['section-create', 'section-view'])
                <li class="{{ Request::is('admin/academic/section*') ? 'active' : '' }}"><a href="{{ route('admin.section.index') }}" class="">{{ trans_choice('module_section', 2) }}</a></li>
                @endcanany
                
                @canany(['class-room-create', 'class-room-view'])
                <li class="{{ Request::is('admin/academic/room*') ? 'active' : '' }}"><a href="{{ route('admin.room.index') }}" class="">{{ trans_choice('module_class_room', 2) }}</a></li>
                @endcanany
                
                @canany(['subject-create', 'subject-view'])
                <li class="{{ Request::is('admin/academic/subject*') ? 'active' : '' }}"><a href="{{ route('admin.subject.index') }}" class="">{{ trans_choice('module_subject', 2) }}</a></li>
                @endcanany

                @canany(['enroll-subject-create', 'enroll-subject-view'])
                <li class="{{ Request::is('admin/academic/enroll-subject*') ? 'active' : '' }}"><a href="{{ route('admin.enroll-subject.index') }}" class="">{{ trans_choice('module_enroll_subject', 2) }}</a></li>
                @endcanany
            </ul>
        </li>
        @endcanany

        @canany(['class-routine-create', 'class-routine-view', 'class-routine-print', 'exam-routine-create', 'exam-routine-view', 'exam-routine-print', 'class-routine-teacher', 'routine-setting-class', 'routine-setting-exam'])
        <li class="nav-item pcoded-hasmenu {{ Request::is('admin/routine*') ? 'pcoded-trigger active' : '' }}">
            <a href="#!" class="nav-link">
                <span class="pcoded-micon"><i class="far fa-calendar-alt"></i></span>
                <span class="pcoded-mtext">{{ trans_choice('module_routine', 2) }}</span>
            </a>
            <ul class="pcoded-submenu">
                @canany(['class-routine-create'])
                <li class="{{ Request::is('admin/routine/class-routine/create') ? 'active' : '' }}"><a href="{{ route('admin.class-routine.create') }}" class="">{{ trans_choice('module_manage_class', 2) }}</a></li>
                @endcanany

                @canany(['class-routine-create'])
                <li class="{{ Request::is('admin/routine/class-routine-joint') ? 'active' : '' }}"><a href="{{ route('admin.class-routine.joint') }}" class=""><i class="fas fa-users text-info"></i> {{ __('Joint Class Schedule') }}</a></li>
                @endcanany

                @canany(['class-routine-view', 'class-routine-print'])
                <li class="{{ Request::is('admin/routine/class-routine') ? 'active' : '' }}"><a href="{{ route('admin.class-routine.index') }}" class="">{{ trans_choice('module_class_routine', 2) }}</a></li>
                @endcanany

                @canany(['exam-routine-create'])
                <li class="{{ Request::is('admin/routine/exam-routine/create') ? 'active' : '' }}"><a href="{{ route('admin.exam-routine.create') }}" class="">{{ trans_choice('module_manage_exam', 2) }}</a></li>
                @endcanany

                @canany(['exam-routine-create'])
                <li class="{{ Request::is('admin/routine/exam-routine-joint') ? 'active' : '' }}"><a href="{{ route('admin.exam-routine.joint') }}" class=""><i class="fas fa-users text-info"></i> {{ __('Joint Exam Schedule') }}</a></li>
                @endcanany

                @canany(['exam-routine-view', 'exam-routine-print'])
                <li class="{{ Request::is('admin/routine/exam-routine') ? 'active' : '' }}"><a href="{{ route('admin.exam-routine.index') }}" class="">{{ trans_choice('module_exam_routine', 2) }}</a></li>
                @endcanany

                @canany(['exam-routine-view', 'exam-routine-print'])
                <li class="{{ Request::is('admin/routine/exam-schedule-overview') ? 'active' : '' }}"><a href="{{ route('admin.exam-routine.schedule-overview') }}" class=""><i class="fas fa-calendar-check text-success"></i> {{ __('Exam Schedule Overview') }}</a></li>
                @endcanany

                @can('class-routine-teacher')
                <li class="{{ Request::is('admin/routine/class-routine-teacher') ? 'active' : '' }}"><a href="{{ route('admin.class-routine.teacher') }}" class="">{{ trans_choice('module_teacher_routine', 2) }}</a></li>
                @endcan

                @canany(['routine-setting-class', 'routine-setting-exam'])
                <li class="nav-item pcoded-hasmenu {{ Request::is('admin/routine/routine-setting*') ? 'pcoded-trigger active' : '' }}">
                    <a href="#!" class="nav-link">
                        <span class="pcoded-mtext">{{ trans_choice('module_setting', 2) }}</span>
                    </a>

                    <ul class="pcoded-submenu">
                        @can('routine-setting-class')
                        <li class="{{ Request::is('admin/routine/routine-setting/class*') ? 'active' : '' }}"><a href="{{ route('admin.routine-setting.class') }}" class="">{{ trans_choice('module_class_routine', 1) }}</a></li>
                        @endcan

                        @can('routine-setting-exam')
                        <li class="{{ Request::is('admin/routine/routine-setting/exam*') ? 'active' : '' }}"><a href="{{ route('admin.routine-setting.exam') }}" class="">{{ trans_choice('module_exam_routine', 1) }}</a></li>
                        @endcan
                    </ul>
                </li>
                @endcanany
            </ul>
        </li>
        @endcanany

        @canany(['exam-attendance', 'exam-marking', 'exam-result', 'subject-marking', 'subject-result', 'exam-publishing-view', 'grade-view', 'grade-create', 'exam-type-view', 'exam-type-create', 'admit-card-view', 'admit-card-print', 'admit-card-download', 'admit-setting-view', 'result-contribution-view', 'results-summary-view', 'senate-deliberation-view'])
        <li class="nav-item pcoded-hasmenu {{ Request::is('admin/exam*') ? 'pcoded-trigger active' : '' }}">
            <a href="#!" class="nav-link">
                <span class="pcoded-micon"><i class="fas fa-file-alt"></i></span>
                <span class="pcoded-mtext">{{ trans_choice('module_examination', 2) }}</span>
            </a>
            <ul class="pcoded-submenu">
                @can('exam-attendance')
                <li class="{{ Request::is('admin/exam/exam-attendance*') ? 'active' : '' }}"><a href="{{ route('admin.exam-attendance.index') }}" class="">{{ trans_choice('module_exam_attendance', 2) }}</a></li>
                <li class="{{ Request::is('admin/exam/attendance-eligibility*') ? 'active' : '' }}"><a href="{{ route('admin.exam-attendance-settings.index') }}" class=""><i class="fas fa-cog"></i> {{ __('Attendance Eligibility') }}</a></li>
                @endcan

                @can('exam-marking')
                <li class="{{ Request::is('admin/exam/exam-marking*') ? 'active' : '' }}"><a href="{{ route('admin.exam-marking.index') }}" class="">{{ trans_choice('module_exam_marking', 2) }}</a></li>
                <li class="{{ Request::is('admin/exam/student-exam-config*') ? 'active' : '' }}"><a href="{{ route('admin.student-exam-config.index') }}" class="">{{ __('Student Exam Config') }}</a></li>
                @endcan

                @can('exam-result')
                <li class="{{ Request::is('admin/exam/exam-result*') ? 'active' : '' }}"><a href="{{ route('admin.exam-result') }}" class="">{{ trans_choice('module_exam_result', 2) }}</a></li>
                @endcan

                @can('subject-marking')
                <li class="{{ Request::is('admin/exam/subject-marking*') ? 'active' : '' }}"><a href="{{ route('admin.subject-marking.index') }}" class="">{{ trans_choice('module_subject_marking', 2) }}</a></li>
                @endcan

                @can('exam-publishing-view')
                <li class="{{ Request::is('admin/exam/exam-publishing*') ? 'active' : '' }}"><a href="{{ route('admin.exam-publishing.index') }}" class=""><i class="fas fa-broadcast-tower"></i> {{ __('Exam Publishing') }}</a></li>
                @endcan

                @can('subject-result')
                <li class="{{ Request::is('admin/exam/subject-result*') ? 'active' : '' }}"><a href="{{ route('admin.subject-result') }}" class="">{{ trans_choice('module_subject_result', 2) }}</a></li>
                @endcan

                @canany(['results-summary-view'])
                <li class="nav-item pcoded-hasmenu {{ Request::is('admin/exam/results-summary*') ? 'pcoded-trigger active' : '' }}">
                    <a href="#!" class="nav-link">
                        <span class="pcoded-mtext"><i class="fas fa-chart-bar"></i> {{ __('Results Summary') }}</span>
                    </a>

                    <ul class="pcoded-submenu">
                        <li class="{{ Request::is('admin/exam/results-summary') && !Request::is('admin/exam/results-summary/*') ? 'active' : '' }}">
                            <a href="{{ route('admin.results-summary.index') }}" class=""><i class="fas fa-tachometer-alt"></i> {{ __('Dashboard') }}</a>
                        </li>
                        <li class="{{ Request::is('admin/exam/results-summary/course-marksheet*') ? 'active' : '' }}">
                            <a href="{{ route('admin.results-summary.course-marksheet') }}" class=""><i class="fas fa-file-spreadsheet"></i> {{ __('Course Mark Sheet') }}</a>
                        </li>
                        <li class="{{ Request::is('admin/exam/results-summary/department-report*') ? 'active' : '' }}">
                            <a href="{{ route('admin.results-summary.department-report') }}" class=""><i class="fas fa-building"></i> {{ __('Department Report') }}</a>
                        </li>
                        <li class="{{ Request::is('admin/exam/results-summary/faculty-report*') ? 'active' : '' }}">
                            <a href="{{ route('admin.results-summary.faculty-report') }}" class=""><i class="fas fa-university"></i> {{ __('Faculty Report') }}</a>
                        </li>
                    </ul>
                </li>
                @endcanany

                @canany(['senate-deliberation-view'])
                <li class="nav-item pcoded-hasmenu {{ Request::is('admin/exam/senate-deliberation*') ? 'pcoded-trigger active' : '' }}">
                    <a href="#!" class="nav-link">
                        <span class="pcoded-mtext"><i class="fas fa-gavel"></i> {{ __('Senate Deliberation') }}</span>
                    </a>

                    <ul class="pcoded-submenu">
                        <li class="{{ Request::is('admin/exam/senate-deliberation') && !Request::is('admin/exam/senate-deliberation/*') ? 'active' : '' }}">
                            <a href="{{ route('admin.senate-deliberation.index') }}" class=""><i class="fas fa-tachometer-alt"></i> {{ __('Dashboard') }}</a>
                        </li>
                        <li class="{{ Request::is('admin/exam/senate-deliberation/results-preview*') ? 'active' : '' }}">
                            <a href="{{ route('admin.senate-deliberation.results-preview') }}" class=""><i class="fas fa-clipboard-check"></i> {{ __('Results Preview') }}</a>
                        </li>
                        <li class="{{ Request::is('admin/exam/senate-deliberation/academic-standings*') ? 'active' : '' }}">
                            <a href="{{ route('admin.senate-deliberation.academic-standings') }}" class=""><i class="fas fa-graduation-cap"></i> {{ __('Academic Standings') }}</a>
                        </li>
                        <li class="{{ Request::is('admin/exam/senate-deliberation/deliberations*') || Request::is('admin/exam/senate-deliberation/[0-9]*') ? 'active' : '' }}">
                            <a href="{{ route('admin.senate-deliberation.deliberations') }}" class=""><i class="fas fa-clipboard-list"></i> {{ __('Deliberations') }}</a>
                        </li>
                    </ul>
                </li>
                @endcanany

                @can('resit-request-view')
                <li class="{{ Request::is('admin/exam/resit-requests*') ? 'active' : '' }}"><a href="{{ route('admin.resit-requests.index') }}" class="">{{ __('Resit Requests') }}</a></li>
                @endcan

                @can('resit-request-transition')
                <li class="{{ Request::is('admin/exam/resit-settings*') ? 'active' : '' }}"><a href="{{ route('admin.resit-settings.edit') }}" class="">{{ __('Resit Settings') }}</a></li>
                @endcan

                @canany(['grade-view', 'grade-create'])
                <li class="{{ Request::is('admin/exam/grade*') ? 'active' : '' }}"><a href="{{ route('admin.grade.index') }}" class="">{{ trans_choice('module_grade', 2) }}</a></li>
                @endcanany

                @canany(['exam-type-view', 'exam-type-create'])
                <li class="{{ Request::is('admin/exam/exam-type*') ? 'active' : '' }}"><a href="{{ route('admin.exam-type.index') }}" class="">{{ trans_choice('module_exam_type', 2) }}</a></li>
                @endcanany

                @canany(['admit-card-view', 'admit-card-print', 'admit-card-download'])
                <li class="{{ Request::is('admin/exam/admit-card*') ? 'active' : '' }}"><a href="{{ route('admin.admit-card.index') }}" class="">{{ trans_choice('module_admit_card', 2) }}</a></li>
                @endcanany

                @canany(['admit-setting-view', 'result-contribution-view'])
                <li class="nav-item pcoded-hasmenu {{ Request::is('admin/exam/admit-setting*') ? 'pcoded-trigger active' : '' }} {{ Request::is('admin/exam/result-contribution*') ? 'pcoded-trigger active' : '' }}">
                    <a href="#!" class="nav-link">
                        <span class="pcoded-mtext">{{ trans_choice('module_setting', 2) }}</span>
                    </a>

                    <ul class="pcoded-submenu">
                        @can('admit-setting-view')
                        <li class="{{ Request::is('admin/exam/admit-setting*') ? 'active' : '' }}"><a href="{{ route('admin.admit-setting.index') }}" class="">{{ trans_choice('module_admit_setting', 1) }}</a></li>
                        @endcan

                        @can('result-contribution-view')
                        <li class="{{ Request::is('admin/exam/result-contribution*') ? 'active' : '' }}"><a href="{{ route('admin.result-contribution.index') }}" class="">{{ trans_choice('module_result_contribution', 2) }}</a></li>
                        @endcan
                    </ul>
                </li>
                @endcanany
            </ul>
        </li>
        @endcanany

        @canany(['assignment-create', 'assignment-view', 'assignment-marking', 'content-create', 'content-view', 'content-type-view', 'content-type-create'])
        <li class="nav-item pcoded-hasmenu {{ Request::is('admin/download*') ? 'pcoded-trigger active' : '' }}">
            <a href="#!" class="nav-link">
                <span class="pcoded-micon"><i class="fas fa-newspaper"></i></span>
                <span class="pcoded-mtext">{{ trans_choice('module_study_material', 2) }}</span>
            </a>
            <ul class="pcoded-submenu">
                @canany(['assignment-create', 'assignment-view', 'assignment-marking'])
                <li class="{{ Request::is('admin/download/assignment*') ? 'active' : '' }}"><a href="{{ route('admin.assignment.index') }}" class="">{{ trans_choice('module_assignment', 2) }}</a></li>
                @endcanany
                
                @canany(['content-create', 'content-view'])
                <li class="{{ Request::is('admin/download/content*') ? 'active' : '' }}"><a href="{{ route('admin.content.index') }}" class="">{{ trans_choice('module_content', 1) }} {{ __('list') }}</a></li>
                @endcanany

                @canany(['content-type-view', 'content-type-create'])
                <li class="{{ Request::is('admin/download/content-type*') ? 'active' : '' }}"><a href="{{ route('admin.content-type.index') }}" class="">{{ trans_choice('module_content_type', 2) }}</a></li>
                @endcanany
            </ul>
        </li>
        @endcanany

        @canany(['fees-student-due', 'fees-student-quick-assign', 'fees-student-quick-received', 'fees-student-report', 'fees-student-print', 'fees-master-view', 'fees-master-create', 'fees-category-view', 'fees-category-create', 'fees-discount-view', 'fees-discount-create', 'fees-fine-view', 'fees-fine-create', 'fees-receipt-view', 'payment-receipt-verify', 'payment-plan.index', 'program-semester-fee-view', 'program-semester-fee-create', 'fee-assignments-history-view'])
        <li class="nav-item pcoded-hasmenu {{ Request::is('admin/fees*') || Request::is('admin/payment-plan*') ? 'pcoded-trigger active' : '' }}">
            <a href="#!" class="nav-link">
                <span class="pcoded-micon"><i class="fas fa-money-bill-wave"></i></span>
                <span class="pcoded-mtext">{{ trans_choice('module_fees_collection', 1) }}</span>
            </a>
            <ul class="pcoded-submenu">
                @canany(['fees-student-due', 'fees-student-quick-assign', 'fees-student-quick-received', 'fees-student-report', 'fees-student-print'])
                <li class="nav-item pcoded-hasmenu {{ Request::is('admin/fees-student*') ? 'pcoded-trigger active' : '' }}">
                    <a href="#!" class="nav-link">
                        <span class="pcoded-mtext">{{ trans_choice('module_student_fees', 2) }}</span>
                    </a>

                    <ul class="pcoded-submenu">
                        @can('fees-student-due')
                        <li class="{{ Request::is('admin/fees-student') ? 'active' : '' }}"><a href="{{ route('admin.fees-student.index') }}" class="">{{ trans_choice('module_fees_due', 1) }}</a></li>
                        @endcan

                        @can('fees-student-quick-assign')
                        <li class="{{ Request::is('admin/fees-student-quick-assign*') ? 'active' : '' }}"><a href="{{ route('admin.fees-student.quick.assign') }}" class="">{{ trans_choice('module_fees_quick_assign', 1) }}</a></li>
                        @endcan

                        @can('fees-student-quick-received')
                        <li class="{{ Request::is('admin/fees-student-quick-received*') ? 'active' : '' }}"><a href="{{ route('admin.fees-student.quick.received') }}" class="">{{ trans_choice('module_fees_quick_received', 1) }}</a></li>
                        @endcan

                        @canany(['fees-student-report', 'fees-student-print'])
                        <li class="{{ Request::is('admin/fees-student-report*') ? 'active' : '' }}"><a href="{{ route('admin.fees-student.report') }}" class="">{{ trans_choice('module_fees_report', 2) }}</a></li>
                        @endcanany

                        @can('fees-student-report')
                        <li class="{{ Request::is('admin/student-credits*') ? 'active' : '' }}"><a href="{{ route('admin.student-credits.index') }}" class="">{{ __('student_credits') }}</a></li>
                        @endcan
                        @can('fee-credit-audit-view')
                        <li class="{{ Request::is('admin/fees-credit-audit*') ? 'active' : '' }}"><a href="{{ route('admin.fees-credit-audit.index') }}" class="">{{ __('Credit audit') }}</a></li>
                        @endcan
                    </ul>
                </li>
                @endcanany

                @can('payment-plan.index')
                <li class="{{ Request::is('admin/payment-plan*') ? 'active' : '' }}">
                    <a href="{{ route('admin.payment-plan.index') }}" class="">
                        <i class="fas fa-calendar-alt"></i> Payment Plans
                    </a>
                </li>
                @endcan

                @canany(['fees-master-create'])
                <li class="{{ Request::is('admin/fees-master/create*') ? 'active' : '' }}"><a href="{{ route('admin.fees-master.create') }}" class="">{{ trans_choice('module_fees_master', 2) }}</a></li>
                @endcanany

                @canany(['fees-master-view'])
                <li class="{{ Request::is('admin/fees-master') ? 'active' : '' }}"><a href="{{ route('admin.fees-master.index') }}" class="">{{ trans_choice('module_fees_master_history', 2) }}</a></li>
                @endcanany

                @can('fee-assignments-history-view')
                <li class="{{ Request::is('admin/fee-assignments-history*') ? 'active' : '' }}">
                    <a href="{{ route('admin.fee-assignments-history.index') }}" class="">
                        <i class="fas fa-stream"></i> {{ __('fee_assignments_history') }}
                    </a>
                </li>
                @endcan

                @canany(['program-semester-fee-view', 'program-semester-fee-create'])
                <li class="{{ Request::is('admin/program-semester-fee*') ? 'active' : '' }}"><a href="{{ route('admin.program-semester-fee.index') }}" class="">Program Semester Fees</a></li>
                @endcanany

                @canany(['fees-category-view', 'fees-category-create'])
                <li class="{{ Request::is('admin/fees-category*') ? 'active' : '' }}"><a href="{{ route('admin.fees-category.index') }}" class="">{{ trans_choice('module_fees_category', 2) }}</a></li>
                @endcanany

                @canany(['fees-discount-view', 'fees-discount-create'])
                <li class="{{ Request::is('admin/fees-discount*') ? 'active' : '' }}"><a href="{{ route('admin.fees-discount.index') }}" class="">{{ trans_choice('module_fees_discount', 2) }}</a></li>
                @endcanany

                @canany(['fees-fine-view', 'fees-fine-create'])
                <li class="{{ Request::is('admin/fees-fine*') ? 'active' : '' }}"><a href="{{ route('admin.fees-fine.index') }}" class="">{{ trans_choice('module_fees_fine', 2) }}</a></li>
                @endcanany

                @can('payment-receipt-verify')
                <li class="{{ Request::is('admin/payment-verification*') ? 'active' : '' }}"><a href="{{ route('admin.payment-verification.index') }}" class="">{{ trans_choice('module_payment_verification', 2) }}</a></li>
                @endcan

                {{-- Its own permission. This used to hang off payment-receipt-verify,
                     which gates a different screen — so the report was granted to
                     whoever could verify receipts and withheld from everyone else,
                     with no way to say otherwise. --}}
                @can('admission-fees-report-view')
                <li class="{{ Request::is('admin/admission-fees-report*') ? 'active' : '' }}"><a href="{{ route('admin.admission-fees-report.index') }}" class="">{{ __('Admission Fees') }}</a></li>
                @endcan

                @canany(['fees-fine-view', 'fees-fine-create', 'fees-receipt-view'])
                <li class="nav-item pcoded-hasmenu {{ Request::is('admin/fees-receipt*') ? 'pcoded-trigger active' : '' }}">
                    <a href="#!" class="nav-link">
                        <span class="pcoded-mtext">{{ trans_choice('module_setting', 2) }}</span>
                    </a>

                    <ul class="pcoded-submenu">
                        @can('fees-receipt-view')
                        <li class="{{ Request::is('admin/fees-receipt*') ? 'active' : '' }}"><a href="{{ route('admin.fees-receipt.index') }}" class="">{{ trans_choice('module_fees_receipt_setting', 1) }}</a></li>
                        @endcan
                    </ul>
                </li>
                @endcanany
            </ul>
        </li>
        @endcanany

        {{-- Platform Fee Management --}}
        @canany(['platform-fee-view', 'platform-fee-action', 'platform-fee-delete'])
        <li class="nav-item pcoded-hasmenu {{ Request::is('admin/platform-fee*') ? 'pcoded-trigger active' : '' }}">
            <a href="#!" class="nav-link">
                <span class="pcoded-micon"><i class="fas fa-shield-alt"></i></span>
                <span class="pcoded-mtext">Platform Fee</span>
            </a>
            <ul class="pcoded-submenu">
                <li class="{{ Request::is('admin/platform-fee/settings*') ? 'active' : '' }}">
                    <a href="{{ route('admin.platform-fee.settings') }}" class="">
                        <i class="fas fa-cog"></i> Settings
                    </a>
                </li>
                <li class="{{ Request::is('admin/platform-fee/verifications*') ? 'active' : '' }}">
                    <a href="{{ route('admin.platform-fee.verifications') }}" class="">
                        <i class="fas fa-check-double"></i> Verifications
                        <span class="badge badge-warning" id="platformFeeBadge" style="margin-left: 5px;"></span>
                    </a>
                </li>
                <li class="{{ Request::is('admin/platform-fee/statistics*') ? 'active' : '' }}">
                    <a href="{{ route('admin.platform-fee.statistics') }}" class="">
                        <i class="fas fa-chart-bar"></i> Statistics
                    </a>
                </li>
                <li class="{{ Request::is('admin/platform-fee/exemptions*') ? 'active' : '' }}">
                    <a href="{{ route('admin.platform-fee.exemptions') }}" class="">
                        <i class="fas fa-user-shield"></i> Exemptions
                    </a>
                </li>
            </ul>
        </li>
        @endcanany

        @canany(['user-create', 'user-view', 'user-password-print', 'user-password-change', 'staff-note-create', 'staff-note-view', 'payroll-view', 'payroll-action', 'payroll-print', 'payroll-report', 'work-shift-type-create', 'work-shift-type-view', 'designation-create', 'designation-view', 'department-create', 'department-view', 'tax-setting-create', 'tax-setting-view', 'pay-slip-setting-view', 'staff-assignment-index', 'staff-assignment-create', 'staff-assignment-edit', 'staff-assignment-delete'])
        <li class="nav-item pcoded-hasmenu {{ Request::is('admin/staff*') ? 'pcoded-trigger active' : '' }}">
            <a href="#!" class="nav-link">
                <span class="pcoded-micon"><i class="fas fa-users-cog"></i></span>
                <span class="pcoded-mtext">{{ trans_choice('module_human_resource', 2) }}</span>
            </a>
            <ul class="pcoded-submenu">
                @canany(['user-create', 'user-view', 'user-password-print', 'user-password-change',])
                <li class="{{ Request::is('admin/staff/user*') ? 'active' : '' }}"><a href="{{ route('admin.user.index') }}" class="">{{ trans_choice('module_staff', 1) }} {{ __('list') }}</a></li>
                @endcanany

                @canany(['staff-assignment-index','staff-assignment-create','staff-assignment-edit','staff-assignment-delete'])
                <li class="{{ Request::is('admin/staff-assignment*') ? 'active' : '' }}"><a href="{{ route('admin.staff-assignment.index') }}" class="">{{ __('Staff Assign') }}</a></li>
                @endcanany

                @can('staff-id-card-view')
                <li class="{{ Request::is('admin/staff/staff-id-card') ? 'active' : '' }}"><a href="{{ route('admin.staff-id-card.index') }}" class="">{{ trans_choice('module_staff', 1) }} {{ __('field_id_card') }}</a></li>
                @endcan

                @can('staff-id-card-setting-view')
                <li class="{{ Request::is('admin/staff/staff-id-card-setting*') ? 'active' : '' }}"><a href="{{ route('admin.staff-id-card-setting.index') }}" class="">{{ trans_choice('module_staff', 1) }} {{ __('field_id_card') }} {{ __('Setting') }}</a></li>
                @endcan

                @canany(['staff-note-create', 'staff-note-view'])
                <li class="{{ Request::is('admin/staff/staff-note*') ? 'active' : '' }}"><a href="{{ route('admin.staff-note.index') }}" class="">{{ trans_choice('module_staff_note', 2) }}</a></li>
                @endcanany

                @canany(['payroll-view', 'payroll-action', 'payroll-print'])
                <li class="{{ Request::is('admin/staff/payroll') ? 'active' : '' }}"><a href="{{ route('admin.payroll.index') }}" class="">{{ trans_choice('module_payroll', 2) }}</a></li>
                @endcanany

                @canany(['payroll-report'])
                <li class="{{ Request::is('admin/staff/payroll-report*') ? 'active' : '' }}"><a href="{{ route('admin.payroll.report') }}" class="">{{ trans_choice('module_payroll_report', 2) }}</a></li>
                @endcanany

                @canany(['work-shift-type-create', 'work-shift-type-view'])
                <li class="{{ Request::is('admin/staff/work-shift-type*') ? 'active' : '' }}"><a href="{{ route('admin.work-shift-type.index') }}" class="">{{ trans_choice('module_work_shift_type', 2) }}</a></li>
                @endcanany

                @canany(['designation-create', 'designation-view'])
                <li class="{{ Request::is('admin/staff/designation*') ? 'active' : '' }}"><a href="{{ route('admin.designation.index') }}" class="">{{ trans_choice('module_designation', 2) }}</a></li>
                @endcanany
                
                @canany(['department-create', 'department-view'])
                <li class="{{ Request::is('admin/staff/department*') ? 'active' : '' }}"><a href="{{ route('admin.department.index') }}" class="">{{ trans_choice('module_department', 2) }}</a></li>
                @endcanany

                @canany(['allowance-type-create', 'allowance-type-view'])
                <li class="{{ Request::is('admin/staff/allowance-type*') ? 'active' : '' }}"><a href="{{ route('admin.allowance-type.index') }}" class="">{{ trans_choice('module_allowance_type', 2) }}</a></li>
                @endcanany

                @canany(['deduction-type-create', 'deduction-type-view'])
                <li class="{{ Request::is('admin/staff/deduction-type*') ? 'active' : '' }}"><a href="{{ route('admin.deduction-type.index') }}" class="">{{ trans_choice('module_deduction_type', 2) }}</a></li>
                @endcanany

                @canany(['tax-setting-create', 'tax-setting-view', 'pay-slip-setting-view', 'tax-remittance-view'])
                <li class="nav-item pcoded-hasmenu {{ Request::is('admin/staff/tax-setting*') ? 'pcoded-trigger active' : '' }} {{ Request::is('admin/staff/tax-group*') ? 'pcoded-trigger active' : '' }} {{ Request::is('admin/staff/pay-slip-setting*') ? 'pcoded-trigger active' : '' }} {{ Request::is('admin/staff/tax-report*') ? 'pcoded-trigger active' : '' }} {{ Request::is('admin/staff/tax-remittance*') ? 'pcoded-trigger active' : '' }}">
                    <a href="#!" class="nav-link">
                        <span class="pcoded-mtext">{{ trans_choice('module_setting', 2) }}</span>
                    </a>

                    <ul class="pcoded-submenu">
                        @canany(['tax-setting-create', 'tax-setting-view'])
                        <li class="{{ Request::is('admin/staff/tax-group*') ? 'active' : '' }}"><a href="{{ route('admin.tax-group.index') }}" class="">{{ __('module_tax_group') }}</a></li>
                        @endcanany

                        @canany(['tax-setting-create', 'tax-setting-view'])
                        <li class="{{ Request::is('admin/staff/tax-setting*') ? 'active' : '' }}"><a href="{{ route('admin.tax-setting.index') }}" class="">{{ trans_choice('module_tax_setting', 2) }}</a></li>
                        @endcanany

                        @canany(['tax-setting-view', 'payroll-report'])
                        <li class="{{ Request::is('admin/staff/tax-report*') ? 'active' : '' }}"><a href="{{ route('admin.staff-tax-report.index') }}" class=""><i class="fas fa-chart-bar mr-1"></i> Tax Distribution Report</a></li>
                        @endcanany

                        @can('tax-remittance-view')
                        <li class="{{ Request::is('admin/staff/tax-remittance*') ? 'active' : '' }}"><a href="{{ route('admin.tax-remittance.index') }}" class=""><i class="fas fa-hand-holding-usd mr-1"></i> {{ __('Tax Remittance') }}</a></li>
                        @endcan

                        @can('pay-slip-setting-view')
                        <li class="{{ Request::is('admin/staff/pay-slip-setting*') ? 'active' : '' }}"><a href="{{ route('admin.pay-slip-setting.index') }}" class="">{{ trans_choice('module_pay_slip_setting', 1) }}</a></li>
                        @endcan
                    </ul>
                </li>
                @endcanany
            </ul>
        </li>
        @endcanany

        @canany(['staff-daily-attendance-action', 'staff-daily-attendance-report', 'staff-hourly-attendance-action', 'staff-hourly-attendance-report'])
        <li class="nav-item pcoded-hasmenu {{ Request::is('admin/attendance*') ? 'pcoded-trigger active' : '' }}">
            <a href="#!" class="nav-link">
                <span class="pcoded-micon"><i class="fas fa-calendar-check"></i></span>
                <span class="pcoded-mtext">{{ trans_choice('module_staff_attendance', 2) }}</span>
            </a>
            <ul class="pcoded-submenu">
                @can('staff-daily-attendance-action')
                <li class="{{ Request::is('admin/attendance/staff-daily-attendance*') ? 'active' : '' }}"><a href="{{ route('admin.staff-daily-attendance.index') }}" class="">{{ trans_choice('module_staff_daily_attendance', 2) }}</a></li>
                @endcan

                @can('staff-daily-attendance-report')
                <li class="{{ Request::is('admin/attendance/staff-daily-report*') ? 'active' : '' }}"><a href="{{ route('admin.staff-daily-attendance.report') }}" class="">{{ trans_choice('module_staff_daily_report', 2) }}</a></li>
                @endcan

                @can('staff-hourly-attendance-action')
                <li class="{{ Request::is('admin/attendance/staff-hourly-attendance*') ? 'active' : '' }}"><a href="{{ route('admin.staff-hourly-attendance.index') }}" class="">{{ trans_choice('module_staff_hourly_attendance', 2) }}</a></li>
                @endcan

                @can('staff-hourly-attendance-report')
                <li class="{{ Request::is('admin/attendance/staff-hourly-report*') ? 'active' : '' }}"><a href="{{ route('admin.staff-hourly-attendance.report') }}" class="">{{ trans_choice('module_staff_hourly_report', 2) }}</a></li>
                @endcan
            </ul>
        </li>
        @endcanany

        @canany(['staff-leave-create', 'staff-leave-view', 'leave-type-create', 'leave-type-view', 'staff-leave-manage-edit', 'staff-leave-manage-view'])
        <li class="nav-item pcoded-hasmenu {{ Request::is('admin/leave*') ? 'pcoded-trigger active' : '' }}">
            <a href="#!" class="nav-link">
                <span class="pcoded-micon"><i class="fas fa-notes-medical"></i></span>
                <span class="pcoded-mtext">{{ trans_choice('module_leave_manager', 2) }}</span>
            </a>
            <ul class="pcoded-submenu">
                @canany(['staff-leave-create'])
                <li class="{{ Request::is('admin/leave/staff-leave/create') ? 'active' : '' }}"><a href="{{ route('admin.staff-leave.create') }}" class="">{{ trans_choice('module_apply_leave', 1) }}</a></li>
                @endcanany

                @canany(['staff-leave-view'])
                <li class="{{ Request::is('admin/leave/staff-leave') ? 'active' : '' }}"><a href="{{ route('admin.staff-leave.index') }}" class="">{{ trans_choice('module_my_leave', 2) }}</a></li>
                @endcanany

                @canany(['leave-type-create', 'leave-type-view'])
                <li class="{{ Request::is('admin/leave/leave-type*') ? 'active' : '' }}"><a href="{{ route('admin.leave-type.index') }}" class="">{{ trans_choice('module_leave_type', 2) }}</a></li>
                @endcanany

                @canany(['staff-leave-manage-edit', 'staff-leave-manage-view'])
                <li class="{{ Request::is('admin/leave/leave-manage*') ? 'active' : '' }}"><a href="{{ route('admin.leave-manage.index') }}" class="">{{ trans_choice('module_leave_manage', 1) }}</a></li>
                @endcanany
            </ul>
        </li>
        @endcanany

        @canany(['income-create', 'income-view', 'income-category-create', 'income-category-view', 'expense-create', 'expense-view', 'expense-category-create', 'expense-category-view', 'outcome-view'])
        <li class="nav-item pcoded-hasmenu {{ Request::is('admin/account*') && !Request::is('admin/chart-of-accounts*') && !Request::is('admin/accounting*') ? 'pcoded-trigger active' : '' }}">
            <a href="#!" class="nav-link">
                <span class="pcoded-micon"><i class="fas fa-credit-card"></i></span>
                <span class="pcoded-mtext">{{ trans_choice('module_income_expense', 2) }}</span>
            </a>
            <ul class="pcoded-submenu">
                @canany(['income-create', 'income-view'])
                <li class="{{ Request::is('admin/account/income*') ? 'active' : '' }}"><a href="{{ route('admin.income.index') }}" class="">{{ trans_choice('module_income', 1) }} {{ __('list') }}</a></li>
                @endcanany
                
                @canany(['income-category-create', 'income-category-view'])
                <li class="{{ Request::is('admin/account/income-category*') ? 'active' : '' }}"><a href="{{ route('admin.income-category.index') }}" class="">{{ trans_choice('module_income_category', 2) }}</a></li>
                @endcanany
                
                @canany(['expense-create', 'expense-view'])
                <li class="{{ Request::is('admin/account/expense*') ? 'active' : '' }}"><a href="{{ route('admin.expense.index') }}" class="">{{ trans_choice('module_expense', 1) }} {{ __('list') }}</a></li>
                @endcanany
                
                @canany(['expense-category-create', 'expense-category-view'])
                <li class="{{ Request::is('admin/account/expense-category*') ? 'active' : '' }}"><a href="{{ route('admin.expense-category.index') }}" class="">{{ trans_choice('module_expense_category', 2) }}</a></li>
                @endcanany
                
                @can('outcome-view')
                <li class="{{ Request::is('admin/account/outcome*') ? 'active' : '' }}"><a href="{{ route('admin.outcome.index') }}" class="">{{ trans_choice('module_outcome_calculation', 2) }}</a></li>
                @endcan
            </ul>
        </li>
        @endcanany

        {{-- Budgets Module --}}
        @can('budget-view')
        <li class="nav-item pcoded-hasmenu {{ Request::is('admin/budget*') ? 'pcoded-trigger active' : '' }}">
            <a href="#!" class="nav-link">
                <span class="pcoded-micon"><i class="fas fa-chart-pie"></i></span>
                <span class="pcoded-mtext">{{ trans_choice('module_budget', 2) }}</span>
            </a>
            <ul class="pcoded-submenu">
                <li class="{{ Request::is('admin/budget-dashboard*') ? 'active' : '' }}">
                    <a href="{{ route('admin.budget.dashboard') }}" class="">
                        <i class="fas fa-tachometer-alt"></i> {{ __('budget_dashboard') }}
                    </a>
                </li>
                
                <li class="{{ Request::is('admin/budget-sheet*') ? 'active' : '' }}">
                    <a href="{{ route('admin.budget-sheet.index') }}" class="">
                        <i class="fas fa-file-invoice-dollar"></i> {{ __('Income & Expenditure Sheet') }}
                    </a>
                </li>

                @can('daybook-view')
                {{-- Sits directly beneath the sheet: it is the same money, one
                     itemised and one summed. --}}
                <li class="{{ Request::is('admin/daybook*') ? 'active' : '' }}">
                    <a href="{{ route('admin.daybook.index') }}" class="">
                        <i class="fas fa-book-open"></i> {{ __('Daybook') }}
                    </a>
                </li>
                @endcan

                <li class="{{ Request::is('admin/budget') && !Request::is('admin/budget-*') ? 'active' : '' }}">
                    <a href="{{ route('admin.budget.index') }}" class="">
                        {{-- Named explicitly: sitting beside the institutional
                             sheet, a bare "Budgets" gave no way to tell the two
                             apart. --}}
                        <i class="fas fa-list"></i> {{ __('Departmental Budgets') }}
                    </a>
                </li>

                @can('budget-line-view')
                <li class="{{ Request::is('admin/budget-line*') ? 'active' : '' }}">
                    <a href="{{ route('admin.budget-line.index') }}" class="">
                        <i class="fas fa-stream"></i> {{ __('Budget Lines') }}
                    </a>
                </li>
                @endcan
                
                @canany(['budget-report-performance', 'budget-report-variance', 'budget-report-department', 'budget-report-cashflow'])
                <li class="{{ Request::is('admin/budget-reports*') ? 'active' : '' }} pcoded-hasmenu">
                    <a href="#!" class="">
                        <i class="fas fa-file-alt"></i> {{ __('budget_reports') }}
                    </a>
                    <ul class="pcoded-submenu">
                        @can('budget-report-performance')
                        <li><a href="{{ route('admin.budget.reports.performance') }}">{{ __('performance_report') }}</a></li>
                        @endcan
                        @can('budget-report-variance')
                        <li><a href="{{ route('admin.budget.reports.variance') }}">{{ __('variance_report') }}</a></li>
                        @endcan
                        @can('budget-report-department')
                        <li><a href="{{ route('admin.budget.reports.department') }}">{{ __('department_report') }}</a></li>
                        @endcan
                        @can('budget-report-cashflow')
                        <li><a href="{{ route('admin.budget.reports.cashflow') }}">{{ __('cashflow_projection') }}</a></li>
                        @endcan
                    </ul>
                </li>
                @endcanany
            </ul>
        </li>
        @endcan

        {{-- Payment Accounts Module --}}
        @can('payment-account-view')
        <li class="nav-item pcoded-hasmenu {{ Request::is('admin/payment-account*') ? 'pcoded-trigger active' : '' }}">
            <a href="#!" class="nav-link">
                <span class="pcoded-micon"><i class="fas fa-university"></i></span>
                <span class="pcoded-mtext">{{ __('payment_accounts') }}</span>
            </a>
            <ul class="pcoded-submenu">
                <li class="{{ Request::is('admin/payment-account') && !Request::is('admin/payment-account-*') ? 'active' : '' }}">
                    <a href="{{ route('admin.payment-account.index') }}" class="">
                        <i class="fas fa-list"></i> {{ __('account_list') }}
                    </a>
                </li>
                
                @can('payment-account-transfer-view')
                <li class="{{ Request::is('admin/payment-account-transfer*') ? 'active' : '' }}">
                    <a href="{{ route('admin.payment-account-transfer.index') }}" class="">
                        <i class="fas fa-exchange-alt"></i> {{ __('fund_transfers') }}
                    </a>
                </li>
                @endcan
                
                @canany(['payment-account-report-view', 'payment-account-report-cashflow', 'payment-account-report-statement', 'payment-account-report-summary'])
                <li class="{{ Request::is('admin/payment-account-report*') ? 'active' : '' }} pcoded-hasmenu">
                    <a href="#!" class="">
                        <i class="fas fa-chart-line"></i> {{ __('reports') }}
                    </a>
                    <ul class="pcoded-submenu">
                        @can('payment-account-report-cashflow')
                        <li><a href="{{ route('admin.payment-account-report.cashflow') }}">{{ __('cashflow_report') }}</a></li>
                        @endcan
                        @can('payment-account-report-statement')
                        <li><a href="{{ route('admin.payment-account-report.unlinked') }}">{{ __('payment_account_report') }}</a></li>
                        @endcan
                    </ul>
                </li>
                @endcanany
            </ul>
        </li>
        @endcan

        {{-- OHADA Accounting Module --}}
        @canany(['chart-of-accounts-view', 'accounting-period-view', 'journal-entry-view', 'transaction-mapping-view', 'general-ledger-view', 'fixed-asset-list', 'fixed-asset-category-list', 'bank-reconciliation-list', 'recurring-entry-list', 'year-end-closing-list', 'accounting-report-view'])
        <li class="nav-item pcoded-hasmenu {{ Request::is('admin/chart-of-accounts*') || Request::is('admin/fiscal-years*') || Request::is('admin/journal-entries*') || Request::is('admin/general-ledger*') || Request::is('admin/accounting/mappings*') || Request::is('admin/fixed-assets*') || Request::is('admin/fixed-asset-categories*') || Request::is('admin/bank-reconciliation*') || Request::is('admin/recurring-entries*') || Request::is('admin/year-end-closing*') || Request::is('admin/accounting-reports*') ? 'pcoded-trigger active' : '' }}">
            <a href="#!" class="nav-link">
                <span class="pcoded-micon"><i class="fas fa-calculator"></i></span>
                <span class="pcoded-mtext">{{ __('accounting') }} (OHADA)</span>
            </a>
            <ul class="pcoded-submenu">
                {{-- Chart of Accounts --}}
                @can('chart-of-accounts-view')
                <li class="{{ Request::is('admin/chart-of-accounts*') ? 'active' : '' }}">
                    <a href="{{ route('admin.chart-of-accounts.index') }}" class="">
                        <i class="fas fa-list-alt"></i> {{ __('chart_of_accounts') }}
                    </a>
                </li>
                @endcan
                
                {{-- Fiscal Years --}}
                @can('accounting-period-view')
                <li class="{{ Request::is('admin/fiscal-years*') ? 'active' : '' }}">
                    <a href="{{ route('admin.fiscal-years.index') }}" class="">
                        <i class="fas fa-calendar-alt"></i> {{ __('fiscal_years') }}
                    </a>
                </li>
                @endcan
                
                {{-- Journal Entries --}}
                @can('journal-entry-view')
                <li class="{{ Request::is('admin/journal-entries*') ? 'active' : '' }}">
                    <a href="{{ route('admin.journal-entries.index') }}" class="">
                        <i class="fas fa-book"></i> {{ __('journal_entries') }}
                    </a>
                </li>
                @endcan
                
                {{-- General Ledger & Reports --}}
                @canany(['general-ledger-view', 'trial-balance-view'])
                <li class="{{ Request::is('admin/general-ledger*') ? 'active' : '' }} pcoded-hasmenu">
                    <a href="#!" class="">
                        <i class="fas fa-file-invoice"></i> {{ __('reports') }}
                    </a>
                    <ul class="pcoded-submenu">
                        @can('general-ledger-view')
                        <li><a href="{{ route('admin.general-ledger.index') }}">{{ __('general_ledger') }}</a></li>
                        @endcan
                        @can('trial-balance-view')
                        <li><a href="{{ route('admin.general-ledger.trial-balance') }}">{{ __('trial_balance') }}</a></li>
                        @endcan
                    </ul>
                </li>
                @endcanany
                
                {{-- Transaction Mappings --}}
                @canany(['transaction-mapping-view', 'transaction-mapping-settings'])
                <li class="{{ Request::is('admin/accounting/mappings*') ? 'active' : '' }} pcoded-hasmenu">
                    <a href="#!" class="">
                        <i class="fas fa-link"></i> {{ __('transaction_mappings') }}
                    </a>
                    <ul class="pcoded-submenu">
                        @can('transaction-mapping-settings')
                        <li><a href="{{ route('admin.accounting.mappings.settings') }}"><i class="fas fa-cog"></i> {{ __('mapping_settings') }}</a></li>
                        @endcan
                        @can('transaction-mapping-view')
                        <li><a href="{{ route('admin.accounting.mappings.transactions') }}"><i class="fas fa-list"></i> {{ __('view_transactions') }}</a></li>
                        @endcan
                    </ul>
                </li>
                @endcanany

                {{-- Fixed Assets --}}
                @canany(['fixed-asset-list', 'fixed-asset-category-list'])
                <li class="{{ Request::is('admin/fixed-assets*') || Request::is('admin/fixed-asset-categories*') ? 'active' : '' }} pcoded-hasmenu">
                    <a href="#!" class="">
                        <i class="fas fa-building"></i> {{ __('Fixed Assets') }}
                    </a>
                    <ul class="pcoded-submenu">
                        @can('fixed-asset-list')
                        <li class="{{ Request::is('admin/fixed-assets') ? 'active' : '' }}"><a href="{{ route('admin.fixed-assets.index') }}"><i class="fas fa-list"></i> {{ __('Asset Register') }}</a></li>
                        @endcan
                        @can('fixed-asset-category-list')
                        <li class="{{ Request::is('admin/fixed-asset-categories*') ? 'active' : '' }}"><a href="{{ route('admin.fixed-asset-categories.index') }}"><i class="fas fa-folder"></i> {{ __('Categories') }}</a></li>
                        @endcan
                        @can('fixed-asset-list')
                        <li class="{{ Request::is('admin/fixed-assets-register*') ? 'active' : '' }}"><a href="{{ route('admin.fixed-assets.register') }}"><i class="fas fa-file-alt"></i> {{ __('Asset Report') }}</a></li>
                        <li class="{{ Request::is('admin/fixed-assets-depreciation*') ? 'active' : '' }}"><a href="{{ route('admin.fixed-assets.depreciation-report') }}"><i class="fas fa-chart-line"></i> {{ __('Depreciation Report') }}</a></li>
                        @endcan
                    </ul>
                </li>
                @endcanany

                {{-- Bank Reconciliation --}}
                @can('bank-reconciliation-list')
                <li class="{{ Request::is('admin/bank-reconciliation*') ? 'active' : '' }}">
                    <a href="{{ route('admin.bank-reconciliation.index') }}" class="">
                        <i class="fas fa-balance-scale"></i> {{ __('Bank Reconciliation') }}
                    </a>
                </li>
                @endcan

                {{-- Recurring Entries --}}
                @can('recurring-entry-list')
                <li class="{{ Request::is('admin/recurring-entries*') ? 'active' : '' }}">
                    <a href="{{ route('admin.recurring-entries.index') }}" class="">
                        <i class="fas fa-redo"></i> {{ __('Recurring Entries') }}
                    </a>
                </li>
                @endcan

                {{-- Year-End Closing --}}
                @can('year-end-closing-list')
                <li class="{{ Request::is('admin/year-end-closing*') ? 'active' : '' }}">
                    <a href="{{ route('admin.year-end-closing.index') }}" class="">
                        <i class="fas fa-calendar-check"></i> {{ __('Year-End Closing') }}
                    </a>
                </li>
                @endcan

                {{-- Accounting Reports --}}
                @canany(['accounting-report-view', 'accounting-report-receivables', 'accounting-report-payables', 'accounting-report-student-fee', 'accounting-report-cashflow', 'accounting-report-budget'])
                <li class="{{ Request::is('admin/accounting-reports*') ? 'active' : '' }} pcoded-hasmenu">
                    <a href="#!" class="">
                        <i class="fas fa-chart-bar"></i> {{ __('Accounting Reports') }}
                    </a>
                    <ul class="pcoded-submenu">
                        @can('accounting-report-view')
                        <li><a href="{{ route('admin.accounting-reports.index') }}"><i class="fas fa-home"></i> {{ __('Reports Dashboard') }}</a></li>
                        @endcan
                        @can('accounting-report-receivables')
                        <li><a href="{{ route('admin.accounting-reports.receivables-aging') }}"><i class="fas fa-file-invoice-dollar"></i> {{ __('Receivables Aging') }}</a></li>
                        @endcan
                        @can('accounting-report-payables')
                        <li><a href="{{ route('admin.accounting-reports.payables-aging') }}"><i class="fas fa-file-invoice"></i> {{ __('Payables Aging') }}</a></li>
                        @endcan
                        @can('accounting-report-student-fee')
                        <li><a href="{{ route('admin.accounting-reports.student-fee-aging') }}"><i class="fas fa-user-graduate"></i> {{ __('Student Fee Aging') }}</a></li>
                        @endcan
                        @can('accounting-report-cashflow')
                        <li><a href="{{ route('admin.accounting-reports.cash-flow-statement') }}"><i class="fas fa-money-bill-wave"></i> {{ __('Cash Flow Statement') }}</a></li>
                        @endcan
                        @can('accounting-report-budget')
                        <li><a href="{{ route('admin.accounting-reports.budget-vs-actual') }}"><i class="fas fa-balance-scale-left"></i> {{ __('Budget vs Actual') }}</a></li>
                        @endcan
                    </ul>
                </li>
                @endcanany
            </ul>
        </li>
        @endcanany

        @canany(['email-notify-create', 'email-notify-view', 'sms-notify-create', 'sms-notify-view', 'event-create', 'event-view', 'event-calendar', 'notice-create', 'notice-view', 'notice-category-create', 'notice-category-view'])
        <li class="nav-item pcoded-hasmenu {{ Request::is('admin/communicate*') ? 'pcoded-trigger active' : '' }}">
            <a href="#!" class="nav-link">
                <span class="pcoded-micon"><i class="fas fa-bullhorn"></i></span>
                <span class="pcoded-mtext">{{ trans_choice('module_communicate', 2) }}</span>
            </a>
            <ul class="pcoded-submenu">
                @canany(['email-notify-create', 'email-notify-view'])
                <li class="{{ Request::is('admin/communicate/email-notify*') ? 'active' : '' }}"><a href="{{ route('admin.email-notify.index') }}" class="">{{ trans_choice('module_email_notify', 2) }}</a></li>
                @endcanany

                @canany(['sms-notify-create', 'sms-notify-view'])
                <li class="{{ Request::is('admin/communicate/sms-notify*') ? 'active' : '' }}"><a href="{{ route('admin.sms-notify.index') }}" class="">{{ trans_choice('module_sms_notify', 2) }}</a></li>
                @endcanany

                @canany(['event-create', 'event-view'])
                <li class="{{ Request::is('admin/communicate/event') ? 'active' : '' }}"><a href="{{ route('admin.event.index') }}" class="">{{ trans_choice('module_event', 2) }} {{ __('list') }}</a></li>
                @endcanany
                
                @can('event-calendar')
                <li class="{{ Request::is('admin/communicate/event-calendar') ? 'active' : '' }}"><a href="{{ route('admin.event.calendar') }}" class="">{{ trans_choice('module_calendar', 2) }}</a></li>
                @endcan
                
                @canany(['notice-create', 'notice-view'])
                <li class="{{ Request::is('admin/communicate/notice*') ? 'active' : '' }}"><a href="{{ route('admin.notice.index') }}" class="">{{ trans_choice('module_notice', 1) }} {{ __('list') }}</a></li>
                @endcanany
                
                @canany('notice-category-create', 'notice-category-view')
                <li class="{{ Request::is('admin/communicate/notice-category*') ? 'active' : '' }}"><a href="{{ route('admin.notice-category.index') }}" class="">{{ trans_choice('module_notice_category', 2) }}</a></li>
                @endcanany
            </ul>
        </li>
        @endcanany

        @canany(['book-issue-action', 'book-issue-view', 'library-member-view', 'library-member-create', 'library-member-card', 'book-create', 'book-view', 'book-print', 'book-request-create', 'book-request-view', 'book-category-create', 'book-category-view', 'library-card-setting-view'])
        <li class="nav-item pcoded-hasmenu {{ Request::is('admin/library*') ? 'pcoded-trigger active' : '' }} {{ Request::is('admin/member/library*') ? 'pcoded-trigger active' : '' }}">
            <a href="#!" class="nav-link">
                <span class="pcoded-micon"><i class="fas fa-book-open"></i></span>
                <span class="pcoded-mtext">{{ trans_choice('module_library', 2) }}</span>
            </a>
            <ul class="pcoded-submenu">
                @canany(['book-issue-action'])
                <li class="{{ Request::is('admin/library/issue-return/create') ? 'active' : '' }}"><a href="{{ route('admin.issue-return.create') }}" class="">{{ trans_choice('module_book_issue', 1) }}</a></li>
                @endcanany

                @canany(['book-issue-action', 'book-issue-view'])
                <li class="{{ Request::is('admin/library/issue-return') ? 'active' : '' }}"><a href="{{ route('admin.issue-return.index') }}" class="">{{ trans_choice('module_book_issue_return', 1) }}</a></li>
                @endcanany

                @canany(['library-member-create', 'library-member-view', 'library-member-card'])
                <li class="nav-item pcoded-hasmenu {{ Request::is('admin/member/library*') ? 'pcoded-trigger active' : '' }}">
                    <a href="#!" class="nav-link">
                        <span class="pcoded-mtext">{{ trans_choice('module_member', 2) }}</span>
                    </a>

                    <ul class="pcoded-submenu">
                        <li class="{{ Request::is('admin/member/library-student*') ? 'active' : '' }}"><a href="{{ route('admin.library-student.index') }}" class="">{{ trans_choice('module_student', 1) }} {{ __('list') }}</a></li>

                        <li class="{{ Request::is('admin/member/library-staff*') ? 'active' : '' }}"><a href="{{ route('admin.library-staff.index') }}" class="">{{ trans_choice('module_staff', 1) }} {{ __('list') }}</a></li>

                        <li hidden class="{{ Request::is('admin/member/library-outsider*') ? 'active' : '' }}"><a href="{{ route('admin.library-outsider.index') }}" class="">{{ trans_choice('module_outsider', 1) }} {{ __('list') }}</a></li>
                    </ul>
                </li>
                @endcanany
                
                @canany(['book-create', 'book-view', 'book-print'])
                <li class="{{ Request::is('admin/library/book-list*') ? 'active' : '' }}"><a href="{{ route('admin.book-list.index') }}" class="">{{ trans_choice('module_book', 1) }} {{ __('list') }}</a></li>
                @endcanany

                @canany(['book-request-create', 'book-request-view'])
                <li class="{{ Request::is('admin/library/book-request*') ? 'active' : '' }}"><a href="{{ route('admin.book-request.index') }}" class="">{{ trans_choice('module_book_request', 2) }}</a></li>
                @endcanany

                @canany(['book-category-create', 'book-category-view'])
                <li class="{{ Request::is('admin/library/book-category*') ? 'active' : '' }}"><a href="{{ route('admin.book-category.index') }}" class="">{{ trans_choice('module_book_category', 2) }}</a></li>
                @endcanany

                @canany(['library-card-setting-view'])
                <li class="nav-item pcoded-hasmenu {{ Request::is('admin/library-card-setting*') ? 'pcoded-trigger active' : '' }}">
                    <a href="#!" class="nav-link">
                        <span class="pcoded-mtext">{{ trans_choice('module_setting', 2) }}</span>
                    </a>

                    <ul class="pcoded-submenu">
                        @can('library-card-setting-view')
                        <li class="{{ Request::is('admin/library-card-setting*') ? 'active' : '' }}"><a href="{{ route('admin.library-card-setting.index') }}" class="">{{ trans_choice('module_library_card_setting', 1) }}</a></li>
                        @endcan
                    </ul>
                </li>
                @endcanany
            </ul>
        </li>
        @endcanany

        @canany(['e-library-view', 'e-library-create', 'e-library-edit', 'e-library-delete'])
        <li class="nav-item pcoded-hasmenu {{ Request::is('admin/e-library*') ? 'pcoded-trigger active' : '' }}">
            <a href="#!" class="nav-link">
                <span class="pcoded-micon"><i class="fas fa-book-reader"></i></span>
                <span class="pcoded-mtext">E-Library</span>
            </a>
            <ul class="pcoded-submenu">
                <li class="{{ Request::is('admin/e-library') && !Request::is('admin/e-library/*') ? 'active' : '' }}">
                    <a href="{{ route('admin.e-library.index') }}">
                        <i class="fas fa-list"></i> All Books
                    </a>
                </li>
                <li class="{{ Request::is('admin/e-library/create') ? 'active' : '' }}">
                    <a href="{{ route('admin.e-library.create') }}">
                        <i class="fas fa-plus"></i> Add Book
                    </a>
                </li>
                <li class="{{ Request::is('admin/e-library/categories*') ? 'active' : '' }}">
                    <a href="{{ route('admin.e-library.categories') }}">
                        <i class="fas fa-tags"></i> Categories
                    </a>
                </li>
                <li class="{{ Request::is('admin/e-library/statistics*') ? 'active' : '' }}">
                    <a href="{{ route('admin.e-library.statistics') }}">
                        <i class="fas fa-chart-bar"></i> Statistics
                    </a>
                </li>
            </ul>
        </li>
        @endcanany

        @canany(['item-issue-action', 'item-issue-view', 'item-stock-create', 'item-stock-view', 'item-create', 'item-view', 'item-store-create', 'item-store-view', 'item-supplier-create', 'item-supplier-view', 'item-category-create', 'item-category-view'])
        <li class="nav-item pcoded-hasmenu {{ Request::is('admin/inventory*') ? 'pcoded-trigger active' : '' }}">
            <a href="#!" class="nav-link">
                <span class="pcoded-micon"><i class="fas fa-dolly-flatbed"></i></span>
                <span class="pcoded-mtext">{{ trans_choice('module_inventory', 2) }}</span>
            </a>
            <ul class="pcoded-submenu">
                @canany(['item-issue-action'])
                <li class="{{ Request::is('admin/inventory/item-issue/create') ? 'active' : '' }}"><a href="{{ route('admin.item-issue.create') }}" class="">{{ trans_choice('module_item_issue', 1) }}</a></li>
                @endcanany

                @canany(['item-issue-action', 'item-issue-view'])
                <li class="{{ Request::is('admin/inventory/item-issue') ? 'active' : '' }}"><a href="{{ route('admin.item-issue.index') }}" class="">{{ trans_choice('module_item_issue_return', 1) }}</a></li>
                @endcanany

                @canany(['item-stock-create', 'item-stock-view'])
                <li class="{{ Request::is('admin/inventory/item-stock*') ? 'active' : '' }}"><a href="{{ route('admin.item-stock.index') }}" class="">{{ trans_choice('module_item_stock', 2) }}</a></li>
                @endcanany

                @canany(['item-create', 'item-view'])
                <li class="{{ Request::is('admin/inventory/item-list*') ? 'active' : '' }}"><a href="{{ route('admin.item-list.index') }}" class="">{{ trans_choice('module_item', 1) }} {{ __('list') }}</a></li>
                @endcanany

                @canany(['item-store-create', 'item-store-view'])
                <li class="{{ Request::is('admin/inventory/item-store*') ? 'active' : '' }}"><a href="{{ route('admin.item-store.index') }}" class="">{{ trans_choice('module_item_store', 2) }}</a></li>
                @endcanany

                @canany(['item-supplier-create', 'item-supplier-view'])
                <li class="{{ Request::is('admin/inventory/item-supplier*') ? 'active' : '' }}"><a href="{{ route('admin.item-supplier.index') }}" class="">{{ trans_choice('module_item_supplier', 2) }}</a></li>
                @endcanany
                
                @canany(['item-category-create', 'item-category-view'])
                <li class="{{ Request::is('admin/inventory/item-category*') ? 'active' : '' }}"><a href="{{ route('admin.item-category.index') }}" class="">{{ trans_choice('module_item_category', 2) }}</a></li>
                @endcanany
            </ul>
        </li>
        @endcanany

        @canany(['hostel-member-create', 'hostel-member-view', 'hostel-room-create', 'hostel-room-view', 'hostel-create', 'hostel-view', 'room-type-create', 'room-type-view'])
        <li class="nav-item pcoded-hasmenu {{ Request::is('admin/hostel*') ? 'pcoded-trigger active' : '' }}">
            <a href="#!" class="nav-link">
                <span class="pcoded-micon"><i class="fas fa-hotel"></i></span>
                <span class="pcoded-mtext">{{ trans_choice('module_hostel', 2) }}</span>
            </a>
            <ul class="pcoded-submenu">
                @canany(['hostel-member-create', 'hostel-member-view'])
                <li class="nav-item pcoded-hasmenu {{ Request::is('admin/hostel-student*') ? 'pcoded-trigger active' : '' }} {{ Request::is('admin/hostel-staff*') ? 'pcoded-trigger active' : '' }}">
                    <a href="#!" class="nav-link">
                        <span class="pcoded-mtext">{{ trans_choice('module_member', 2) }}</span>
                    </a>

                    <ul class="pcoded-submenu">
                        <li class="{{ Request::is('admin/hostel-student*') ? 'active' : '' }}"><a href="{{ route('admin.hostel-student.index') }}" class="">{{ trans_choice('module_student', 1) }} {{ __('list') }}</a></li>

                        <li class="{{ Request::is('admin/hostel-staff*') ? 'active' : '' }}"><a href="{{ route('admin.hostel-staff.index') }}" class="">{{ trans_choice('module_staff', 1) }} {{ __('list') }}</a></li>
                    </ul>
                </li>
                @endcanany

                @canany(['hostel-room-create', 'hostel-room-view'])
                <li class="{{ Request::is('admin/hostel/hostel-room*') ? 'active' : '' }}"><a href="{{ route('admin.hostel-room.index') }}" class="">{{ trans_choice('module_hostel_room', 2) }}</a></li>
                @endcanany

                @canany(['hostel-create', 'hostel-view'])
                <li class="{{ Request::is('admin/hostel/hostel') ? 'active' : '' }}"><a href="{{ route('admin.hostel.index') }}" class="">{{ trans_choice('module_hostel', 1) }} {{ __('list') }}</a></li>
                @endcanany

                @canany(['room-type-create', 'room-type-view'])
                <li class="{{ Request::is('admin/hostel/room-type*') ? 'active' : '' }}"><a href="{{ route('admin.room-type.index') }}" class="">{{ trans_choice('module_room_type', 2) }}</a></li>
                @endcanany
            </ul>
        </li>
        @endcanany

        @canany(['transport-member-create', 'transport-member-view', 'transport-vehicle-create', 'transport-vehicle-view', 'transport-route-create', 'transport-route-view'])
        <li class="nav-item pcoded-hasmenu {{ Request::is('admin/transport*') ? 'pcoded-trigger active' : '' }}">
            <a href="#!" class="nav-link">
                <span class="pcoded-micon"><i class="fas fa-bus-alt"></i></span>
                <span class="pcoded-mtext">{{ trans_choice('module_transport', 2) }}</span>
            </a>
            <ul class="pcoded-submenu">
                @canany(['transport-member-create', 'transport-member-view'])
                <li class="nav-item pcoded-hasmenu {{ Request::is('admin/transport-student*') ? 'pcoded-trigger active' : '' }} {{ Request::is('admin/transport-staff*') ? 'pcoded-trigger active' : '' }}">
                    <a href="#!" class="nav-link">
                        <span class="pcoded-mtext">{{ trans_choice('module_member', 2) }}</span>
                    </a>

                    <ul class="pcoded-submenu">
                        <li class="{{ Request::is('admin/transport-student*') ? 'active' : '' }}"><a href="{{ route('admin.transport-student.index') }}" class="">{{ trans_choice('module_student', 1) }} {{ __('list') }}</a></li>

                        <li class="{{ Request::is('admin/transport-staff*') ? 'active' : '' }}"><a href="{{ route('admin.transport-staff.index') }}" class="">{{ trans_choice('module_staff', 1) }} {{ __('list') }}</a></li>
                    </ul>
                </li>
                @endcanany

                @canany(['transport-vehicle-create', 'transport-vehicle-view'])
                <li class="{{ Request::is('admin/transport-vehicle*') ? 'active' : '' }}"><a href="{{ route('admin.transport-vehicle.index') }}" class="">{{ trans_choice('module_transport_vehicle', 2) }}</a></li>
                @endcanany

                @canany(['transport-route-create', 'transport-route-view'])
                <li class="{{ Request::is('admin/transport-route*') ? 'active' : '' }}"><a href="{{ route('admin.transport-route.index') }}" class="">{{ trans_choice('module_transport_route', 2) }}</a></li>
                @endcanany
            </ul>
        </li>
        @endcanany

        @canany(['visitor-create', 'visitor-view', 'visitor-print', 'visit-purpose-create', 'visit-purpose-view', 'visitor-token-setting-view', 'enquiry-create', 'enquiry-view', 'enquiry-source-create', 'enquiry-source-view', 'enquiry-reference-create', 'enquiry-reference-view', 'phone-log-create', 'phone-log-view', 'complain-create', 'complain-view', 'complain-type-create', 'complain-type-view', 'complain-source-create', 'complain-source-view', 'postal-exchange-create', 'postal-exchange-view', 'postal-type-create', 'postal-type-view', 'meeting-create', 'meeting-view', 'meeting-type-create', 'meeting-type-view'])
        <li class="nav-item pcoded-hasmenu {{ Request::is('admin/frontdesk*') ? 'pcoded-trigger active' : '' }}">
            <a href="#!" class="nav-link">
                <span class="pcoded-micon"><i class="fas fa-desktop"></i></span>
                <span class="pcoded-mtext">{{ trans_choice('module_front_desk', 2) }}</span>
            </a>
            <ul class="pcoded-submenu">
                @canany(['visitor-create', 'visitor-view', 'visitor-print'])
                <li class="{{ Request::is('admin/frontdesk/visit*') ? 'active' : '' }}"><a href="{{ route('admin.visitor.index') }}" class="">{{ trans_choice('module_visitor_log', 2) }}</a></li>
                @endcanany

                @canany(['phone-log-create', 'phone-log-view'])
                <li class="{{ Request::is('admin/frontdesk/phone-log*') ? 'active' : '' }}"><a href="{{ route('admin.phone-log.index') }}" class="">{{ trans_choice('module_phone_log', 2) }}</a></li>
                @endcanany
                
                @canany(['enquiry-create', 'enquiry-view'])
                <li class="{{ Request::is('admin/frontdesk/enquiry*') ? 'active' : '' }}"><a href="{{ route('admin.enquiry.index') }}" class="">{{ trans_choice('module_enquiry', 1) }} {{ __('list') }}</a></li>
                @endcanany

                @canany(['complain-create', 'complain-view'])
                <li class="{{ Request::is('admin/frontdesk/complain*') ? 'active' : '' }}"><a href="{{ route('admin.complain.index') }}" class="">{{ trans_choice('module_complain', 1) }} {{ __('list') }}</a></li>
                @endcanany

                @canany(['postal-exchange-create', 'postal-exchange-view'])
                <li class="{{ Request::is('admin/frontdesk/postal*') ? 'active' : '' }}"><a href="{{ route('admin.postal-exchange.index') }}" class="">{{ trans_choice('module_postal_exchange', 2) }}</a></li>
                @endcanany

                @canany(['meeting-create', 'meeting-view'])
                <li class="{{ Request::is('admin/frontdesk/meeting*') ? 'active' : '' }}"><a href="{{ route('admin.meeting.index') }}" class="">{{ trans_choice('module_meeting', 2) }}</a></li>
                @endcanany

                @canany(['visit-purpose-create', 'visit-purpose-view', 'visitor-token-setting-view', 'enquiry-source-create', 'enquiry-source-view', 'enquiry-reference-create', 'enquiry-reference-view', 'complain-type-create', 'complain-type-view', 'complain-source-create', 'complain-source-view', 'postal-type-create', 'postal-type-view', 'meeting-type-create', 'meeting-type-view'])
                <li class="nav-item pcoded-hasmenu {{ Request::is('admin/frontdesk/visit-purpose*') ? 'pcoded-trigger active' : '' }} {{ Request::is('admin/frontdesk/visitor-token-setting*') ? 'pcoded-trigger active' : '' }} {{ Request::is('admin/frontdesk/enquiry-source*') ? 'pcoded-trigger active' : '' }} {{ Request::is('admin/frontdesk/enquiry-reference*') ? 'pcoded-trigger active' : '' }} {{ Request::is('admin/frontdesk/complain-type*') ? 'pcoded-trigger active' : '' }} {{ Request::is('admin/frontdesk/complain-source*') ? 'pcoded-trigger active' : '' }} {{ Request::is('admin/frontdesk/postal-type*') ? 'pcoded-trigger active' : '' }} {{ Request::is('admin/frontdesk/meeting-type*') ? 'pcoded-trigger active' : '' }}">
                    <a href="#!" class="nav-link">
                        <span class="pcoded-mtext">{{ trans_choice('module_setting', 2) }}</span>
                    </a>

                    <ul class="pcoded-submenu">
                        @canany(['visit-purpose-create', 'visit-purpose-view'])
                        <li class="{{ Request::is('admin/frontdesk/visit-purpose*') ? 'active' : '' }}"><a href="{{ route('admin.visit-purpose.index') }}" class="">{{ trans_choice('module_visit_purpose', 2) }}</a></li>
                        @endcanany

                        @can('visitor-token-setting-view')
                        <li class="{{ Request::is('admin/frontdesk/visitor-token-setting*') ? 'active' : '' }}"><a href="{{ route('admin.visitor-token-setting.index') }}" class="">{{ trans_choice('module_visitor_token_setting', 2) }}</a></li>
                        @endcan

                        @canany(['enquiry-source-create', 'enquiry-source-view'])
                        <li class="{{ Request::is('admin/frontdesk/enquiry-source*') ? 'active' : '' }}"><a href="{{ route('admin.enquiry-source.index') }}" class="">{{ trans_choice('module_enquiry_source', 2) }}</a></li>
                        @endcanany

                        @canany(['enquiry-reference-create', 'enquiry-reference-view'])
                        <li class="{{ Request::is('admin/frontdesk/enquiry-reference*') ? 'active' : '' }}"><a href="{{ route('admin.enquiry-reference.index') }}" class="">{{ trans_choice('module_enquiry_reference', 2) }}</a></li>
                        @endcanany

                        @canany(['complain-type-create', 'complain-type-view'])
                        <li class="{{ Request::is('admin/frontdesk/complain-type*') ? 'active' : '' }}"><a href="{{ route('admin.complain-type.index') }}" class="">{{ trans_choice('module_complain_type', 2) }}</a></li>
                        @endcanany

                        @canany(['complain-source-create', 'complain-source-view'])
                        <li class="{{ Request::is('admin/frontdesk/complain-source*') ? 'active' : '' }}"><a href="{{ route('admin.complain-source.index') }}" class="">{{ trans_choice('module_complain_source', 2) }}</a></li>
                        @endcanany

                        @canany(['postal-type-create', 'postal-type-view'])
                        <li class="{{ Request::is('admin/frontdesk/postal-type*') ? 'active' : '' }}"><a href="{{ route('admin.postal-type.index') }}" class="">{{ trans_choice('module_postal_type', 2) }}</a></li>
                        @endcanany

                        @canany(['meeting-type-create', 'meeting-type-view'])
                        <li class="{{ Request::is('admin/frontdesk/meeting-type*') ? 'active' : '' }}"><a href="{{ route('admin.meeting-type.index') }}" class="">{{ trans_choice('module_meeting_type', 2) }}</a></li>
                        @endcanany
                    </ul>
                </li>
                @endcanany
            </ul>
        </li>
        @endcanany

        @canany(['marksheet-view', 'marksheet-print', 'marksheet-download', 'marksheet-setting-view', 'certificate-view', 'certificate-create', 'certificate-print', 'certificate-download', 'certificate-template-view', 'certificate-template-create'])
        <li class="nav-item pcoded-hasmenu {{ Request::is('admin/transcript*') ? 'pcoded-trigger active' : '' }}">
            <a href="#!" class="nav-link">
                <span class="pcoded-micon"><i class="fas fa-address-card"></i></span>
                <span class="pcoded-mtext">{{ trans_choice('module_transcript', 2) }}</span>
            </a>
            <ul class="pcoded-submenu">
                @canany(['marksheet-view', 'marksheet-print', 'marksheet-download'])
                <li class="{{ Request::is('admin/transcript/marksheet-semester*') ? 'active' : '' }}"><a href="{{ route('admin.marksheet.semester') }}" class="">{{ trans_choice('module_marksheet_semester', 2) }}</a></li>
                @endcanany
                
                @canany(['marksheet-view', 'marksheet-print', 'marksheet-download'])
                <li class="{{ Request::is('admin/transcript/marksheet') ? 'active' : '' }}"><a href="{{ route('admin.marksheet.index') }}" class="">{{ trans_choice('module_marksheet_total', 2) }}</a></li>
                @endcanany

                @canany(['marksheet-setting-view'])
                <li class="{{ Request::is('admin/transcript/marksheet-setting*') ? 'active' : '' }}"><a href="{{ route('admin.marksheet-setting.index') }}" class="">{{ trans_choice('module_marksheet_setting', 1) }}</a></li>
                @endcanany

                @canany(['certificate-view', 'certificate-create', 'certificate-print', 'certificate-download'])
                <li class="{{ Request::is('admin/transcript/certificate*') ? 'active' : '' }}"><a href="{{ route('admin.certificate.index') }}" class="">{{ trans_choice('module_certificate', 2) }}</a></li>
                @endcanany

                @canany(['certificate-template-view', 'certificate-template-create'])
                <li class="{{ Request::is('admin/transcript/certificate-template*') ? 'active' : '' }}"><a href="{{ route('admin.certificate-template.index') }}" class="">{{ trans_choice('module_certificate_template', 2) }}</a></li>
                @endcanany
            </ul>
        </li>
        @endcanany

        @canany(['report-student-progress', 'report-subject-students', 'report-student-attendance', 'report-subject-attendance', 'report-collected-fees', 'report-student-fees', 'report-salary-paid', 'report-staff-leaves', 'report-income', 'report-expense', 'report-library', 'report-book-return', 'report-inventory', 'report-hostel', 'report-transport'])
        <li class="nav-item pcoded-hasmenu {{ Request::is('admin/report*') ? 'pcoded-trigger active' : '' }}">
            <a href="#!" class="nav-link">
                <span class="pcoded-micon"><i class="fas fa-chart-line"></i></span>
                <span class="pcoded-mtext">{{ trans_choice('module_report', 2) }}</span>
            </a>
            <ul class="pcoded-submenu">
                @can('report-student-progress')
                <li class="{{ Request::is('admin/report/student') ? 'active' : '' }}"><a href="{{ route('admin.report.student') }}" class="">{{ trans_choice('module_student_progress', 1) }}</a></li>
                @endcan

                @can('report-subject-students')
                <li class="{{ Request::is('admin/report/subject') ? 'active' : '' }}"><a href="{{ route('admin.report.subject') }}" class="">{{ trans_choice('module_course_students', 1) }}</a></li>
                @endcan

                @can('report-student-attendance')
                <li class="{{ Request::is('admin/report/student-attendance') ? 'active' : '' }}"><a href="{{ route('admin.report.student-attendance') }}" class="">{{ trans_choice('module_student_attendance', 1) }}</a></li>
                @endcan

                @can('report-subject-attendance')
                <li class="{{ Request::is('admin/report/subject-attendance') ? 'active' : '' }}"><a href="{{ route('admin.report.subject-attendance') }}" class="">{{ trans_choice('module_student_subject_attendance', 1) }}</a></li>
                @endcan

                @can('report-collected-fees')
                <li class="{{ Request::is('admin/report/fees') ? 'active' : '' }}"><a href="{{ route('admin.report.fees') }}" class="">{{ trans_choice('module_collected_fees', 1) }}</a></li>
                @endcan

                @can('report-student-fees')
                <li class="{{ Request::is('admin/report/student-fees') ? 'active' : '' }}"><a href="{{ route('admin.report.student-fees') }}" class="">{{ trans_choice('module_student_fees', 1) }}</a></li>
                @endcan

                @can('partial-payment-report-view')
                <li class="{{ Request::is('admin/partial-payment-report') ? 'active' : '' }}"><a href="{{ route('admin.partial-payment-report.index') }}" class="">{{ __('partial_payment_report') }}</a></li>
                @endcan

                @can('report-salary-paid')
                <li class="{{ Request::is('admin/report/payroll') ? 'active' : '' }}"><a href="{{ route('admin.report.payroll') }}" class="">{{ trans_choice('module_salary_paid', 1) }}</a></li>
                @endcan

                @can('report-staff-leaves')
                <li class="{{ Request::is('admin/report/leave') ? 'active' : '' }}"><a href="{{ route('admin.report.leave') }}" class="">{{ trans_choice('module_staff_leaves', 1) }}</a></li>
                @endcan

                @can('report-income')
                <li class="{{ Request::is('admin/report/income') ? 'active' : '' }}"><a href="{{ route('admin.report.income') }}" class="">{{ trans_choice('module_total_income', 1) }}</a></li>
                @endcan

                @can('report-expense')
                <li class="{{ Request::is('admin/report/expense') ? 'active' : '' }}"><a href="{{ route('admin.report.expense') }}" class="">{{ trans_choice('module_total_expense', 1) }}</a></li>
                @endcan

                @can('report-library')
                <li class="{{ Request::is('admin/report/library') ? 'active' : '' }}"><a href="{{ route('admin.report.library') }}" class="">{{ trans_choice('module_library_history', 1) }}</a></li>
                @endcan

                @can('report-book-return')
                <li class="{{ Request::is('admin/report/book-return') ? 'active' : '' }}"><a href="{{ route('admin.report.book-return') }}" class="">{{ trans_choice('module_book_return_due', 1) }}</a></li>
                @endcan

                @can('report-inventory')
                <li class="{{ Request::is('admin/report/inventory') ? 'active' : '' }}"><a href="{{ route('admin.report.inventory') }}" class="">{{ trans_choice('module_inventory_history', 1) }}</a></li>
                @endcan

                @can('report-hostel')
                <li class="{{ Request::is('admin/report/hostel') ? 'active' : '' }}"><a href="{{ route('admin.report.hostel') }}" class="">{{ trans_choice('module_hostel_members', 1) }}</a></li>
                @endcan

                @can('report-transport')
                <li class="{{ Request::is('admin/report/transport') ? 'active' : '' }}"><a href="{{ route('admin.report.transport') }}" class="">{{ trans_choice('module_transport_members', 1) }}</a></li>
                @endcan
            </ul>
        </li>
        @endcanany

        @canany(['topbar-setting-view', 'social-setting-view', 'slider-view', 'slider-create', 'about-us-view', 'welcome-message-view', 'welcome-message-create', 'feature-view', 'feature-create', 'course-view', 'course-create', 'web-event-view', 'web-event-create', 'news-view', 'news-create', 'gallery-view', 'gallery-create', 'faq-view', 'faq-create', 'testimonial-view', 'testimonial-create', 'page-view', 'page-create', 'call-to-action-view', 'project-view', 'project-create', 'leadership-team-view', 'leadership-team-create', 'accreditation-view', 'accreditation-create', 'history-timeline-view', 'history-timeline-create', 'support-service-view', 'support-service-create', 'admission-date-view', 'admission-date-create', 'admissions-page-view', 'admissions-page-edit', 'campus-life-view', 'campus-life-create'])
        <li class="nav-item pcoded-hasmenu {{ Request::is('admin/web*') ? 'pcoded-trigger active' : '' }}">
            <a href="#!" class="nav-link">
                <span class="pcoded-micon"><i class="fas fa-globe"></i></span>
                <span class="pcoded-mtext">{{ trans_choice('module_front_web', 2) }}</span>
            </a>
            <ul class="pcoded-submenu">
                @can('topbar-setting-view')
                <li class="{{ Request::is('admin/web/topbar-setting*') ? 'active' : '' }}"><a href="{{ route('admin.topbar-setting.index') }}" class="">{{ trans_choice('module_topbar_setting', 1) }}</a></li>
                @endcan

                @can('social-setting-view')
                <li class="{{ Request::is('admin/web/social-setting*') ? 'active' : '' }}"><a href="{{ route('admin.social-setting.index') }}" class="">{{ trans_choice('module_social_setting', 1) }}</a></li>
                @endcan

                @canany(['slider-view', 'slider-create'])
                <li class="{{ Request::is('admin/web/slider*') ? 'active' : '' }}"><a href="{{ route('admin.slider.index') }}" class="">{{ trans_choice('module_slider', 2) }}</a></li>
                @endcanany

                @can('about-us-view')
                <li class="{{ Request::is('admin/web/about-us*') ? 'active' : '' }}"><a href="{{ route('admin.about-us.index') }}" class="">{{ trans_choice('module_about_us', 1) }}</a></li>
                @endcan

                @canany(['welcome-message-view', 'welcome-message-create'])
                <li class="{{ Request::is('admin/web/welcome-message*') ? 'active' : '' }}"><a href="{{ route('admin.welcome-message.index') }}" class="">{{ trans_choice('module_welcome_message', 1) }}</a></li>
                @endcanany

                @canany(['feature-view', 'feature-create'])
                <li class="{{ Request::is('admin/web/feature*') ? 'active' : '' }}"><a href="{{ route('admin.feature.index') }}" class="">{{ trans_choice('module_feature', 2) }}</a></li>
                @endcanany

                @canany(['course-view', 'course-create'])
                <li class="{{ Request::is('admin/web/course*') ? 'active' : '' }}"><a href="{{ route('admin.course.index') }}" class="">{{ trans_choice('module_course', 2) }}</a></li>
                @endcanany

                @canany(['web-event-view', 'web-event-create'])
                <li class="{{ Request::is('admin/web/web-event*') ? 'active' : '' }}"><a href="{{ route('admin.web-event.index') }}" class="">{{ trans_choice('module_event', 2) }}</a></li>
                @endcanany

                @canany(['news-view', 'news-create'])
                <li class="{{ Request::is('admin/web/news*') ? 'active' : '' }}"><a href="{{ route('admin.news.index') }}" class="">{{ trans_choice('module_news', 2) }}</a></li>
                @endcanany

                @canany(['announcement-view', 'announcement-create'])
                <li class="{{ Request::is('admin/web/announcement*') ? 'active' : '' }}"><a href="{{ route('admin.announcement.index') }}" class="">Announcements</a></li>
                @endcanany

                @canany(['faq-view', 'faq-create'])
                <li class="{{ Request::is('admin/web/faq*') ? 'active' : '' }}"><a href="{{ route('admin.faq.index') }}" class="">{{ trans_choice('module_faq', 2) }}</a></li>
                @endcanany

                @canany(['gallery-view', 'gallery-create'])
                <li class="{{ Request::is('admin/web/gallery*') ? 'active' : '' }}"><a href="{{ route('admin.gallery.index') }}" class="">{{ trans_choice('module_gallery', 2) }}</a></li>
                @endcanany

                @canany(['testimonial-view', 'testimonial-create'])
                <li class="{{ Request::is('admin/web/testimonial*') ? 'active' : '' }}"><a href="{{ route('admin.testimonial.index') }}" class="">{{ trans_choice('module_testimonial', 2) }}</a></li>
                @endcanany

                @canany(['page-view', 'page-create'])
                <li class="{{ Request::is('admin/web/page*') ? 'active' : '' }}"><a href="{{ route('admin.page.index') }}" class="">{{ trans_choice('module_footer_page', 2) }}</a></li>
                @endcanany

                @can('call-to-action-view')
                <li class="{{ Request::is('admin/web/call-to-action*') ? 'active' : '' }}"><a href="{{ route('admin.call-to-action.index') }}" class="">{{ trans_choice('module_call_to_action', 1) }}</a></li>
                @endcan

                @canany(['project-view', 'project-create'])
                <li class="{{ Request::is('admin/web/project*') ? 'active' : '' }}"><a href="{{ route('admin.project.index') }}" class="">{{ trans_choice('module_project', 2) }}</a></li>
                @endcanany

                @canany(['leadership-team-view', 'leadership-team-create'])
                <li class="{{ Request::is('admin/web/leadership-team*') ? 'active' : '' }}"><a href="{{ route('admin.leadership-team.index') }}" class="">{{ trans_choice('module_leadership_team', 1) }}</a></li>
                @endcanany

                @canany(['accreditation-view', 'accreditation-create'])
                <li class="{{ Request::is('admin/web/accreditation*') ? 'active' : '' }}"><a href="{{ route('admin.accreditation.index') }}" class="">{{ trans_choice('module_accreditation', 2) }}</a></li>
                @endcanany

                @canany(['history-timeline-view', 'history-timeline-create'])
                <li class="{{ Request::is('admin/web/history-timeline*') ? 'active' : '' }}"><a href="{{ route('admin.history-timeline.index') }}" class="">{{ trans_choice('module_history_timeline', 1) }}</a></li>
                @endcanany

                @canany(['support-service-view', 'support-service-create'])
                <li class="{{ Request::is('admin/web/support-service*') ? 'active' : '' }}"><a href="{{ route('admin.support-service.index') }}" class="">{{ trans_choice('module_support_service', 2) }}</a></li>
                @endcanany

                @canany(['admission-date-view', 'admission-date-create'])
                <li class="{{ Request::is('admin/web/admission-date*') ? 'active' : '' }}"><a href="{{ route('admin.admission-date.index') }}" class="">{{ trans_choice('module_admission_date', 2) }}</a></li>
                @endcanany

                @canany(['admissions-page-view', 'admissions-page-edit'])
                <li class="{{ Request::is('admin/web/admissions-page*') ? 'active' : '' }}"><a href="{{ route('admin.admissions-page.index') }}" class="">Admissions Page</a></li>
                @endcanany

                @canany(['campus-life-view', 'campus-life-create', 'campus-life-edit', 'campus-life-delete'])
                <li class="{{ Request::is('admin/web/campus-life*') ? 'active' : '' }}"><a href="{{ route('admin.campus-life.index') }}" class="">Campus Life</a></li>
                @endcanany

                @canany(['resource-view', 'resource-create', 'resource-edit', 'resource-delete'])
                <li class="{{ Request::is('admin/web/resource*') ? 'active' : '' }}"><a href="{{ route('admin.resource.index') }}" class=""><i class="fas fa-download me-1"></i> Resources & Downloads</a></li>
                @endcanany
            </ul>
        </li>
        @endcanany

        @canany(['dynamic-popup-view', 'dynamic-popup-create', 'dynamic-popup-edit', 'dynamic-popup-delete'])
        <li class="nav-item pcoded-hasmenu {{ Request::is('admin/marketing*') ? 'pcoded-trigger active' : '' }}">
            <a href="#!" class="nav-link">
                <span class="pcoded-micon"><i class="fas fa-bullhorn"></i></span>
                <span class="pcoded-mtext">{{ __('Marketing') }}</span>
            </a>
            <ul class="pcoded-submenu">
                @canany(['dynamic-popup-view', 'dynamic-popup-create'])
                <li class="{{ Request::is('admin/marketing/dynamic-popup*') ? 'active' : '' }}"><a href="{{ route('admin.marketing.dynamic-popup.index') }}" class="">{{ __('Dynamic Popups') }}</a></li>
                @endcanany
            </ul>
        </li>
        @endcanany

        @canany(['setting-view', 'province-view', 'province-create', 'district-view', 'district-create', 'language-view', 'language-create', 'translations-view', 'translations-create', 'setting-mail', 'setting-sms', 'setting-payment', 'application-setting-view', 'religion-view', 'religion-create', 'schedule-setting-view', 'role-view', 'role-edit', 'field-staff', 'field-student', 'field-application', 'student-panel-view'])
        <li class="nav-item pcoded-hasmenu {{ Request::is('admin/setting*') ? 'pcoded-trigger active' : '' }} {{ Request::is('admin/translations*') ? 'pcoded-trigger active' : '' }} {{ Request::is('admin/chat*') ? 'pcoded-trigger active' : '' }}">
            <a href="#!" class="nav-link">
                <span class="pcoded-micon"><i class="fas fa-cog"></i></span>
                <span class="pcoded-mtext">{{ trans_choice('module_setting', 2) }}</span>
            </a>
            <ul class="pcoded-submenu">
                @can('setting-view')
                <li class="{{ Request::is('admin/setting') ? 'active' : '' }}"><a href="{{ route('admin.setting.index') }}" class="">{{ trans_choice('module_general_setting', 1) }}</a></li>
                @endcan

                @canany(['province-view', 'province-create'])
                <li class="{{ Request::is('admin/setting/province*') ? 'active' : '' }}"><a href="{{ route('admin.province.index') }}" class="">{{ trans_choice('module_province', 2) }}</a></li>
                @endcanany

                @canany(['district-view', 'district-create'])
                <li class="{{ Request::is('admin/setting/district*') ? 'active' : '' }}"><a href="{{ route('admin.district.index') }}" class="">{{ trans_choice('module_district', 2) }}</a></li>
                @endcanany
                
                @canany(['language-view', 'language-create'])
                <li class="{{ Request::is('admin/setting/language*') ? 'active' : '' }}"><a href="{{ route('admin.language.index') }}" class="">{{ trans_choice('module_language', 2) }}</a></li>
                @endcanany
                
                @canany(['translations-view', 'translations-create'])
                <li class="{{ Request::is('admin/translations*') ? 'active' : '' }}"><a href="{{ route('admin.translations.index') }}" class="">{{ trans_choice('module_translate', 2) }}</a></li>
                @endcanany

                @can('setting-mail')
                <li class="{{ Request::is('admin/setting/mail-setting*') ? 'active' : '' }}"><a href="{{ route('admin.mail-setting.index') }}" class="">{{ trans_choice('module_mail_setting', 1) }}</a></li>
                @endcan

                @can('setting-sms')
                <li class="{{ Request::is('admin/setting/sms-setting*') ? 'active' : '' }}"><a href="{{ route('admin.sms-setting.index') }}" class="">{{ trans_choice('module_sms_setting', 2) }}</a></li>
                @endcan

                @can('setting-payment')
                <li class="{{ Request::is('admin/setting/payment-setting*') ? 'active' : '' }}"><a href="{{ route('admin.payment-setting.index') }}" class="">{{ trans_choice('module_payment_setting', 2) }}</a></li>
                @endcan

                @can('payment-gateway-manage')
                <li class="{{ Request::is('admin/mobile-money-config*') ? 'active' : '' }}"><a href="{{ route('admin.mobile-money-config.index') }}" class="">{{ __('Mobile Money (MTN / Orange)') }}</a></li>
                @endcan

                @can('application-setting-view')
                <li class="{{ Request::is('admin/setting/application-setting*') ? 'active' : '' }}"><a href="{{ route('admin.application-setting.index') }}" class="">{{ trans_choice('module_application_setting', 1) }}</a></li>
                @endcan

                @can('chat-setting-view')
                <li class="{{ Request::is('admin/chat/setting*') ? 'active' : '' }}"><a href="{{ route('admin.chat-setting.index') }}" class="">{{ __('Chat Assistant') }}</a></li>
                @endcan

                @can('chat-knowledge-view')
                <li class="{{ Request::is('admin/chat/knowledge*') ? 'active' : '' }}"><a href="{{ route('admin.chat-knowledge.index') }}" class="">{{ __('Chat Knowledge Base') }}</a></li>
                @endcan

                @can('chat-conversation-view')
                <li class="{{ Request::is('admin/chat/conversation*') ? 'active' : '' }}"><a href="{{ route('admin.chat-conversation.index') }}" class="">{{ __('Chat Conversations') }}</a></li>
                @endcan

                @canany(['religion-view', 'religion-create', 'religion-edit', 'religion-delete'])
                <li class="{{ Request::is('admin/setting/religion*') ? 'active' : '' }}"><a href="{{ route('admin.religion.index') }}" class="">{{ trans_choice('module_religion', 2) }}</a></li>
                @endcanany

                {{-- @can('schedule-setting-view')
                <li class="{{ Request::is('admin/setting/schedule-setting*') ? 'active' : '' }}"><a href="{{ route('admin.schedule-setting.index') }}" class="">{{ trans_choice('module_schedule_setting', 1) }}</a></li>
                @endcan --}}

                @canany(['role-view', 'role-edit'])
                <li class="{{ Request::is('admin/setting/role*') ? 'active' : '' }}"><a href="{{ route('admin.role.index') }}" class="">{{ trans_choice('module_role', 2) }}</a></li>
                @endcanany

                @canany(['field-staff', 'field-student', 'field-application'])
                <li class="nav-item pcoded-hasmenu {{ Request::is('admin/setting/field*') ? 'pcoded-trigger active' : '' }}">
                    <a href="#!" class="nav-link">
                        <span class="pcoded-mtext">{{ trans_choice('module_field_setting', 2) }}</span>
                    </a>

                    <ul class="pcoded-submenu">
                        @canany(['field-staff'])
                        <li class="{{ Request::is('admin/setting/field-user*') ? 'active' : '' }}"><a href="{{ route('admin.field.user') }}" class="">{{ trans_choice('module_staff', 2) }}</a></li>
                        @endcan

                        @canany(['field-student'])
                        <li class="{{ Request::is('admin/setting/field-student*') ? 'active' : '' }}"><a href="{{ route('admin.field.student') }}" class="">{{ trans_choice('module_student', 2) }}</a></li>
                        @endcan

                        @canany(['field-application'])
                        <li class="{{ Request::is('admin/setting/field-application*') ? 'active' : '' }}"><a href="{{ route('admin.field.application') }}" class="">{{ trans_choice('module_application', 2) }}</a></li>
                        @endcan
                    </ul>
                </li>
                @endcanany

                @canany(['student-panel-view'])
                <li class="{{ Request::is('admin/setting/student-panel*') ? 'active' : '' }}"><a href="{{ route('admin.student.panel') }}" class="">{{ trans_choice('module_student_panel', 2) }}</a></li>
                @endcanany
            </ul>
        </li>
        @endcanany

        {{-- Security Management Menu --}}
        @canany(['security-dashboard-view', 'security-users-view', 'security-logs-view', 'security-blocked-ips-view', 'security-whitelist-view', 'security-settings-view'])
        <li class="nav-item pcoded-hasmenu {{ Request::is('admin/security*') ? 'pcoded-trigger active' : '' }}">
            <a href="#!" class="nav-link">
                <span class="pcoded-micon"><i class="fas fa-shield-alt"></i></span>
                <span class="pcoded-mtext">{{ __('Security') }}</span>
            </a>
            <ul class="pcoded-submenu">
                @can('security-dashboard-view')
                <li class="{{ Request::is('admin/security/dashboard') ? 'active' : '' }}">
                    <a href="{{ route('admin.security.dashboard') }}" class="">
                        <i class="fas fa-tachometer-alt"></i> {{ __('Dashboard') }}
                    </a>
                </li>
                @endcan
                @can('security-users-view')
                <li class="{{ Request::is('admin/security/users') ? 'active' : '' }}">
                    <a href="{{ route('admin.security.users') }}" class="">
                        <i class="fas fa-users-cog"></i> {{ __('User Management') }}
                    </a>
                </li>
                @endcan
                @can('security-logs-view')
                <li class="{{ Request::is('admin/security/logs') ? 'active' : '' }}">
                    <a href="{{ route('admin.security.logs') }}" class="">
                        <i class="fas fa-list-alt"></i> {{ __('Security Logs') }}
                    </a>
                </li>
                @endcan
                @can('security-blocked-ips-view')
                <li class="{{ Request::is('admin/security/blocked-ips') ? 'active' : '' }}">
                    <a href="{{ route('admin.security.blocked-ips') }}" class="">
                        <i class="fas fa-ban"></i> {{ __('Blocked IPs') }}
                    </a>
                </li>
                @endcan
                @can('security-whitelist-view')
                <li class="{{ Request::is('admin/security/whitelist') ? 'active' : '' }}">
                    <a href="{{ route('admin.security.whitelist') }}" class="">
                        <i class="fas fa-shield-alt"></i> {{ __('IP Whitelist') }}
                    </a>
                </li>
                @endcan
                @can('security-settings-view')
                <li class="{{ Request::is('admin/security/settings') ? 'active' : '' }}">
                    <a href="{{ route('admin.security.settings') }}" class="">
                        <i class="fas fa-cogs"></i> {{ __('Settings') }}
                    </a>
                </li>
                @endcan
            </ul>
        </li>
        @endcanany

        @canany(['audit-log-view', 'audit-log-export'])
        <li class="nav-item {{ Request::is('admin/audit-log*') ? 'active' : '' }}">
            <a href="{{ route('admin.audit-log.index') }}" class="nav-link">
                <span class="pcoded-micon"><i class="fas fa-history"></i></span>
                <span class="pcoded-mtext">{{ trans_choice('module_audit_trail', 2) }}</span>
            </a>
        </li>
        @endcanany

        {{-- Where this institution's monthly figures are sent, and proof that
             the credentials still work. Sits beside the audit trail because it
             is oversight of how this institution is reported on. --}}
        @can('edutrustpay-view')
        <li class="nav-item {{ Request::is('admin/edutrustpay*') ? 'active' : '' }}">
            <a href="{{ route('admin.edutrustpay.index') }}" class="nav-link">
                <span class="pcoded-micon"><i class="fas fa-satellite-dish"></i></span>
                <span class="pcoded-mtext">{{ __('EdutrustPay Reporting') }}</span>
            </a>
        </li>
        @endcan

        @canany(['profile-view', 'profile-edit'])
        <li class="nav-item {{ Request::is('admin/profile*') ? 'active' : '' }}">
            <a href="{{ route('admin.profile.index') }}" class="nav-link">
                <span class="pcoded-micon"><i class="fas fa-user-edit"></i></span>
                <span class="pcoded-mtext">{{ trans_choice('module_profile', 2) }}</span>
            </a>
        </li>
        @endcanany

    </ul>
    {{-- <script defer src="https://chatbot.innovakickstarter.com/vendor/chatbot/js/external-chatbot.js" data-chatbot-uuid="da16e707-2e49-4691-a562-423c7cd25ac3" data-iframe-width="420" data-iframe-height="745" data-language="en" ></script> --}}

    {{-- Platform Fee Pending Badge Script --}}
    @push('scripts')
    <script>
        $(document).ready(function() {
            // Load pending platform fee count
            function loadPlatformFeeBadge() {
                $.ajax({
                    url: "{{ route('admin.platform-fee.pending-count') }}",
                    type: 'GET',
                    success: function(response) {
                        if(response.count > 0) {
                            $('#platformFeeBadge').text(response.count).show();
                        } else {
                            $('#platformFeeBadge').hide();
                        }
                    }
                });
            }
            
            // Load on page load
            loadPlatformFeeBadge();
            
            // Reload every 30 seconds
            setInterval(loadPlatformFeeBadge, 30000);
        });
    </script>
    @endpush
</div>
<!-- End Sidebar -->