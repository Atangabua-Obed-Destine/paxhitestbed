@extends('admin.layouts.master')
@section('title', __('Resit Settings'))
@section('content')
<div class="main-body">
    <div class="page-wrapper">
        <div class="row">
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header">
                        <h5>{{ __('Resit Settings') }}</h5>
                    </div>
                    <div class="card-block">
                        @if(session('success'))
                            <div class="alert alert-success">{{ session('success') }}</div>
                        @endif
                        <form action="{{ route('admin.resit-settings.update') }}" method="post" class="row g-3">
                            @csrf
                            @method('put')
                            <div class="form-group col-md-4">
                                <label for="default_fee" class="form-label">{{ __('Default Resit Fee') }}</label>
                                <input type="number" step="0.01" min="0" name="default_fee" id="default_fee" value="{{ old('default_fee', number_format($currentFee, 2, '.', '')) }}" class="form-control" required>
                                @error('default_fee')
                                    <div class="text-danger small">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-12">
                                <button type="submit" class="btn btn-primary">{{ __('Save Changes') }}</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
