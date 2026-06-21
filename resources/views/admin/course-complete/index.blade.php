@extends('admin.layouts.master')
@section('title', $title)

@section('page_css')
<style>
.eligibility-badge {
    font-size: 0.85rem;
    padding: 0.35rem 0.65rem;
    font-weight: 600;
}
.student-row {
    cursor: pointer;
    transition: background-color 0.2s;
}
.student-row:hover {
    background-color: #f8f9fa !important;
}
.student-row.ineligible {
    background-color: #fff3f3;
}
.student-row.eligible {
    background-color: #f0fff4;
}
.course-details-row {
    display: none;
    background-color: #f8f9fa;
}
.course-details-row.show {
    display: table-row;
}
.stats-card {
    border-left: 4px solid;
    background: #fff;
    border-radius: 0.25rem;
    padding: 1rem;
    margin-bottom: 1rem;
}
.stats-card.success {
    border-color: #28a745;
}
.stats-card.danger {
    border-color: #dc3545;
}
.stats-card.warning {
    border-color: #ffc107;
}
.stats-card.info {
    border-color: #17a2b8;
}
.course-table {
    font-size: 0.85rem;
}
.course-table th {
    background-color: #f1f3f5;
    font-weight: 600;
    border-top: none;
}
.expand-icon {
    transition: transform 0.3s;
    display: inline-block;
}
.expand-icon.rotated {
    transform: rotate(90deg);
}
</style>
@endsection

@section('content')

<!-- Start Content-->
<div class="main-body">
    <div class="page-wrapper">
        <!-- [ Main Content ] start -->
        <div class="row">
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header">
                        <h5>{{ $title }}</h5>
                    </div>
                    <div class="card-block">
                        <form class="needs-validation" novalidate method="get" action="{{ route($route.'.index') }}">
                            <div class="row gx-2">
                                @include('common.inc.common_search_filter')

                                <div class="form-group col-md-3">
                                    <button type="submit" class="btn btn-info btn-filter"><i class="fas fa-search"></i> {{ __('btn_search') }}</button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            @isset($students_with_eligibility)
            @if(isset($batch_statistics))
            <!-- Statistics Summary -->
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-chart-bar"></i> Graduation Summary</h5>
                    </div>
                    <div class="card-block">
                        <div class="row">
                            <div class="col-md-3">
                                <div class="stats-card info">
                                    <h6 class="text-muted mb-2">Total Students</h6>
                                    <h3 class="mb-0">{{ $batch_statistics['total_students'] }}</h3>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="stats-card success">
                                    <h6 class="text-muted mb-2">Eligible to Graduate</h6>
                                    <h3 class="mb-0 text-success">{{ $batch_statistics['eligible'] }}</h3>
                                    <small class="text-muted">
                                        {{ $batch_statistics['total_students'] > 0 ? round(($batch_statistics['eligible'] / $batch_statistics['total_students']) * 100, 1) : 0 }}%
                                    </small>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="stats-card danger">
                                    <h6 class="text-muted mb-2">Not Eligible</h6>
                                    <h3 class="mb-0 text-danger">{{ $batch_statistics['ineligible'] }}</h3>
                                    <small class="text-muted">
                                        {{ $batch_statistics['total_students'] > 0 ? round(($batch_statistics['ineligible'] / $batch_statistics['total_students']) * 100, 1) : 0 }}%
                                    </small>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="stats-card warning">
                                    <h6 class="text-muted mb-2">Average Credits</h6>
                                    <h3 class="mb-0">{{ $batch_statistics['average_credits'] }}</h3>
                                </div>
                            </div>
                        </div>
                        
                        @if(!empty($batch_statistics['ineligibility_reasons']))
                        <div class="row mt-3">
                            <div class="col-md-12">
                                <h6 class="text-muted mb-2">Common Ineligibility Reasons:</h6>
                                <ul class="list-unstyled">
                                    @foreach($batch_statistics['ineligibility_reasons'] as $reason => $count)
                                    <li><i class="fas fa-exclamation-circle text-warning"></i> {{ $reason }} ({{ $count }} student{{ $count > 1 ? 's' : '' }})</li>
                                    @endforeach
                                </ul>
                            </div>
                        </div>
                        @endif
                    </div>
                </div>
            </div>
            @endif

            <div class="col-sm-12">
                <div class="card">
                    @if(count($students_with_eligibility) > 0)
                    <form action="{{ route($route.'.store') }}" method="post" id="graduationForm">
                    @csrf
                    <input type="hidden" name="program" value="{{ $selected_program }}">
                    <div class="card-block">
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle"></i>
                            <strong>Instructions:</strong> Click on any row to view detailed course breakdown. Only eligible students can be graduated. 
                            Students must have ≥50% in all Compulsory and University Requirement courses.
                        </div>
                        
                        <!-- [ Data table ] start -->
                        <div class="table-responsive">
                            <table class="table table-hover table-striped">
                                <thead>
                                    <tr>
                                        <th width="30"></th>
                                        <th width="40">
                                            <div class="checkbox checkbox-success d-inline">
                                                <input type="checkbox" id="checkbox-all" class="all_select">
                                                <label for="checkbox-all" class="cr" style="margin-bottom: 0px;"></label>
                                            </div>
                                        </th>
                                        <th>Matricule No</th>
                                        <th>{{ __('field_name') }}</th>
                                        <th>{{ __('field_gender') }}</th>
                                        <th class="text-center">Credits</th>
                                        <th class="text-center">CGPA</th>
                                        <th>{{ __('field_batch') }}</th>
                                        <th class="text-center">Eligibility Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                  @foreach($students_with_eligibility as $index => $data)
                                    @php
                                        $student = $data['student'];
                                        $matchingEnrollment = $data['matching_enrollment'];
                                        $eligibility = $data['eligibility'];
                                        $courseBreakdown = $data['course_breakdown'];
                                        $isEligible = $eligibility['is_eligible'];
                                        $rowClass = $isEligible ? 'eligible' : 'ineligible';
                                        
                                        // Use matricule from the matching enrollment (for multi-program students)
                                        $displayMatricule = $matchingEnrollment ? $matchingEnrollment->matricule : $student->student_id;
                                    @endphp
                                    
                                    <!-- Main Student Row -->
                                    <tr class="student-row {{ $rowClass }}" data-toggle="collapse" data-target="#details-{{ $student->id }}" onclick="toggleRow({{ $student->id }})">
                                        <td>
                                            <i class="fas fa-chevron-right expand-icon" id="icon-{{ $student->id }}"></i>
                                        </td>
                                        <td onclick="event.stopPropagation()">
                                            @if($isEligible)
                                            <div class="checkbox checkbox-primary d-inline">
                                                <input type="checkbox" name="students[]" id="checkbox-{{ $student->id }}" value="{{ $student->id }}" class="student-checkbox" checked>
                                                <label for="checkbox-{{ $student->id }}" class="cr"></label>
                                            </div>
                                            @else
                                            <div class="checkbox checkbox-primary d-inline">
                                                <input type="checkbox" id="checkbox-disabled-{{ $student->id }}" disabled>
                                                <label for="checkbox-disabled-{{ $student->id }}" class="cr" title="Student not eligible"></label>
                                            </div>
                                            @endif
                                        </td>
                                        <td>
                                            <a href="{{ route('admin.student.show', $student->id) }}" target="_blank" onclick="event.stopPropagation()">
                                                #{{ $displayMatricule }}
                                            </a>
                                        </td>
                                        <td>{{ $student->first_name }} {{ $student->last_name }}</td>
                                        <td>
                                            @if($student->gender == 1) {{ __('gender_male') }}
                                            @elseif($student->gender == 2) {{ __('gender_female') }}
                                            @else {{ __('gender_other') }}
                                            @endif
                                        </td>
                                        <td class="text-center">
                                            <strong>{{ $eligibility['completed_credits'] }}</strong> / {{ $eligibility['total_credits'] }}
                                        </td>
                                        <td class="text-center"><strong>{{ $data['cgpa'] }}</strong></td>
                                        <td>{{ $student->batch->title ?? '' }}</td>
                                        <td class="text-center">
                                            @if($isEligible)
                                                <span class="badge bg-success eligibility-badge">
                                                    <i class="fas fa-check-circle"></i> Eligible
                                                </span>
                                            @else
                                                <span class="badge bg-danger eligibility-badge">
                                                    <i class="fas fa-times-circle"></i> Not Eligible
                                                </span>
                                            @endif
                                        </td>
                                    </tr>
                                    </tr>
                                    
                                    <!-- Include course details row -->
                                    @include('admin.course-complete.course-details')
                                    
                                  @endforeach
                                </tbody>
                            </table>
                        </div>
                        <!-- [ Data table ] end -->
                    </div>
                    
                    <div class="card-footer">
                        <button type="button" class="btn btn-success" onclick="showApprovalModal()">
                            <i class="fas fa-user-graduate"></i> {{ __('btn_make_alumni') }}
                        </button>
                    </div>
                    
                    <!-- Include Enhanced Approval modal -->
                    @include($view.'.approval-modal')
                    </form>
                    @else
                    <div class="card-block">
                        <h5>{{ __('no_result_found') }}</h5>
                    </div>
                    @endif
                </div>
            </div>
            @endisset
        </div>
        <!-- [ Main Content ] end -->
    </div>
