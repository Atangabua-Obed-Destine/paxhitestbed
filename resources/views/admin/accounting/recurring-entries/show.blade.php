@extends('admin.layouts.master')

@section('title', __('Recurring Entry Details'))

@section('content')
<div class="content-wrapper">
    <section class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1>{{ __('Recurring Entry Details') }}</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="{{ route('admin.dashboard.index') }}">{{ __('Dashboard') }}</a></li>
                        <li class="breadcrumb-item"><a href="{{ route('admin.recurring-entries.index') }}">{{ __('Recurring Entries') }}</a></li>
                        <li class="breadcrumb-item active">{{ $recurringEntry->name }}</li>
                    </ol>
                </div>
            </div>
        </div>
    </section>

    <section class="content">
        <div class="container-fluid">
            <div class="row">
                <div class="col-md-8">
                    <!-- Entry Details -->
                    <div class="card card-outline card-primary">
                        <div class="card-header">
                            <h3 class="card-title">{{ __('Entry Information') }}</h3>
                            <div class="card-tools">
                                <a href="{{ route('admin.recurring-entries.edit', $recurringEntry) }}" class="btn btn-warning btn-sm">
                                    <i class="fas fa-edit"></i> {{ __('Edit') }}
                                </a>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <table class="table table-borderless">
                                        <tr>
                                            <th style="width: 40%;">{{ __('Name') }}:</th>
                                            <td>{{ $recurringEntry->name }}</td>
                                        </tr>
                                        <tr>
                                            <th>{{ __('Name (French)') }}:</th>
                                            <td>{{ $recurringEntry->name_fr ?? '-' }}</td>
                                        </tr>
                                        <tr>
                                            <th>{{ __('Frequency') }}:</th>
                                            <td>
                                                <span class="badge badge-info">{{ ucfirst($recurringEntry->frequency) }}</span>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th>{{ __('Status') }}:</th>
                                            <td>
                                                @if($recurringEntry->status === 'active')
                                                    <span class="badge badge-success">{{ __('Active') }}</span>
                                                @elseif($recurringEntry->status === 'paused')
                                                    <span class="badge badge-warning">{{ __('Paused') }}</span>
                                                @else
                                                    <span class="badge badge-secondary">{{ ucfirst($recurringEntry->status) }}</span>
                                                @endif
                                            </td>
                                        </tr>
                                    </table>
                                </div>
                                <div class="col-md-6">
                                    <table class="table table-borderless">
                                        <tr>
                                            <th style="width: 40%;">{{ __('Next Run Date') }}:</th>
                                            <td>
                                                @if($recurringEntry->next_run_date)
                                                    {{ $recurringEntry->next_run_date->format('Y-m-d') }}
                                                    @if($recurringEntry->next_run_date->isPast())
                                                        <span class="badge badge-danger">{{ __('Overdue') }}</span>
                                                    @endif
                                                @else
                                                    -
                                                @endif
                                            </td>
                                        </tr>
                                        <tr>
                                            <th>{{ __('Last Run Date') }}:</th>
                                            <td>{{ $recurringEntry->last_run_date?->format('Y-m-d') ?? '-' }}</td>
                                        </tr>
                                        <tr>
                                            <th>{{ __('End Date') }}:</th>
                                            <td>{{ $recurringEntry->end_date?->format('Y-m-d') ?? __('No End Date') }}</td>
                                        </tr>
                                        <tr>
                                            <th>{{ __('Times Executed') }}:</th>
                                            <td>{{ $recurringEntry->times_executed ?? 0 }}</td>
                                        </tr>
                                    </table>
                                </div>
                            </div>
                            @if($recurringEntry->description)
                            <div class="mt-3">
                                <strong>{{ __('Description') }}:</strong>
                                <p class="text-muted">{{ $recurringEntry->description }}</p>
                            </div>
                            @endif
                        </div>
                    </div>

                    <!-- Journal Entry Lines -->
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">{{ __('Journal Entry Template') }}</h3>
                        </div>
                        <div class="card-body table-responsive p-0">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>{{ __('Account Code') }}</th>
                                        <th>{{ __('Account Name') }}</th>
                                        <th class="text-right">{{ __('Debit') }}</th>
                                        <th class="text-right">{{ __('Credit') }}</th>
                                        <th>{{ __('Description') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @php
                                        $totalDebit = 0;
                                        $totalCredit = 0;
                                    @endphp
                                    @foreach($recurringEntry->lines as $line)
                                    @php
                                        $totalDebit += $line->debit;
                                        $totalCredit += $line->credit;
                                    @endphp
                                    <tr>
                                        <td>{{ $line->account->code ?? '-' }}</td>
                                        <td>{{ $line->account->name ?? '-' }}</td>
                                        <td class="text-right">{{ $line->debit > 0 ? number_format($line->debit) : '' }}</td>
                                        <td class="text-right">{{ $line->credit > 0 ? number_format($line->credit) : '' }}</td>
                                        <td>{{ $line->description ?? '-' }}</td>
                                    </tr>
                                    @endforeach
                                </tbody>
                                <tfoot class="font-weight-bold bg-light">
                                    <tr>
                                        <td colspan="2">{{ __('Total') }}</td>
                                        <td class="text-right">{{ number_format($totalDebit) }}</td>
                                        <td class="text-right">{{ number_format($totalCredit) }}</td>
                                        <td></td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>

                    <!-- Recent Executions -->
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">{{ __('Recent Executions') }}</h3>
                        </div>
                        <div class="card-body table-responsive p-0">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>{{ __('Date') }}</th>
                                        <th>{{ __('Reference') }}</th>
                                        <th>{{ __('Amount') }}</th>
                                        <th>{{ __('Status') }}</th>
                                        <th>{{ __('Actions') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($recurringEntry->journalEntries ?? [] as $entry)
                                    <tr>
                                        <td>{{ $entry->entry_date->format('Y-m-d') }}</td>
                                        <td>{{ $entry->reference_number }}</td>
                                        <td>{{ number_format($entry->lines->sum('debit')) }}</td>
                                        <td>
                                            @if($entry->status === 'posted')
                                                <span class="badge badge-success">{{ __('Posted') }}</span>
                                            @else
                                                <span class="badge badge-secondary">{{ ucfirst($entry->status) }}</span>
                                            @endif
                                        </td>
                                        <td>
                                            <a href="{{ route('admin.journal-entry.show', $entry) }}" class="btn btn-xs btn-info">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                        </td>
                                    </tr>
                                    @empty
                                    <tr>
                                        <td colspan="5" class="text-center text-muted">{{ __('No executions yet') }}</td>
                                    </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div class="col-md-4">
                    <!-- Actions -->
                    <div class="card card-outline card-success">
                        <div class="card-header">
                            <h3 class="card-title">{{ __('Actions') }}</h3>
                        </div>
                        <div class="card-body">
                            @if($recurringEntry->status === 'active')
                            <form action="{{ route('admin.recurring-entries.process', $recurringEntry) }}" method="POST" class="mb-2">
                                @csrf
                                <button type="submit" class="btn btn-success btn-block" onclick="return confirm('{{ __('Process this entry now?') }}')">
                                    <i class="fas fa-play"></i> {{ __('Process Now') }}
                                </button>
                            </form>
                            <form action="{{ route('admin.recurring-entries.pause', $recurringEntry) }}" method="POST" class="mb-2">
                                @csrf
                                <button type="submit" class="btn btn-warning btn-block">
                                    <i class="fas fa-pause"></i> {{ __('Pause') }}
                                </button>
                            </form>
                            <form action="{{ route('admin.recurring-entries.skip-next', $recurringEntry) }}" method="POST" class="mb-2">
                                @csrf
                                <button type="submit" class="btn btn-info btn-block">
                                    <i class="fas fa-forward"></i> {{ __('Skip Next Run') }}
                                </button>
                            </form>
                            @elseif($recurringEntry->status === 'paused')
                            <form action="{{ route('admin.recurring-entries.resume', $recurringEntry) }}" method="POST" class="mb-2">
                                @csrf
                                <button type="submit" class="btn btn-success btn-block">
                                    <i class="fas fa-play"></i> {{ __('Resume') }}
                                </button>
                            </form>
                            @endif

                            <form action="{{ route('admin.recurring-entries.duplicate', $recurringEntry) }}" method="POST" class="mb-2">
                                @csrf
                                <button type="submit" class="btn btn-secondary btn-block">
                                    <i class="fas fa-copy"></i> {{ __('Duplicate') }}
                                </button>
                            </form>

                            <hr>

                            <form action="{{ route('admin.recurring-entries.destroy', $recurringEntry) }}" method="POST">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-danger btn-block" 
                                        onclick="return confirm('{{ __('Are you sure you want to delete this recurring entry?') }}')">
                                    <i class="fas fa-trash"></i> {{ __('Delete') }}
                                </button>
                            </form>
                        </div>
                    </div>

                    <!-- Schedule Info -->
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">{{ __('Schedule Information') }}</h3>
                        </div>
                        <div class="card-body">
                            <ul class="list-unstyled">
                                <li class="mb-2">
                                    <i class="fas fa-calendar-alt text-primary mr-2"></i>
                                    <strong>{{ __('Frequency') }}:</strong> {{ ucfirst($recurringEntry->frequency) }}
                                </li>
                                @if($recurringEntry->day_of_month)
                                <li class="mb-2">
                                    <i class="fas fa-calendar-day text-info mr-2"></i>
                                    <strong>{{ __('Day of Month') }}:</strong> {{ $recurringEntry->day_of_month }}
                                </li>
                                @endif
                                @if($recurringEntry->day_of_week)
                                <li class="mb-2">
                                    <i class="fas fa-calendar-week text-info mr-2"></i>
                                    <strong>{{ __('Day of Week') }}:</strong> {{ $recurringEntry->day_of_week }}
                                </li>
                                @endif
                                <li class="mb-2">
                                    <i class="fas fa-clock text-success mr-2"></i>
                                    <strong>{{ __('Auto Post') }}:</strong> 
                                    {{ $recurringEntry->auto_post ? __('Yes') : __('No') }}
                                </li>
                                @if($recurringEntry->max_occurrences)
                                <li class="mb-2">
                                    <i class="fas fa-hashtag text-warning mr-2"></i>
                                    <strong>{{ __('Max Occurrences') }}:</strong> 
                                    {{ $recurringEntry->times_executed }}/{{ $recurringEntry->max_occurrences }}
                                </li>
                                @endif
                            </ul>
                        </div>
                    </div>

                    <!-- Meta Info -->
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">{{ __('Meta Information') }}</h3>
                        </div>
                        <div class="card-body">
                            <small class="text-muted">
                                <p class="mb-1">
                                    <strong>{{ __('Created by') }}:</strong> {{ $recurringEntry->creator->name ?? '-' }}
                                </p>
                                <p class="mb-1">
                                    <strong>{{ __('Created at') }}:</strong> {{ $recurringEntry->created_at->format('Y-m-d H:i') }}
                                </p>
                                <p class="mb-0">
                                    <strong>{{ __('Last updated') }}:</strong> {{ $recurringEntry->updated_at->format('Y-m-d H:i') }}
                                </p>
                            </small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</div>
@endsection
