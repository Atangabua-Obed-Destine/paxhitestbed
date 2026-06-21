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
                                    <input type="hidden" name="semester" value="{{ $selected_semester }}">
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
                                <div class="form-group col-md-2">
                                    <label for="semester">{{ __('Semester') }} <span class="text-danger">*</span></label>
                                    <select class="form-control" name="semester" id="semester" required>
                                        <option value="">{{ __('select') }}</option>
                                        @foreach($semesters as $sem)
                                        <option value="{{ $sem->id }}" @if($selected_semester == $sem->id) selected @endif>{{ $sem->title }}@if(!empty($sem->is_resit)) (Resit)@endif</option>
                                        @endforeach
                                    </select>
                                </div>
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

            @if($selected_session !== '0' && $selected_semester !== '0')

            {{-- Standing summary cards --}}
            @if(!empty($standing_counts))
            <div class="col-sm-12">
                <div class="row g-3 mb-3">
                    @php
                        $standingConfig = [
                            'deans_list' => ['icon' => 'fas fa-trophy', 'bg' => 'bg-primary', 'label' => "Dean's List"],
                            'good_standing' => ['icon' => 'fas fa-check-circle', 'bg' => 'bg-success', 'label' => 'Good Standing'],
                            'academic_warning' => ['icon' => 'fas fa-exclamation-triangle', 'bg' => 'bg-warning', 'label' => 'Warning'],
                            'academic_probation' => ['icon' => 'fas fa-exclamation-circle', 'bg' => 'bg-orange', 'label' => 'Probation'],
                            'recommended_dismissal' => ['icon' => 'fas fa-times-circle', 'bg' => 'bg-danger', 'label' => 'Dismissal'],
                        ];
                        $totalStandings = array_sum($standing_counts);
                    @endphp
                    @foreach($standingConfig as $sKey => $sConf)
                    <div class="col">
                        <div class="card {{ $sConf['bg'] }} text-white mb-0 h-100">
                            <div class="card-block text-center py-3">
                                <i class="{{ $sConf['icon'] }} fa-lg"></i>
                                <h3 class="mb-0 mt-1">{{ $standing_counts[$sKey] ?? 0 }}</h3>
                                <small>{{ $sConf['label'] }}</small>
                                @if($totalStandings > 0)
                                <br><small class="opacity-75">{{ round((($standing_counts[$sKey] ?? 0) / $totalStandings) * 100, 1) }}%</small>
                                @endif
                            </div>
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>
            @endif

            {{-- Generate / Refresh button --}}
            <div class="col-sm-12">
                <div class="d-flex justify-content-between mb-3">
                    <div>
                        @if($standings && $standings->total() > 0)
                            <span class="text-muted">Showing {{ $standings->firstItem() }}-{{ $standings->lastItem() }} of {{ $standings->total() }} students</span>
                        @endif
                    </div>
                    <div>
                        <form method="post" action="{{ route($route.'.classify') }}" class="d-inline">
                            @csrf
                            <input type="hidden" name="session" value="{{ $selected_session }}">
                            <input type="hidden" name="semester" value="{{ $selected_semester }}">
                            <button type="submit" class="btn btn-sm btn-{{ ($standings_exist ?? false) ? 'outline-warning' : 'primary' }}"
                                onclick="return confirm('{{ ($standings_exist ?? false) ? 'Re-classify all standings? Previous data will be overwritten.' : 'Compute academic standings for all students?' }}')">
                                <i class="fas fa-{{ ($standings_exist ?? false) ? 'sync-alt' : 'cog' }}"></i>
                                {{ ($standings_exist ?? false) ? __('Re-classify') : __('Generate Standings') }}
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            {{-- Standings Table --}}
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-block p-0">
                        @if($standings && $standings->count() > 0)
                        <div class="p-3 border-bottom">
                            <div class="input-group input-group-sm" style="max-width:420px;">
                                <span class="input-group-text"><i class="fas fa-search"></i></span>
                                <input type="text" id="standingsSearch" class="form-control" placeholder="{{ __('Search matricule, name, program, faculty, standing...') }}" autocomplete="off">
                                <button type="button" class="btn btn-outline-secondary" id="standingsSearchClear" title="{{ __('Clear') }}"><i class="fas fa-times"></i></button>
                            </div>
                            <small class="text-muted d-block mt-1" id="standingsSearchInfo">{{ __('Searches the current page. Clear the filter to load all pages.') }}</small>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-hover table-striped mb-0" id="standingsTable">
                                <thead class="thead-dark">
                                    <tr>
                                        <th style="width:50px">{{ __('S/N') }}</th>
                                        <th>{{ __('Matricule') }}</th>
                                        <th>{{ __('Student Name') }}</th>
                                        <th>{{ __('Program') }}</th>
                                        <th>{{ __('Faculty') }}</th>
                                        <th class="text-center">{{ __('Cr. Reg') }}</th>
                                        <th class="text-center">{{ __('Cr. Earned') }}</th>
                                        <th class="text-center">{{ __('Passed') }}</th>
                                        <th class="text-center">{{ __('Failed') }}</th>
                                        <th class="text-center" title="Number of carry-over courses (failed in prior enrollments, not yet passed and not in an active resit)">#CO</th>
                                        <th class="text-center" title="Total credit hours of carry-over courses">CO Cr.</th>
                                        <th class="text-center">{{ __('GPA') }}</th>
                                        <th>{{ __('Standing') }}</th>
                                        <th>{{ __('Previous') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($standings as $idx => $s)
                                    <tr>
                                        <td>{{ $standings->firstItem() + $idx }}</td>
                                        <td><strong>{{ $s->enrollment->matricule ?? $s->student->student_id ?? 'N/A' }}</strong></td>
                                        <td>{{ trim(($s->student->first_name ?? '') . ' ' . ($s->student->last_name ?? '')) }}</td>
                                        <td>{{ $s->program->title ?? '' }}</td>
                                        <td>{{ $s->program->faculty->title ?? '' }}</td>
                                        <td class="text-center">{{ $s->total_credits_registered }}</td>
                                        <td class="text-center">{{ $s->total_credits_earned }}</td>
                                        <td class="text-center text-success">{{ $s->courses_passed }}</td>
                                        <td class="text-center text-danger">{{ $s->courses_failed }}</td>
                                        <td class="text-center">
                                            @if(($s->carry_over_courses ?? 0) > 0)
                                                <span class="badge" style="background:#fd7e14;color:#fff;">{{ $s->carry_over_courses }}</span>
                                            @else
                                                <span class="text-muted">0</span>
                                            @endif
                                        </td>
                                        <td class="text-center">
                                            @if(($s->carry_over_credits ?? 0) > 0)
                                                <strong style="color:#fd7e14;">{{ rtrim(rtrim(number_format($s->carry_over_credits, 1), '0'), '.') }}</strong>
                                            @else
                                                <span class="text-muted">0</span>
                                            @endif
                                        </td>
                                        <td class="text-center">
                                            <strong class="text-{{ $s->gpa >= 3.5 ? 'primary' : ($s->gpa >= 2.0 ? 'success' : ($s->gpa >= 1.0 ? 'warning' : 'danger')) }}">
                                                {{ number_format($s->gpa, 2) }}
                                            </strong>
                                        </td>
                                        <td>
                                            <span class="badge {{ \App\Models\AcademicStanding::standingBadgeClass($s->standing) }}">
                                                <i class="{{ \App\Models\AcademicStanding::standingIcon($s->standing) }}"></i>
                                                {{ $s->standing_label }}
                                            </span>
                                        </td>
                                        <td>
                                            @if($s->previous_standing)
                                                <span class="badge {{ \App\Models\AcademicStanding::standingBadgeClass($s->previous_standing) }} badge-sm">
                                                    {{ \App\Models\AcademicStanding::standingLabels()[$s->previous_standing] ?? '-' }}
                                                </span>
                                            @else
                                                <span class="text-muted">-</span>
                                            @endif
                                        </td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        {{-- Pagination --}}
                        @if($standings->hasPages())
                        <div class="card-footer">
                            {{ $standings->appends(request()->query())->links() }}
                        </div>
                        @endif

                        @elseif($standings_exist)
                        <div class="text-center py-5 text-muted">
                            <i class="fas fa-filter fa-3x mb-3"></i>
                            <p>{{ __('No students match your current filter criteria.') }}</p>
                        </div>
                        @else
                        <div class="text-center py-5 text-muted">
                            <i class="fas fa-graduation-cap fa-3x mb-3"></i>
                            <p class="mb-0">{{ __('Academic standings have not been computed yet for this session/semester.') }}</p>
                            <p class="small">{{ __('Click "Generate Standings" to compute GPA and classify all students.') }}</p>
                        </div>
                        @endif
                    </div>
                </div>
            </div>

            @else
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

@section('page-js')
<script>
// Client-side search over the currently-visible standings rows
$(document).ready(function(){
    var $input = $('#standingsSearch');
    var $rows  = $('#standingsTable tbody tr');
    var $info  = $('#standingsSearchInfo');

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
    $('#standingsSearchClear').on('click', function(){ $input.val(''); applyFilter(); $input.focus(); });
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
