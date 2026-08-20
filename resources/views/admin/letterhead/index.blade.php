@extends('admin.layouts.master')

@section('content')
<div class="pcoded-content">
    <div class="pcoded-inner-content">
        <div class="main-body">
            <div class="page-wrapper">

                <div class="page-header">
                    <div class="row align-items-end">
                        <div class="col-lg-8">
                            <div class="page-header-title">
                                <div class="d-inline">
                                    <h4>{{ __('Letterhead') }}</h4>
                                    <span>{{ __('Configured once here, and used by every document the system prints or downloads.') }}</span>
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-4 text-right">
                            <a href="{{ route('admin.letterhead.preview') }}" target="_blank" class="btn btn-outline-primary btn-sm">
                                <i class="fas fa-file-pdf"></i> {{ __('Preview as PDF') }}
                            </a>
                        </div>
                    </div>
                </div>

                <div class="page-body">
                    <form action="{{ route('admin.letterhead.update') }}" method="post">
                        @csrf

                        <div class="card">
                            <div class="card-header"><h5>{{ __('How documents should be headed') }}</h5></div>
                            <div class="card-block">
                                <div class="row">
                                    <div class="form-group col-md-4">
                                        <label for="mode" class="form-label">{{ __('Mode') }} <span>*</span></label>
                                        <select class="form-control" name="mode" id="mode" required>
                                            <option value="html" {{ $row->mode === 'html' ? 'selected' : '' }}>
                                                {{ __('Print the letterhead below') }}
                                            </option>
                                            <option value="reserve_space" {{ $row->mode === 'reserve_space' ? 'selected' : '' }}>
                                                {{ __('Leave space — paper already has a letterhead') }}
                                            </option>
                                            <option value="none" {{ $row->mode === 'none' ? 'selected' : '' }}>
                                                {{ __('No letterhead at all') }}
                                            </option>
                                        </select>
                                    </div>

                                    <div class="form-group col-md-4">
                                        <label for="reserve_height_mm" class="form-label">{{ __('Space to leave (mm)') }}</label>
                                        <input type="number" min="0" max="150" class="form-control"
                                               name="reserve_height_mm" id="reserve_height_mm"
                                               value="{{ old('reserve_height_mm', $row->reserve_height_mm) }}">
                                        <small class="text-muted">
                                            {{ __('Only used when leaving space for pre-printed paper.') }}
                                        </small>
                                    </div>

                                    <div class="form-group col-md-4">
                                        <label class="form-label d-block">{{ __('Active') }}</label>
                                        <div class="switch d-inline">
                                            <input type="checkbox" id="status" name="status" value="1"
                                                   {{ $row->status ? 'checked' : '' }}>
                                            <label for="status" class="cr"></label>
                                        </div>
                                        <small class="text-muted d-block">
                                            {{ __('Switch off to suspend it without losing what is written below.') }}
                                        </small>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="card">
                            <div class="card-header">
                                <h5>{{ __('The letterhead') }}</h5>
                            </div>
                            <div class="card-block">
                                <p class="text-muted small">
                                    {{ __('This appears at the top of the first page of a document — not on every page. Images pasted or uploaded here are embedded when a document is downloaded, so a printed copy looks the same as the screen.') }}
                                </p>

                                <textarea class="form-control texteditor" name="html" rows="14">{{ old('html', $row->html) }}</textarea>

                                <div class="mt-3">
                                    <span class="text-muted small">{{ __('These are replaced automatically:') }}</span>
                                    @foreach($tokens as $token => $value)
                                        <code class="mr-2" title="{{ $value ?: __('not set in Settings') }}">{{ $token }}</code>
                                    @endforeach
                                </div>
                            </div>
                            <div class="card-footer text-right">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save"></i> {{ __('Save letterhead') }}
                                </button>
                            </div>
                        </div>
                    </form>
                </div>

            </div>
        </div>
    </div>
</div>
@endsection
