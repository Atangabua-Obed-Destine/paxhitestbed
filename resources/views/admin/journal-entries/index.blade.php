@extends('admin.layouts.master')
@section('title', __('journal_entries'))

@section('content')
<!-- Main content -->
<section class="content">
    <div class="container-fluid">
        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">{{ __('journal_entries') }} ({{ __('ecritures_comptables') }})</h3>
                        <div class="card-tools">
                            @can('journal-entry-delete')
                            <a href="{{ route('admin.journal-entries.trash') }}" class="btn btn-warning btn-sm mr-2">
                                <i class="fas fa-trash"></i> {{ __('trash') }} / {{ __('deleted_entries') }}
                            </a>
                            @endcan
                            @can('journal-entry-create')
                            <a href="{{ route('admin.journal-entries.create') }}" class="btn btn-primary btn-sm">
                                <i class="fas fa-plus"></i> {{ __('add_journal_entry') }}
                            </a>
                            @endcan
                        </div>
                    </div>
                    <!-- /.card-header -->
                    <div class="card-body">
                        <!-- Filters -->
                        <div class="row mb-3">
                            <div class="col-md-12">
                                <form action="{{ route('admin.journal-entries.index') }}" method="GET">
                                    <div class="row">
                                        <div class="col-md-2">
                                            <div class="form-group">
                                                <label for="fiscal_year">{{ __('fiscal_year') }}</label>
                                                <select name="fiscal_year" id="fiscal_year" class="form-control form-control-sm">
                                                    <option value="">{{ __('all') }}</option>
                                                    @foreach($fiscalYears as $year)
                                                    <option value="{{ $year->id }}" {{ request('fiscal_year') == $year->id ? 'selected' : '' }}>
                                                        {{ $year->name }}
                                                    </option>
                                                    @endforeach
                                                </select>
                                            </div>
                                        </div>
                                        <div class="col-md-2">
                                            <div class="form-group">
                                                <label for="journal_type">{{ __('journal_type') }}</label>
                                                <select name="journal_type" id="journal_type" class="form-control form-control-sm">
                                                    <option value="">{{ __('all') }}</option>
                                                    <option value="general" {{ request('journal_type') == 'general' ? 'selected' : '' }}>{{ __('general') }}</option>
                                                    <option value="sales" {{ request('journal_type') == 'sales' ? 'selected' : '' }}>{{ __('sales') }}</option>
                                                    <option value="purchase" {{ request('journal_type') == 'purchase' ? 'selected' : '' }}>{{ __('purchase') }}</option>
                                                    <option value="cash" {{ request('journal_type') == 'cash' ? 'selected' : '' }}>{{ __('cash') }}</option>
                                                    <option value="bank" {{ request('journal_type') == 'bank' ? 'selected' : '' }}>{{ __('bank') }}</option>
                                                    <option value="adjustment" {{ request('journal_type') == 'adjustment' ? 'selected' : '' }}>{{ __('adjustment') }}</option>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="col-md-2">
                                            <div class="form-group">
                                                <label for="is_posted">{{ __('status_title') }}</label>
                                                <select name="is_posted" id="is_posted" class="form-control form-control-sm">
                                                    <option value="">{{ __('all') }}</option>
                                                    <option value="1" {{ request('is_posted') == '1' ? 'selected' : '' }}>{{ __('posted') }}</option>
                                                    <option value="0" {{ request('is_posted') == '0' ? 'selected' : '' }}>{{ __('draft') }}</option>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="col-md-2">
                                            <div class="form-group">
                                                <label for="start_date">{{ __('start_date') }}</label>
                                                <input type="date" name="start_date" id="start_date" class="form-control form-control-sm" value="{{ request('start_date') }}">
                                            </div>
                                        </div>
                                        <div class="col-md-2">
                                            <div class="form-group">
                                                <label for="end_date">{{ __('end_date') }}</label>
                                                <input type="date" name="end_date" id="end_date" class="form-control form-control-sm" value="{{ request('end_date') }}">
                                            </div>
                                        </div>
                                        <div class="col-md-2">
                                            <div class="form-group">
                                                <label>&nbsp;</label>
                                                <button type="submit" class="btn btn-primary btn-sm btn-block">
                                                    <i class="fas fa-filter"></i> {{ __('filter') }}
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>

                        <!-- Entries Table -->
                        <div class="table-responsive">
                            <table class="table table-bordered table-hover">
                                <thead>
                                    <tr>
                                        <th width="5%">{{ __('sl') }}</th>
                                        <th width="12%">{{ __('entry_number') }}</th>
                                        <th width="10%">{{ __('date') }}</th>
                                        <th width="10%">{{ __('journal_type') }}</th>
                                        <th>{{ __('description') }}</th>
                                        <th width="10%" class="text-right">{{ __('total_debit') }}</th>
                                        <th width="10%" class="text-right">{{ __('total_credit') }}</th>
                                        <th width="8%">{{ __('status_title') }}</th>
                                        <th width="15%">{{ __('action') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($entries as $key => $entry)
                                    <tr>
                                        <td>{{ $entries->firstItem() + $key }}</td>
                                        <td><strong>{{ $entry->entry_number }}</strong></td>
                                        <td>{{ \Carbon\Carbon::parse($entry->entry_date)->format('d M Y') }}</td>
                                        <td>
                                            <span class="badge badge-info">{{ __(strtolower($entry->journal_type)) }}</span>
                                        </td>
                                        <td>{{ $entry->description }}</td>
                                        <td class="text-right"><strong>{{ number_format($entry->total_debit, 0, ',', ' ') }}</strong></td>
                                        <td class="text-right"><strong>{{ number_format($entry->total_credit, 0, ',', ' ') }}</strong></td>
                                        <td>
                                            @if($entry->is_posted)
                                            <span class="badge badge-success">{{ __('posted') }}</span>
                                            @else
                                            <span class="badge badge-warning">{{ __('draft') }}</span>
                                            @endif
                                        </td>
                                        <td>
                                            @can('journal-entry-view')
                                            <a href="{{ route('admin.journal-entries.show', $entry->id) }}" class="btn btn-info btn-xs" title="{{ __('view') }}">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            @endcan
                                            
                                            @can('journal-entry-edit')
                                            @if(!$entry->is_posted)
                                            <a href="{{ route('admin.journal-entries.edit', $entry->id) }}" class="btn btn-primary btn-xs" title="{{ __('edit') }}">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            @endif
                                            @endcan
                                            
                                            @can('journal-entry-post')
                                            @if(!$entry->is_posted)
                                            <form action="{{ route('admin.journal-entries.post', $entry->id) }}" method="POST" style="display: inline-block;">
                                                @csrf
                                                <button type="submit" class="btn btn-success btn-xs" title="{{ __('post_entry') }}" onclick="return confirm('{{ __('confirm_post_entry') }}')">
                                                    <i class="fas fa-check"></i>
                                                </button>
                                            </form>
                                            @else
                                            <form action="{{ route('admin.journal-entries.unpost', $entry->id) }}" method="POST" style="display: inline-block;">
                                                @csrf
                                                <button type="submit" class="btn btn-warning btn-xs" title="{{ __('unpost_entry') }}" onclick="return confirm('{{ __('confirm_unpost_entry') }}')">
                                                    <i class="fas fa-undo"></i>
                                                </button>
                                            </form>
                                            @endif
                                            @endcan
                                            
                                            @can('journal-entry-create')
                                            <form action="{{ route('admin.journal-entries.duplicate', $entry->id) }}" method="POST" style="display: inline-block;">
                                                @csrf
                                                <button type="submit" class="btn btn-secondary btn-xs" title="{{ __('duplicate') }}" onclick="return confirm('{{ __('confirm_duplicate_entry') }}')">
                                                    <i class="fas fa-copy"></i>
                                                </button>
                                            </form>
                                            @endcan
                                            
                                            @can('journal-entry-delete')
                                            @if(!$entry->is_posted && !$entry->is_system_generated)
                                            <form action="{{ route('admin.journal-entries.destroy', $entry->id) }}" method="POST" style="display: inline-block;">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-danger btn-xs" onclick="return confirm('{{ __('are_you_sure') }}')" title="{{ __('delete') }}">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </form>
                                            @endif
                                            @endcan
                                        </td>
                                    </tr>
                                    @empty
                                    <tr>
                                        <td colspan="9" class="text-center">{{ __('no_data_found') }}</td>
                                    </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>

                        <!-- Pagination -->
                        <div class="mt-3">
                            {{ $entries->appends(request()->query())->links() }}
                        </div>
                    </div>
                    <!-- /.card-body -->
                </div>
                <!-- /.card -->
            </div>
            <!-- /.col -->
        </div>
        <!-- /.row -->
    </div>
    <!-- /.container-fluid -->
</section>
<!-- /.content -->
@endsection
