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
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5>{{ __('modal_add') }} / {{ __('modal_edit') }} {{ $title }}</h5>
                        <a href="{{ route($route.'.joint') }}" class="btn btn-info btn-sm">
                            <i class="fas fa-users"></i> {{ __('Joint Class Schedule') }}
                        </a>
                    </div>
                    <div class="card-block">
                        <form class="needs-validation" novalidate method="get" action="{{ route($route.'.create') }}">
                            <div class="row gx-2">
                                @include('common.inc.common_search_filter')

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
                    @php
                    $weekdays = array('1', '2', '3', '4', '5', '6', '7');
                    @endphp
                    <ul class="nav nav-pills mb-3 card-block" id="myTab" role="tablist">

                        @foreach($weekdays as $weekday)
                        <li class="nav-item">
                            <a class="nav-link @if($weekday == 1) active @endif text-uppercase" id="day{{ $weekday }}-tab" data-bs-toggle="tab" href="#day{{ $weekday }}" role="tab" aria-controls="day{{ $weekday }}" aria-selected="true">
                                @if( $weekday == 1 )
                                {{ __('day_saturday') }}
                                @elseif( $weekday == 2 )
                                    {{ __('day_sunday') }}
                                @elseif( $weekday == 3 )
                                    {{ __('day_monday') }}
                                @elseif( $weekday == 4 )
                                    {{ __('day_tuesday') }}
                                @elseif( $weekday == 5 )
                                    {{ __('day_wednesday') }}
                                @elseif( $weekday == 6 )
                                    {{ __('day_thursday') }}
                                @elseif( $weekday == 7 )
                                    {{ __('day_friday') }}
                                @endif
                            </a>
                        </li>
                        @endforeach

                    </ul>
                    <div class="tab-content" id="myTabContent">

                        @foreach($weekdays as $weekday)
                        <div class="tab-pane fade @if($weekday == 1) show active @endif" id="day{{ $weekday }}" role="tabpanel" aria-labelledby="day{{ $weekday }}-tab">
                            <div class="">
                                <div class="row">
                                    <div class="col-md-12">
                                    <form class="needs-validation" novalidate action="{{ route($route.'.store') }}" method="post" id="fields" enctype="multipart/form-data">
                                    @csrf
                                    <input type="text" name="program" value="{{ $selected_program }}" hidden>
                                    <input type="text" name="session" value="{{ $selected_session }}" hidden>
                                    <input type="text" name="semester" value="{{ $selected_semester }}" hidden>
                                    <input type="text" name="section" value="{{ $selected_section }}" hidden>
                                    <input type="text" name="day" value="{{ $weekday }}" hidden>
                                    @forelse($rows->where('day', $weekday) as $row)
                                        @include('admin.class-routine.form_edit_field')
                                    @empty
                                        @include('admin.class-routine.form_field')
                                    @endforelse
                                    <div id="newField-tab-{{ $weekday }}" class="clearfix"></div>
                                    <div class="card-block">
                                        <button id="addField" type="button" class="btn btn-info" data-bs-tab="tab-{{ $weekday }}"><i class="fas fa-plus"></i> {{ __('btn_add_new') }}</button>
                                    </div>
                                    <div class="card-footer text-right">
                                        <button type="submit" class="btn btn-success"><i class="fas fa-check"></i> {{ __('btn_save') }}</button>
                                    </div>
                                    </form>
                                    </div>
                                </div>
                            </div>
                        </div>
                        @endforeach

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
                
                <p><strong>You are trying to schedule a class that overlaps with the following class(es):</strong></p>
                
                <div id="conflictingClassesList" class="mb-3"></div>
                
                <div class="alert alert-warning">
                    <strong><i class="fas fa-exclamation-circle"></i> Potential Consequences:</strong>
                    <ul class="mb-0 mt-2">
                        <li>Students will have conflicting class schedules</li>
                        <li>Teachers may be assigned to multiple classes at the same time</li>
                        <li>Rooms may be double-booked</li>
                        <li>This may cause confusion and scheduling issues</li>
                    </ul>
                </div>
                
                <p class="text-danger"><strong>Do you still want to continue and schedule this class?</strong></p>
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
        
        // Store existing class schedules
        var existingClasses = [];
        @isset($rows)
        @foreach($rows as $row)
        existingClasses.push({
            id: {{ $row->id }},
            day: {{ $row->day }},
            subject: '{{ $row->subject->code ?? '' }} - {{ $row->subject->title ?? '' }}',
            teacher: '{{ $row->teacher->first_name ?? '' }} {{ $row->teacher->last_name ?? '' }}',
            room: '{{ $row->room->title ?? '' }}',
            start_time: '{{ $row->start_time }}',
            end_time: '{{ $row->end_time }}'
        });
        @endforeach
        @endisset

        var pendingForm = null;
        var pendingRoutines = [];

        // Get day name from number
        function getDayName(dayNum) {
            var days = {
                1: '{{ __("day_saturday") }}',
                2: '{{ __("day_sunday") }}',
                3: '{{ __("day_monday") }}',
                4: '{{ __("day_tuesday") }}',
                5: '{{ __("day_wednesday") }}',
                6: '{{ __("day_thursday") }}',
                7: '{{ __("day_friday") }}'
            };
            return days[dayNum] || 'Day ' + dayNum;
        }

        // Check for overlapping classes
        function checkOverlap($form) {
            var currentDay = parseInt($form.find('input[name="day"]').val());
            var overlappingClasses = [];

            // Get all routine entries in this form
            var routines = [];
            $form.find('#inputFormField, [id^="deleteRoutine-"]').each(function() {
                var $container = $(this);
                var startTime = $container.find('input[name="start_time[]"]').val();
                var endTime = $container.find('input[name="end_time[]"]').val();
                var subject = $container.find('select[name="subject[]"] option:selected').text();
                var teacher = $container.find('select[name="teacher[]"] option:selected').text();
                var room = $container.find('select[name="room[]"] option:selected').text();
                var routineId = $container.find('input[name="routine_id[]"]').val();

                if (startTime && endTime) {
                    routines.push({
                        id: routineId,
                        subject: subject,
                        teacher: teacher,
                        room: room,
                        start_time: startTime,
                        end_time: endTime
                    });
                }
            });

            // Check each routine against existing classes
            routines.forEach(function(routine, index) {
                existingClasses.forEach(function(existingClass) {
                    // Skip if same day and editing the same routine
                    if (routine.id && existingClass.id == routine.id) {
                        return;
                    }

                    // Check if same day
                    if (existingClass.day === currentDay) {
                        // Check for time overlap
                        if (routine.start_time < existingClass.end_time && existingClass.start_time < routine.end_time) {
                            overlappingClasses.push({
                                newClass: routine,
                                existingClass: existingClass,
                                routineIndex: index
                            });
                        }
                    }
                });

                // Also check against other routines in the same form
                routines.forEach(function(otherRoutine, otherIndex) {
                    if (index !== otherIndex) {
                        if (routine.start_time < otherRoutine.end_time && otherRoutine.start_time < routine.end_time) {
                            overlappingClasses.push({
                                newClass: routine,
                                existingClass: otherRoutine,
                                routineIndex: index,
                                sameForm: true
                            });
                        }
                    }
                });
            });

            return overlappingClasses;
        }

        // add Field
        $(document).on('click', '#addField', function () {
            var tab = $(this).attr('data-bs-tab');
            var html = '';
            html += '<hr/>';
            html += '<div id="inputFormField" class="card-block">';
            html += '<div class="row">';
            html += '<div class="form-group col-md-2"><label for="subject">{{ __('field_subject') }} <span>*</span></label><select class="form-control select2" name="subject[]" id="subject" required><option value="">{{ __('select') }}</option> @isset($subjects) @foreach( $subjects as $subject ) <option value="{{ $subject->id }}">{{ $subject->code }} - {{ $subject->title }}</option> @endforeach @endisset </select> <div class="invalid-feedback"> {{ __('required_field') }} {{ __('field_subject') }}</div></div>';
            html += '<div class="form-group col-md-2"><label for="teacher">{{ __('field_teacher') }} <span>*</span></label> <select class="form-control select2" name="teacher[]" id="teacher"><option value="">{{ __('select') }}</option> @isset($teachers) @foreach( $teachers as $teacher ) <option value="{{ $teacher->id }}">{{ $teacher->staff_id }} - {{ $teacher->first_name }} {{ $teacher->last_name }}</option> @endforeach @endisset </select> <div class="invalid-feedback"> {{ __('required_field') }} {{ __('field_teacher') }} </div> </div>';
            html += '<div class="form-group col-md-2"> <label for="room">{{ __('field_room') }} {{ __('field_no') }} <span>*</span></label> <select class="form-control select2" name="room[]" id="room" required> <option value="">{{ __('select') }}</option> @isset($rooms) @foreach( $rooms as $room ) <option value="{{ $room->id }}">{{ $room->title }}</option> @endforeach @endisset </select> <div class="invalid-feedback"> {{ __('required_field') }} {{ __('field_room') }} {{ __('field_no') }} </div> </div>';
            html += '<div class="form-group col-md-2"> <label for="start_time">{{ __('field_time') }} {{ __('field_from') }} <span>*</span></label><input type="time" class="form-control time start-time-input" name="start_time[]" required><div class="invalid-feedback">{{ __('required_field') }} {{ __('field_time') }} {{ __('field_from') }}</div></div>';
            html += '<div class="form-group col-md-2"> <label for="end_time">{{ __('field_time') }} {{ __('field_to') }} <span>*</span></label> <input type="time" class="form-control time end-time-input" name="end_time[]" required> <div class="invalid-feedback"> {{ __('required_field') }} {{ __('field_time') }} {{ __('field_to') }} </div> </div>';
            html += '<div class="form-group col-md-2"><button id="removeField" type="button" class="btn btn-danger btn-filter"><i class="fas fa-trash-alt"></i> {{ __('btn_remove') }}</button></div>';
            html += '</div>';

            $('#newField-'+tab).append(html);

            // Time Picker
            $('.time').bootstrapMaterialDatePicker({
                date: false,
                shortTime: true,
                format: 'HH:mm'
            });
        });

        // remove Field
        $(document).on('click', '#removeField', function () {
            $(this).closest('#inputFormField').remove();

            // Time Picker
            $('.time').bootstrapMaterialDatePicker({
                date: false,
                shortTime: true,
                format: 'HH:mm'
            });
        });

        // Validate time from/to on form submission
        $('form').on('submit', function(e) {
            e.preventDefault();
            var isValid = true;
            var errorMessage = '';
            var $form = $(this);

            // Check all time pairs
            $('#inputFormField, [id^="deleteRoutine-"]').each(function() {
                var $container = $(this);
                var startTime = $container.find('input[name="start_time[]"]').val();
                var endTime = $container.find('input[name="end_time[]"]').val();

                if (startTime && endTime) {
                    if (startTime >= endTime) {
                        isValid = false;
                        errorMessage = 'Time From must be less than Time To';
                        $container.find('input[name="end_time[]"]').addClass('is-invalid');
                        $container.find('input[name="end_time[]"]').siblings('.invalid-feedback').text(errorMessage).show();
                    } else {
                        $container.find('input[name="end_time[]"]').removeClass('is-invalid');
                    }
                }
            });

            if (!isValid) {
                alert(errorMessage);
                return false;
            }

            // Check for overlapping classes
            var overlaps = checkOverlap($form);
            if (overlaps.length > 0) {
                // Remove duplicates
                var uniqueOverlaps = [];
                var seenPairs = new Set();
                
                overlaps.forEach(function(overlap) {
                    var key = overlap.newClass.subject + '|' + overlap.existingClass.subject + '|' + 
                              overlap.newClass.start_time + '|' + overlap.existingClass.start_time;
                    if (!seenPairs.has(key)) {
                        seenPairs.add(key);
                        uniqueOverlaps.push(overlap);
                    }
                });

                // Show modal with conflict details
                var currentDay = parseInt($form.find('input[name="day"]').val());
                var dayName = getDayName(currentDay);
                
                var conflictHtml = '<div class="list-group">';
                
                uniqueOverlaps.forEach(function(overlap, index) {
                    conflictHtml += '<div class="list-group-item">';
                    conflictHtml += '<div class="d-flex w-100 justify-content-between">';
                    conflictHtml += '<h6 class="mb-1"><strong>' + (index + 1) + '. Conflict on ' + dayName + '</strong></h6>';
                    conflictHtml += '</div>';
                    
                    conflictHtml += '<div class="row mt-2">';
                    conflictHtml += '<div class="col-md-6">';
                    conflictHtml += '<p class="mb-1 text-primary"><strong>New Class:</strong></p>';
                    conflictHtml += '<small><i class="fas fa-book"></i> ' + overlap.newClass.subject + '</small><br>';
                    conflictHtml += '<small><i class="fas fa-user"></i> ' + overlap.newClass.teacher + '</small><br>';
                    conflictHtml += '<small><i class="fas fa-door-open"></i> Room: ' + overlap.newClass.room + '</small><br>';
                    conflictHtml += '<small><i class="fas fa-clock"></i> ' + overlap.newClass.start_time + ' - ' + overlap.newClass.end_time + '</small>';
                    conflictHtml += '</div>';
                    
                    conflictHtml += '<div class="col-md-6">';
                    conflictHtml += '<p class="mb-1 text-danger"><strong>' + (overlap.sameForm ? 'Conflicts With (Same Form):' : 'Existing Class:') + '</strong></p>';
                    conflictHtml += '<small><i class="fas fa-book"></i> ' + overlap.existingClass.subject + '</small><br>';
                    conflictHtml += '<small><i class="fas fa-user"></i> ' + overlap.existingClass.teacher + '</small><br>';
                    conflictHtml += '<small><i class="fas fa-door-open"></i> Room: ' + overlap.existingClass.room + '</small><br>';
                    conflictHtml += '<small><i class="fas fa-clock"></i> ' + overlap.existingClass.start_time + ' - ' + overlap.existingClass.end_time + '</small>';
                    conflictHtml += '</div>';
                    conflictHtml += '</div>';
                    
                    conflictHtml += '</div>';
                });
                
                conflictHtml += '</div>';
                
                $('#conflictingClassesList').html(conflictHtml);
                pendingForm = $form;
                
                // Show modal
                var modal = new bootstrap.Modal(document.getElementById('overlapWarningModal'));
                modal.show();
                
                return false;
            } else {
                // No overlap, submit form
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
        $(document).on('change', 'input[name="start_time[]"], input[name="end_time[]"]', function() {
            var $container = $(this).closest('.row');
            var startTime = $container.find('input[name="start_time[]"]').val();
            var endTime = $container.find('input[name="end_time[]"]').val();

            if (startTime && endTime) {
                if (startTime >= endTime) {
                    $container.find('input[name="end_time[]"]').addClass('is-invalid');
                    $container.find('input[name="end_time[]"]').siblings('.invalid-feedback').text('Time To must be greater than Time From').show();
                } else {
                    $container.find('input[name="end_time[]"]').removeClass('is-invalid');
                    $container.find('input[name="end_time[]"]').siblings('.invalid-feedback').hide();
                }
            }
        });
    }(jQuery));


    // Delete Routine
    function deleteRoutine(id) {
        jQuery("#deleteRoutine-"+id).hide();
        jQuery("#delete_routine-"+id).attr("checked", "checked");
    }
</script>
@endsection