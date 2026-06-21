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
                        <h4 class="page-title"><i class="fas fa-gavel"></i> {{ $title }}</h4>
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="{{ route('admin.dashboard.index') }}"><i class="ti ti-home"></i></a></li>
                            <li class="breadcrumb-item"><a href="#">{{ trans_choice('module_examination', 2) }}</a></li>
                            <li class="breadcrumb-item"><a href="{{ route($route.'.index') }}">{{ __('Senate Deliberation') }}</a></li>
                            <li class="breadcrumb-item"><a href="{{ route($route.'.deliberations') }}">{{ __('Deliberations') }}</a></li>
                            <li class="breadcrumb-item active">{{ $deliberation->meeting_number }}</li>
                        </ol>
                    </div>
                    <div class="page-header-right">
                        <div class="btn-group">
                            <a href="{{ route($route.'.deliberations', ['session' => $deliberation->session_id, 'semester' => $deliberation->semester_id]) }}" class="btn btn-secondary">
                                <i class="fas fa-arrow-left"></i> {{ __('Back') }}
                            </a>
                            <a href="{{ route($route.'.academic-standings', ['session' => $deliberation->session_id, 'semester' => $deliberation->semester_id]) }}" class="btn btn-info">
                                <i class="fas fa-graduation-cap"></i> {{ __('Standings') }}
                            </a>
                            <form method="post" action="{{ route($route.'.export-pdf') }}" class="d-inline">
                                @csrf
                                <input type="hidden" name="deliberation_id" value="{{ $deliberation->id }}">
                                <button type="submit" class="btn btn-danger">
                                    <i class="fas fa-file-pdf"></i> {{ __('Export PDF') }}
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            {{-- ══════════════════════════════════════════════════════════════════
                 DELIBERATION HEADER CARD
            ══════════════════════════════════════════════════════════════════ --}}
            <div class="col-sm-12">
                <div class="card border-primary">
                    <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">
                            <i class="fas fa-gavel"></i> {{ $deliberation->meeting_number }}
                        </h5>
                        <div>
                            <span class="badge {{ \App\Models\SenateDeliberation::statusBadgeClass($deliberation->status) }} me-1" style="font-size:0.85rem">
                                {{ $statusLabels[$deliberation->status] ?? 'Unknown' }}
                            </span>
                            <span class="badge {{ \App\Models\SenateDeliberation::decisionBadgeClass($deliberation->overall_decision) }}" style="font-size:0.85rem">
                                {{ $decisionLabels[$deliberation->overall_decision] ?? 'Pending' }}
                            </span>
                        </div>
                    </div>
                    <div class="card-block">
                        <form method="post" action="{{ route($route.'.update', $deliberation->id) }}">
                            @csrf
                            @method('PUT')
                            <div class="row g-3">
                                <div class="col-md-3">
                                    <label class="small text-muted">{{ __('Session') }}</label>
                                    <p class="fw-bold mb-0">{{ $deliberation->session->title ?? '-' }}</p>
                                </div>
                                <div class="col-md-3">
                                    <label class="small text-muted">{{ __('Semester') }}</label>
                                    <p class="fw-bold mb-0">{{ $deliberation->semester->title ?? '-' }}</p>
                                </div>
                                <div class="col-md-3">
                                    <label class="small text-muted">{{ __('Meeting Date') }}</label>
                                    <input type="date" name="meeting_date" class="form-control form-control-sm"
                                        value="{{ $deliberation->meeting_date ? $deliberation->meeting_date->format('Y-m-d') : '' }}">
                                </div>
                                <div class="col-md-3">
                                    <label class="small text-muted">{{ __('Venue') }}</label>
                                    <input type="text" name="venue" class="form-control form-control-sm"
                                        value="{{ $deliberation->venue }}">
                                </div>
                                <div class="col-md-3">
                                    <label class="small text-muted">{{ __('Chairperson') }}</label>
                                    <input type="text" name="chairperson" class="form-control form-control-sm"
                                        value="{{ $deliberation->chairperson }}">
                                </div>
                                <div class="col-md-3">
                                    <label class="small text-muted">{{ __('Registrar') }}</label>
                                    <input type="text" name="registrar" class="form-control form-control-sm"
                                        value="{{ $deliberation->registrar }}">
                                </div>
                                <div class="col-md-3">
                                    <label class="small text-muted">{{ __('Status') }}</label>
                                    <select name="status" class="form-control form-control-sm">
                                        @foreach($statusLabels as $sKey => $sLabel)
                                        <option value="{{ $sKey }}" @if($deliberation->status === $sKey) selected @endif>{{ $sLabel }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label class="small text-muted">{{ __('Overall Decision') }}</label>
                                    <select name="overall_decision" class="form-control form-control-sm">
                                        @foreach($decisionLabels as $dKey => $dLabel)
                                        <option value="{{ $dKey }}" @if($deliberation->overall_decision === $dKey) selected @endif>{{ $dLabel }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="small text-muted">{{ __('Remarks') }}</label>
                                    <textarea name="remarks" class="form-control form-control-sm" rows="2">{{ $deliberation->remarks }}</textarea>
                                </div>
                                <div class="col-md-6">
                                    <label class="small text-muted">{{ __('Conditions / Action Items') }}</label>
                                    <textarea name="conditions" class="form-control form-control-sm" rows="2">{{ $deliberation->conditions }}</textarea>
                                </div>
                                <div class="col-12 text-end">
                                    <button type="submit" class="btn btn-primary btn-sm">
                                        <i class="fas fa-save"></i> {{ __('Update Deliberation') }}
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            {{-- ══════════════════════════════════════════════════════════════════
                 ACADEMIC STANDING SUMMARY BAR
            ══════════════════════════════════════════════════════════════════ --}}
            @if(!empty($standingCounts))
            <div class="col-sm-12">
                <div class="row g-2 mb-3">
                    @foreach($standingLabels as $sKey => $sLabel)
                    <div class="col">
                        <div class="text-center p-2 rounded {{ \App\Models\AcademicStanding::standingBadgeClass($sKey) }} text-white">
                            <i class="{{ \App\Models\AcademicStanding::standingIcon($sKey) }}"></i><br>
                            <strong>{{ $standingCounts[$sKey] ?? 0 }}</strong><br>
                            <small>{{ $sLabel }}</small>
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>
            @endif

            {{-- ══════════════════════════════════════════════════════════════════
                 PROGRAM DECISIONS BY FACULTY
            ══════════════════════════════════════════════════════════════════ --}}
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-tasks"></i> {{ __('Program Decisions') }}
                            <span class="badge bg-secondary float-end">{{ $deliberation->programs->count() }} {{ __('Programs') }}</span>
                        </h5>
                    </div>
                    <div class="card-block">
                        @foreach($programsByFaculty as $facTitle => $facPrograms)
                        <div class="mb-4">
                            <h6 class="border-bottom pb-2 mb-3">
                                <i class="fas fa-university text-primary"></i> {{ $facTitle }}
                                <small class="text-muted">({{ $facPrograms->count() }} programs)</small>
                            </h6>

                            <div class="accordion" id="faculty-{{ Str::slug($facTitle) }}">
                                @foreach($facPrograms as $progDelib)
                                @php $prog = $progDelib->program; @endphp
                                <div class="accordion-item border mb-2">
                                    <h2 class="accordion-header">
                                        <button class="accordion-button collapsed py-2" type="button"
                                            data-bs-toggle="collapse" data-bs-target="#prog-{{ $progDelib->id }}">
                                            <div class="d-flex w-100 justify-content-between align-items-center pe-3">
                                                <span>
                                                    <strong>{{ $prog->title ?? 'Unknown' }}</strong>
                                                    <small class="text-muted ms-2">
                                                        {{ $progDelib->total_students }} students |
                                                        GPA: {{ number_format($progDelib->average_gpa, 2) }} |
                                                        Pass: {{ $progDelib->pass_rate }}%
                                                    </small>
                                                </span>
                                                <span class="badge {{ \App\Models\SenateDeliberation::decisionBadgeClass($progDelib->decision) }}">
                                                    {{ $decisionLabels[$progDelib->decision] ?? 'Pending' }}
                                                </span>
                                            </div>
                                        </button>
                                    </h2>
                                    <div id="prog-{{ $progDelib->id }}" class="accordion-collapse collapse"
                                        data-bs-parent="#faculty-{{ Str::slug($facTitle) }}">
                                        <div class="accordion-body">
                                            <!-- Program Stats -->
                                            <div class="row text-center mb-3 g-2">
                                                <div class="col">
                                                    <div class="p-2 bg-light rounded">
                                                        <strong class="text-primary d-block">{{ $progDelib->total_students }}</strong>
                                                        <small>Total</small>
                                                    </div>
                                                </div>
                                                <div class="col">
                                                    <div class="p-2 bg-light rounded">
                                                        <strong class="text-success d-block">{{ $progDelib->total_passed }}</strong>
                                                        <small>Passed</small>
                                                    </div>
                                                </div>
                                                <div class="col">
                                                    <div class="p-2 bg-light rounded">
                                                        <strong class="text-danger d-block">{{ $progDelib->total_failed }}</strong>
                                                        <small>Failed</small>
                                                    </div>
                                                </div>
                                                <div class="col">
                                                    <div class="p-2 bg-light rounded">
                                                        <strong class="{{ $progDelib->average_gpa >= 2 ? 'text-success' : 'text-danger' }} d-block">
                                                            {{ number_format($progDelib->average_gpa, 2) }}
                                                        </strong>
                                                        <small>Avg GPA</small>
                                                    </div>
                                                </div>
                                                <div class="col">
                                                    <div class="p-2 bg-light rounded">
                                                        <strong class="{{ $progDelib->pass_rate >= 70 ? 'text-success' : ($progDelib->pass_rate >= 50 ? 'text-warning' : 'text-danger') }} d-block">
                                                            {{ $progDelib->pass_rate }}%
                                                        </strong>
                                                        <small>Pass Rate</small>
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- Decision Form -->
                                            <form method="post" action="{{ route($route.'.program-decision', $deliberation->id) }}">
                                                @csrf
                                                <input type="hidden" name="program_deliberation_id" value="{{ $progDelib->id }}">
                                                <div class="row g-2">
                                                    <div class="col-md-3">
                                                        <label class="small">{{ __('Decision') }}</label>
                                                        <select name="decision" class="form-control form-control-sm">
                                                            @foreach($decisionLabels as $dKey => $dLabel)
                                                            <option value="{{ $dKey }}" @if($progDelib->decision === $dKey) selected @endif>{{ $dLabel }}</option>
                                                            @endforeach
                                                        </select>
                                                    </div>
                                                    <div class="col-md-3">
                                                        <label class="small">{{ __('Remarks') }}</label>
                                                        <textarea name="remarks" class="form-control form-control-sm" rows="2">{{ $progDelib->remarks }}</textarea>
                                                    </div>
                                                    <div class="col-md-3">
                                                        <label class="small">{{ __('Conditions') }}</label>
                                                        <textarea name="conditions" class="form-control form-control-sm" rows="2">{{ $progDelib->conditions }}</textarea>
                                                    </div>
                                                    <div class="col-md-3 d-flex align-items-end">
                                                        <button type="submit" class="btn btn-sm btn-primary w-100">
                                                            <i class="fas fa-save"></i> {{ __('Save Decision') }}
                                                        </button>
                                                    </div>
                                                </div>
                                                @if($progDelib->reviewed_by)
                                                <div class="small text-muted mt-2">
                                                    <i class="fas fa-user-check"></i> Reviewed by {{ $progDelib->reviewer->name ?? 'Unknown' }}
                                                    @ {{ $progDelib->reviewed_at ? $progDelib->reviewed_at->format('d M Y H:i') : '' }}
                                                </div>
                                                @endif
                                            </form>
                                        </div>
                                    </div>
                                </div>
                                @endforeach
                            </div>
                        </div>
                        @endforeach
                    </div>
                </div>
            </div>

            {{-- ══════════════════════════════════════════════════════════════════
                 SIGNATURES
            ══════════════════════════════════════════════════════════════════ --}}
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5><i class="fas fa-signature"></i> {{ __('Signatures') }}</h5>
                        <span class="badge bg-info">{{ $deliberation->signatures->count() }}</span>
                    </div>
                    <div class="card-block">
                        @if($deliberation->signatures->count() > 0)
                        <div class="table-responsive">
                            <table class="table table-sm mb-3">
                                <thead>
                                    <tr>
                                        <th>{{ __('Name') }}</th>
                                        <th>{{ __('Position') }}</th>
                                        <th>{{ __('Signed At') }}</th>
                                        <th style="width:50px"></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($deliberation->signatures as $sig)
                                    <tr>
                                        <td>{{ $sig->signatory_name }}</td>
                                        <td>{{ $sig->signatory_position }}</td>
                                        <td class="small">{{ $sig->signed_at ? $sig->signed_at->format('d M Y H:i') : '-' }}</td>
                                        <td>
                                            <form method="post" action="{{ route($route.'.remove-signature', $sig->id) }}"
                                                onsubmit="return confirm('Remove this signature?')">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-sm btn-outline-danger p-1">
                                                    <i class="fas fa-times"></i>
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        @endif

                        <!-- Add Signature Form -->
                        <form method="post" action="{{ route($route.'.add-signature', $deliberation->id) }}">
                            @csrf
                            <div class="row g-2 align-items-end">
                                <div class="col-5">
                                    <label class="small">{{ __('Name') }}</label>
                                    <input type="text" name="signatory_name" class="form-control form-control-sm" required
                                        placeholder="e.g. Prof. John Doe">
                                </div>
                                <div class="col-5">
                                    <label class="small">{{ __('Position') }}</label>
                                    <input type="text" name="signatory_position" class="form-control form-control-sm" required
                                        placeholder="e.g. Chairperson, Dean of Faculty">
                                </div>
                                <div class="col-2">
                                    <button type="submit" class="btn btn-sm btn-success w-100">
                                        <i class="fas fa-plus"></i>
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            {{-- ══════════════════════════════════════════════════════════════════
                 ACTIVITY LOG
            ══════════════════════════════════════════════════════════════════ --}}
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-history"></i> {{ __('Activity Log') }}</h5>
                    </div>
                    <div class="card-block p-0" style="max-height: 400px; overflow-y: auto;">
                        @if($deliberation->logs->count() > 0)
                        <ul class="list-group list-group-flush">
                            @foreach($deliberation->logs as $log)
                            <li class="list-group-item py-2">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        @php
                                            $actionIcons = [
                                                'created' => 'fas fa-plus-circle text-success',
                                                'status_changed' => 'fas fa-exchange-alt text-info',
                                                'decision_made' => 'fas fa-gavel text-primary',
                                                'program_reviewed' => 'fas fa-clipboard-check text-warning',
                                                'student_flagged' => 'fas fa-flag text-danger',
                                                'standing_classified' => 'fas fa-graduation-cap text-info',
                                                'signature_added' => 'fas fa-signature text-success',
                                                'exported' => 'fas fa-file-export text-secondary',
                                            ];
                                        @endphp
                                        <i class="{{ $actionIcons[$log->action] ?? 'fas fa-circle text-muted' }}"></i>
                                        <strong class="small">{{ \App\Models\SenateDeliberationLog::actionLabels()[$log->action] ?? ucfirst($log->action) }}</strong>
                                        <br><span class="small text-muted">{{ $log->description }}</span>
                                    </div>
                                    <div class="text-end">
                                        <small class="text-muted d-block">{{ $log->created_at->format('d M H:i') }}</small>
                                        <small class="text-muted">{{ $log->performer->name ?? 'System' }}</small>
                                    </div>
                                </div>
                            </li>
                            @endforeach
                        </ul>
                        @else
                        <div class="text-center py-4 text-muted">
                            <i class="fas fa-history fa-2x mb-2"></i>
                            <p class="small mb-0">{{ __('No activity recorded yet.') }}</p>
                        </div>
                        @endif
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>

@endsection
