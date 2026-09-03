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
                        <h5 class="d-inline">{{ $title }}</h5>

                        {{-- Enrolment ids, not student ids: a student who has
                             moved programme has more than one transcript, and
                             the ticked row says which one is meant. --}}
                        @can($access.'-print')
                        @if(isset($rows) && count($rows) > 0)
                        <form class="needs-validation d-inline float-end" novalidate method="get"
                              action="{{ route($route.'.bulk') }}" target="_blank">
                            <input type="hidden" name="students" class="students" value="">
                            <button type="submit" class="btn btn-sm btn-dark bulk-print-btn">
                                <i class="fas fa-print"></i> {{ __('btn_print') }} {{ __('field_selected') }}
                            </button>
                        </form>
                        @endif
                        @endcan
                    </div>
                    <div class="card-block">
                        <form class="needs-validation" novalidate method="get" action="{{ route($route.'.index') }}">
                            <div class="row gx-2">
                                <div class="form-group col-md-2">
                                    <label for="batch">{{ __('field_batch') }}</label>
                                    <select class="form-control" name="batch" id="batch" required>
                                        <option value="0">{{ __('all') }}</option>
                                        @foreach( $batchs as $batch )
                                        <option value="{{ $batch->id }}" @if( $selected_batch == $batch->id) selected @endif>{{ $batch->title }}</option>
                                        @endforeach
                                    </select>

                                    <div class="invalid-feedback">
                                      {{ __('required_field') }} {{ __('field_batch') }}
                                    </div>
                                </div>
                                <div class="form-group col-md-2">
                                    <label for="program">{{ __('field_program') }}</label>
                                    <select class="form-control" name="program" id="program" required>
                                        <option value="0">{{ __('all') }}</option>
                                        @foreach( $programs as $program )
                                        <option value="{{ $program->id }}" @if( $selected_program == $program->id) selected @endif>{{ $program->title }}</option>
                                        @endforeach
                                    </select>

                                    <div class="invalid-feedback">
                                      {{ __('required_field') }} {{ __('field_program') }}
                                    </div>
                                </div>
                                <div class="form-group col-md-2">
                                    <label for="session">{{ __('field_last_session') }}</label>
                                    <select class="form-control" name="session" id="session">
                                        <option value="0">{{ __('all') }}</option>
                                        @foreach( $sessions as $session )
                                        <option value="{{ $session->id }}" @if($selected_session == $session->id) selected @endif>{{ $session->title }}</option>
                                        @endforeach
                                    </select>

                                    <div class="invalid-feedback">
                                        {{ __('required_field') }} {{ __('field_last_session') }}
                                    </div>
                                </div>
                                <div class="form-group col-md-2">
                                    <label for="student_id">{{ __('field_matricule') }}</label>
                                    <input type="text" class="form-control" name="student_id" id="student_id" value="{{ $selected_student_id }}">

                                    <div class="invalid-feedback">
                                      {{ __('required_field') }} {{ __('field_matricule') }}
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
                        <!-- [ Data table ] start -->
                        <div class="table-responsive">
                            <table id="basic-table" class="display table nowrap table-striped table-hover" style="width:100%">
                                <thead>
                                    <tr>
                                        <th style="width: 34px;">
                                            <div class="checkbox checkbox-success d-inline">
                                                <input type="checkbox" id="checkbox_all" class="all_select">
                                                <label for="checkbox_all" class="cr" style="margin-bottom: 0px;"></label>
                                            </div>
                                        </th>
                                        <th>{{ __('field_matricule') }}</th>
                                        <th>{{ __('field_name') }}</th>
                                        <th>{{ __('field_batch') }}</th>
                                        <th>{{ __('field_program') }}</th>
                                        <th>{{ __('field_admission') }}</th>
                                        <th>{{ __('field_last_session') }}</th>
                                        <th>{{ __('field_action') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                  @foreach( $rows as $key => $row )
                                    <tr>
                                        <td>
                                            <div class="checkbox checkbox-primary d-inline">
                                                <input type="checkbox" data_id="{{ $row->id }}" id="checkbox-{{ $row->id }}" value="{{ $row->id }}">
                                                <label for="checkbox-{{ $row->id }}" class="cr"></label>
                                            </div>
                                        </td>
                                        <td>
                                            @if($row->student)
                                            <a href="{{ route('admin.student.show', $row->student->id) }}?enrollment_id={{ $row->id }}">
                                            <strong style="color: #667eea;">#{{ $row->matricule }}</strong>
                                            @if($row->program)
                                                <span class="badge" style="background: {{ $row->program->academic_level == 'M' ? '#f5576c' : ($row->program->academic_level == 'D' ? '#00f2fe' : '#38f9d7') }}; font-size: 9px;">
                                                    {{ $row->program->academic_level == 'A' ? 'UG' : ($row->program->academic_level == 'M' ? 'MS' : 'PhD') }}
                                                </span>
                                            @endif
                                            </a>
                                            @endif
                                        </td>
                                        <td>{{ $row->student->first_name ?? '' }} {{ $row->student->last_name ?? '' }}</td>
                                        <td>{{ $row->student->batch->title ?? '' }}</td>
                                        <td>{{ $row->program->shortcode ?? '' }}</td>
                                        <td>{{ $row->session->title ?? '' }}</td>
                                        <td>{{ $row->session->title ?? '' }}</td>
                                        <td>
                                            <a href="{{ route($route.'.show', $row->student->id) }}?enrollment_id={{ $row->id }}" class="btn btn-icon btn-success btn-sm"><i class="fas fa-eye"></i></a>

                                            @can($access.'-print')
                                            @if(isset($print))
                                            <a href="#" class="btn btn-icon btn-dark btn-sm" onclick="PopupWin('{{ route($route.'.print', ['id' => $row->student->id]) }}?enrollment_id={{ $row->id }}', '{{ $title }}', 1000, 600);">
                                                <i class="fas fa-print"></i>
                                            </a>
                                            @endif
                                            @endcan
                                            
                                            @can($access.'-download')
                                            @if(isset($print))
                                            <a href="{{ route($route.'.download', ['id' => $row->student->id]) }}?enrollment_id={{ $row->id }}" target="_blank" class="btn btn-icon btn-dark btn-sm">
                                                <i class="fas fa-download"></i>
                                            </a>
                                            @endif
                                            @endcan
                                        </td>
                                    </tr>
                                  @endforeach
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

@push('scripts')
<script type="text/javascript">
    "use strict";

    $(document).ready(function () {

        // The ticked enrolment ids travel to the bulk view as one comma
        // separated field, the same way the ID card list does it.
        $(".bulk-print-btn").on('click', function (e) {
            var picked = $("input[data_id]:checked");

            if (picked.length === 0) {
                e.preventDefault();
                alert("{{ __('Select at least one student first.') }}");
                return;
            }

            var ids = [];
            picked.each(function () {
                ids.push($(this).val());
            });

            $(".students").val(ids.join(','));
        });

        // Scoped to the row checkboxes: a blanket "input:checkbox" would also
        // tick anything else on the page that happens to be a checkbox.
        $(".all_select").on('click', function () {
            $("input[data_id]").prop('checked', $(this).is(":checked"));
        });

        // Ticking every row by hand should leave the header box ticked too.
        $(document).on('change', "input[data_id]", function () {
            var total = $("input[data_id]").length;
            var checked = $("input[data_id]:checked").length;
            $(".all_select").prop('checked', total > 0 && total === checked);
        });
    });
</script>
@endpush