</div>
<!-- End Content-->

@endsection

@section('page_js')
<script type="text/javascript">
"use strict";

// Toggle row details
function toggleRow(studentId) {
    const detailsRow = document.getElementById('details-' + studentId);
    const icon = document.getElementById('icon-' + studentId);
    
    if (detailsRow.classList.contains('show')) {
        detailsRow.classList.remove('show');
        icon.classList.remove('rotated');
    } else {
        detailsRow.classList.add('show');
        icon.classList.add('rotated');
    }
}

// Select all checkbox functionality
$("#checkbox-all").on('click', function(e){
    e.stopPropagation();
    if($(this).is(":checked")){
        $(".student-checkbox:not(:disabled)").prop('checked', true);
    } else {
        $(".student-checkbox:not(:disabled)").prop('checked', false);
    }
});

// Show approval modal
function showApprovalModal() {
    const checkedStudents = $(".student-checkbox:checked");
    
    if (checkedStudents.length === 0) {
        alert('Please select at least one student to graduate.');
        return;
    }
    
    // Build the approval summary
    let summaryHTML = '<ul class="list-group">';
    checkedStudents.each(function() {
        const row = $(this).closest('tr');
        const studentId = row.find('a').text().trim();
        const studentName = row.find('td:nth-child(4)').text().trim();
        const cgpa = row.find('td:nth-child(7) strong').text();
        
        summaryHTML += `<li class="list-group-item d-flex justify-content-between align-items-center">
            <span><strong>${studentId}</strong> - ${studentName}</span>
            <span class="badge bg-primary rounded-pill">CGPA: ${cgpa}</span>
        </li>`;
    });
    summaryHTML += '</ul>';
    
    $('#graduationSummaryList').html(summaryHTML);
    $('#studentCount').text(checkedStudents.length);
    $('#approvalModal').modal('show');
}

// Confirm graduation
function confirmGraduation() {
    $('#graduationForm').submit();
}
</script>
@endsection