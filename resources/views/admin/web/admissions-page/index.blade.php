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
                        <div class="table-responsive">
                            <table class="display table data-table table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>{{ __('field_title') }}</th>
                                        <th>{{ __('field_language') }}</th>
                                        <th>{{ __('field_banner') }}</th>
                                        <th>{{ __('field_status') }}</th>
                                        <th>{{ __('field_action') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                  @foreach( $rows as $key => $row )
                                    <tr>
                                        <td>{{ $key + 1 }}</td>
                                        <td>{{ $row->title }}</td>
                                        <td>
                                            @if(isset($row->language->name))
                                            <span class="badge badge-primary">{{ $row->language->name }}</span>
                                            @endif
                                        </td>
                                        <td>
                                            @if(isset($row->banner_image) && upload_exists($path.'/'.$row->banner_image))
                                            <img src="{{ upload_asset($path.'/'.$row->banner_image) }}" class="img-thumbnail" style="max-height: 50px; max-width: 100px;" alt="{{ __('field_banner') }}">
                                            @else
                                            <span class="text-muted">No image</span>
                                            @endif
                                        </td>
                                        <td>
                                            @if($row->status == 1)
                                            <span class="badge badge-success">{{ __('status_active') }}</span>
                                            @else
                                            <span class="badge badge-danger">{{ __('status_inactive') }}</span>
                                            @endif
                                        </td>
                                        <td>
                                            @can($access.'-edit')
                                            <a href="{{ route($route.'.edit', $row->id) }}" class="btn btn-info btn-sm">
                                                <i class="fas fa-edit"></i> {{ __('btn_edit') }}
                                            </a>
                                            @endcan
                                        </td>
                                    </tr>
                                  @endforeach
                                </tbody>
                            </table>
                        </div>
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

