{{-- One semester's workflow counts in multi-programme mode: $faculty_groups, $global_stats. --}}
            <!-- Multi-Mode Global Stats -->
            <div class="col-sm-12 no-print">
                <div class="row">
                    <div class="col-md-2">
                        <div class="card bg-dark text-white">
                            <div class="card-body text-center py-3">
                                <h3 class="mb-0">{{ count($faculty_groups) }}</h3>
                                <small>{{ __('Faculties') }}</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="card bg-info text-white">
                            <div class="card-body text-center py-3">
                                <h3 class="mb-0">{{ $global_stats['total_programs'] }}</h3>
                                <small>{{ __('Programs') }}</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="card bg-primary text-white">
                            <div class="card-body text-center py-3">
                                <h3 class="mb-0">{{ $global_stats['total_courses'] }}</h3>
                                <small>{{ __('Total Courses') }}</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="card bg-secondary text-white">
                            <div class="card-body text-center py-3">
                                <h3 class="mb-0">{{ number_format($global_stats['total_students']) }}</h3>
                                <small>{{ __('Total Students') }}</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="card bg-success text-white">
                            <div class="card-body text-center py-3">
                                <h3 class="mb-0">{{ $global_stats['published'] }}/{{ $global_stats['total_courses'] }}</h3>
                                <small>{{ __('Published') }}</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="card bg-warning text-dark">
                            <div class="card-body text-center py-3">
                                <h3 class="mb-0">{{ $global_stats['draft'] + $global_stats['submitted'] + $global_stats['checked'] + $global_stats['approved'] }}</h3>
                                <small>{{ __('Pending') }}</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Multi-Mode State Distribution -->
            <div class="col-sm-12 no-print">
                @php
                    $totalCourses = $global_stats['total_courses'] ?: 1;
                    $draftPct = round(($global_stats['draft'] / $totalCourses) * 100);
                    $submittedPct = round(($global_stats['submitted'] / $totalCourses) * 100);
                    $checkedPct = round(($global_stats['checked'] / $totalCourses) * 100);
                    $approvedPct = round(($global_stats['approved'] / $totalCourses) * 100);
                    $publishedPct = round(($global_stats['published'] / $totalCourses) * 100);
                @endphp
                <div class="progress mb-3" style="height: 28px; border-radius: 6px;">
                    <div class="progress-bar bg-secondary" style="width: {{ $draftPct }}%" title="Draft: {{ $global_stats['draft'] }}">
                        @if($draftPct > 5) Draft ({{ $global_stats['draft'] }}) @endif
                    </div>
                    <div class="progress-bar bg-info" style="width: {{ $submittedPct }}%" title="Submitted: {{ $global_stats['submitted'] }}">
                        @if($submittedPct > 5) Submitted ({{ $global_stats['submitted'] }}) @endif
                    </div>
                    <div class="progress-bar bg-primary" style="width: {{ $checkedPct }}%" title="Checked: {{ $global_stats['checked'] }}">
                        @if($checkedPct > 5) Checked ({{ $global_stats['checked'] }}) @endif
                    </div>
                    <div class="progress-bar bg-warning text-dark" style="width: {{ $approvedPct }}%" title="Approved: {{ $global_stats['approved'] }}">
                        @if($approvedPct > 5) Approved ({{ $global_stats['approved'] }}) @endif
                    </div>
                    <div class="progress-bar bg-success" style="width: {{ $publishedPct }}%" title="Published: {{ $global_stats['published'] }}">
                        @if($publishedPct > 5) Published ({{ $global_stats['published'] }}) @endif
                    </div>
                </div>
            </div>
