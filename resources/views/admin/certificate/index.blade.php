@extends('admin.layouts.master')
@section('title', $title)

@section('page_css')
    <link href="https://fonts.googleapis.com/css2?family=Italianno&display=swap" rel="stylesheet"> 

    <link rel="stylesheet" type="text/css" href="{{ asset('dashboard/css/prints/certificate.css') }}" media="screen">
@endsection

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
                                    <label for="template">{{ __('field_certificate') }} <span>*</span></label>
                                    <select class="form-control" name="template" id="template" required>
                                        <option value="">{{ __('select') }}</option>
                                        @foreach( $templates as $template )
                                        <option value="{{ $template->id }}" @if( $selected_template == $template->id) selected @endif>{{ $template->title }}</option>
                                        @endforeach
                                    </select>

                                    <div class="invalid-feedback">
                                      {{ __('required_field') }} {{ __('field_certificate') }}
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
                    <div class="card-header">
                        @isset($rows)
                        @can($access.'-print')
                        <form class="needs-validation d-inline" novalidate method="get" action="{{ route($route.'.multiprint') }}" target="_blank">
                            <input type="hidden" name="students" class="students" value="">
                            <input type="hidden" name="template" value="{{ $selected_template }}">
                            <button type="submit" class="btn btn-sm btn-dark print-btn"><i class="fas fa-print"></i> {{ __('btn_print') }} {{ __('field_selected') }}</button>
                        </form>
                        @endcan
                        @endisset
                    </div>
                    
                    <div class="card-block">
                        <!-- [ Data table ] start -->
                        <div class="table-responsive">
                            <table id="basic-table" class="display table nowrap table-striped table-hover" style="width:100%">
                                <thead>
                                    <tr>
                                        <th>
                                            <div class="checkbox checkbox-success d-inline">
                                                <input type="checkbox" id="checkbox" class="all_select">
                                                <label for="checkbox" class="cr" style="margin-bottom: 0px;"></label>
                                            </div>
                                        </th>
                                        <th>{{ __('field_matricule') }}</th>
                                        <th>{{ __('field_name') }}</th>
                                        <th>{{ __('field_batch') }}</th>
                                        <th>{{ __('field_program') }}</th>
                                        <th>{{ __('field_admission') }}</th>
                                        <th>{{ __('field_last_session') }}</th>
                                        <th>{{ __('field_status') }}</th>
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
                                            @isset($row->student_id)
                                            <a href="{{ route('admin.student.show', $row->id) }}">
                                            #{{ $row->lastEnroll->matricule ?? $row->student_id }}
                                            </a>
                                            @endisset
                                        </td>
                                        <td>{{ $row->first_name }} {{ $row->last_name }}</td>
                                        <td>{{ $row->batch->title ?? '' }}</td>
                                        <td>{{ $row->program->shortcode ?? '' }}</td>
                                        <td>{{ $row->firstEnroll->session->title ?? '' }}</td>
                                        <td>{{ $row->lastEnroll->session->title ?? '' }}</td>
                                        
                                        @php
                                            $certificate_generate = 0;
                                        @endphp
                                        @if(isset($certificates))
                                        @foreach( $certificates as $certificate)
                                            @if($certificate->student_id == $row->id)
                                            @php
                                                $certificate_generate = 1;
                                            @endphp
                                            @endif
                                        @endforeach
                                        @endif
                                        <td>
                                            @if( $certificate_generate == 1 )
                                            <span class="badge badge-pill badge-primary">{{ __('status_generated') }}</span>
                                            @else
                                            <span class="badge badge-pill badge-danger">{{ __('status_not_generated') }}</span>
                                            @endif
                                        </td>
                                        <td>
                                            @if( $certificate_generate == 0 )
                                            @can($access.'-create')
                                            <button type="button" class="btn btn-icon btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addModal-{{ $row->id }}">
                                                <i class="fas fa-plus"></i>
                                            </button>
                                            <!-- Include Show modal -->
                                            @include($view.'.create')
                                            @endcan

                                            @else

                                            @foreach( $certificates as $certificate)
                                            @if($certificate->student_id == $row->id)

                                            <button type="button" class="btn btn-icon btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#showModal-{{ $row->id }}">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            <!-- Include Show modal -->
                                            @include($view.'.show')

                                            @can($access.'-print')
                                            @if(isset($certificate_template))
                                            <a href="#" class="btn btn-icon btn-dark btn-sm" onclick="PopupWin('{{ route($route.'.print', ['id' => $certificate->id]) }}', '{{ $title }}', 1000, 600);">
                                                <i class="fas fa-print"></i>
                                            </a>
                                            @endif
                                            @endcan

                                            @can($access.'-download')
                                            @if(isset($certificate_template))
                                            <a href="{{ route($route.'.download', ['id' => $certificate->id]) }}" target="_blank" class="btn btn-icon btn-dark btn-sm">
                                                <i class="fas fa-download"></i>
                                            </a>
                                            @endif
                                            @endcan

                                            @can($access.'-edit')
                                            <button type="button" class="btn btn-icon btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#editModal-{{ $row->id }}">
                                                <i class="far fa-edit"></i>
                                            </button>
                                            <!-- Include Edit modal -->
                                            @include($view.'.edit')
                                            @endcan

                                            @endif
                                            @endforeach
                                            
                                            @endif
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

@section('page_js')
<script type="text/javascript">
    "use strict";
    $(document).ready(function() {
        $(".print-btn").on('click',function(e){

            var numberOfChecked = $("input[data_id]:checked").length;
            if(numberOfChecked <= 0){
                e.preventDefault();
                alert("{{ __('select') }} {{ __('field_student') }}");
            }

            var students = [];
            $.each($("input[data_id]:checked"), function(){
                students.push($(this).val());
            });

            $(".students").val( students.join(',') );
        });
    });

    // checkbox all-check-button selector
    $(".all_select").on('click',function(e){
        if($(this).is(":checked")){
            // check all checkbox
            $("input:checkbox").prop('checked', true);
        }
        else if($(this).is(":not(:checked)")){
            // uncheck all checkbox
            $("input:checkbox").prop('checked', false);
        }
    });
</script>
@endsection