@extends('admin.layouts.master')
@section('title', __('deleted_journal_entries'))

@section('content')
<!-- Main content -->
<section class="content">
    <div class="container-fluid">
        <div class="row">
            <div class col-md-12">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">
                            <i class="fas fa-trash"></i> {{ __('deleted_journal_entries') }}
                        </h3>
                        <div class="card-tools">
                            <a href="{{ route('admin.journal-entries.index') }}" class="btn btn-primary btn-sm">
                                <i class="fas fa-arrow-left"></i> {{ __('back_to_journal_entries') }}
                            </a>
                        </div>
                    </div>
                    <!-- /.card-header -->
                    <div class="card-body">
                        @if($entries->count() > 0)
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle"></i> 
                            <strong>{{ __('trash_info') }}:</strong> {{ __('deleted_entries_can_be_restored_or_permanently_deleted') }}
                        </div>

                        <div class="table-responsive">
                            <table class="table table-bordered table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th width="10%">{{ __('entry_number') }}</th>
                                        <th width="10%">{{ __('entry_date') }}</th>
                                        <th width="10%">{{ __('journal_type') }}</th>
                                        <th width="25%">{{ __('description') }}</th>
                                        <th width="10%">{{ __('total_debit') }}</th>
                                        <th width="10%">{{ __('total_credit') }}</th>
                                        <th width="10%">{{ __('deleted_at') }}</th>
                                        <th width="15%">{{ __('action') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($entries as $entry)
                                    <tr>
                                        <td>
                                            <span class="badge badge-secondary">{{ $entry->entry_number }}</span>
                                        </td>
                                        <td>{{ $entry->entry_date->format('d M Y') }}</td>
                                        <td>
                                            <span class="badge badge-info">{{ ucfirst($entry->journal_type) }}</span>
                                        </td>
                                        <td>{{ \Str::limit($entry->description, 40) }}</td>
                                        <td class="text-right">{{ number_format($entry->total_debit, 2) }}</td>
                                        <td class="text-right">{{ number_format($entry->total_credit, 2) }}</td>
                                        <td>
                                            <small>{{ $entry->deleted_at->format('d M Y H:i') }}</small>
                                        </td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <!-- Restore Button -->
                                                <button type="button" class="btn btn-success" 
                                                        onclick="event.preventDefault(); if(confirm('{{ __('confirm_restore_journal_entry') }}')) { document.getElementById('restore-form-{{ $entry->id }}').submit(); }">
                                                    <i class="fas fa-undo"></i> {{ __('restore') }}
                                                </button>
                                                <form id="restore-form-{{ $entry->id }}" 
                                                      action="{{ route('admin.journal-entries.restore', $entry->id) }}" 
                                                      method="POST" style="display: none;">
                                                    @csrf
                                                </form>

                                                <!-- Permanent Delete Button -->
                                                <button type="button" class="btn btn-danger" 
                                                        onclick="event.preventDefault(); if(confirm('{{ __('confirm_permanent_delete_warning') }}\n\n{{ __('this_action_cannot_be_undone') }}!')) { document.getElementById('force-delete-form-{{ $entry->id }}').submit(); }">
                                                    <i class="fas fa-trash-alt"></i> {{ __('delete_permanently') }}
                                                </button>
                                                <form id="force-delete-form-{{ $entry->id }}" 
                                                      action="{{ route('admin.journal-entries.force-delete', $entry->id) }}" 
                                                      method="POST" style="display: none;">
                                                    @csrf
                                                    @method('DELETE')
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                    @endforeach
                                </tbody>
                                <tfoot>
                                    <tr>
                                        <th colspan="4" class="text-right">{{ __('total') }}:</th>
                                        <th class="text-right">{{ number_format($entries->sum('total_debit'), 2) }}</th>
                                        <th class="text-right">{{ number_format($entries->sum('total_credit'), 2) }}</th>
                                        <th colspan="2"></th>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>

                        <!-- Pagination -->
                        <div class="mt-3">
                            {{ $entries->links() }}
                        </div>

                        <!-- Bulk Actions -->
                        <div class="mt-3">
                            <div class="alert alert-warning">
                                <h5><i class="fas fa-exclamation-triangle"></i> {{ __('important') }}:</h5>
                                <ul class="mb-0">
                                    <li><strong>{{ __('restore') }}:</strong> {{ __('recover_entry_to_active_list') }}</li>
                                    <li><strong>{{ __('delete_permanently') }}:</strong> {{ __('permanently_remove_cannot_be_undone') }}</li>
                                    <li>{{ __('deleted_entries_kept_for_30_days') }}</li>
                                </ul>
                            </div>
                        </div>

                        @else
                        <div class="alert alert-success text-center">
                            <i class="fas fa-check-circle fa-3x mb-3"></i>
                            <h4>{{ __('trash_is_empty') }}!</h4>
                            <p>{{ __('no_deleted_journal_entries_found') }}</p>
                            <a href="{{ route('admin.journal-entries.index') }}" class="btn btn-primary">
                                <i class="fas fa-arrow-left"></i> {{ __('back_to_journal_entries') }}
                            </a>
                        </div>
                        @endif
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

@push('scripts')
<script>
    $(document).ready(function() {
        // Add confirmation styling
        $('.btn-danger').hover(function() {
            $(this).css('cursor', 'pointer');
        });
    });
</script>
@endpush
