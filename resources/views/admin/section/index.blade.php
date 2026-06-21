@extends('admin.layouts.master')
@section('title', $title)
@section('content')

<!-- Start Content-->
<div class="main-body">
    <div class="page-wrapper">
        <!-- [ Main Content ] start -->
        <div class="row">
            @can($access.'-create')
            <div class="col-md-4">
                <form class="needs-validation" novalidate action="{{ route($route.'.store') }}" method="post" enctype="multipart/form-data">
                @csrf
                    <div class="card">
                        <div class="card-header">
                            <h5>{{ __('btn_create') }} {{ $title }}</h5>
                        </div>
                        <div class="card-block">
                            <!-- Form Start -->
                            <div class="form-group">
                                <label for="title" class="form-label">{{ __('field_title') }} <span>*</span></label>
                                <input type="text" class="form-control" name="title" id="title" value="{{ old('title') }}" required>

                                <div class="invalid-feedback">
                                  {{ __('required_field') }} {{ __('field_title') }}
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="program">{{ __('field_assign') }} {{ __('field_program') }} <span>*</span></label>

                                <div class="checkbox">
                                    <input type="checkbox" name="all_check" id="all_check" class="all_check" checked>
                                    <label for="all_check" class="cr">{{ __('all') }}</label>
                                </div>

                                @php $items = 0; @endphp
                                @foreach($programs as $key => $program)
                                <span class="badge badge-primary">{{ $key + 1 }}. {{ $program->title }}</span>
                                <hr/><br/>
                                @foreach($program->semesters->where('status', 1)->sortBy('title') as $semester)
                                <input type="text" hidden name="programs[]" value="{{ $program->id }}">
                                <input type="text" hidden name="semesters[]" value="{{ $semester->id }}">
                                <div class="checkbox d-inline">
                                    <input type="checkbox" class="semester" name="items[]" id="semester-{{ $key }}-{{ $semester->id }}" value="{{ $items = $items + 1 }}" checked>
                                    <label for="semester-{{ $key }}-{{ $semester->id }}" class="cr">{{ $semester->title }}</label>
                                </div>
                                @endforeach
                                <br/><br/>
                                @endforeach

                                <div class="invalid-feedback">
                                {{ __('required_field') }} {{ __('field_program') }}
                                </div>
                            </div>
                            <!-- Form End -->
                        </div>
                        <div class="card-footer">
                            <button type="submit" class="btn btn-success"><i class="fas fa-check"></i> {{ __('btn_save') }}</button>
                        </div>
                    </div>
                </form>
            </div>
            @endcan
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">
                        <h5>{{ $title }} {{ __('list') }}</h5>
                    </div>
                    <div class="card-block">
                        <!-- [ Data table ] start -->
                        <div class="table-responsive">
                            <table id="basic-table" class="display table nowrap table-striped table-hover" style="width:100%">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>{{ __('field_title') }}</th>
                                        <th>{{ __('field_program') }} > {{ __('field_semester') }}</th>
                                        <th>{{ __('field_status') }}</th>
                                        <th>{{ __('field_action') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                  @foreach( $rows as $key => $row )
                                    <tr>
                                        <td>{{ $key + 1 }}</td>
                                        <td>{{ $row->title }}</td>
                                        <td>
                                            @php
                                                $totalAssociations = $row->semesterPrograms->count();
                                                $displayLimit = 5;
                                            @endphp
                                            
                                            @if($totalAssociations > 0)
                                                @foreach($row->semesterPrograms->take($displayLimit) as $semesterProgram)
                                                <span class="badge badge-primary">{{ $semesterProgram->program->title ?? '' }} > {{ $semesterProgram->semester->title ?? '' }}</span>
                                                @if(!$loop->last)<hr/>@endif
                                                @endforeach
                                                
                                                @if($totalAssociations > $displayLimit)
                                                <br/><br/>
                                                <span class="badge badge-info">
                                                    <i class="fas fa-plus-circle"></i> {{ $totalAssociations - $displayLimit }} more...
                                                </span>
                                                @endif
                                            @else
                                                <span class="text-muted">{{ __('none') }}</span>
                                            @endif
                                        </td>
                                        <td>
                                            @if( $row->status == 1 )
                                            <span class="badge badge-pill badge-success">{{ __('status_active') }}</span>
                                            @else
                                            <span class="badge badge-pill badge-danger">{{ __('status_inactive') }}</span>
                                            @endif
                                        </td>
                                        <td>
                                            @can($access.'-edit')
                                            <button type="button" class="btn btn-icon btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#editModal-{{ $row->id }}">
                                                <i class="far fa-edit"></i>
                                            </button>
                                            <!-- Include Edit modal -->
                                            @include($view.'.edit')
                                            @endcan

                                            @can($access.'-delete')
                                            <button type="button" class="btn btn-icon btn-danger btn-sm" data-bs-toggle="modal" data-bs-target="#deleteModal-{{ $row->id }}">
                                                <i class="fas fa-trash-alt"></i>
                                            </button>
                                            <!-- Include Delete modal -->
                                            @include('admin.layouts.inc.delete')
                                            @endcan
                                        </td>
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

@section('scripts')
<script type="text/javascript">
    jQuery(document).ready(function($) {
        "use strict";
        
        // checkbox all-check-button selector for create form
        $(".all_check").on('click',function(e){
            if($(this).is(":checked")){
                // check all checkbox
                $(".semester").prop('checked', true);
            }
            else if($(this).is(":not(:checked)")){
                // uncheck all checkbox
                $(".semester").prop('checked', false);
            }
        });

        // checkbox all-check-button selector for edit modals
        $(document).on('click', '.all_check_edit', function(e){
            var sectionId = $(this).data('section-id');
            if($(this).is(":checked")){
                // check all checkboxes for this section
                $(".semester-checkbox-" + sectionId).prop('checked', true);
            }
            else {
                // uncheck all checkboxes for this section
                $(".semester-checkbox-" + sectionId).prop('checked', false);
            }
            updateSelectedCount(sectionId);
        });

        // Update selected count when checkboxes change
        $(document).on('change', '[class^="semester-checkbox-"]', function() {
            var sectionId = $(this).data('section');
            updateSelectedCount(sectionId);
        });

        // Function to update selected count display
        function updateSelectedCount(sectionId) {
            var count = $(".semester-checkbox-" + sectionId + ":checked").length;
            $("#selected_count_" + sectionId).text(count);
        }

        // Before form submission, collect checked values into JSON field
        $(document).on('submit', 'form[action*="section"]', function(e) {
            var form = $(this);
            var modalId = form.closest('.modal').attr('id');
            
            if (modalId && modalId.indexOf('editModal-') === 0) {
                var sectionId = modalId.replace('editModal-', '');
                var selectedValues = [];
                
                $(".semester-checkbox-" + sectionId + ":checked").each(function() {
                    selectedValues.push($(this).val());
                });
                
                console.log('Section ID:', sectionId);
                console.log('Total checkboxes found:', $(".semester-checkbox-" + sectionId).length);
                console.log('Total checked:', selectedValues.length);
                console.log('JSON length:', JSON.stringify(selectedValues).length);
                
                // Store as JSON in hidden field
                $("#program_semesters_json_" + sectionId).val(JSON.stringify(selectedValues));
                
                // Alert user with count
                if (selectedValues.length > 0) {
                    console.log('Submitting ' + selectedValues.length + ' program-semester combinations');
                }
            }
        });

        // Initialize selected counts on page load
        $('[id^="editModal-"]').each(function() {
            var modalId = $(this).attr('id');
            var sectionId = modalId.replace('editModal-', '');
            updateSelectedCount(sectionId);
        });
    });
</script>
@endsection