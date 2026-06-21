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
                        <!-- Statistics Cards -->
                        <div class="row mb-4">
                            <div class="col-md-3 col-sm-6">
                                <div class="card text-white bg-primary">
                                    <div class="card-body">
                                        <div class="text-center">
                                            <h2 class="mb-0">{{ $total_catholic }}</h2>
                                            <p class="mb-0"><i class="fas fa-users"></i> Total Catholic Students</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3 col-sm-6">
                                <div class="card text-white bg-success">
                                    <div class="card-body">
                                        <div class="text-center">
                                            <h2 class="mb-0">{{ $baptised_count }}</h2>
                                            <p class="mb-0"><i class="fas fa-check-circle"></i> Baptised</p>
                                            <small>({{ $baptised_percentage }}%)</small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3 col-sm-6">
                                <div class="card text-white bg-info">
                                    <div class="card-body">
                                        <div class="text-center">
                                            <h2 class="mb-0">{{ $confirmed_count }}</h2>
                                            <p class="mb-0"><i class="fas fa-certificate"></i> Confirmed</p>
                                            <small>({{ $confirmed_percentage }}%)</small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3 col-sm-6">
                                <div class="card text-white bg-warning">
                                    <div class="card-body">
                                        <div class="text-center">
                                            <h2 class="mb-0">{{ $communion_count }}</h2>
                                            <p class="mb-0"><i class="fas fa-cross"></i> First Communion</p>
                                            <small>({{ $communion_percentage }}%)</small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Filters -->
                        <div class="row mb-3">
                            <div class="col-md-2">
                                <label>{{ __('field_faculty') }}</label>
                                <select class="form-control" id="filter_faculty">
                                    <option value="">{{ __('all') }}</option>
                                    @foreach($faculties as $faculty)
                                    <option value="{{ $faculty->id }}">{{ $faculty->title }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label>{{ __('field_program') }}</label>
                                <select class="form-control" id="filter_program">
                                    <option value="">{{ __('all') }}</option>
                                    @foreach($programs as $program)
                                    <option value="{{ $program->id }}">{{ $program->title }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label>{{ __('field_session') }}</label>
                                <select class="form-control" id="filter_session">
                                    <option value="">{{ __('all') }}</option>
                                    @foreach($sessions as $session)
                                    <option value="{{ $session->id }}">{{ $session->title }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label>{{ __('Baptised') }}</label>
                                <select class="form-control" id="filter_baptised">
                                    <option value="">{{ __('all') }}</option>
                                    <option value="1">Yes</option>
                                    <option value="0">No</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label>{{ __('Confirmed') }}</label>
                                <select class="form-control" id="filter_confirmed">
                                    <option value="">{{ __('all') }}</option>
                                    <option value="1">Yes</option>
                                    <option value="0">No</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label>{{ __('First Communion') }}</label>
                                <select class="form-control" id="filter_communion">
                                    <option value="">{{ __('all') }}</option>
                                    <option value="1">Yes</option>
                                    <option value="0">No</option>
                                </select>
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-12">
                                <div>
                                    @can($access.'-view')
                                    <a href="{{ route($route.'.export') }}" class="btn btn-success btn-sm" id="export-btn">
                                        <i class="fas fa-file-excel"></i> {{ __('btn_export') }}
                                    </a>
                                    <a href="{{ route($route.'.print') }}" class="btn btn-info btn-sm" target="_blank" id="print-btn">
                                        <i class="fas fa-print"></i> {{ __('btn_print') }}
                                    </a>
                                    @endcan
                                </div>
                            </div>
                        </div>

                        <!-- DataTable -->
                        <div class="table-responsive">
                            <table id="catholic-students-table" class="display table nowrap table-striped table-hover" style="width:100%">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>{{ __('field_matricule') }}</th>
                                        <th>{{ __('field_name') }}</th>
                                        <th>{{ __('field_program') }}</th>
                                        <th>{{ __('field_session') }}</th>
                                        <th>{{ __('Baptised') }}</th>
                                        <th>{{ __('Confirmed') }}</th>
                                        <th>{{ __('First Communion') }}</th>
                                        <th>{{ __('field_action') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($students as $index => $row)
                                    <tr data-faculty="{{ $row->program && $row->program->faculty_id ? $row->program->faculty_id : '' }}" 
                                        data-program="{{ $row->program_id }}" 
                                        data-session="{{ $row->session_id }}"
                                        data-baptised="{{ $row->student && $row->student->is_catholic_baptised ? '1' : '0' }}"
                                        data-confirmed="{{ $row->student && $row->student->is_confirmed ? '1' : '0' }}"
                                        data-communion="{{ $row->student && $row->student->has_first_communion ? '1' : '0' }}">
                                        <td>{{ $index + 1 }}</td>
                                        <td>{{ $row->matricule ?? ($row->student ? $row->student->student_id : 'N/A') }}</td>
                                        <td>{{ $row->student ? $row->student->first_name . ' ' . $row->student->last_name : 'N/A' }}</td>
                                        <td>{{ $row->program ? $row->program->title : 'N/A' }}</td>
                                        <td>{{ $row->session ? $row->session->title : 'N/A' }}</td>
                                        <td>
                                            @if($row->student && $row->student->is_catholic_baptised)
                                            <span class="badge badge-success"><i class="fas fa-check"></i> Yes</span>
                                            @else
                                            <span class="badge badge-secondary"><i class="fas fa-times"></i> No</span>
                                            @endif
                                        </td>
                                        <td>
                                            @if($row->student && $row->student->is_confirmed)
                                            <span class="badge badge-success"><i class="fas fa-check"></i> Yes</span>
                                            @else
                                            <span class="badge badge-secondary"><i class="fas fa-times"></i> No</span>
                                            @endif
                                        </td>
                                        <td>
                                            @if($row->student && $row->student->has_first_communion)
                                            <span class="badge badge-success"><i class="fas fa-check"></i> Yes</span>
                                            @else
                                            <span class="badge badge-secondary"><i class="fas fa-times"></i> No</span>
                                            @endif
                                        </td>
                                        <td>
                                            @can($access.'-edit')
                                            <button type="button" class="btn btn-sm btn-primary edit-btn" 
                                                    data-toggle="modal" 
                                                    data-target="#editModal-{{ $row->id }}" 
                                                    data-student-id="{{ $row->id }}"
                                                    title="{{ __('btn_edit') }}">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            @endcan
                                        </td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <!-- [ Main Content ] end -->
    </div>
</div>
<!-- End Content-->

<!-- Edit Modals -->
@can($access.'-edit')
@foreach($students as $row)
<div class="modal fade" id="editModal-{{ $row->id }}" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <form class="update-sacrament-form" data-id="{{ $row->id }}">
                <div class="modal-header">
                    <h5 class="modal-title">{{ __('btn_edit') }} - {{ $row->student ? $row->student->first_name . ' ' . $row->student->last_name : '' }}</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <div class="custom-control custom-checkbox">
                            <input type="checkbox" class="custom-control-input" id="baptised-{{ $row->id }}" name="is_catholic_baptised" value="1" {{ $row->student && $row->student->is_catholic_baptised ? 'checked' : '' }}>
                            <label class="custom-control-label" for="baptised-{{ $row->id }}">{{ __('I am a baptised Catholic with proof of baptism') }}</label>
                        </div>
                    </div>
                    <div class="form-group">
                        <div class="custom-control custom-checkbox">
                            <input type="checkbox" class="custom-control-input" id="confirmed-{{ $row->id }}" name="is_confirmed" value="1" {{ $row->student && $row->student->is_confirmed ? 'checked' : '' }}>
                            <label class="custom-control-label" for="confirmed-{{ $row->id }}">{{ __('I have received Confirmation') }}</label>
                        </div>
                    </div>
                    <div class="form-group">
                        <div class="custom-control custom-checkbox">
                            <input type="checkbox" class="custom-control-input" id="communion-{{ $row->id }}" name="has_first_communion" value="1" {{ $row->student && $row->student->has_first_communion ? 'checked' : '' }}>
                            <label class="custom-control-label" for="communion-{{ $row->id }}">{{ __('I have received First Holy Communion') }}</label>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">{{ __('btn_close') }}</button>
                    <button type="submit" class="btn btn-primary">{{ __('btn_update') }}</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endforeach
@endcan
@endsection

@section('page_js')
<script>
$(document).ready(function() {
    "use strict";

    // Initialize DataTable with client-side processing
    var table = $('#catholic-students-table').DataTable({
        processing: false,
        order: [[1, 'desc']], // Order by student ID
        pageLength: 25,
        dom: 'Blfrtip',
        buttons: [],
        language: {
            searchPlaceholder: "{{ __('btn_search') }}...",
            sSearch: "",
            lengthMenu: "_MENU_ {{ __('field_items_per_page') }}"
        }
    });

    // Debug: Check if modals exist
    console.log('Number of edit modals found:', $('[id^="editModal-"]').length);
    console.log('Number of edit buttons found:', $('.edit-btn').length);

    // Custom filtering function for client-side DataTables
    $.fn.dataTable.ext.search.push(
        function(settings, data, dataIndex) {
            if (settings.nTable.id !== 'catholic-students-table') {
                return true;
            }

            var filterFaculty = $('#filter_faculty').val();
            var filterProgram = $('#filter_program').val();
            var filterSession = $('#filter_session').val();
            var filterBaptised = $('#filter_baptised').val();
            var filterConfirmed = $('#filter_confirmed').val();
            var filterCommunion = $('#filter_communion').val();

            var row = table.row(dataIndex).node();
            var rowFaculty = $(row).attr('data-faculty');
            var rowProgram = $(row).attr('data-program');
            var rowSession = $(row).attr('data-session');
            var rowBaptised = $(row).attr('data-baptised');
            var rowConfirmed = $(row).attr('data-confirmed');
            var rowCommunion = $(row).attr('data-communion');

            // Check faculty filter
            if (filterFaculty && filterFaculty !== rowFaculty) {
                return false;
            }

            // Check program filter
            if (filterProgram && filterProgram !== rowProgram) {
                return false;
            }

            // Check session filter
            if (filterSession && filterSession !== rowSession) {
                return false;
            }

            // Check baptised filter
            if (filterBaptised !== '' && filterBaptised !== rowBaptised) {
                return false;
            }

            // Check confirmed filter
            if (filterConfirmed !== '' && filterConfirmed !== rowConfirmed) {
                return false;
            }

            // Check communion filter
            if (filterCommunion !== '' && filterCommunion !== rowCommunion) {
                return false;
            }

            return true;
        }
    );

    // Filter changes
    $('#filter_faculty, #filter_program, #filter_session, #filter_baptised, #filter_confirmed, #filter_communion').change(function() {
        table.draw();
        
        // Update export/print links with filters
        updateExportPrintLinks();
    });

    // Manual click handler for edit buttons (fallback if data-toggle doesn't work)
    $(document).on('click', '.edit-btn', function(e) {
        e.preventDefault();
        var studentId = $(this).data('student-id');
        var modalId = '#editModal-' + studentId;
        console.log('Opening modal:', modalId);
        $(modalId).modal('show');
    });

    // Update export and print links with current filters
    function updateExportPrintLinks() {
        var params = {
            filter_faculty: $('#filter_faculty').val(),
            filter_program: $('#filter_program').val(),
            filter_session: $('#filter_session').val(),
            filter_baptised: $('#filter_baptised').val(),
            filter_confirmed: $('#filter_confirmed').val(),
            filter_communion: $('#filter_communion').val()
        };
        
        var queryString = $.param(params);
        
        $('#export-btn').attr('href', "{{ route($route.'.export') }}?" + queryString);
        $('#print-btn').attr('href', "{{ route($route.'.print') }}?" + queryString);
    }

    // Handle form submission via AJAX
    $(document).on('submit', '.update-sacrament-form', function(e) {
        e.preventDefault();
        
        var form = $(this);
        var enrollId = form.data('id');
        var formData = {
            _token: "{{ csrf_token() }}",
            _method: 'PUT',
            is_catholic_baptised: form.find('input[name="is_catholic_baptised"]').is(':checked') ? 1 : 0,
            is_confirmed: form.find('input[name="is_confirmed"]').is(':checked') ? 1 : 0,
            has_first_communion: form.find('input[name="has_first_communion"]').is(':checked') ? 1 : 0
        };
        
        $.ajax({
            url: "{{ route($route.'.index') }}/" + enrollId,
            type: 'POST',
            data: formData,
            success: function(response) {
                $('#editModal-' + enrollId).modal('hide');
                
                // Show success message
                toastr.success(response.message || 'Updated successfully!');
                
                // Reload page to update statistics and table data
                setTimeout(function() {
                    location.reload();
                }, 1500);
            },
            error: function(xhr) {
                toastr.error('Error updating status. Please try again.');
            }
        });
    });
});
</script>
@endsection
