@extends('admin.layouts.master')
@section('title', $title)
@section('content')

<!-- Start Content-->
<div class="main-body">
    <div class="page-wrapper">
        <!-- [ Main Content ] start -->
        <div class="row">
            <!-- [ Card ] start -->
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header">
                        <h5>{{ __('modal_add') }} / {{ __('modal_edit') }} {{ $title }}</h5>
                    </div>
                    <div class="card-block">
                        <form class="needs-validation" novalidate method="get" action="{{ route($route.'.create') }}">
                            <div class="row gx-2">
                                @include('common.inc.common_search_filter')

                                <div class="form-group col-md-3">
                                    <label for="type">{{ __('field_type') }} <span>*</span></label>
                                    <select class="form-control" name="type" id="type" required>
                                        <option value="">{{ __('select') }}</option>
                                        @foreach( $types as $type )
                                        <option value="{{ $type->id }}" @if( $selected_type == $type->id) selected @endif>{{ $type->title }}</option>
                                        @endforeach
                                    </select>

                                    <div class="invalid-feedback">
                                    {{ __('required_field') }} {{ __('field_type') }}
                                    </div>
                                </div>
                                <div class="form-group col-md-3">
                                    <button type="submit" class="btn btn-info btn-filter"><i class="fas fa-search"></i> {{ __('btn_filter') }}</button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-sm-12">
                <div class="card">
                    @isset($rows)
                    <div class="card-block">
                        
                        @isset($rows)
                        @foreach($rows as $row)

                        <form class="needs-validation mt-5" novalidate action="{{ route($route.'.update', $row->id) }}" method="post" id="fields" enctype="multipart/form-data">
                        @csrf
                        @method('PUT')
                            <div class="row">

                                @include('admin.exam-routine.form_edit')

                                <input type="text" name="program" value="{{ $selected_program }}" hidden>
                                <input type="text" name="session" value="{{ $selected_session }}" hidden>
                                <input type="text" name="semester" value="{{ $selected_semester }}" hidden>
                                <input type="text" name="section" value="{{ $selected_section }}" hidden>
                                <input type="text" name="type" value="{{ $selected_type }}" hidden>

                                <div class="form-group col-6 col-md-3">
                                    <button type="submit" class="btn btn-success btn-filter"><i class="fas fa-check"></i> {{ __('btn_update') }}</button>
                                </div>

                                @can($access.'-delete')
                                <div class="form-group col-6 col-md-3">
                                    <button type="button" class="btn btn-danger btn-filter" data-bs-toggle="modal" data-bs-target="#deleteModal-{{ $row->id }}">
                                        <i class="fas fa-trash-alt"></i> {{ __('btn_remove') }}
                                    </button>
                                </div>
                                @endcan

                            </div>
                        </form>

                        @can($access.'-delete')
                        <!-- Include Delete modal -->
                        @include('admin.layouts.inc.delete')
                        @endcan
                        @endforeach
                        @endisset

                        <form action="{{ route($route.'.store') }}" class="needs-validation mt-5 btn-submit" novalidate method="post" enctype="multipart/form-data">
                        @csrf

                        <div class="row">
                            
                            @include('admin.exam-routine.form_field')

                            <input type="text" name="program" value="{{ $selected_program }}" hidden>
                            <input type="text" name="session" value="{{ $selected_session }}" hidden>
                            <input type="text" name="semester" value="{{ $selected_semester }}" hidden>
                            <input type="text" name="section" value="{{ $selected_section }}" hidden>
                            <input type="text" name="type" value="{{ $selected_type }}" hidden>

                            <div class="form-group col-md-3">
                                <button type="submit" class="btn btn-success btn-filter"><i class="fas fa-check"></i> {{ __('btn_save') }}</button>
                            </div>
                        </div>
                        </form>
                   </div>
                   @endisset
                </div>
                
            </div>
            <!-- [ Card ] end -->
        </div>
        <!-- [ Main Content ] end -->
    </div>
</div>
<!-- End Content-->

<!-- Overlap Warning Modal -->
<div class="modal fade" id="overlapWarningModal" tabindex="-1" role="dialog" aria-labelledby="overlapWarningModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header bg-warning text-dark">
                <h5 class="modal-title" id="overlapWarningModalLabel">
                    <i class="fas fa-exclamation-triangle"></i> Time Conflict Warning!
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-danger">
                    <strong><i class="fas fa-times-circle"></i> WARNING: Schedule Conflict Detected!</strong>
                </div>
                
                <p><strong>You are trying to schedule an exam that overlaps with the following exam(s):</strong></p>
                
                <div id="conflictingExamsList" class="mb-3"></div>
                
                <div class="alert alert-warning">
                    <strong><i class="fas fa-exclamation-circle"></i> Potential Consequences:</strong>
                    <ul class="mb-0 mt-2">
                        <li>Students will have conflicting exam schedules</li>
                        <li>Teachers may be assigned to multiple exams at the same time</li>
                        <li>Rooms may be double-booked</li>
                        <li>This may cause confusion and scheduling issues</li>
                    </ul>
                </div>
                
                <p class="text-danger"><strong>Do you still want to continue and schedule this exam?</strong></p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="fas fa-times"></i> Cancel
                </button>
                <button type="button" class="btn btn-danger" id="confirmOverlapSubmit">
                    <i class="fas fa-check"></i> Yes, Continue Anyway
                </button>
            </div>
        </div>
    </div>
</div>

@endsection

