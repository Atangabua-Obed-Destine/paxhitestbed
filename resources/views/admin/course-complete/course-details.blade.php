<!-- Detailed Course Breakdown Row -->
<tr class="course-details-row" id="details-{{ $student->id }}">
    <td colspan="9" style="padding: 0;">
        <div class="p-4" style="background-color: #f8f9fa;">
            <h6 class="mb-3"><i class="fas fa-graduation-cap"></i> Course Performance Details - {{ $student->first_name }} {{ $student->last_name }}</h6>
            
            @if(!$isEligible)
            <div class="alert alert-danger">
                <strong><i class="fas fa-exclamation-triangle"></i> Ineligibility Reasons:</strong>
                <ul class="mb-0 mt-2">
                    @foreach($eligibility['reasons'] as $reason)
                    <li>{{ $reason }}</li>
                    @endforeach
                </ul>
            </div>
            @endif
            
            <div class="row">
                <!-- Compulsory Courses -->
                <div class="col-md-4">
                    <div class="card mb-0">
                        <div class="card-header {{ $eligibility['compulsory']['all_passed'] ? 'bg-success text-white' : 'bg-danger text-white' }}">
                            <h6 class="mb-0">
                                <i class="fas fa-star"></i> Compulsory Courses
                                <span class="float-end">
                                    {{ $eligibility['compulsory']['passed_subjects'] }}/{{ $eligibility['compulsory']['total_subjects'] }}
                                </span>
                            </h6>
                        </div>
                        <div class="card-body" style="max-height: 300px; overflow-y: auto;">
                            @if(count($courseBreakdown['compulsory']) > 0)
                            <table class="table table-sm course-table mb-0">
                                <thead>
                                    <tr>
                                        <th>Course</th>
                                        <th class="text-center">Credits</th>
                                        <th class="text-center">Mark</th>
                                        <th class="text-center">Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($courseBreakdown['compulsory'] as $course)
                                    <tr>
                                        <td>
                                            <strong>{{ $course['code'] }}</strong><br>
                                            <small class="text-muted">{{ \Illuminate\Support\Str::limit($course['title'], 25) }}</small>
                                        </td>
                                        <td class="text-center">{{ $course['credit_hour'] }}</td>
                                        <td class="text-center">
                                            @if($course['has_marks'])
                                                <strong class="{{ $course['passed'] ? 'text-success' : 'text-danger' }}">
                                                    {{ $course['percentage'] }}%
                                                </strong>
                                            @else
                                                <span class="text-muted">N/A</span>
                                            @endif
                                        </td>
                                        <td class="text-center">
                                            @if($course['has_marks'])
                                                @if($course['passed'])
                                                    <span class="badge bg-success">Pass</span>
                                                @else
                                                    <span class="badge bg-danger">Fail</span>
                                                @endif
                                            @else
                                                <span class="badge bg-secondary">Not Taken</span>
                                            @endif
                                        </td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                            @else
                            <p class="text-muted mb-0">No compulsory courses</p>
                            @endif
                        </div>
                    </div>
                </div>
                
                <!-- University Requirement Courses -->
                <div class="col-md-4">
                    <div class="card mb-0">
                        <div class="card-header {{ $eligibility['university_requirement']['all_passed'] ? 'bg-success text-white' : 'bg-danger text-white' }}">
                            <h6 class="mb-0">
                                <i class="fas fa-university"></i> University Requirements
                                <span class="float-end">
                                    {{ $eligibility['university_requirement']['passed_subjects'] }}/{{ $eligibility['university_requirement']['total_subjects'] }}
                                </span>
                            </h6>
                        </div>
                        <div class="card-body" style="max-height: 300px; overflow-y: auto;">
                            @if(count($courseBreakdown['university_requirement']) > 0)
                            <table class="table table-sm course-table mb-0">
                                <thead>
                                    <tr>
                                        <th>Course</th>
                                        <th class="text-center">Credits</th>
                                        <th class="text-center">Mark</th>
                                        <th class="text-center">Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($courseBreakdown['university_requirement'] as $course)
                                    <tr>
                                        <td>
                                            <strong>{{ $course['code'] }}</strong><br>
                                            <small class="text-muted">{{ \Illuminate\Support\Str::limit($course['title'], 25) }}</small>
                                        </td>
                                        <td class="text-center">{{ $course['credit_hour'] }}</td>
                                        <td class="text-center">
                                            @if($course['has_marks'])
                                                <strong class="{{ $course['passed'] ? 'text-success' : 'text-danger' }}">
                                                    {{ $course['percentage'] }}%
                                                </strong>
                                            @else
                                                <span class="text-muted">N/A</span>
                                            @endif
                                        </td>
                                        <td class="text-center">
                                            @if($course['has_marks'])
                                                @if($course['passed'])
                                                    <span class="badge bg-success">Pass</span>
                                                @else
                                                    <span class="badge bg-danger">Fail</span>
                                                @endif
                                            @else
                                                <span class="badge bg-secondary">Not Taken</span>
                                            @endif
                                        </td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                            @else
                            <p class="text-muted mb-0">No university requirement courses</p>
                            @endif
                        </div>
                    </div>
                </div>
                
                <!-- Optional Courses -->
                <div class="col-md-4">
                    <div class="card mb-0">
                        <div class="card-header bg-info text-white">
                            <h6 class="mb-0">
                                <i class="fas fa-check-circle"></i> Optional Courses
                                <span class="float-end">
                                    {{ $eligibility['optional']['passed_subjects'] }}/{{ $eligibility['optional']['total_subjects'] }}
                                </span>
                            </h6>
                        </div>
                        <div class="card-body" style="max-height: 300px; overflow-y: auto;">
                            @if(count($courseBreakdown['optional']) > 0)
                            <table class="table table-sm course-table mb-0">
                                <thead>
                                    <tr>
                                        <th>Course</th>
                                        <th class="text-center">Credits</th>
                                        <th class="text-center">Mark</th>
                                        <th class="text-center">Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($courseBreakdown['optional'] as $course)
                                    <tr>
                                        <td>
                                            <strong>{{ $course['code'] }}</strong><br>
                                            <small class="text-muted">{{ \Illuminate\Support\Str::limit($course['title'], 25) }}</small>
                                        </td>
                                        <td class="text-center">{{ $course['credit_hour'] }}</td>
                                        <td class="text-center">
                                            @if($course['has_marks'])
                                                <strong class="{{ $course['passed'] ? 'text-success' : 'text-danger' }}">
                                                    {{ $course['percentage'] }}%
                                                </strong>
                                            @else
                                                <span class="text-muted">N/A</span>
                                            @endif
                                        </td>
                                        <td class="text-center">
                                            @if($course['has_marks'])
                                                @if($course['passed'])
                                                    <span class="badge bg-success">Pass</span>
                                                @else
                                                    <span class="badge bg-danger">Fail</span>
                                                @endif
                                            @else
                                                <span class="badge bg-secondary">Not Taken</span>
                                            @endif
                                        </td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                            @else
                            <p class="text-muted mb-0">No optional courses</p>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </td>
</tr>
