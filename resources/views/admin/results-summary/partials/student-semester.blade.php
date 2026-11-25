{{-- One semester's student-by-course results: what the page shows for that semester alone. --}}
@if(!empty($show_heading))
            <div class="col-sm-12">
                <h4 class="mt-3 mb-2 semester-block-heading" data-semester-block="{{ $semester->id }}"><i class="fas fa-bookmark"></i> {{ $semester->title }}</h4>
            </div>
@endif
            <!-- Results Summary Header - Printable -->
            <div class="col-sm-12 printable-summary" id="printable-summary{{ $bk }}">
                <div class="card">
                    <div class="card-body">
                        <!-- Institution Header -->
                        <div class="text-center mb-3">
                            <h3 class="mb-1 font-weight-bold">{{ $meta['institution_name'] }}</h3>
                            <h5 class="text-primary">{{ $meta['faculty_name'] }}</h5>
                            <h6>{{ $meta['semester_title'] }} Results {{ $meta['session_title'] }}</h6>
                        </div>
                        
                        <hr>
                        
                        <!-- Program Information Grid -->
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <table class="table table-sm table-borderless">
                                    <tr>
                                        <td width="140"><strong>Department:</strong></td>
                                        <td>{{ $meta['department_name'] }}</td>
                                    </tr>
                                    <tr>
                                        <td><strong>Programme:</strong></td>
                                        <td>{{ $meta['program_name'] }} ({{ $meta['degree_type'] }})</td>
                                    </tr>
                                </table>
                            </div>
                            <div class="col-md-6">
                                <table class="table table-sm table-borderless">
                                    <tr>
                                        <td width="140"><strong>Level/Semester:</strong></td>
                                        <td>{{ $meta['level'] }}</td>
                                    </tr>
                                    <tr>
                                        <td><strong>Section:</strong></td>
                                        <td>{{ $meta['section_title'] }}</td>
                                    </tr>
                                </table>
                            </div>
                        </div>

                        <!-- Quick Stats -->
                        <div class="row mb-3 no-print">
                            <div class="col-md-3">
                                <div class="alert alert-info text-center mb-0">
                                    <h4 class="mb-0">{{ $overall_stats['total_students'] }}</h4>
                                    <small>Total Students</small>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="alert alert-primary text-center mb-0">
                                    <h4 class="mb-0">{{ $overall_stats['courses_count'] }}</h4>
                                    <small>Courses</small>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="alert alert-success text-center mb-0">
                                    <h4 class="mb-0">{{ $overall_stats['total_passed'] }}</h4>
                                    <small>Clear Pass (All Courses)</small>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="alert alert-danger text-center mb-0">
                                    <h4 class="mb-0">{{ $overall_stats['total_failed'] }}</h4>
                                    <small>Failed (≥1 Course)</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Student Results Matrix Table -->
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header no-print">
                        <h5><i class="fas fa-table"></i> {{ __('Student Results Matrix') }} ({{ count($student_results) }} Students × {{ count($subjects) }} Courses)</h5>
                        <div class="card-header-right">
                            <div class="btn-group">
                                <button type="button" class="btn btn-outline-secondary btn-sm" onclick="toggleView('compact')">
                                    <i class="fas fa-compress-alt"></i> Compact
                                </button>
                                <button type="button" class="btn btn-outline-secondary btn-sm" onclick="toggleView('full')">
                                    <i class="fas fa-expand-alt"></i> Full
                                </button>
                                <form method="post" action="{{ route($route.'.export-student-results-summary') }}" class="d-inline">
                                    @csrf
                                    <input type="hidden" name="faculty" value="{{ $selected_faculty }}">
                                    <input type="hidden" name="program" value="{{ $selected_program }}">
                                    <input type="hidden" name="session" value="{{ $selected_session }}">
                                    <input type="hidden" name="semester" value="{{ $semester->id }}">
                                    <input type="hidden" name="section" value="{{ $selected_section }}">
                                    <button type="submit" class="btn btn-success btn-sm">
                                        <i class="fas fa-file-excel"></i> {{ __('Export to Excel') }}
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive" style="max-height: 70vh; overflow: auto;">
                            <table class="table table-bordered table-sm mb-0 results-matrix" id="results-matrix{{ $bk }}" style="font-size: 0.85em;">
                                <thead class="thead-dark" style="position: sticky; top: 0; z-index: 10;">
                                    <!-- Course Headers Row -->
                                    <tr>
                                        <th rowspan="2" class="text-center align-middle sticky-col" style="min-width: 40px; left: 0; background: #343a40; z-index: 11;">S/N</th>
                                        <th rowspan="2" class="text-center align-middle sticky-col" style="min-width: 100px; left: 40px; background: #343a40; z-index: 11;">Mat No.</th>
                                        <th rowspan="2" class="align-middle sticky-col" style="min-width: 180px; left: 140px; background: #343a40; z-index: 11;">Name</th>
                                        @foreach($subjects as $subject)
                                        <th colspan="5" class="text-center course-header" style="background: #495057; border-left: 2px solid #212529; padding: 4px;">
                                            <span class="badge badge-primary" style="font-size: 0.9em;">{{ $subject->code }}</span>
                                            <br><small class="text-warning" style="font-size: 0.75em;" title="{{ $subject->title }}">{{ Str::limit($subject->title, 20) }}</small>
                                            <br><small class="text-light">CV: {{ $subject->credit_hour }}</small>
                                        </th>
                                        @endforeach
                                        <th colspan="5" class="text-center" style="background: #17a2b8; border-left: 2px solid #212529;">
                                            <span class="text-white font-weight-bold">SUMMARY</span>
                                        </th>
                                    </tr>
                                    <!-- Sub-headers Row -->
                                    <tr>
                                        @foreach($subjects as $subject)
                                        <th class="text-center sub-header att-col" style="background: #6c757d; border-left: 2px solid #212529; min-width: 35px;" title="Attendance">Att</th>
                                        <th class="text-center sub-header ca-col" style="background: #6c757d; min-width: 35px;" title="Continuous Assessment">CA</th>
                                        <th class="text-center sub-header ex-col" style="background: #6c757d; min-width: 35px;" title="Exam">EX</th>
                                        <th class="text-center sub-header tot-col" style="background: #6c757d; min-width: 40px;" title="Total">TOT</th>
                                        <th class="text-center sub-header grd-col" style="background: #6c757d; min-width: 35px;" title="Grade">Grd</th>
                                        @endforeach
                                        <th class="text-center" style="background: #138496; color: white; border-left: 2px solid #212529; min-width: 40px;" title="Total Credits Registered">TCR</th>
                                        <th class="text-center" style="background: #138496; color: white; min-width: 40px;" title="Total Credits Earned">TCE</th>
                                        <th class="text-center" style="background: #138496; color: white; min-width: 45px;" title="Grade Point Average">GPA</th>
                                        <th class="text-center" style="background: #28a745; color: white; min-width: 35px;" title="Courses Passed">✓</th>
                                        <th class="text-center" style="background: #dc3545; color: white; min-width: 35px;" title="Courses Failed">✗</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($student_results as $student)
                                    @php
                                        $hasFailure = $student['summary']['courses_failed'] > 0;
                                        $rowClass = $hasFailure ? 'table-warning' : '';
                                    @endphp
                                    <tr class="{{ $rowClass }}">
                                        <td class="text-center sticky-col" style="left: 0; background: #fff;">{{ $student['sn'] }}</td>
                                        <td class="text-center sticky-col" style="left: 40px; background: #fff; font-size: 0.8em;">{{ $student['matricule'] }}</td>
                                        <td class="sticky-col" style="left: 140px; background: #fff; white-space: nowrap;">{{ Str::limit($student['name'], 25) }}</td>
                                        @foreach($subjects as $subject)
                                            @php
                                                $course = $student['courses'][$subject->id] ?? null;
                                                $isRegistered = $course && $course['registered'];
                                                $isPassed = $isRegistered && ($course['status'] ?? '') == 'P';
                                                $isFailed = $isRegistered && ($course['status'] ?? '') == 'F';
                                                $isAbsent = $isRegistered && ($course['status'] ?? '') == 'ABS';
                                            @endphp
                                            @if($isRegistered)
                                                <td class="text-center att-col" style="border-left: 2px solid #dee2e6;">{{ $course['attendance_marks'] }}</td>
                                                <td class="text-center ca-col">{{ $course['ca_marks'] }}</td>
                                                <td class="text-center ex-col">{{ $course['exam_marks'] }}</td>
                                                <td class="text-center tot-col font-weight-bold {{ $isFailed ? 'text-danger' : ($isPassed ? 'text-success' : '') }}">{{ $course['total_marks'] }}</td>
                                                <td class="text-center grd-col">
                                                    <span class="badge {{ $isPassed ? 'badge-success' : ($isFailed ? 'badge-danger' : 'badge-secondary') }}">
                                                        {{ $course['grade'] }}
                                                    </span>
                                                </td>
                                            @else
                                                <td class="text-center text-muted" style="border-left: 2px solid #dee2e6;">-</td>
                                                <td class="text-center text-muted">-</td>
                                                <td class="text-center text-muted">-</td>
                                                <td class="text-center text-muted">-</td>
                                                <td class="text-center text-muted">-</td>
                                            @endif
                                        @endforeach
                                        <!-- Summary columns -->
                                        <td class="text-center font-weight-bold" style="border-left: 2px solid #17a2b8; background: #e8f4f8;">{{ $student['summary']['total_credits_registered'] }}</td>
                                        <td class="text-center font-weight-bold" style="background: #e8f4f8;">{{ $student['summary']['total_credits_earned'] }}</td>
                                        <td class="text-center font-weight-bold" style="background: #e8f4f8;">
                                            <span class="badge {{ $student['summary']['gpa'] >= 2.0 ? 'badge-success' : 'badge-warning' }}">
                                                {{ number_format($student['summary']['gpa'], 2) }}
                                            </span>
                                        </td>
                                        <td class="text-center text-success font-weight-bold" style="background: #d4edda;">{{ $student['summary']['courses_passed'] }}</td>
                                        <td class="text-center text-danger font-weight-bold" style="background: #f8d7da;">{{ $student['summary']['courses_failed'] }}</td>
                                    </tr>
                                    @endforeach
                                </tbody>
                                <tfoot class="thead-light">
                                    <tr>
                                        <th colspan="3" class="text-right sticky-col" style="left: 0; background: #e9ecef; z-index: 11;">Course Summary:</th>
                                        @foreach($subjects as $subject)
                                            @php
                                                $stats = $course_stats[$subject->id] ?? null;
                                            @endphp
                                            <th colspan="5" class="text-center" style="font-size: 0.75em; border-left: 2px solid #dee2e6;">
                                                @if($stats)
                                                <div>Reg: {{ $stats['registered'] }} | Exam: {{ $stats['examined'] }}</div>
                                                <div class="text-success">P: {{ $stats['passed'] }} ({{ $stats['pass_rate'] }}%)</div>
                                                <div class="text-danger">F: {{ $stats['failed'] }}</div>
                                                @endif
                                            </th>
                                        @endforeach
                                        <th colspan="5" class="text-center" style="border-left: 2px solid #17a2b8; background: #d1ecf1; font-size: 0.8em;">
                                            <div><strong>{{ $overall_stats['total_students'] }}</strong> Students</div>
                                            <div class="text-success">Clear: {{ $overall_stats['total_passed'] }}</div>
                                            <div class="text-danger">With Fails: {{ $overall_stats['total_failed'] }}</div>
                                            @if(($overall_stats['total_pending'] ?? 0) > 0)
                                            <div class="text-muted">Pending: {{ $overall_stats['total_pending'] }}</div>
                                            @endif
                                        </th>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Course Performance Summary -->
            <div class="col-sm-12 no-print">
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-chart-bar"></i> {{ __('Course Performance Overview') }}</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            @foreach($subjects as $subject)
                            @php
                                $stats = $course_stats[$subject->id] ?? null;
                            @endphp
                            <div class="col-md-4 col-lg-3 mb-3">
                                <div class="card border {{ $stats && $stats['pass_rate'] >= 50 ? 'border-success' : 'border-danger' }}">
                                    <div class="card-body p-2">
                                        <h6 class="card-title mb-1">
                                            <span class="badge badge-dark">{{ $subject->code }}</span>
                                            <small class="text-muted">(CV: {{ $subject->credit_hour }})</small>
                                        </h6>
                                        <p class="card-text small text-truncate mb-2" title="{{ $subject->title }}">{{ $subject->title }}</p>
                                        @if($stats)
                                        <div class="d-flex justify-content-between small">
                                            <span>Examined: {{ $stats['examined'] }}</span>
                                            <span class="text-success">Pass: {{ $stats['passed'] }}</span>
                                            <span class="text-danger">Fail: {{ $stats['failed'] }}</span>
                                        </div>
                                        <div class="progress mt-2" style="height: 8px;">
                                            <div class="progress-bar bg-success" style="width: {{ $stats['pass_rate'] }}%"></div>
                                            <div class="progress-bar bg-danger" style="width: {{ 100 - $stats['pass_rate'] }}%"></div>
                                        </div>
                                        <div class="text-center mt-1">
                                            <span class="badge {{ $stats['pass_rate'] >= 50 ? 'badge-success' : 'badge-danger' }}">
                                                {{ $stats['pass_rate'] }}% Pass
                                            </span>
                                            <span class="badge badge-info">Avg: {{ $stats['average'] }}%</span>
                                        </div>
                                        @else
                                        <div class="text-muted small">No data</div>
                                        @endif
                                    </div>
                                </div>
                            </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>

            <!-- Grade Distribution Legend -->
            <div class="col-sm-12 no-print">
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-info-circle"></i> {{ __('Grade Legend') }}</h5>
                    </div>
                    <div class="card-body">
                        <div class="d-flex flex-wrap">
                            @foreach($grades as $grade)
                            <div class="mr-4 mb-2">
                                <span class="badge {{ $grade->min_mark >= 50 ? 'badge-success' : 'badge-danger' }}" style="font-size: 1em;">
                                    {{ $grade->title }}
                                </span>
                                <span class="small text-muted">{{ $grade->min_mark }}-{{ $grade->max_mark }}% ({{ $grade->point }} pts)</span>
                            </div>
                            @endforeach
                        </div>
                        <hr>
                        <div class="small text-muted">
                            <strong>Legend:</strong>
                            <span class="mr-3"><span class="badge badge-success">P</span> = Passed (≥50%)</span>
                            <span class="mr-3"><span class="badge badge-danger">F</span> = Failed (<50%)</span>
                            <span class="mr-3"><span class="badge badge-secondary">ABS</span> = Absent</span>
                            <span class="mr-3"><span class="badge badge-light">NR</span> = Not Registered</span>
                            <span class="mr-3"><strong>TCR</strong> = Total Credits Registered</span>
                            <span class="mr-3"><strong>TCE</strong> = Total Credits Earned</span>
                            <span class="mr-3"><strong>GPA</strong> = Grade Point Average</span>
                        </div>
                    </div>
                </div>
            </div>