@section('page_js')
<script type="text/javascript">
    (function ($) {
        "use strict";
        
        // Store existing exam schedules
        var existingExams = [];
        @isset($rows)
        @foreach($rows as $row)
        existingExams.push({
            id: {{ $row->id }},
            subject: '{{ $row->subject->code ?? '' }} - {{ $row->subject->title ?? '' }}',
            date: '{{ $row->date }}',
            start_time: '{{ $row->start_time }}',
            end_time: '{{ $row->end_time }}'
        });
        @endforeach
        @endisset

        var pendingForm = null;

        // Check for overlapping exams
        function checkOverlap($form) {
            var currentDate = $form.find('input[name="date"]').val();
            var currentStartTime = $form.find('input[name="start_time"]').val();
            var currentEndTime = $form.find('input[name="end_time"]').val();
            var currentFormId = $form.find('input[name="_method"]').length > 0 ? 
                                $form.attr('action').split('/').pop() : null;

            var overlappingExams = [];

            // Check against all existing exams
            existingExams.forEach(function(exam) {
                // Skip if editing the same exam
                if (currentFormId && exam.id == currentFormId) {
                    return;
                }

                // Check if same date
                if (exam.date === currentDate) {
                    // Check for time overlap
                    // Two time ranges overlap if: start1 < end2 AND start2 < end1
                    if (currentStartTime < exam.end_time && exam.start_time < currentEndTime) {
                        overlappingExams.push(exam);
                    }
                }
            });

            return overlappingExams;
        }

        // Validate time from/to on form submission
        $('form.needs-validation').on('submit', function(e) {
            e.preventDefault();
            var $form = $(this);

            // Get start and end time from the form
            var startTime = $form.find('input[name="start_time"]').val();
            var endTime = $form.find('input[name="end_time"]').val();

            // First check: Time From must be less than Time To
            if (startTime && endTime) {
                if (startTime >= endTime) {
                    $form.find('input[name="end_time"]').addClass('is-invalid');
                    $form.find('input[name="end_time"]').siblings('.invalid-feedback').text('Time From must be less than Time To').css('display', 'block');
                    alert('Time From must be less than Time To');
                    return false;
                } else {
                    $form.find('input[name="end_time"]').removeClass('is-invalid');
                    $form.find('input[name="end_time"]').siblings('.invalid-feedback').css('display', 'none');
                }
            }

            // Second check: Check for overlapping exams
            var overlaps = checkOverlap($form);
            if (overlaps.length > 0) {
                // Show modal with conflict details
                var conflictHtml = '<div class="list-group">';
                
                overlaps.forEach(function(exam, index) {
                    conflictHtml += '<div class="list-group-item">';
                    conflictHtml += '<div class="d-flex w-100 justify-content-between">';
                    conflictHtml += '<h6 class="mb-1"><strong>' + (index + 1) + '. ' + exam.subject + '</strong></h6>';
                    conflictHtml += '</div>';
                    conflictHtml += '<p class="mb-1"><i class="fas fa-calendar"></i> Date: ' + exam.date + '</p>';
                    conflictHtml += '<small><i class="fas fa-clock"></i> Time: ' + exam.start_time + ' - ' + exam.end_time + '</small>';
                    conflictHtml += '</div>';
                });
                
                conflictHtml += '</div>';
                
                $('#conflictingExamsList').html(conflictHtml);
                pendingForm = $form;
                
                // Show modal
                var modal = new bootstrap.Modal(document.getElementById('overlapWarningModal'));
                modal.show();
                
                return false;
            } else {
                // No overlap, submit form directly
                $form.off('submit').submit();
            }
        });

        // Handle confirm button in modal
        $('#confirmOverlapSubmit').on('click', function() {
            if (pendingForm) {
                // Close modal
                var modal = bootstrap.Modal.getInstance(document.getElementById('overlapWarningModal'));
                modal.hide();
                
                // Submit the form
                pendingForm.off('submit').submit();
                pendingForm = null;
            }
        });

        // Real-time validation when time inputs change
        $(document).on('change', 'input[name="start_time"], input[name="end_time"]', function() {
            var $form = $(this).closest('form');
            var startTime = $form.find('input[name="start_time"]').val();
            var endTime = $form.find('input[name="end_time"]').val();

            if (startTime && endTime) {
                if (startTime >= endTime) {
                    $form.find('input[name="end_time"]').addClass('is-invalid');
                    $form.find('input[name="end_time"]').siblings('.invalid-feedback').text('Time To must be greater than Time From').css('display', 'block');
                } else {
                    $form.find('input[name="end_time"]').removeClass('is-invalid');
                    $form.find('input[name="end_time"]').siblings('.invalid-feedback').css('display', 'none');
                }
            }
        });

        // Real-time overlap warning on date/time change
        $(document).on('change', 'input[name="date"], input[name="start_time"], input[name="end_time"]', function() {
            var $form = $(this).closest('form');
            var overlaps = checkOverlap($form);
            
            // Remove any existing warning
            $form.find('.overlap-warning').remove();
            
            if (overlaps.length > 0) {
                var warningHtml = '<div class="col-md-12 overlap-warning">';
                warningHtml += '<div class="alert alert-warning alert-dismissible fade show" role="alert">';
                warningHtml += '<strong><i class="fas fa-exclamation-triangle"></i> Time Conflict Warning!</strong><br>';
                warningHtml += 'This exam time overlaps with:<br>';
                overlaps.forEach(function(exam) {
                    warningHtml += '<strong>' + exam.subject + '</strong> (' + exam.start_time + ' - ' + exam.end_time + ')<br>';
                });
                warningHtml += '<small>Continuing may cause scheduling conflicts.</small>';
                warningHtml += '<button type="button" class="btn-close" data-bs-dismiss="alert"></button>';
                warningHtml += '</div></div>';
                
                $form.find('.row').append(warningHtml);
            }
        });
    }(jQuery));
</script>
@endsection
