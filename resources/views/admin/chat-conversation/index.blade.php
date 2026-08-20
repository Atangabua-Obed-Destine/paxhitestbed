@extends('admin.layouts.master')
@section('title', $title)
@section('content')

<div class="main-body">
    <div class="page-wrapper">
        <div class="card">
            <div class="card-header">
                <h5>{{ $title }}</h5>
                <span class="text-muted d-block mt-1" style="font-size:.85rem;">
                    {{ __('Every question asked of the assistant, and every record lookup it performed.') }}
                </span>
            </div>

            <div class="card-block">
                <form method="get" class="row mb-3">
                    <div class="col-md-3">
                        <select name="actor_type" class="form-control" onchange="this.form.submit()">
                            <option value="">{{ __('All users') }}</option>
                            @foreach(['guest' => __('Guests'), 'applicant' => __('Applicants'), 'student' => __('Students'), 'user' => __('Staff')] as $value => $label)
                                <option value="{{ $value }}" {{ ($filters['actor_type'] ?? '') === $value ? 'selected' : '' }}>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3">
                        <select name="surface" class="form-control" onchange="this.form.submit()">
                            <option value="">{{ __('All portals') }}</option>
                            @foreach(['web' => __('Public site'), 'application' => __('Application portal'), 'student' => __('Student portal'), 'admin' => __('Admin portal')] as $value => $label)
                                <option value="{{ $value }}" {{ ($filters['surface'] ?? '') === $value ? 'selected' : '' }}>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                </form>

                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>{{ __('Who') }}</th>
                                <th>{{ __('Portal') }}</th>
                                <th>{{ __('Opening question') }}</th>
                                <th class="text-center">{{ __('Messages') }}</th>
                                <th>{{ __('Last activity') }}</th>
                                <th class="text-end">{{ __('field_action') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                        @forelse($rows as $row)
                            <tr>
                                <td>
                                    {{ $row->actorName() ?? __('Deleted record') }}
                                    <small class="d-block text-muted">{{ ucfirst($row->actor_type) }}</small>
                                </td>
                                <td>{{ ucfirst($row->surface) }}</td>
                                <td>{{ $row->title ?: '—' }}</td>
                                <td class="text-center">{{ $row->messages_count }}</td>
                                <td>{{ optional($row->last_message_at)->format('M j, Y g:i A') ?: '—' }}</td>
                                <td class="text-end">
                                    @can('chat-conversation-view')
                                        <a href="{{ route($route.'.show', $row) }}" class="btn btn-sm btn-outline-primary">{{ __('View') }}</a>
                                    @endcan
                                    @can('chat-conversation-delete')
                                        <form action="{{ route($route.'.destroy', $row) }}" method="post" class="d-inline"
                                              onsubmit="return confirm('{{ __('Delete this conversation and its audit records?') }}');">
                                            @csrf @method('DELETE')
                                            <button class="btn btn-sm btn-outline-danger">{{ __('btn_delete') }}</button>
                                        </form>
                                    @endcan
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="text-center text-muted py-4">{{ __('No conversations yet.') }}</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>

                {{ $rows->links() }}
            </div>
        </div>
    </div>
</div>

@endsection
