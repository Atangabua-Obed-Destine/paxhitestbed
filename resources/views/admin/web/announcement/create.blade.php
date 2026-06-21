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
                        <h5>{{ __('btn_add_new') }} {{ $title }}</h5>
                    </div>
                    <div class="card-block">
                        <form action="{{ route($route.'.store') }}" method="POST">
                            @csrf

                            <div class="row">
                                <div class="col-md-12">
                                    <div class="form-group">
                                        <label for="language_id">{{ __('field_language') }}</label>
                                        <select name="language_id" class="form-control">
                                            <option value="">-- {{ __('All Languages') }} --</option>
                                            @foreach($languages as $lang)
                                                <option value="{{ $lang->id }}">{{ $lang->name }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>

                                <div class="col-md-12">
                                    <div class="form-group">
                                        <label for="message">{{ __('field_message') }} <span class="text-danger">*</span></label>
                                        <textarea name="message" class="form-control texteditor" rows="8" required></textarea>
                                        <small class="form-text text-muted">You can use HTML formatting for emphasis, links, etc.</small>
                                    </div>
                                </div>

                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="start_date">{{ __('field_start_date') }}</label>
                                        <input type="date" name="start_date" class="form-control">
                                    </div>
                                </div>

                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="end_date">{{ __('field_end_date') }}</label>
                                        <input type="date" name="end_date" class="form-control">
                                    </div>
                                </div>

                                <div class="col-md-12">
                                    <div class="form-group">
                                        <label for="status">{{ __('field_status') }} <span class="text-danger">*</span></label>
                                        <select name="status" class="form-control" required>
                                            <option value="1" selected>{{ __('status_active') }}</option>
                                            <option value="0">{{ __('status_inactive') }}</option>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <div class="form-group">
                                <button type="submit" class="btn btn-primary">{{ __('btn_save') }}</button>
                                <a href="{{ route($route.'.index') }}" class="btn btn-secondary">{{ __('btn_cancel') }}</a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
        <!-- [ Main Content ] end -->
    </div>
</div>
<!-- End Content-->

@endsection
