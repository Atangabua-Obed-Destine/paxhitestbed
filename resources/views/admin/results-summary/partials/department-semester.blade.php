{{-- One semester's department report: what the page shows for that semester alone. --}}
@if(!empty($show_heading))
            <div class="col-sm-12">
                <h4 class="mt-3 mb-2 semester-block-heading" data-semester-block="{{ $semester->id }}"><i class="fas fa-bookmark"></i> {{ $semester->title }}</h4>
            </div>
@endif
            <!-- Report Header -->
            <div class="col-sm-12" id="printable-report{{ $bk }}">
                <div class="card">
                    <div class="card-body text-center">
                        <h3 class="mb-1">{{ $report_meta['institution_name'] ?? config('app.name') }}</h3>
                        <hr>
                        <div class="row">
                            <div class="col-md-6 text-left">
                                <p class="mb-1"><strong>SCHOOL/FACULTY:</strong> {{ $report_meta['faculty_name'] }}</p>
                                <p class="mb-1"><strong>Degree Programme:</strong> {{ $report_meta['degree_type'] }} Programme</p>
                            </div>
                            <div class="col-md-6 text-right">
                                <p class="mb-1"><strong>DEPARTMENT:</strong> {{ $report_meta['department_name'] }}</p>
                                <p class="mb-1"><strong>SEMESTER:</strong> {{ $report_meta['semester_title'] }}</p>
                                <p class="mb-1"><strong>SCHOOL YEAR:</strong> {{ $report_meta['session_title'] }}</p>
                            </div>
                        </div>
                        <hr>
                        <h5 class="text-primary">FREQUENCY DISTRIBUTION OF STUDENT PERFORMANCES</h5>
                    </div>
                </div>
            </div>

            <!-- Course Results Table -->
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-bordered table-striped table-sm" id="results-table{{ $bk }}">
                                <thead class="thead-dark">
                                    <tr>
                                        <th rowspan="2" class="text-center align-middle">SN</th>
                                        <th rowspan="2" class="text-center align-middle">COURSE CODE</th>
                                        <th rowspan="2" class="align-middle">COURSE TITLE</th>
                                        <th rowspan="2" class="text-center align-middle">CV</th>
                                        <th rowspan="2" class="text-center align-middle">ST</th>
                                        <th rowspan="2" class="align-middle">LECTURER(S)</th>
                                        <th rowspan="2" class="text-center align-middle">% CC</th>
                                        <th colspan="2" class="text-center">NUMBER</th>
                                        <th colspan="2" class="text-center">RESULTS</th>
                                        <th colspan="2" class="text-center">PERCENTAGE</th>
                                        <th colspan="{{ count($grades) }}" class="text-center">GRADE DISTRIBUTION</th>
                                    </tr>
                                    <tr>
                                        <th class="text-center">CR</th>
                                        <th class="text-center">CE</th>
                                        <th class="text-center">PASS</th>
                                        <th class="text-center">FAIL</th>
                                        <th class="text-center">% PASS</th>
                                        <th class="text-center">% FAIL</th>
                                        @foreach($grades as $grade)
                                        <th class="text-center">{{ $grade->title }}</th>
                                        @endforeach
                                    </tr>
                                </thead>
                                <tbody>
                                    @php
                                        $totalCR = 0;
                                        $totalCE = 0;
                                        $totalPass = 0;
                                        $totalFail = 0;
                                        $gradeColumnTotals = [];
                                        foreach($grades as $grade) {
                                            $gradeColumnTotals[$grade->title] = 0;
                                        }
                                    @endphp
                                    @foreach($course_results as $key => $course)
                                    @php
                                        $totalCR += $course['candidates_registered'];
                                        $totalCE += $course['candidates_examined'];
                                        $totalPass += $course['passed'];
                                        $totalFail += $course['failed'];
                                    @endphp
                                    <tr>
                                        <td class="text-center">{{ $key + 1 }}</td>
                                        <td class="text-center"><strong>{{ $course['course_code'] }}</strong></td>
                                        <td>{{ $course['course_title'] }}</td>
                                        <td class="text-center">{{ $course['credit_value'] }}</td>
                                        <td class="text-center">{{ $course['status'] }}</td>
                                        <td>{{ Str::limit($course['lecturers'], 25) }}</td>
                                        <td class="text-center">{{ $course['course_coverage'] }}</td>
                                        <td class="text-center">{{ $course['candidates_registered'] }}</td>
                                        <td class="text-center">{{ $course['candidates_examined'] }}</td>
                                        <td class="text-center text-success"><strong>{{ $course['passed'] }}</strong></td>
                                        <td class="text-center text-danger"><strong>{{ $course['failed'] }}</strong></td>
                                        <td class="text-center">
                                            <span class="badge badge-{{ $course['pass_rate'] >= 70 ? 'success' : ($course['pass_rate'] >= 50 ? 'warning' : 'danger') }}">
                                                {{ number_format($course['pass_rate'], 2) }}
                                            </span>
                                        </td>
                                        <td class="text-center">
                                            <span class="badge badge-{{ $course['fail_rate'] <= 30 ? 'success' : ($course['fail_rate'] <= 50 ? 'warning' : 'danger') }}">
                                                {{ number_format($course['fail_rate'], 2) }}
                                            </span>
                                        </td>
                                        @foreach($grades as $grade)
                                        @php
                                            $gradeCount = $course['grade_distribution'][$grade->title] ?? 0;
                                            $gradeColumnTotals[$grade->title] += $gradeCount;
                                        @endphp
                                        <td class="text-center">{{ $gradeCount }}</td>
                                        @endforeach
                                    </tr>
                                    @endforeach
                                </tbody>
                                <tfoot class="thead-light">
                                    <tr>
                                        <th colspan="7" class="text-right"><strong>TOTAL/AVERAGE</strong></th>
                                        <th class="text-center">{{ $totalCR }}</th>
                                        <th class="text-center">{{ $totalCE }}</th>
                                        <th class="text-center text-success"><strong>{{ $totalPass }}</strong></th>
                                        <th class="text-center text-danger"><strong>{{ $totalFail }}</strong></th>
                                        <th class="text-center">
                                            <span class="badge badge-success">
                                                {{ $totalCE > 0 ? number_format(($totalPass / $totalCE) * 100, 2) : 0 }}
                                            </span>
                                        </th>
                                        <th class="text-center">
                                            <span class="badge badge-danger">
                                                {{ $totalCE > 0 ? number_format(($totalFail / $totalCE) * 100, 2) : 0 }}
                                            </span>
                                        </th>
                                        @foreach($grades as $grade)
                                        <th class="text-center">{{ $gradeColumnTotals[$grade->title] }}</th>
                                        @endforeach
                                    </tr>
                                </tfoot>
                            </table>
                        </div>

                        <!-- Legend -->
                        <div class="mt-3 p-3 bg-light rounded">
                            <small class="text-muted">
                                <strong>CV</strong> = Credit Value; 
                                <strong>ST</strong> = Status (C = Compulsory; E = Elective); 
                                <strong>CR</strong> = Candidates Registered; 
                                <strong>CE</strong> = Candidates Examined; 
                                <strong>% CC</strong> = Course Coverage; 
                                The % Pass is based on CE (Candidates who completed the course by writing the final examination).
                            </small>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Summary Statistics -->
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-3">
                                <div class="border rounded p-3 text-center">
                                    <h6 class="text-muted">Number of Marked Scripts</h6>
                                    <h3 class="text-primary">{{ $totalCE }}</h3>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="border rounded p-3 text-center">
                                    <h6 class="text-muted">Number of Passed Scripts</h6>
                                    <h3 class="text-success">{{ $totalPass }}</h3>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="border rounded p-3 text-center">
                                    <h6 class="text-muted">Number of Failed Scripts</h6>
                                    <h3 class="text-danger">{{ $totalFail }}</h3>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="border rounded p-3 text-center">
                                    <h6 class="text-muted">Success Rate</h6>
                                    <h3 class="text-info">{{ $totalCE > 0 ? number_format(($totalPass / $totalCE), 4) : 0 }}</h3>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Lecturers Section -->
            @if(isset($lecturers) && count($lecturers) > 0)
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-chalkboard-teacher"></i> {{ __('Lecturers') }}</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            @foreach($lecturers as $lecturer)
                            <div class="col-md-3 mb-3">
                                <div class="border rounded p-2 text-center">
                                    <i class="fas fa-user-tie fa-2x text-muted mb-2"></i>
                                    <p class="mb-0"><strong>{{ $lecturer }}</strong></p>
                                    <small class="text-muted">_______________________</small>
                                    <p class="mb-0 small">Signature</p>
                                </div>
                            </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>
            @endif

            <!-- Report Footer -->
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-body text-center">
                        <small class="text-muted">
                            Report generated on {{ $report_meta['generated_at'] }} by {{ $report_meta['generated_by'] }}
                        </small>
                    </div>
                </div>
            </div>
