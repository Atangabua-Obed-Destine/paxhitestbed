@extends('admin.layouts.master')
@section('title', $title)
@section('content')

<div class="main-body">
    <div class="page-wrapper">
        <div class="row">
            <div class="col-md-12 col-lg-9">
                <form class="needs-validation" novalidate action="{{ route($route.'.update') }}" method="post">
                @csrf

                    <div class="card">
                        <div class="card-header">
                            <h5>{{ $title }}</h5>
                            <span class="text-muted d-block mt-1" style="font-size:.85rem;">
                                {{ __('The assistant answers only from data the signed-in user is already entitled to see.') }}
                            </span>
                        </div>
                        <div class="card-block">
                            <div class="row">

                                <div class="form-group col-md-6">
                                    <label for="title" class="form-label">{{ __('Widget title') }} <span>*</span></label>
                                    <input type="text" class="form-control" name="title" id="title" value="{{ old('title', $row->title) }}" required>
                                    <div class="invalid-feedback">{{ __('required_field') }}</div>
                                </div>

                                <div class="form-group col-md-6 d-flex align-items-end">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" value="1" name="is_enabled" id="is_enabled" {{ old('is_enabled', $row->is_enabled) ? 'checked' : '' }}>
                                        <label class="form-check-label fw-bold" for="is_enabled">{{ __('Enable the assistant') }}</label>
                                        <small class="d-block text-muted">{{ __('Global kill switch. When off, the widget is hidden everywhere.') }}</small>
                                    </div>
                                </div>

                            </div>
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-header"><h5>{{ __('Where the widget appears') }}</h5></div>
                        <div class="card-block">
                            <div class="row">
                                @php
                                    $surfaceLabels = [
                                        'web' => __('Public website'),
                                        'application' => __('Application portal'),
                                        'student' => __('Student portal'),
                                        'admin' => __('Admin portal'),
                                    ];
                                @endphp
                                @foreach($surfaces as $surface => $column)
                                    <div class="form-group col-md-3">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" value="1" name="{{ $column }}" id="{{ $column }}" {{ old($column, $row->{$column}) ? 'checked' : '' }}>
                                            <label class="form-check-label" for="{{ $column }}">{{ $surfaceLabels[$surface] }}</label>
                                        </div>
                                    </div>
                                @endforeach
                            </div>

                            <div class="row mt-3">
                                @foreach($surfaces as $surface => $column)
                                    <div class="form-group col-md-6">
                                        <label for="greeting_{{ $surface }}" class="form-label">{{ __('Greeting') }} — {{ $surfaceLabels[$surface] }}</label>
                                        <textarea class="form-control" rows="2" name="greeting_{{ $surface }}" id="greeting_{{ $surface }}">{{ old('greeting_'.$surface, $row->{'greeting_'.$surface}) }}</textarea>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-header"><h5>{{ __('Model') }}</h5></div>
                        <div class="card-block">
                            <div class="row">
                                <div class="form-group col-md-4">
                                    <label for="model" class="form-label">{{ __('Model') }} <span>*</span></label>
                                    <input type="text" class="form-control" name="model" id="model" value="{{ old('model', $row->model) }}" list="model-options" required>
                                    <small class="text-muted">{{ __('Provider') }}: {{ $row->provider }}. {{ __('The free tier allows 20 requests per day per model, so switching model here starts a fresh daily allowance.') }}</small><datalist id="model-options"><option value="gemini-3.1-flash-lite"><option value="gemini-3.5-flash-lite"><option value="gemini-3.5-flash"><option value="gemini-3.6-flash"><option value="gemini-3.7-flash"><option value="gemini-3-flash-preview"></datalist>
                                </div>
                                <div class="form-group col-md-2">
                                    <label for="temperature" class="form-label">{{ __('Temperature') }} <span>*</span></label>
                                    <input type="number" step="0.05" min="0" max="2" class="form-control" name="temperature" id="temperature" value="{{ old('temperature', $row->temperature) }}" required>
                                </div>
                                <div class="form-group col-md-3">
                                    <label for="max_output_tokens" class="form-label">{{ __('Max output tokens') }} <span>*</span></label>
                                    <input type="number" min="128" max="8192" class="form-control" name="max_output_tokens" id="max_output_tokens" value="{{ old('max_output_tokens', $row->max_output_tokens) }}" required>
                                </div>
                                <div class="form-group col-md-3">
                                    <label for="max_tool_calls" class="form-label">{{ __('Max tool calls per message') }} <span>*</span></label>
                                    <input type="number" min="1" max="8" class="form-control" name="max_tool_calls" id="max_tool_calls" value="{{ old('max_tool_calls', $row->max_tool_calls) }}" required>
                                    <small class="text-muted">{{ __('Bounds cost and latency.') }}</small>
                                </div>

                                <div class="form-group col-md-12">
                                    <label for="system_prompt" class="form-label">{{ __('Extra instructions') }}</label>
                                    <textarea class="form-control" rows="4" name="system_prompt" id="system_prompt" placeholder="{{ __('Optional house style, escalation wording, things the assistant should never promise.') }}">{{ old('system_prompt', $row->system_prompt) }}</textarea>
                                    <small class="text-muted">{{ __('Appended to the generated prompt. It cannot widen what data the assistant may read.') }}</small>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-header"><h5>{{ __('Limits and retention') }}</h5></div>
                        <div class="card-block">
                            <div class="row">
                                <div class="form-group col-md-4">
                                    <label for="rate_limit_per_minute" class="form-label">{{ __('Messages per minute, per user') }} <span>*</span></label>
                                    <input type="number" min="1" max="120" class="form-control" name="rate_limit_per_minute" id="rate_limit_per_minute" value="{{ old('rate_limit_per_minute', $row->rate_limit_per_minute) }}" required>
                                </div>
                                <div class="form-group col-md-4">
                                    <label for="history_retention_days" class="form-label">{{ __('Keep conversations for (days)') }} <span>*</span></label>
                                    <input type="number" min="1" max="3650" class="form-control" name="history_retention_days" id="history_retention_days" value="{{ old('history_retention_days', $row->history_retention_days) }}" required>
                                </div>
                                <div class="form-group col-md-4 d-flex align-items-end">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" value="1" name="escalation_enabled" id="escalation_enabled" {{ old('escalation_enabled', $row->escalation_enabled) ? 'checked' : '' }}>
                                        <label class="form-check-label" for="escalation_enabled">{{ __('Allow "talk to a person"') }}</label>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="card-footer text-end">
                            <button type="submit" class="btn btn-primary">{{ __('btn_save') }}</button>
                        </div>
                    </div>

                </form>
            </div>

            <div class="col-md-12 col-lg-3">
                <div class="card">
                    <div class="card-header"><h5>{{ __('How access is controlled') }}</h5></div>
                    <div class="card-block">
                        <p class="text-muted" style="font-size:.85rem;">
                            {{ __('The assistant cannot write queries. It chooses from a fixed list of read-only capabilities, and the subject of every lookup is taken from the signed-in session — never from the question.') }}
                        </p>
                        <ul class="text-muted" style="font-size:.85rem;">
                            <li>{{ __('Students reach only their own records.') }}</li>
                            <li>{{ __('Applicants reach only their own application.') }}</li>
                            <li>{{ __('Visitors reach only public information.') }}</li>
                            <li>{{ __('Staff are limited by their existing permissions.') }}</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@endsection
