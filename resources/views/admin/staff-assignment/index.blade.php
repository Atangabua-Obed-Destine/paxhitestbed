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
                        <div class="alert alert-info" role="alert">
                            <i class="fas fa-info-circle"></i>
                            <strong>{{ __('Info') }}:</strong> {{ __('Staff assignments restrict access to specific faculties, programs, and courses. Staff without assignments have default role-based access to all areas.') }}
                        </div>

                        @can('staff-assignment-create')
                        <a href="{{ route($route.'.create') }}" class="btn btn-primary"><i class="fas fa-plus"></i> {{ __('Assign Staff') }}</a>
                        @endcan

                        <a href="{{ route($route.'.index') }}" class="btn btn-info"><i class="fas fa-sync-alt"></i> {{ __('btn_refresh') }}</a>
                    </div>

                    <div class="card-block">
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>{{ __('Staff Name') }}</th>
                                        <th>{{ __('Staff ID') }}</th>
                                        <th>{{ __('Role') }}</th>
                                        <th>{{ __('Assigned To') }}</th>
                                        <th>{{ __('Assignments') }}</th>
                                        <th>{{ __('Created By') }}</th>
                                        <th>{{ __('Last Updated') }}</th>
                                        <th>{{ __('action') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                @forelse($assignments as $key => $user)
                                    @php
                                        $facultyAssignments = $user->staffAssignments->where('assignable_type', 'App\Models\Faculty');
                                        $programAssignments = $user->staffAssignments->where('assignable_type', 'App\Models\Program');
                                        $courseAssignments = $user->staffAssignments->where('assignable_type', 'App\Models\Subject');
                                        $totalAssignments = $user->staffAssignments->count();
                                        $latestAssignment = $user->staffAssignments->sortByDesc('updated_at')->first();
                                    @endphp
                                    <tr>
                                        <td>{{ $assignments->firstItem() + $key }}</td>
                                        <td>
                                            <strong>{{ $user->first_name }} {{ $user->last_name }}</strong>
                                            <br><small class="text-muted">{{ $user->email }}</small>
                                        </td>
                                        <td>{{ $user->staff_id ?? '-' }}</td>
                                        <td>
                                            @foreach($user->roles as $role)
                                                <span class="badge badge-primary">{{ $role->name }}</span>
                                            @endforeach
                                        </td>
                                        <td>
                                            @if($facultyAssignments->count() > 0)
                                                <span class="badge badge-info">
                                                    <i class="fas fa-university"></i> {{ $facultyAssignments->count() }} {{ __('Faculties') }}
                                                </span>
                                            @endif
                                            @if($programAssignments->count() > 0)
                                                <span class="badge badge-success">
                                                    <i class="fas fa-graduation-cap"></i> {{ $programAssignments->count() }} {{ __('Programs') }}
                                                </span>
                                            @endif
                                            @if($courseAssignments->count() > 0)
                                                <span class="badge badge-warning">
                                                    <i class="fas fa-book"></i> {{ $courseAssignments->count() }} {{ __('Courses') }}
                                                </span>
                                            @endif
                                        </td>
                                        <td>
                                            <button type="button" class="btn btn-sm btn-outline-primary" data-toggle="modal" data-target="#assignmentModal{{ $user->id }}">
                                                <i class="fas fa-eye"></i> {{ __('View') }} ({{ $totalAssignments }})
                                            </button>

                                            <!-- Assignment Details Modal -->
                                            <div class="modal fade" id="assignmentModal{{ $user->id }}" tabindex="-1" role="dialog">
                                                <div class="modal-dialog modal-lg" role="document">
                                                    <div class="modal-content">
                                                        <div class="modal-header">
                                                            <h5 class="modal-title">{{ __('Assignment Details') }}: {{ $user->first_name }} {{ $user->last_name }}</h5>
                                                            <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                                                <span aria-hidden="true">&times;</span>
                                                            </button>
                                                        </div>
                                                        <div class="modal-body">
                                                            @if($facultyAssignments->count() > 0)
                                                                <h6 class="text-info"><i class="fas fa-university"></i> {{ __('Faculties') }}</h6>
                                                                <ul>
                                                                    @foreach($facultyAssignments as $assignment)
                                                                        <li>{{ $assignment->assignable->title ?? 'N/A' }}</li>
                                                                    @endforeach
                                                                </ul>
                                                            @endif

                                                            @if($programAssignments->count() > 0)
                                                                <h6 class="text-success"><i class="fas fa-graduation-cap"></i> {{ __('Programs') }}</h6>
                                                                <ul>
                                                                    @foreach($programAssignments as $assignment)
                                                                        <li>{{ $assignment->assignable->title ?? 'N/A' }} 
                                                                            <small class="text-muted">({{ $assignment->assignable->faculty->title ?? 'N/A' }})</small>
                                                                        </li>
                                                                    @endforeach
                                                                </ul>
                                                            @endif

                                                            @if($courseAssignments->count() > 0)
                                                                <h6 class="text-warning"><i class="fas fa-book"></i> {{ __('Courses') }}</h6>
                                                                <ul>
                                                                    @foreach($courseAssignments as $assignment)
                                                                        <li>{{ $assignment->assignable->code ?? '' }} - {{ $assignment->assignable->title ?? 'N/A' }} 
                                                                            <small class="text-muted">({{ $assignment->assignable->program->title ?? 'N/A' }})</small>
                                                                        </li>
                                                                    @endforeach
                                                                </ul>
                                                            @endif
                                                        </div>
                                                        <div class="modal-footer">
                                                            <button type="button" class="btn btn-secondary" data-dismiss="modal">{{ __('btn_close') }}</button>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            @if($latestAssignment && $latestAssignment->creator)
                                                {{ $latestAssignment->creator->first_name }} {{ $latestAssignment->creator->last_name }}
                                            @else
                                                -
                                            @endif
                                        </td>
                                        <td>{{ $latestAssignment->updated_at->diffForHumans() ?? '-' }}</td>
                                        <td>
                                            @can('staff-assignment-edit')
                                            <a href="{{ route($route.'.edit', $user->id) }}" class="btn btn-sm btn-info">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            @endcan

                                            @can('staff-assignment-delete')
                                            <form action="{{ route($route.'.destroy', $user->id) }}" method="post" class="d-inline">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-sm btn-danger" onclick="return confirm('{{ __('Remove all assignments for this staff? They will revert to default role-based access.') }}')">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </form>
                                            @endcan
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="9" class="text-center">{{ __('No staff assignments found. All staff have default role-based access.') }}</td>
                                    </tr>
                                @endforelse
                                </tbody>
                            </table>
                        </div>
                        <!-- Pagination -->
                        {{ $assignments->links() }}
                    </div>
                </div>
            </div>
        </div>
        <!-- [ Main Content ] end -->
    </div>
</div>
<!-- End Content-->

@endsection
