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
                        <form class="needs-validation" novalidate method="get" action="{{ route($route.'.teacher') }}">
                            <div class="row gx-2">
                                <div class="form-group col-md-3">
                                    <label for="teacher">{{ __('field_staff_id') }} <span>*</span></label>
                                    <select class="form-control select2" name="teacher" id="teacher" required>
                                        <option value="">{{ __('select') }}</option>
                                        @foreach($teachers as $teacher)
                                        <option value="{{ $teacher->id }}" @if($selected_staff == $teacher->id) selected @endif>{{ $teacher->staff_id }} - {{ $teacher->first_name }} {{ $teacher->last_name }}</option>
                                        @endforeach
                                    </select>

                                    <div class="invalid-feedback">
                                      {{ __('required_field') }} {{ __('field_staff_id') }}
                                    </div>
                                </div>
                                <div class="form-group col-md-3">
                                    <button type="submit" class="btn btn-info btn-filter"><i class="fas fa-search"></i> {{ __('btn_search') }}</button>
                                </div>
                            </div>
                        </form>
                    </div>


                    @if(isset($rows))
                    <div class="card-block">
                        <style>
                            .teacher-routine-table {
                                width: 100%;
                                border-collapse: separate;
                                border-spacing: 0;
                                table-layout: fixed;
                            }
                            .teacher-routine-table th {
                                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                                color: #fff;
                                font-weight: 600;
                                text-align: center;
                                padding: 12px 8px;
                                font-size: 13px;
                                border: 1px solid #5a67d8;
                                width: 14.28%;
                            }
                            .teacher-routine-table td {
                                vertical-align: top;
                                padding: 8px;
                                border: 1px solid #e2e8f0;
                                background-color: #f8fafc;
                                min-height: 120px;
                            }
                            .routine-slot {
                                background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
                                border-radius: 8px;
                                padding: 10px;
                                margin-bottom: 8px;
                                color: #fff;
                                box-shadow: 0 2px 4px rgba(0,0,0,0.1);
                                word-wrap: break-word;
                                overflow-wrap: break-word;
                            }
                            .routine-slot:last-child {
                                margin-bottom: 0;
                            }
                            .routine-slot .subject-code {
                                font-weight: 700;
                                font-size: 14px;
                                margin-bottom: 2px;
                                word-break: break-all;
                            }
                            .routine-slot .subject-title {
                                font-size: 11px;
                                opacity: 0.9;
                                margin-bottom: 6px;
                                padding-bottom: 6px;
                                border-bottom: 1px solid rgba(255,255,255,0.3);
                                word-break: break-word;
                                line-height: 1.3;
                            }
                            .routine-slot .time-info {
                                font-size: 12px;
                                margin-bottom: 4px;
                                display: flex;
                                align-items: center;
                                gap: 4px;
                            }
                            .routine-slot .time-info i {
                                font-size: 10px;
                                opacity: 0.8;
                                width: 12px;
                            }
                            .routine-slot .time-info .time-label {
                                font-size: 10px;
                                opacity: 0.8;
                                min-width: 32px;
                            }
                            .routine-slot .duration-info {
                                font-size: 12px;
                                margin: 6px 0;
                                display: flex;
                                align-items: center;
                                gap: 6px;
                            }
                            .routine-slot .duration-info i {
                                font-size: 10px;
                                opacity: 0.8;
                            }
                            .routine-slot .duration-badge {
                                background: rgba(255,255,255,0.25);
                                padding: 2px 8px;
                                border-radius: 10px;
                                font-size: 11px;
                                font-weight: 600;
                            }
                            .routine-slot .room-info {
                                font-size: 11px;
                                margin-top: 6px;
                                padding-top: 6px;
                                border-top: 1px solid rgba(255,255,255,0.3);
                                word-break: break-word;
                            }
                            .routine-slot .teacher-info {
                                font-size: 11px;
                                margin-top: 4px;
                                opacity: 0.9;
                                word-break: break-word;
                                white-space: normal;
                            }
                            .no-class {
                                color: #a0aec0;
                                font-size: 12px;
                                text-align: center;
                                padding: 20px 5px;
                                font-style: italic;
                            }
                            @media (max-width: 1200px) {
                                .teacher-routine-table {
                                    font-size: 11px;
                                }
                                .routine-slot {
                                    padding: 8px;
                                }
                                .routine-slot .subject-code {
                                    font-size: 12px;
                                }
                            }
                        </style>
                        
                        <!-- [ Data table ] start -->
                        <div class="table-responsive">
                            <table class="teacher-routine-table">
                                <thead>
                                    <tr>
                                        <th><i class="fas fa-calendar-day me-1"></i>{{ __('day_saturday') }}</th>
                                        <th><i class="fas fa-calendar-day me-1"></i>{{ __('day_sunday') }}</th>
                                        <th><i class="fas fa-calendar-day me-1"></i>{{ __('day_monday') }}</th>
                                        <th><i class="fas fa-calendar-day me-1"></i>{{ __('day_tuesday') }}</th>
                                        <th><i class="fas fa-calendar-day me-1"></i>{{ __('day_wednesday') }}</th>
                                        <th><i class="fas fa-calendar-day me-1"></i>{{ __('day_thursday') }}</th>
                                        <th><i class="fas fa-calendar-day me-1"></i>{{ __('day_friday') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @php
                                    $weekdays = array('1', '2', '3', '4', '5', '6', '7');
                                    @endphp
                                    <tr>
                                        @foreach($weekdays as $weekday)
                                        <td>
                                            @php $dayClasses = $rows->where('day', $weekday); @endphp
                                            @if($dayClasses->count() > 0)
                                                @foreach($dayClasses as $row)
                                                <div class="routine-slot">
                                                    <div class="subject-code">
                                                        <i class="fas fa-book me-1"></i>{{ $row->subject->code ?? 'N/A' }}
                                                    </div>
                                                    <div class="subject-title">
                                                        {{ $row->subject->title ?? '' }}
                                                    </div>
                                                    @php
                                                        $startTime = strtotime($row->start_time);
                                                        $endTime = strtotime($row->end_time);
                                                        $durationMinutes = ($endTime - $startTime) / 60;
                                                        $hours = floor($durationMinutes / 60);
                                                        $minutes = $durationMinutes % 60;
                                                        $durationText = $hours > 0 
                                                            ? ($minutes > 0 ? "{$hours}h {$minutes}m" : "{$hours}h")
                                                            : "{$minutes}m";
                                                    @endphp
                                                    <div class="time-info">
                                                        <i class="fas fa-play-circle"></i>
                                                        <span class="time-label">Start:</span>
                                                        @if(isset($setting->time_format))
                                                            {{ date($setting->time_format, $startTime) }}
                                                        @else
                                                            {{ date("h:i A", $startTime) }}
                                                        @endif
                                                    </div>
                                                    <div class="time-info">
                                                        <i class="fas fa-stop-circle"></i>
                                                        <span class="time-label">End:</span>
                                                        @if(isset($setting->time_format))
                                                            {{ date($setting->time_format, $endTime) }}
                                                        @else
                                                            {{ date("h:i A", $endTime) }}
                                                        @endif
                                                    </div>
                                                    <div class="duration-info">
                                                        <i class="fas fa-hourglass-half"></i>
                                                        <span class="duration-badge">{{ $durationText }}</span>
                                                    </div>
                                                    <div class="room-info">
                                                        <i class="fas fa-door-open me-1"></i>{{ $row->room->title ?? 'N/A' }}
                                                    </div>
                                                    <div class="teacher-info">
                                                        <i class="fas fa-user-tie me-1"></i>{{ $row->teacher->staff_id ?? '' }} - {{ $row->teacher->first_name ?? '' }} {{ $row->teacher->last_name ?? '' }}
                                                    </div>
                                                </div>
                                                @endforeach
                                            @else
                                                <div class="no-class">
                                                    <i class="fas fa-minus-circle"></i><br>
                                                    {{ __('No Class') }}
                                                </div>
                                            @endif
                                        </td>
                                        @endforeach
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <!-- [ Data table ] end -->
                    </div>
                    @endif

                </div>
            </div>
        </div>
        <!-- [ Main Content ] end -->
    </div>
</div>
<!-- End Content-->

@endsection