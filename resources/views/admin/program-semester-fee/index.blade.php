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
                                <div class="form-group col-md-3">
                                    <label for="faculty">{{ __('field_faculty') }}</label>
                                    <select class="form-control faculty" name="faculty" id="faculty">
                                        <option value="0">{{ __('all') }}</option>
                                        @foreach( $faculties as $faculty )
                                        <option value="{{ $faculty->id }}" @if( $selected_faculty == $faculty->id) selected @endif>{{ $faculty->title }}</option>
                                        @endforeach
                                    </select>

                                    <div class="invalid-feedback">
                                      {{ __('required_field') }} {{ __('field_faculty') }}
                                    </div>
                                </div>
                                <div class="form-group col-md-3">
                                    <label for="program">{{ __('field_program') }}</label>
                                    <select class="form-control program" name="program" id="program">
                                        <option value="0">{{ __('all') }}</option>
                                        @if(isset($programs))
                                        @foreach( $programs as $program )
                                        <option value="{{ $program->id }}" @if( $selected_program == $program->id) selected @endif>{{ $program->title }}</option>
                                        @endforeach
                                        @endif
                                    </select>

                                    <div class="invalid-feedback">
                                      {{ __('required_field') }} {{ __('field_program') }}
                                    </div>
                                </div>
                                <div class="form-group col-md-3">
                                    <label for="semester">{{ __('field_semester') }}</label>
                                    <select class="form-control semester" name="semester" id="semester">
                                        <option value="0">{{ __('all') }}</option>
                                        @if(isset($semesters))
                                        @foreach( $semesters as $semester )
                                        <option value="{{ $semester->id }}" @if( $selected_semester == $semester->id) selected @endif>{{ $semester->title }} @if($semester->year) (Year {{ $semester->year }}) @endif</option>
                                        @endforeach
                                        @endif
                                    </select>

                                    <div class="invalid-feedback">
                                      {{ __('required_field') }} {{ __('field_semester') }}
                                    </div>
                                </div>
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
                    <div class="card-header">
                        <h5>{{ __('field_list') }}</h5>
                        @can($access.'-create')
                        <a href="{{ route($route.'.create') }}" class="btn btn-primary"><i class="fas fa-plus"></i> {{ __('btn_add') }}</a>
                        @endcan
                    </div>
                    <div class="card-block">
                        <!-- [ Data table ] start -->
                        <div class="table-responsive">
                            <table id="basic-table" class="display table nowrap table-striped table-hover" style="width:100%">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>{{ __('field_faculty') }}</th>
                                        <th>{{ __('field_program') }}</th>
                                        <th>{{ __('field_semester') }}</th>
                                        <th>{{ __('field_fees_type') }}</th>
                                        <th>{{ __('field_amount') }}</th>
                                        <th>Due Days</th>
                                        <th>Fine</th>
                                        <th>{{ __('field_status') }}</th>
                                        <th>{{ __('field_action') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                  @foreach( $rows as $key => $row )
                                    <tr>
                                        <td>{{ $key + 1 }}</td>
                                        <td>{{ $row->program->faculty->title ?? 'N/A' }}</td>
                                        <td>{{ $row->program->title ?? 'N/A' }}</td>
                                        <td>{{ $row->semester->title ?? 'N/A' }} @if($row->semester && $row->semester->year)(Year {{ $row->semester->year }})@endif</td>
                                        <td>
                                            {{ $row->feesCategory->title ?? 'N/A' }}
                                            @if($row->feesCategory)
                                                @if($row->feesCategory->is_first_installment)
                                                <span class="badge badge-pill badge-info">1st Installment</span>
                                                @endif
                                                @if($row->feesCategory->is_second_installment)
                                                <span class="badge badge-pill badge-warning">2nd Installment</span>
                                                @endif
                                            @endif
                                            @if($row->breakdowns->count() > 0)
                                                <br>
                                                <button type="button" class="btn btn-xs btn-outline-primary mt-1" data-toggle="modal" data-target="#breakdownModal{{ $row->id }}">
                                                    <i class="fas fa-list-ul"></i> View Breakdown ({{ $row->breakdowns->count() }})
                                                </button>
                                            @endif
                                        </td>
                                        <td>
                                            @if(isset($setting->decimal_place))
                                            {{ number_format((float)$row->amount, $setting->decimal_place, '.', '') }} 
                                            @else
                                            {{ number_format((float)$row->amount, 2, '.', '') }} 
                                            @endif 
                                            {!! $setting->currency_symbol !!}
                                        </td>
                                        <td>
                                            @if($row->due_days)
                                                <span class="badge badge-info">{{ $row->due_days }} days</span>
                                            @else
                                                <span class="text-muted small">Default</span>
                                            @endif
                                        </td>
                                        <td>
                                            @if($row->fine_amount && $row->fine_type)
                                                @if($row->fine_type == 'fixed')
                                                    {{ number_format((float)$row->fine_amount, 2, '.', '') }} {!! $setting->currency_symbol !!}
                                                @else
                                                    {{ number_format((float)$row->fine_amount, 2, '.', '') }}%
                                                @endif
                                                <br><small class="text-muted">{{ ucfirst($row->fine_type) }}</small>
                                            @else
                                                <span class="text-muted small">None</span>
                                            @endif
                                        </td>
                                        <td>
                                            @if($row->status == 1)
                                            <span class="badge badge-pill badge-success">{{ __('status_active') }}</span>
                                            @else
                                            <span class="badge badge-pill badge-danger">{{ __('status_inactive') }}</span>
                                            @endif
                                        </td>
                                        <td>
                                            @can($access.'-edit')
                                            <a href="{{ route($route.'.edit', $row->id) }}" class="btn btn-icon btn-primary btn-sm">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            @endcan

                                            @can($access.'-delete')
                                            <form action="{{ route($route.'.destroy', $row->id) }}" method="POST" class="d-inline">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-icon btn-danger btn-sm" onclick="return confirm('{{ __('btn_confirm_delete') }}')">
                                                    <i class="fas fa-trash-alt"></i>
                                                </button>
                                            </form>
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

<!-- Breakdown Modals -->
@foreach( $rows as $row )
    @if($row->breakdowns->count() > 0)
    <div class="modal fade" id="breakdownModal{{ $row->id }}" tabindex="-1" role="dialog" aria-labelledby="breakdownModalLabel{{ $row->id }}" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="breakdownModalLabel{{ $row->id }}">
                        <i class="fas fa-list-ul"></i> Fee Breakdown - {{ $row->feesCategory->title }}
                    </h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <p class="mb-2"><strong>Program:</strong> {{ $row->program->title }}</p>
                    <p class="mb-3"><strong>Semester:</strong> {{ $row->semester->title }} @if($row->semester->year)(Year {{ $row->semester->year }})@endif</p>
                    
                    <table class="table table-sm table-bordered">
                        <thead class="thead-light">
                            <tr>
                                <th>Item</th>
                                <th class="text-right">Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($row->breakdowns as $breakdown)
                            <tr>
                                <td>{{ $breakdown->title }}</td>
                                <td class="text-right">
                                    @if(isset($setting->decimal_place))
                                    {{ number_format((float)$breakdown->amount, $setting->decimal_place, '.', '') }} 
                                    @else
                                    {{ number_format((float)$breakdown->amount, 2, '.', '') }} 
                                    @endif 
                                    {!! $setting->currency_symbol !!}
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                        <tfoot class="thead-light">
                            <tr>
                                <th>Total</th>
                                <th class="text-right">
                                    @if(isset($setting->decimal_place))
                                    {{ number_format((float)$row->amount, $setting->decimal_place, '.', '') }} 
                                    @else
                                    {{ number_format((float)$row->amount, 2, '.', '') }} 
                                    @endif 
                                    {!! $setting->currency_symbol !!}
                                </th>
                            </tr>
                        </tfoot>
                    </table>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>
    @endif
@endforeach

@endsection

@section('scripts')
<script>
$(document).ready(function() {
    // Faculty change event
    $('.faculty').on('change', function() {
        var facultyId = $(this).val();
        
        if(facultyId != '0') {
            // Load programs for selected faculty
            var url = "{{ route($route.'.get-programs') }}";
            
            $.ajax({
                url: url,
                type: "GET",
                data: {faculty_id: facultyId},
                success: function(data) {
                    $('.program').empty();
                    $('.program').append('<option value="0">{{ __("all") }}</option>');
                    $.each(data, function(key, value) {
                        $('.program').append('<option value="'+ value.id +'">'+ value.title +'</option>');
                    });
                }
            });
        } else {
            $('.program').empty();
            $('.program').append('<option value="0">{{ __("all") }}</option>');
        }
        
        // Clear semesters when faculty changes
        $('.semester').empty();
        $('.semester').append('<option value="0">{{ __("all") }}</option>');
    });

    // Program change event
    $('.program').on('change', function() {
        var programId = $(this).val();
        
        if(programId != '0') {
            // Load semesters with enrolled courses for selected program
            var url = "{{ route($route.'.get-semesters') }}";
            
            $.ajax({
                url: url,
                type: "GET",
                data: {program_id: programId},
                success: function(data) {
                    $('.semester').empty();
                    $('.semester').append('<option value="0">{{ __("all") }}</option>');
                    $.each(data, function(key, value) {
                        var semesterText = value.title;
                        if(value.year) {
                            semesterText += ' (Year ' + value.year + ')';
                        }
                        $('.semester').append('<option value="'+ value.id +'">'+ semesterText +'</option>');
                    });
                }
            });
        } else {
            $('.semester').empty();
            $('.semester').append('<option value="0">{{ __("all") }}</option>');
        }
    });
});
</script>
@endsection
