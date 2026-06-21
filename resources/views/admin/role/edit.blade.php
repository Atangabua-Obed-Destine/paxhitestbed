@extends('admin.layouts.master')
@section('title', $title)
@section('content')

<!-- Start Content-->
<div class="main-body">
    <div class="page-wrapper">
        <!-- [ Main Content ] start -->
        <div class="row">
            <!-- [ Card ] start -->
            <div class="col-md-12 col-lg-8">
                <div class="card">
                    <div class="card-header">
                        <h5>{{ __('modal_edit') }} {{ $title }}</h5>
                    </div>
                    <div class="card-block">
                        <a href="{{ route($route.'.index') }}" class="btn btn-primary"><i class="fas fa-arrow-left"></i> {{ __('btn_back') }}</a>

                        <a href="{{ route($route.'.edit', $role->id) }}" class="btn btn-info"><i class="fas fa-sync-alt"></i> {{ __('btn_refresh') }}</a>
                    </div>

                    <form class="needs-validation" novalidate action="{{ route($route.'.update', [$role->id]) }}" method="post" enctype="multipart/form-data">
                    @csrf
                    @method('PUT')
                    <div class="card-block">

                        <!-- Form Start -->
                        <div class="form-group">
                            <label for="name">{{ __('field_title') }} <span>*</span></label>
                            <input type="text" class="form-control" name="name" id="name" value="{{ $role->name }}" required>

                            <div class="invalid-feedback">
                              {{ __('required_field') }} {{ __('field_title') }}
                            </div>
                        </div>

                        <div class="form-group">
                            <button type="button" class="btn btn-primary" id="select-all-permissions">
                                <i class="fas fa-check-square"></i> Select All Permissions
                            </button>
                        </div>

                        @php
                            $separation = '0';
                        @endphp
                              
                        @foreach($permission as $value) 

                        @if($separation != $value->group)
                            <hr/>
                            <div class="d-flex justify-content-between align-items-center mt-4 mb-2">
                                <h6 class="text-primary mb-0">{{ $value->group }}</h6>
                                <button type="button" class="btn btn-sm btn-outline-primary select-all-btn" data-group="{{ str_replace(' ', '-', strtolower($value->group)) }}">
                                    <i class="fas fa-check-double"></i> Select All
                                </button>
                            </div>
                        @endif

                        <div class="form-group d-inline permission-item" style="margin-right: 40px;" data-group="{{ str_replace(' ', '-', strtolower($value->group)) }}">
                            <div class="checkbox d-inline">
                                <input type="checkbox" id="checkbox-{{ $value->id }}" name="permission[]" value="{{ $value->id }}" class="permission-checkbox"

                                @foreach($rolePermissions as $rolePermission)
                                    @if($rolePermission->permission_id == $value->id) checked @endif
                                @endforeach  
                                >

                                <label for="checkbox-{{ $value->id }}" class="cr">{{ $value->title }}</label>
                            </div>
                        </div>

                        @php
                            $separation = $value->group;
                        @endphp

                        @endforeach
                        <!-- Form End -->

                    </div>
                    <div class="card-footer">
                        <button type="submit" class="btn btn-success"><i class="fas fa-check"></i> {{ __('btn_update') }}</button>
                    </div>
                    </form>
                </div>
            </div>
            <!-- [ Card ] end -->
        </div>
        <!-- [ Main Content ] end -->
    </div>
</div>
<!-- End Content-->

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Handle overall Select All Permissions button
    const selectAllBtn = document.getElementById('select-all-permissions');
    if (selectAllBtn) {
        selectAllBtn.addEventListener('click', function() {
            const allCheckboxes = document.querySelectorAll('.permission-checkbox');
            const allChecked = Array.from(allCheckboxes).every(cb => cb.checked);
            
            allCheckboxes.forEach(function(checkbox) {
                checkbox.checked = !allChecked;
            });
            
            // Update button text
            if (allChecked) {
                this.innerHTML = '<i class="fas fa-check-square"></i> Select All Permissions';
            } else {
                this.innerHTML = '<i class="fas fa-times-circle"></i> Deselect All Permissions';
            }
        });
    }

    // Handle category Select All buttons
    document.querySelectorAll('.select-all-btn').forEach(function(button) {
        button.addEventListener('click', function() {
            const group = this.getAttribute('data-group');
            const checkboxes = document.querySelectorAll('.permission-item[data-group="' + group + '"] .permission-checkbox');
            const allChecked = Array.from(checkboxes).every(cb => cb.checked);
            
            checkboxes.forEach(function(checkbox) {
                checkbox.checked = !allChecked;
            });
            
            // Update button text
            if (allChecked) {
                this.innerHTML = '<i class="fas fa-check-double"></i> Select All';
            } else {
                this.innerHTML = '<i class="fas fa-times"></i> Deselect All';
            }
        });
    });
});
</script>

@endsection