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
                        <h5>{{ $title }} {{ __('list') }}</h5>
                    </div>
                    <div class="card-block">
                        @can($access.'-create')
                        <a href="{{ route($route.'.create') }}" class="btn btn-success mb-3">
                            <i class="fas fa-plus"></i> {{ __('btn_create') }} {{ $title }}
                        </a>
                        @endcan

                        <a href="{{ route('admin.tax-setting.index') }}" class="btn btn-secondary mb-3">
                            <i class="fas fa-list"></i> {{ __('standalone_brackets') }}
                        </a>

                        <!-- [ Data table ] start -->
                        <div class="table-responsive">
                            <table id="basic-table" class="display table nowrap table-striped table-hover" style="width:100%">
                                <thead>
                                    <tr>
                                        <th>{{ __('field_order') }}</th>
                                        <th>{{ __('field_title') }}</th>
                                        <th>{{ __('field_code') }}</th>
                                        <th>{{ __('field_type') }}</th>
                                        <th>{{ __('field_brackets_count') }}</th>
                                        <th>{{ __('field_effective_dates') }}</th>
                                        <th>{{ __('field_status') }}</th>
                                        <th>{{ __('field_action') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($rows as $row)
                                    <tr>
                                        <td><span class="badge badge-secondary">{{ $row->display_order }}</span></td>
                                        <td><strong>{{ $row->title }}</strong></td>
                                        <td>{{ $row->code ?? '-' }}</td>
                                        <td>
                                            @if($row->is_progressive)
                                            <span class="badge badge-info">{{ __('progressive') }}</span>
                                            @else
                                            <span class="badge badge-warning">{{ __('flat_rate') }}</span>
                                            @endif
                                        </td>
                                        <td>
                                            <span class="badge badge-primary">{{ $row->brackets->count() }} {{ __('brackets') }}</span>
                                        </td>
                                        <td>
                                            @if($row->effective_from || $row->effective_to)
                                                <small>
                                                    {{ $row->effective_from ? $row->effective_from->format('d M Y') : __('always') }}
                                                    -
                                                    {{ $row->effective_to ? $row->effective_to->format('d M Y') : __('forever') }}
                                                </small>
                                            @else
                                                <span class="text-muted">{{ __('always_effective') }}</span>
                                            @endif
                                        </td>
                                        <td>
                                            @if($row->status)
                                            <span class="badge badge-pill badge-success">{{ __('status_active') }}</span>
                                            @else
                                            <span class="badge badge-pill badge-danger">{{ __('status_inactive') }}</span>
                                            @endif
                                        </td>
                                        <td>
                                            <a href="{{ route($route.'.show', $row->id) }}" class="btn btn-icon btn-info btn-sm" title="{{ __('btn_view') }}">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            
                                            @can($access.'-edit')
                                            <a href="{{ route($route.'.edit', $row->id) }}" class="btn btn-icon btn-primary btn-sm" title="{{ __('btn_edit') }}">
                                                <i class="far fa-edit"></i>
                                            </a>
                                            @endcan

                                            @can($access.'-delete')
                                            <button type="button" class="btn btn-icon btn-danger btn-sm" data-bs-toggle="modal" data-bs-target="#deleteModal-{{ $row->id }}">
                                                <i class="fas fa-trash-alt"></i>
                                            </button>
                                            
                                            <!-- Delete Modal -->
                                            <div id="deleteModal-{{ $row->id }}" class="modal fade" tabindex="-1" role="dialog">
                                                <div class="modal-dialog" role="document">
                                                    <div class="modal-content">
                                                        <div class="modal-header">
                                                            <h5 class="modal-title">{{ __('modal_delete') }}</h5>
                                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                        </div>
                                                        <div class="modal-body">
                                                            <p>{{ __('delete_confirm') }} <strong>{{ $row->title }}</strong>?</p>
                                                            <p class="text-danger"><i class="fas fa-exclamation-triangle"></i> {{ __('delete_group_warning') }}</p>
                                                        </div>
                                                        <div class="modal-footer">
                                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ __('btn_close') }}</button>
                                                            <form action="{{ route($route.'.destroy', $row->id) }}" method="post" style="display: inline;">
                                                                @csrf
                                                                @method('DELETE')
                                                                <button type="submit" class="btn btn-danger">{{ __('btn_delete') }}</button>
                                                            </form>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            @endcan
                                        </td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        <!-- [ Data table ] end -->
                    </div>
                </div>
            </div>
        </div>
        <!-- [ Main Content ] end -->
    </div>
</div>
<!-- End Content-->

@endsection
