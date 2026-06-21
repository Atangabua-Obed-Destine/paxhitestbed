@extends('admin.layouts.master')
@section('title', $title)
@section('content')

<style>
    .table-responsive {
        max-height: 80vh;
        overflow-y: auto;
    }
    thead th {
        position: sticky;
        top: 0;
        background-color: #fff;
        z-index: 1;
        box-shadow: 0 2px 2px -1px rgba(0, 0, 0, 0.4);
    }
</style>

<!-- Start Content-->
<div class="main-body">
    <div class="page-wrapper">
        <!-- [ Main Content ] start -->
        <div class="row">
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header">
                        <h5>{{ $title }}</h5>

                        @can($access.'-action')
                        @if(isset($selected_subject) && $selected_subject !== '0')
                        <a href="{{ route($route.'.scanner', [
                            'faculty' => $selected_faculty ?? '0',
                            'program' => $selected_program ?? '0',
                            'session' => $selected_session ?? '0',
                            'semester' => $selected_semester ?? '0',
                            'section' => $selected_section ?? '0',
                            'subject' => $selected_subject ?? '0',
                            'date' => $selected_date ?? date('Y-m-d')
                        ]) }}" class="btn btn-primary btn-sm float-right mr-2" target="_blank">
                            <i class="fas fa-qrcode"></i> {{ __('Open Kiosk') }}
                        </a>
                        @endif
                        @endcan

                        @can($access.'-import')
                        <a href="{{ route($route.'.import') }}" class="btn btn-dark btn-sm float-right"><i class="fas fa-upload"></i> {{ __('btn_import') }}</a>
                        @endcan
                    </div>
                    <div class="card-block">
                        <form class="needs-validation" novalidate method="get" action="{{ route($route.'.index') }}">
                            <div class="row gx-2">
                                @include('common.inc.common_search_filter', [
                                  'faculties' => $faculties ?? collect(),
                                  'programs' => $programs ?? collect(),
                                  'sessions' => $sessions ?? collect(),
                                  'semesters' => $semesters ?? collect(),
                                  'sections' => $sections ?? collect(),
                                  'semesterOptions' => $semesterOptions ?? [],
                                  'selected_faculty' => $selected_faculty ?? '0',
                                  'selected_program' => $selected_program ?? '0',
                                  'selected_session' => $selected_session ?? '0',
                                  'selected_semester' => $selected_semester ?? '0',
                                  'selected_semester_year' => $selected_semester_year ?? '0',
                                  'selected_section' => $selected_section ?? '0',
                                  'include_semester_all' => true,
                                  'include_section_all' => true,
                                ])

                                <div class="form-group col-md-3">
                                    <label for="subject">{{ __('field_subject') }} <span>*</span></label>
                                    <select class="form-control subject subject-filter" name="subject" id="subject" required>
                                        <option value="">{{ __('select') }}</option>
                                        @foreach(($subjects ?? collect())->sortBy('code') as $subject)
                                        <option value="{{ $subject->id }}" @if($selected_subject == $subject->id) selected @endif>{{ $subject->code }} - {{ $subject->title }}</option>
                                        @endforeach
                                    </select>

                                    <div class="invalid-feedback">
                                        {{ __('required_field') }} {{ __('field_subject') }}
                                    </div>
                                </div>

                                <div class="form-group col-md-3">
                                    <label for="date">{{ __('field_date') }} <span>*</span></label>
                                    <input type="date" class="form-control date" name="date" value="{{ $selected_date }}" required>

                                    <div class="invalid-feedback">
                                      {{ __('required_field') }} {{ __('field_date') }}
                                    </div>
                                </div>
                                <div class="form-group col-md-3">
                                    <button type="submit" class="btn btn-info btn-filter"><i class="fas fa-search"></i> {{ __('btn_search') }}</button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-sm-12">
                <div class="card">
                    @if(isset($rows))
                    @if(count($rows) > 0)
                    <div class="card-block">
                        @if(isset($attendances))
                            @if(count($attendances) > 0)
                            <div class="alert alert-success" role="alert">
                                {{ __('attendance_taken') }}
                            </div>
                            @else
                            <div class="alert alert-danger" role="alert">
                                {{ __('attendance_not_taken') }}
                            </div>
                            @endif
                        @else
                            <div class="alert alert-danger" role="alert">
                                {{ __('attendance_not_taken') }}
                            </div>
                        @endif
                    </div>
                    @endif
                    @endif

                    @if(isset($rows))
                    @if(count($rows) > 0)
                    <div class="card-header">
                        <div class="form-group d-inline">
                            <div class="radio radio-primary d-inline">
                                <input type="radio" name="all_check" id="attendance-p" class="all_present">
                                <label for="attendance-p" class="cr">{{ __('all') }} {{ __('attendance_present') }}</label>
                            </div>
                        </div>
                        <div class="form-group d-inline">
                            <div class="radio radio-danger d-inline">
                                <input type="radio" name="all_check" id="attendance-a" class="all_absent">
                                <label for="attendance-a" class="cr">{{ __('all') }} {{ __('attendance_absent') }}</label>
                            </div>
                        </div>
                        <div class="form-group d-inline">
                            <div class="radio radio-success d-inline">
                                <input type="radio" name="all_check" id="attendance-l" class="all_leave">
                                <label for="attendance-l" class="cr">{{ __('all') }} {{ __('attendance_leave') }}</label>
                            </div>
                        </div>
                        <div class="form-group d-inline">
                            <div class="radio radio-warning d-inline">
                                <input type="radio" name="all_check" id="attendance-h" class="all_holiday">
                                <label for="attendance-h" class="cr">{{ __('all') }} {{ __('attendance_holiday') }}</label>
                            </div>
                        </div>

                        <a href="{{ route($route.'.index') }}" class="btn btn-info"><i class="fas fa-sync-alt"></i> {{ __('btn_refresh') }}</a>

                        @if(isset($rows))
                        <button type="button" class="btn btn-dark btn-print">
                            <i class="fas fa-print"></i> {{ __('btn_print') }}
                        </button>

                        <button type="button" class="btn btn-primary" id="start-scan">
                            <i class="fas fa-qrcode"></i> {{ __('Scan QR Code') }}
                        </button>
                        @endif
                        <div class="clearfix"></div>
                    </div>

                    <!-- Scanner Section -->
                    <div id="scanner-container" style="display: none; margin-bottom: 20px;">
                        <div class="row justify-content-center">
                            <div class="col-md-6">
                                <div id="reader" style="width: 100%;"></div>
                                <div class="text-center mt-2">
                                    <button type="button" class="btn btn-danger" id="stop-scan">{{ __('Close Scanner') }}</button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <form class="needs-validation" novalidate action="{{ route($route.'.store') }}" method="post" enctype="multipart/form-data">
                    @csrf
                    <div class="card-block">
                        <input type="hidden" name="subject" value="{{ $selected_subject }}">
                        <input type="hidden" name="date" value="{{ $selected_date }}">
                        <input type="hidden" name="attendances" class="attendances" value="">
                        <!-- [ Data table ] start -->
                        <div class="table-responsive">
                            <table class="display table nowrap table-striped table-hover printable">
                                <thead>
                                    <tr>
                                        <th>{{ __('field_matricule') }}</th>
                                        <th>{{ __('field_name') }}</th>
                                        <th>{{ __('field_attendance') }}</th>
                                        <th>{{ __('field_note') }}</th>
                                        <th>{{ __('field_semester') }}</th>
                                        <th>{{ __('field_section') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                  @foreach( $rows as $key => $row )
                                    <input type="hidden" name="students[]" value="{{ $row->id }}">
                                    <tr class="student-row" data-student-id="{{ $row->student->student_id ?? '' }}" data-matricule="{{ $row->matricule ?? '' }}">
                                        <td>
                                            @isset($row->student->student_id)
                                            <a href="{{ route('admin.student.show', $row->student->id) }}">
                                            <strong style="color: #667eea;">#{{ $row->matricule ?? $row->student->student_id ?? '' }}</strong>
                                            @if($row->program)
                                                <br>
                                                <span class="badge" style="background: {{ $row->program->academic_level == 'M' ? '#f5576c' : ($row->program->academic_level == 'D' ? '#00f2fe' : '#38f9d7') }}; color: white; font-size: 9px;">
                                                    {{ $row->program->academic_level == 'A' ? 'UG' : ($row->program->academic_level == 'M' ? 'MS' : 'PhD') }}
                                                </span>
                                            @endif
                                            </a>
                                            @endisset
                                        </td>
                                        <td>{{ $row->student->first_name ?? '' }} {{ $row->student->last_name ?? '' }}</td>
                                        <td>
                                            <div class="form-group d-inline">
                                                <div class="radio radio-primary d-inline">
                                                    <input class="c-present" type="radio" data_id="{{ $row->id }}"name="attendances-{{ $key }}" id="attendance-p-{{ $key }}" value="1"

                                                    @if(isset($attendances) && count($attendances) > 0)
                                                    @foreach($attendances as $attendance)
                                                        @if($attendance->student_enroll_id == $row->id && $attendance->attendance == 1)
                                                            checked
                                                        @endif
                                                    @endforeach
                                                    @endif
                                                     required>
                                                    <label for="attendance-p-{{ $key }}" class="cr">{{ __('attendance_present') }}</label>
                                                </div>
                                            </div>
                                            <div class="form-group d-inline">
                                                <div class="radio radio-danger d-inline">
                                                    <input class="c-absent" type="radio" data_id="{{ $row->id }}"name="attendances-{{ $key }}" id="attendance-a-{{ $key }}" value="2" 

                                                    @if(isset($attendances))
                                                    @foreach($attendances as $attendance)
                                                        @if($attendance->student_enroll_id == $row->id && $attendance->attendance == 2)
                                                            checked
                                                        @endif
                                                    @endforeach
                                                    @endif
                                                     required>
                                                    <label for="attendance-a-{{ $key }}" class="cr">{{ __('attendance_absent') }}</label>
                                                </div>
                                            </div>
                                            <div class="form-group d-inline">
                                                <div class="radio radio-success d-inline">
                                                    <input class="c-leave" type="radio" data_id="{{ $row->id }}"name="attendances-{{ $key }}" id="attendance-l-{{ $key }}" value="3"

                                                    @if(isset($attendances))
                                                    @foreach($attendances as $attendance)
                                                        @if($attendance->student_enroll_id == $row->id && $attendance->attendance == 3)
                                                            checked
                                                        @endif
                                                    @endforeach
                                                    @endif
                                                     required>
                                                    <label for="attendance-l-{{ $key }}" class="cr">{{ __('attendance_leave') }}</label>
                                                </div>
                                            </div>
                                            <div class="form-group d-inline">
                                                <div class="radio radio-warning d-inline">
                                                    <input class="c-holiday" type="radio" data_id="{{ $row->id }}"name="attendances-{{ $key }}" id="attendance-h-{{ $key }}" value="4"

                                                    @if(isset($attendances))
                                                    @foreach($attendances as $attendance)
                                                        @if($attendance->student_enroll_id == $row->id && $attendance->attendance == 4)
                                                            checked
                                                        @endif
                                                    @endforeach
                                                    @endif
                                                     required>
                                                    <label for="attendance-h-{{ $key }}" class="cr">{{ __('attendance_holiday') }}</label>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <input type="text" style="width: 100px;" class="form-control" name="notes[]" id="note-{{ $key }}" 
                                            @if(isset($attendances))
                                            @foreach($attendances as $attendance)
                                                @if($attendance->student_enroll_id == $row->id)
                                                    value="{{ $attendance->note }}"
                                                @endif
                                            @endforeach
                                            @endif>
                                        </td>
                                        <td>{{ $row->semester->title ?? '' }}</td>
                                        <td>{{ $row->section->title ?? '' }}</td>
                                    </tr>
                                  @endforeach
                                </tbody>

                                @foreach( $subjects as $subject_code )
                                @if($subject_code->id == $selected_subject)
                                @php
                                    $cur_subject = $subject_code->code;
                                @endphp
                                @endif
                                @endforeach

                                <caption>{{ $cur_subject ?? '' }} - {{ date("d F Y", strtotime($selected_date)) ?? '' }}</caption>
                            </table>
                        </div>
                        <!-- [ Data table ] end -->
                    </div>
                    <div class="card-footer">
                        <button type="submit" class="btn btn-success update"><i class="fas fa-check"></i> {{ __('btn_update') }}</button>
                    </div>
                    </form>
                    @endif

                    @if(count($rows) < 1)
                    <div class="card-block">
                        <h5>{{ __('no_result_found') }}</h5>
                    </div>
                    @endif
                    @endif
                </div>
            </div>
        </div>
        <!-- [ Main Content ] end -->
    </div>
</div>
<!-- End Content-->

@endsection

@section('page_js')
<script src="https://cdnjs.cloudflare.com/ajax/libs/html5-qrcode/2.3.8/html5-qrcode.min.js"></script>
<script type="text/javascript">
"use strict";
(function($){
    if(typeof $ === 'undefined'){
        return;
    }

    $(function(){
        // Scanner Logic
        let html5QrcodeScanner = null;
        // const beepSound = new Audio("{{ asset('dashboard/sounds/beep.mp3') }}"); // File not found, using Web Audio API instead
        
        function playBeep() {
            try {
                const audioCtx = new (window.AudioContext || window.webkitAudioContext)();
                
                const playTone = (freq, start, duration) => {
                    const oscillator = audioCtx.createOscillator();
                    const gainNode = audioCtx.createGain();
                    
                    oscillator.connect(gainNode);
                    gainNode.connect(audioCtx.destination);
                    
                    oscillator.type = 'sine';
                    oscillator.frequency.setValueAtTime(freq, start);
                    
                    // Louder and smoother envelope
                    gainNode.gain.setValueAtTime(0, start);
                    gainNode.gain.linearRampToValueAtTime(0.3, start + 0.05); // Attack
                    gainNode.gain.exponentialRampToValueAtTime(0.01, start + duration); // Decay
                    
                    oscillator.start(start);
                    oscillator.stop(start + duration);
                };

                const now = audioCtx.currentTime;
                // Play a "Success" chime (Ascending Major 3rd: C5 -> E5)
                playTone(523.25, now, 0.15); 
                playTone(659.25, now + 0.1, 0.3); 
                
            } catch (e) {
                console.error("Audio play failed", e);
            }
        }

        console.log("Scanner Script Loaded - Version 2.1"); // Debug version

        $('#start-scan').on('click', function() {
            if(typeof Html5QrcodeScanner === 'undefined') {
                alert('Scanner library not loaded. Please check your internet connection or reload the page.');
                return;
            }

            $('#scanner-container').show();

            if (html5QrcodeScanner === null) {
                // Use the library's built-in UI widget which handles camera and file upload robustly
                html5QrcodeScanner = new Html5QrcodeScanner(
                    "reader", 
                    { 
                        fps: 10, 
                        qrbox: { width: 250, height: 250 },
                        rememberLastUsedCamera: true,
                        aspectRatio: 1.0
                    },
                    /* verbose= */ false
                );
                
                html5QrcodeScanner.render(onScanSuccess, (errorMessage) => {
                    // parse error, ignore it.
                });
            }
        });

        $('#stop-scan').on('click', function() {
            if(html5QrcodeScanner) {
                html5QrcodeScanner.clear().then(() => {
                    $('#scanner-container').hide();
                    html5QrcodeScanner = null;
                }).catch(error => {
                    console.error("Failed to clear scanner", error);
                    $('#scanner-container').hide();
                });
            } else {
                $('#scanner-container').hide();
            }
        });

        function onScanSuccess(decodedText, decodedResult) {
            // Handle on success condition with the decoded message.
            console.log(`Scan result:`, decodedText, decodedResult);
            
            // Defensive check: Ensure decodedText is a string
            if (typeof decodedText !== 'string') {
                console.warn("Scanner returned non-string result:", decodedText);
                alert("Scan Error: The scanner returned an invalid result format (" + typeof decodedText + "). Please try again.");
                return;
            }

            // Extract Student ID from URL if it's a URL
            let studentId = decodedText;
            if(decodedText.includes('/verify-student/')) {
                const parts = decodedText.split('/verify-student/');
                if(parts.length > 1) {
                    studentId = parts[1];
                }
            }
            
            // Trim whitespace
            studentId = studentId.trim();

            // Find the row
            let $row = $(`.student-row[data-student-id="${studentId}"]`);
            if($row.length === 0) {
                $row = $(`.student-row[data-matricule="${studentId}"]`);
            }

            if($row.length > 0) {
                // Mark as Present
                $row.find('.c-present').prop('checked', true);
                
                // Visual Feedback
                $row.addClass('table-success');
                
                // Scroll to row
                $row[0].scrollIntoView({ behavior: 'smooth', block: 'center' });

                // Play Sound
                playBeep();
                
                // Note: Html5QrcodeScanner does not support pause/resume easily.
                // We just let it keep scanning. The user can close it when done.

            } else {
                console.log('Student not found in this list: ' + studentId);
                alert('Student not found: ' + studentId);
            }
        }

        const $filterForm = $('form.needs-validation').filter(function(){
            return String($(this).attr('method')).toLowerCase() === 'get';
        }).first();

        if($filterForm.length){
            const $subject = $filterForm.find('.subject-filter');
            const translationSelect = <?php echo json_encode(__('select')); ?>;
            let presetSubject = <?php echo json_encode($selected_subject ?? ''); ?>;
            if(presetSubject === '0'){
                presetSubject = '';
            }

            const resetSubjectOptions = () => {
                if(!$subject.length){
                    return;
                }
                $subject.empty();
                $subject.append($('<option/>', {
                    value: '',
                    text: translationSelect
                }));
            };

            const applyPreset = () => {
                if(!presetSubject || !$subject.length){
                    return;
                }
                $subject.val(String(presetSubject));
            };

            const applySubjectOptions = (items) => {
                resetSubjectOptions();
                if(Array.isArray(items)){
                    items.forEach((item) => {
                        if(!item){
                            return;
                        }
                        const option = $('<option/>', {
                            value: item.id,
                            text: (item.code ? item.code + ' - ' : '') + item.title
                        });
                        if(presetSubject && String(presetSubject) === String(item.id)){
                            option.attr('selected', 'selected');
                        }
                        $subject.append(option);
                    });
                }
                if(presetSubject){
                    applyPreset();
                }
            };

            const fetchSubjects = () => {
                const programId = $filterForm.find('.common-program').val();
                const sessionId = $filterForm.find('.common-session').val();
                const semesterId = $filterForm.find('.common-semester').val();
                const sectionId = $filterForm.find('.common-section').val();

                if(!programId || !sessionId){
                    presetSubject = '';
                    resetSubjectOptions();
                    return;
                }

                $.post("{{ route('filter-techer-subject') }}", {
                    program: programId,
                    session: sessionId,
                    semester: semesterId,
                    section: sectionId
                }, function(response){
                    applySubjectOptions(response);
                });
            };

            const ensureInitialOptions = () => {
                if(!$subject.length){
                    return;
                }
                if(!$subject.children('option').length){
                    resetSubjectOptions();
                }
                if(!$subject.children('option').not('[value=""]').length){
                    fetchSubjects();
                } else {
                    applyPreset();
                }
            };

            $filterForm.on('change', '.common-faculty', function(){
                presetSubject = '';
                resetSubjectOptions();
            });

            $filterForm.on('change', '.common-program', function(){
                presetSubject = '';
                resetSubjectOptions();
            });

            $filterForm.on('change', '.common-session', function(){
                presetSubject = '';
                fetchSubjects();
            });

            $filterForm.on('change', '.common-semester-year', function(){
                presetSubject = '';
                resetSubjectOptions();
            });

            $filterForm.on('change', '.common-semester, .common-section', function(){
                presetSubject = '';
                fetchSubjects();
            });

            $subject.on('change', function(){
                presetSubject = $(this).val() || '';
            });

            ensureInitialOptions();
        }

        $('.update').on('click', function(){
            const attendances = [];
            $('input[data_id]:checked').each(function(){
                attendances.push($(this).val());
            });
            $('.attendances').val(attendances.join(','));
        });

        const toggleGroup = function(triggerSelector, targetSelector){
            $(triggerSelector).on('click', function(){
                const isChecked = $(this).is(':checked');
                $(targetSelector).prop('checked', isChecked);
            });
        };

        toggleGroup('.all_present', '.c-present');
        toggleGroup('.all_absent', '.c-absent');
        toggleGroup('.all_leave', '.c-leave');
        toggleGroup('.all_holiday', '.c-holiday');
    });
})(window.jQuery);
</script>
@endsection

