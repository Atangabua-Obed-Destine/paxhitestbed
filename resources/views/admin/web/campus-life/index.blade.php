@extends('admin.layouts.master')
@section('title', $title)
@section('content')

<!-- Start Content-->
<div class="main-body">
    <div class="page-wrapper">
        <!-- [ Main Content ] start -->
        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header">
                        <h5>{{ $title }} {{ __('list') }}</h5>
                        @can($access.'-create')
                        <a href="{{ route($route.'.create') }}" class="btn btn-sm btn-primary">
                            <i class="fas fa-plus"></i> {{ __('btn_add') }} {{ $title }}
                        </a>
                        @endcan
                    </div>
                    <div class="card-block">
                        <!-- [ Data table ] start -->
                        <div class="table-responsive">
                            <table id="basic-table" class="display table nowrap table-striped table-hover" style="width:100%">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>{{ __('field_title') }}</th>
                                        <th>{{ __('field_type') }}</th>
                                        <th>{{ __('field_language') }}</th>
                                        <th>{{ __('field_photo') }}</th>
                                        <th>{{ __('field_icon') }}</th>
                                        <th>{{ __('field_sort_order') }}</th>
                                        <th>{{ __('field_status') }}</th>
                                        <th>{{ __('field_action') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                  @foreach( $rows as $key => $row )
                                    <tr>
                                        <td>{{ $key + 1 }}</td>
                                        <td>{{ $row->title }}</td>
                                        <td><span class="badge badge-info">{{ ucfirst($row->section_type) }}</span></td>
                                        <td>
                                            @if(isset($row->language))
                                            <span class="badge badge-primary">{{ $row->language->name }}</span>
                                            @endif
                                        </td>
                                        <td>
                                            @if($row->image && upload_exists($path.'/'.$row->image))
                                            <img src="{{ upload_asset($path.'/'.$row->image) }}" alt="{{ $row->title }}" height="50">
                                            @else
                                            <span class="text-muted">{{ __('no_image') }}</span>
                                            @endif
                                        </td>
                                        <td>
                                            @if($row->icon)
                                            <i class="{{ $row->icon }} fa-2x"></i>
                                            @else
                                            -
                                            @endif
                                        </td>
                                        <td>{{ $row->sort_order }}</td>
                                        <td>
                                            @if($row->status == 1)
                                            <span class="badge badge-success">{{ __('status_active') }}</span>
                                            @else
                                            <span class="badge badge-danger">{{ __('status_inactive') }}</span>
                                            @endif
                                        </td>
                                        <td>
                                            @can($access.'-edit')
                                            <a href="{{ route($route.'.edit', $row->id) }}" class="btn btn-icon btn-sm btn-primary" title="{{ __('btn_edit') }}">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            @endcan
                                            
                                            @can($access.'-delete')
                                            <button type="button" class="btn btn-icon btn-sm btn-danger" data-toggle="modal" data-target="#deleteModal-{{ $row->id }}" title="{{ __('btn_delete') }}">
                                                <i class="fas fa-trash-alt"></i>
                                            </button>
                                            
                                            <!-- Delete Modal -->
                                            <div class="modal fade" id="deleteModal-{{ $row->id }}" tabindex="-1" role="dialog" aria-hidden="true">
                                                <div class="modal-dialog modal-dialog-centered" role="document">
                                                    <form class="needs-validation" action="{{ route($route.'.destroy', $row->id) }}" method="post">
                                                    @csrf
                                                    @method('DELETE')
                                                    <div class="modal-content">
                                                        <div class="modal-header">
                                                            <h5 class="modal-title">{{ __('modal_delete') }}</h5>
                                                            <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                                                <span aria-hidden="true">&times;</span>
                                                            </button>
                                                        </div>
                                                        <div class="modal-body">
                                                            {{ __('modal_delete_description') }}
                                                        </div>
                                                        <div class="modal-footer">
                                                            <button type="button" class="btn btn-sm btn-secondary" data-dismiss="modal">{{ __('btn_close') }}</button>
                                                            <button type="submit" class="btn btn-sm btn-danger">{{ __('btn_delete') }}</button>
                                                        </div>
                                                    </div>
                                                    </form>
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

@section('page_js')
    <script type="text/javascript">
        "use strict";
        $(".data-table").DataTable({
            responsive: true,
            aLengthMenu: [
                [10, 30, 50, -1],
                [10, 30, 50, "All"]
            ],
            columnDefs: [{
                targets: -1,
                orderable: false
            }],
            language: {
                search: "_INPUT_",
                searchPlaceholder: "{{ __('btn_search') }}",
                sLengthMenu: "_MENU_items/page",
            }
        });
    </script>
@endsection

