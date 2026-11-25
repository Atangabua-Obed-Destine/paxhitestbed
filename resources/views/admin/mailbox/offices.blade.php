@extends('admin.layouts.master')
@section('title', $title)
@section('content')
<div class="main-body">
    <div class="page-wrapper">
        <div class="card">
            <div class="card-header">
                <h5><i class="fas fa-building"></i> {{ $title }}</h5>
                <p class="text-muted mb-0" style="max-width:60rem">{{ __('Offices students can write to from their Messages page, such as the Bursary or the Registry. Everyone you add to an office sees its conversations and can reply.') }}</p>
            </div>
            <div class="card-block">
                <a href="{{ route('admin.mailbox.index') }}" class="btn btn-sm btn-outline-secondary mb-3"><i class="fas fa-arrow-left"></i> {{ __('Back to Mailbox') }}</a>
                <div class="table-responsive">
                    <table class="table table-striped align-middle">
                        <thead><tr><th>{{ __('Office') }}</th><th>{{ __('Staff who answer') }}</th><th>{{ __('Open to students') }}</th><th></th></tr></thead>
                        <tbody>
                        @forelse($offices as $office)
                            <tr>
                                <td><strong>{{ $office->title }}</strong>@if($office->description)<div class="text-muted small">{{ $office->description }}</div>@endif</td>
                                <td>{{ $office->members->map(fn ($m) => trim($m->first_name . ' ' . $m->last_name))->implode(', ') ?: '—' }}</td>
                                <td>{!! $office->status ? '<span class="badge bg-success">' . e(__('Yes')) . '</span>' : '<span class="badge bg-secondary">' . e(__('No')) . '</span>' !!}</td>
                                <td class="text-end text-nowrap">
                                    <button class="btn btn-sm btn-outline-primary" type="button" data-bs-toggle="collapse" data-bs-target="#office-edit-{{ $office->id }}"><i class="fas fa-edit"></i> {{ __('Edit') }}</button>
                                    <form class="d-inline" method="post" action="{{ route('admin.mailbox.offices.destroy', $office->id) }}" onsubmit="return confirm('{{ __('Remove this office? Students can no longer start new conversations with it.') }}')">
                                        @csrf @method('DELETE')
                                        <button class="btn btn-sm btn-outline-danger" type="submit"><i class="fas fa-trash-alt"></i></button>
                                    </form>
                                </td>
                            </tr>
                            <tr class="collapse" id="office-edit-{{ $office->id }}">
                                <td colspan="4">
                                    @include('admin.mailbox._office_form', ['office' => $office, 'action' => route('admin.mailbox.offices.update', $office->id), 'method' => 'PUT'])
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="text-muted">{{ __('No offices yet. Students can still write to their own lecturers.') }}</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>

                <h6 class="mt-4">{{ __('Add an office') }}</h6>
                @include('admin.mailbox._office_form', ['office' => null, 'action' => route('admin.mailbox.offices.store'), 'method' => 'POST'])
            </div>
        </div>
    </div>
</div>
@endsection
