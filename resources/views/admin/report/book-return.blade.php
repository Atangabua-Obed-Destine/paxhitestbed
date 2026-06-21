@extends('admin.layouts.master')
@section('title', $title)
@section('content')

<!-- Start Content-->
<div class="main-body">
    <div class="page-wrapper">
        <!-- [ Main Content ] start -->
        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header">
                        <h5>{{ $title }}</h5>
                    </div>

                    <div class="card-block">
                        <!-- [ Data table ] start -->
                        <div class="table-responsive">
                            <table id="report-table" class="display table nowrap table-striped table-hover" style="width:100%">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>{{ __('field_library_id') }}</th>
                                        <th>{{ __('field_member') }}</th>
                                        <th>{{ __('field_isbn') }}</th>
                                        <th>{{ __('field_phone') }}</th>
                                        <th>{{ __('field_issue_date') }}</th>
                                        <th>{{ __('field_due_return_date') }}</th>
                                        <th>{{ __('field_recorded_by') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                @foreach($rows as $key => $row)
                                    <tr>
                                        <td>{{ $key + 1 }}</td>
                                        <td>#{{ $row->member->library_id ?? '' }}</td>
                                        <td>
                                            @if($row->member->memberable_type == 'App\Models\Student')
                                            {{ __('field_student') }}
                                            @elseif($row->member->memberable_type == 'App\User')
                                            {{ __('field_staff') }}
                                            @elseif($row->member->memberable_type == 'App\Models\OutsideUser')
                                            {{ __('field_outsider') }}
                                            @endif
                                        </td>
                                        <td>{{ $row->book->isbn ?? '' }}</td>
                                        <td>{{ $row->member->memberable->phone ?? '' }}</td>
                                        <td>
                                            @if(isset($setting->date_format))
                                                {{ date($setting->date_format, strtotime($row->issue_date)) }}
                                            @else
                                                {{ date("Y-m-d", strtotime($row->issue_date)) }}
                                            @endif
                                        </td>
                                        <td>
                                            @if(isset($setting->date_format))
                                                {{ date($setting->date_format, strtotime($row->due_date)) }}
                                            @else
                                                {{ date("Y-m-d", strtotime($row->due_date)) }}
                                            @endif
                                        </td>
                                        <td>#{{ $row->issuedBy->staff_id ?? '' }}</td>
                                    </tr>
                                @endforeach
                                </tbody>
                            </table>
                        </div>
                        <!-- [ Data table ] end -->
                    </div>

                </div>
            </div>
        </div>
        <!-- [ Main Content ] end -->
    </div>
</div>
<!-- End Content-->

@endsection

@section('page_js')
    @include('admin.report.script')
@endsection