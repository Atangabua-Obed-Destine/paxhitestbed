@extends('admin.layouts.master')
@section('title', $title)
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
                                <div class="form-group col-md-2">
                                    <label for="role">{{ __('field_role') }}</label>
                                    <select class="form-control" name="role" id="role">
                                        <option value="">{{ __('all') }}
                                        @foreach( $roles as $role )
                                        @if($role->slug != 'super-admin')
                                        <option value="{{ $role->id }}" @if( $selected_role == $role->id) selected @endif>{{ $role->name }}</option>
                                        @endif
                                        @endforeach
                                    </select>

                                    <div class="invalid-feedback">
                                      {{ __('required_field') }} {{ __('field_role') }}
                                    </div>
                                </div>
                                <div class="form-group col-md-2">
                                    <label for="department">{{ __('field_department') }}</label>
                                    <select class="form-control" name="department" id="department">
                                        <option value="">{{ __('all') }}</option>
                                        @foreach( $departments as $department )
                                        <option value="{{ $department->id }}" @if( $selected_department == $department->id) selected @endif>{{ $department->title }}</option>
                                        @endforeach
                                    </select>

                                    <div class="invalid-feedback">
                                      {{ __('required_field') }} {{ __('field_department') }}
                                    </div>
                                </div>
                                <div class="form-group col-md-2">
                                    <label for="designation">{{ __('field_designation') }}</label>
                                    <select class="form-control" name="designation" id="designation">
                                        <option value="">{{ __('all') }}</option>
                                        @foreach( $designations as $designation )
                                        <option value="{{ $designation->id }}" @if( $selected_designation == $designation->id) selected @endif>{{ $designation->title }}</option>
                                        @endforeach
                                    </select>

                                    <div class="invalid-feedback">
                                      {{ __('required_field') }} {{ __('field_designation') }}
                                    </div>
                                </div>
                                <div class="form-group col-md-2">
                                    <label for="shift">{{ __('field_work_shift') }}</label>
                                    <select class="form-control" name="shift" id="shift">
                                        <option value="">{{ __('all') }}</option>
                                        @foreach( $work_shifts as $shift )
                                        <option value="{{ $shift->id }}" @if( $selected_shift == $shift->id) selected @endif>{{ $shift->title }}</option>
                                        @endforeach
                                    </select>

                                    <div class="invalid-feedback">
                                      {{ __('required_field') }} {{ __('field_work_shift') }}
                                    </div>
                                </div>
                                <div class="form-group col-md-2">
                                    <label for="date">{{ __('field_date') }} <span>*</span></label>
                                    <input type="date" class="form-control date" name="date" id="date" value="{{ $selected_date }}" required>

                                    <div class="invalid-feedback">
                                      {{ __('required_field') }} {{ __('field_date') }}
                                    </div>
                                </div>
                                <div class="form-group col-md-2">
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
                        @endif
                    </div>
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

                        <a href="{{ route('admin.staff-daily-attendance.scanner') }}" class="btn btn-primary" target="_blank"><i class="fas fa-qrcode"></i> Open Kiosk</a>

                        <button type="button" class="btn btn-primary" id="start-scan">
                            <i class="fas fa-qrcode"></i> {{ __('Scan QR Code') }}
                        </button>

                        @if(isset($rows))
                        <button type="button" class="btn btn-dark btn-print">
                            <i class="fas fa-print"></i> {{ __('btn_print') }}
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
                    @endif

                    <form class="needs-validation" novalidate action="{{ route($route.'.store') }}" method="post" enctype="multipart/form-data">
                    @csrf
                    <div class="card-block">
                        <input type="hidden" name="date" value="{{ $selected_date }}">
                        <input type="hidden" name="attendances" class="attendances" value="">
                        <!-- [ Data table ] start -->
                        <div class="table-responsive">
                            <table class="display table nowrap table-striped table-hover printable" style="width:100%">
                                <thead>
                                    <tr>
                                        <th>{{ __('field_staff_id') }}</th>
                                        <th>{{ __('field_name') }}</th>
                                        <th>{{ __('field_start_time') }}</th>
                                        <th>{{ __('field_end_time') }}</th>
                                        <th>{{ __('Duration') }}</th>
                                        <th>{{ __('field_attendance') }}</th>
                                        <th>{{ __('field_note') }}</th>
                                        <th>{{ __('field_department') }}</th>
                                        <th>{{ __('field_designation') }}</th>
                                        <th>{{ __('field_work_shift') }}</th>
                                        
                                    </tr>
                                </thead>
                                <tbody>
                                  @foreach( $rows as $key => $row )
                                    <input type="hidden" name="users[]" value="{{ $row->id }}">
                                    <tr class="staff-row" data-staff-id="{{ $row->staff_id }}" data-id="{{ $row->id }}">
                                        <td>
                                            <a href="{{ route('admin.user.show', $row->id) }}">
                                                #{{ $row->staff_id }}
                                            </a>
                                        </td>
                                        <td>{{ $row->first_name }} {{ $row->last_name }}</td>
                                        @php
                                            $current_attendance = null;
                                            if(isset($attendances)) {
                                                foreach($attendances as $attendance) {
                                                    if($attendance->user_id == $row->id) {
                                                        $current_attendance = $attendance;
                                                        break;
                                                    }
                                                }
                                            }
                                        @endphp
                                        <td>
                                            @if($current_attendance && $current_attendance->start_time)
                                                {{ date("h:i A", strtotime($current_attendance->start_time)) }}
                                            @else
                                                -
                                            @endif
                                        </td>
                                        <td>
                                            @if($current_attendance && $current_attendance->end_time)
                                                {{ date("h:i A", strtotime($current_attendance->end_time)) }}
                                            @else
                                                -
                                            @endif
                                        </td>
                                        <td>
                                            @if($current_attendance && $current_attendance->start_time && $current_attendance->end_time)
                                                {{ \Carbon\Carbon::parse($current_attendance->start_time)->diff(\Carbon\Carbon::parse($current_attendance->end_time))->format('%H:%I') }}
                                            @else
                                                -
                                            @endif
                                        </td>
                                        <td>
                                            <div class="form-group d-inline">
                                                <div class="radio radio-primary d-inline">
                                                    <input class="c-present" type="radio" data_id="{{ $row->id }}"name="attendances-{{ $key }}" id="attendance-p-{{ $key }}" value="1" 

                                                    @if(isset($attendances))
                                                    @foreach($attendances as $attendance)
                                                        @if($attendance->user_id == $row->id && $attendance->attendance == 1)
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
                                                        @if($attendance->user_id == $row->id && $attendance->attendance == 2)
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
                                                        @if($attendance->user_id == $row->id && $attendance->attendance == 3)
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
                                                        @if($attendance->user_id == $row->id && $attendance->attendance == 4)
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
                                            <input type="text" class="form-control" name="notes[]" style="width: 100px" id="note-{{ $key }}" 
                                                @if(isset($attendances))
                                                @foreach($attendances as $attendance)
                                                    @if($attendance->user_id == $row->id)
                                                        value="{{ $attendance->note }}"
                                                    @endif
                                                @endforeach
                                                @endif
                                                placeholder="{{ __('field_note') }}">
                                        </td>
                                        <td>{{ $row->department->title ?? '' }}</td>
                                        <td>{{ $row->designation->title ?? '' }}</td>
                                        <td>{{ $row->workShift->title ?? '' }}</td>
                                    </tr>
                                  @endforeach
                                </tbody>

                                <caption>{{ date("d F Y", strtotime($selected_date)) ?? '' }}</caption>
                            </table>
                        </div>
                        <!-- [ Data table ] end -->
                    </div>
                    @if(count($rows) > 0)
                    <div class="card-footer">
                        <button type="submit" class="btn btn-success update"><i class="fas fa-check"></i> {{ __('btn_update') }}</button>
                    </div>
                    @endif
                    </form>
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
    $(document).ready(function() {
        $(".update").on('click',function(e){
            var attendances = [];
            $.each($("input[data_id]:checked"), function(){
                attendances.push($(this).val());
            });

            $(".attendances").val( attendances.join(',') );
        });

        // Scanner Logic
        let html5QrcodeScanner = null;
        
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
                    gainNode.gain.setValueAtTime(0, start);
                    gainNode.gain.linearRampToValueAtTime(0.3, start + 0.05);
                    gainNode.gain.exponentialRampToValueAtTime(0.01, start + duration);
                    oscillator.start(start);
                    oscillator.stop(start + duration);
                };
                const now = audioCtx.currentTime;
                playTone(523.25, now, 0.15); 
                playTone(659.25, now + 0.1, 0.3); 
            } catch (e) {
                console.error("Audio play failed", e);
            }
        }

        $('#start-scan').on('click', function() {
            if(typeof Html5QrcodeScanner === 'undefined') {
                alert('Scanner library not loaded. Please check your internet connection or reload the page.');
                return;
            }

            $('#scanner-container').show();

            if (html5QrcodeScanner === null) {
                html5QrcodeScanner = new Html5QrcodeScanner(
                    "reader", 
                    { 
                        fps: 10, 
                        qrbox: { width: 250, height: 250 },
                        rememberLastUsedCamera: true,
                        aspectRatio: 1.0
                    },
                    false
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
            console.log(`Scan result:`, decodedText);
            
            if (typeof decodedText !== 'string') return;

            // Extract Staff ID from URL if it's a URL (e.g. /verify-staff/STAFF001)
            let staffId = decodedText;
            if(decodedText.includes('/verify-staff/')) {
                const parts = decodedText.split('/verify-staff/');
                if(parts.length > 1) {
                    staffId = parts[1];
                }
            }
            
            staffId = staffId.trim();

            // Find the row
            let $row = $(`.staff-row[data-staff-id="${staffId}"]`);
            
            // If not found by Staff ID, try finding by Database ID (which is what the QR code contains)
            if($row.length === 0) {
                $row = $(`.staff-row[data-id="${staffId}"]`);
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

            } else {
                console.log('Staff not found in this list: ' + staffId);
                alert('Staff not found in this list: ' + staffId);
            }
        }
    });


    // checkbox all-check-button selector
    $(".all_present").on('click',function(e){
        if($(this).is(":checked")){
            // check all checkbox
            $(".c-present").prop('checked', true);
        }
        else if($(this).is(":not(:checked)")){
            // uncheck all checkbox
            $(".c-present").prop('checked', false);
        }
    });
    $(".all_absent").on('click',function(e){
        if($(this).is(":checked")){
            // check all checkbox
            $(".c-absent").prop('checked', true);
        }
        else if($(this).is(":not(:checked)")){
            // uncheck all checkbox
            $(".c-absent").prop('checked', false);
        }
    });
    $(".all_leave").on('click',function(e){
        if($(this).is(":checked")){
            // check all checkbox
            $(".c-leave").prop('checked', true);
        }
        else if($(this).is(":not(:checked)")){
            // uncheck all checkbox
            $(".c-leave").prop('checked', false);
        }
    });
    $(".all_holiday").on('click',function(e){
        if($(this).is(":checked")){
            // check all checkbox
            $(".c-holiday").prop('checked', true);
        }
        else if($(this).is(":not(:checked)")){
            // uncheck all checkbox
            $(".c-holiday").prop('checked', false);
        }
    });
</script>
@endsection