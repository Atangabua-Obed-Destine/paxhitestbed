@extends('admin.layouts.master')
@section('title', $title)
@section('content')

<div class="main-body">
    <div class="page-wrapper">
        <div class="row">
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header">
                        <h5>{{ $title }}</h5>
                    </div>
                    <div class="card-block">
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th>{{ __('field_date') }}</th>
                                        <th>{{ __('field_start_time') }}</th>
                                        <th>{{ __('field_end_time') }}</th>
                                        <th>Duration</th>
                                        <th>{{ __('field_status') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($attendances as $attendance)
                                    @php
                                        $duration = '-';
                                        $status = 'Absent';
                                        $class = 'danger';
                                        
                                        if($attendance->attendance == 1) {
                                            $status = 'Present';
                                            $class = 'success';
                                        } elseif($attendance->attendance == 3) {
                                            $status = 'Leave';
                                            $class = 'info';
                                        } elseif($attendance->attendance == 4) {
                                            $status = 'Holiday';
                                            $class = 'warning';
                                        }

                                        if($attendance->start_time && $attendance->end_time) {
                                            $start = \Carbon\Carbon::parse($attendance->start_time);
                                            $end = \Carbon\Carbon::parse($attendance->end_time);
                                            $diff = $start->diff($end);
                                            $duration = $diff->format('%H:%I');
                                        }
                                    @endphp
                                    <tr>
                                        <td>{{ date("d M Y", strtotime($attendance->date)) }}</td>
                                        <td>{{ $attendance->start_time ? date("h:i A", strtotime($attendance->start_time)) : '-' }}</td>
                                        <td>{{ $attendance->end_time ? date("h:i A", strtotime($attendance->end_time)) : '-' }}</td>
                                        <td>{{ $duration }}</td>
                                        <td><span class="badge badge-{{ $class }}">{{ $status }}</span></td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        {{ $attendances->links() }}
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@endsection
