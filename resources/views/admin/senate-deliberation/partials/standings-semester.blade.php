{{--
  One semester of the academic standings screen. Included once per chosen
  semester by senate-deliberation/academic-standings with that semester's
  page of standings and counts, $selected_semester set to the block's own
  semester, and $bk ('' when only one semester is shown) for unique DOM ids.
--}}
@if(!empty($show_heading))
            <div class="col-sm-12">
                <h4 class="mt-3 mb-3 semester-block-heading" data-semester-block="{{ $semester->id }}"><i class="fas fa-bookmark"></i> {{ $semester->title }}</h4>
            </div>
@endif
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
                                <input type="text" id="standingsSearch{{ $bk }}" data-bk="{{ $bk }}" class="form-control" placeholder="{{ __('Search matricule, name, program, faculty, standing...') }}" autocomplete="off">
                                <button type="button" class="btn btn-outline-secondary" id="standingsSearchClear{{ $bk }}" title="{{ __('Clear') }}"><i class="fas fa-times"></i></button>
                            </div>
                            <small class="text-muted d-block mt-1" id="standingsSearchInfo{{ $bk }}">{{ __('Searches the current page. Clear the filter to load all pages.') }}</small>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-hover table-striped mb-0" id="standingsTable{{ $bk }}">
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
