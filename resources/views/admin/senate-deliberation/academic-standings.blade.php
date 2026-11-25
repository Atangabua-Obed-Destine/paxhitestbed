@extends('admin.layouts.master')
@section('title', $title)
@section('content')

<!-- Start Content-->
<div class="main-body">
    <div class="page-wrapper">
        <div class="row">
            <!-- Page Header -->
            <div class="col-sm-12">
                <div class="page-header">
                    <div class="page-header-left">
                        <h4 class="page-title"><i class="fas fa-graduation-cap"></i> {{ $title }}</h4>
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="{{ route('admin.dashboard.index') }}"><i class="ti ti-home"></i></a></li>
                            <li class="breadcrumb-item"><a href="#">{{ trans_choice('module_examination', 2) }}</a></li>
                            <li class="breadcrumb-item"><a href="{{ route($route.'.index') }}">{{ __('Senate Deliberation') }}</a></li>
                            <li class="breadcrumb-item active">{{ __('Academic Standings') }}</li>
                        </ol>
                    </div>
                    <div class="page-header-right">
                        <div class="btn-group">
                            <a href="{{ route($route.'.index', request()->query()) }}" class="btn btn-secondary">
                                <i class="fas fa-arrow-left"></i> {{ __('Dashboard') }}
                            </a>
                            @if(($standings_exist ?? false) && $selected_session !== '0' && $selected_semester !== '0')
                                <form method="post" action="{{ route($route.'.export-excel') }}" class="d-inline">
                                    @csrf
                                    <input type="hidden" name="session" value="{{ $selected_session }}">
                                    @if(count($selected_semesters) > 1)
                                    <input type="hidden" name="semester_type" value="{{ $selected_semester_type }}">
                                    @foreach($selected_semesters as $semId)
                                    <input type="hidden" name="semester[]" value="{{ $semId }}">
                                    @endforeach
                                    @else
                                    <input type="hidden" name="semester" value="{{ $selected_semester }}">
                                    @endif
                                    <input type="hidden" name="faculty" value="{{ $selected_faculty ?? '0' }}">
                                    <input type="hidden" name="program" value="{{ $selected_program ?? '0' }}">
                                    <input type="hidden" name="standing" value="{{ $selected_standing ?? 'all' }}">
                                    <button type="submit" class="btn btn-success">
                                        <i class="fas fa-file-excel"></i> {{ __('Export Excel') }}
                                    </button>
                                </form>
                            @endif
                        </div>
                    </div>
                </div>
            </div>

            <!-- Filter Card -->
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-filter"></i> {{ __('Filter Standings') }}</h5>
                    </div>
                    <div class="card-block">
                        <form method="get" action="{{ route($route.'.academic-standings') }}">
                            <div class="row gx-2 align-items-end">
                                <div class="form-group col-md-2">
                                    <label for="session">{{ __('Session') }} <span class="text-danger">*</span></label>
                                    <select class="form-control" name="session" id="session" required>
                                        <option value="">{{ __('select') }}</option>
                                        @foreach($sessions as $sess)
                                        <option value="{{ $sess->id }}" @if($selected_session == $sess->id) selected @endif>{{ $sess->title }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                @include('common.inc.semester_type_field', ['type_col' => 'col-md-2', 'semester_col' => 'col-md-2'])
                                <div class="form-group col-md-2">
                                    <label for="faculty">{{ __('field_faculty') }}</label>
                                    <select class="form-control faculty" name="faculty" id="faculty">
                                        <option value="0">{{ __('All Faculties') }}</option>
                                        @if(isset($faculties))
                                        @foreach($faculties->sortBy('title') as $fac)
                                        <option value="{{ $fac->id }}" @if($selected_faculty == $fac->id) selected @endif>{{ $fac->title }}</option>
                                        @endforeach
                                        @endif
                                    </select>
                                </div>
                                <div class="form-group col-md-2">
                                    <label for="program">{{ __('field_program') }}</label>
                                    <select class="form-control" name="program" id="program">
                                        <option value="0">{{ __('All Programs') }}</option>
                                        @if(isset($programs))
                                        @foreach($programs as $prog)
                                        <option value="{{ $prog->id }}" @if($selected_program == $prog->id) selected @endif>{{ $prog->title }}</option>
                                        @endforeach
                                        @endif
                                    </select>
                                </div>
                                <div class="form-group col-md-2">
                                    <label for="standing">{{ __('Standing') }}</label>
                                    <select class="form-control" name="standing" id="standing">
                                        <option value="all">{{ __('All Standings') }}</option>
                                        @foreach($standing_options as $key => $label)
                                        <option value="{{ $key }}" @if($selected_standing == $key) selected @endif>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="form-group col-md-2">
                                    <button type="submit" class="btn btn-primary w-100">
                                        <i class="fas fa-search"></i> {{ __('Filter') }}
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            @if(count($semester_blocks) > 0)
            @foreach($semester_blocks as $block)
            @include('admin.senate-deliberation.partials.standings-semester', $block + [
                'selected_semester' => (string) $block['semester']->id,
                'bk' => count($semester_blocks) > 1 ? '-s' . $block['semester']->id : '',
                'show_heading' => count($semester_blocks) > 1,
            ])
            @endforeach
            @include('admin.senate-deliberation.partials.skipped-semesters')

            @else
            @include('admin.senate-deliberation.partials.skipped-semesters')
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-block text-center py-5">
                        <i class="fas fa-graduation-cap fa-4x text-muted mb-3"></i>
                        <h5 class="text-muted">{{ __('Select a Session and Semester to view Academic Standings') }}</h5>
                    </div>
                </div>
            </div>
            @endif
        </div>
    </div>
</div>

@endsection

@section('page_js')
<script>
// Client-side search over the currently-visible standings rows
$(document).ready(function(){
  $('input[id^="standingsSearch"][data-bk]').each(function(){
    var bk     = $(this).data('bk') || '';
    var $input = $(this);
    var $rows  = $('#standingsTable' + bk + ' tbody tr');
    var $info  = $('#standingsSearchInfo' + bk);

    function applyFilter(){
        var q = ($input.val() || '').toLowerCase().trim();
        if(!q){
            $rows.show();
            $info.text('{{ __("Searches the current page. Clear the filter to load all pages.") }}');
            return;
        }
        var visible = 0;
        $rows.each(function(){
            var text = $(this).text().toLowerCase();
            if(text.indexOf(q) !== -1){ $(this).show(); visible++; }
            else { $(this).hide(); }
        });
        $info.text(visible + ' {{ __("match(es) on this page") }}');
    }
    $input.on('keyup input', applyFilter);
    $('#standingsSearchClear' + bk).on('click', function(){ $input.val(''); applyFilter(); $input.focus(); });
  });
});

// Dynamic faculty → program filter
$(document).ready(function(){
    $('#faculty').on('change', function(){
        var facultyId = $(this).val();
        var $program = $('#program');
        $program.html('<option value="0">{{ __("All Programs") }}</option>');
        if(facultyId && facultyId !== '0'){
            $.ajax({
                url: '{{ url("admin/section/program") }}/' + facultyId,
                type: 'GET',
                success: function(data){
                    $.each(data, function(i, item){
                        $program.append('<option value="'+item.id+'">'+item.title+'</option>');
                    });
                }
            });
        }
    });
});
</script>
@endsection
