@extends('admin.layouts.master')
@section('title', __('EdutrustPay Reporting'))
@section('content')

{{--
    Where this institution sends its monthly figures, and proof that it can.

    The credentials are minted by the body's operator and pasted in here. This
    institution cannot create its own — otherwise the platform would have to
    trust whatever it was told about who is reporting.
--}}

<div class="page-header">
    <h4 class="page-title">{{ __('EdutrustPay Reporting') }}</h4>
</div>

<div class="row">
    <div class="col-lg-8">

        <div class="card">
            <div class="card-header">
                <h5 class="mb-0" style="font-size:0.95rem;">{{ __('Connection') }}</h5>
                <small class="text-muted">
                    {{ __('This institution PUSHES a signed summary of each month to the console. Nothing reaches in here: outbound only, no inbound endpoint, no access to this database from that side.') }}
                </small>
            </div>

            <div class="card-body">

                @if ($resolved && $resolved['source'] === 'env')
                    {{-- Explain the precedence before somebody saves and wonders
                         why nothing changed. --}}
                    <div class="alert alert-info" style="font-size:0.85rem;">
                        {{ __('The values in force are currently coming from the .env file. Anything you save here takes precedence from the moment you save it.') }}
                    </div>
                @endif

                @if ($setting->last_tested_at)
                    <div class="alert {{ $setting->last_test_ok ? 'alert-success' : 'alert-danger' }}" style="font-size:0.85rem;">
                        <strong>
                            {{ $setting->last_test_ok ? __('Last test passed') : __('Last test failed') }}
                            · {{ $setting->last_tested_at->diffForHumans() }}
                        </strong>
                        <div>{{ $setting->last_test_message }}</div>
                    </div>
                @endif

                <form method="POST" action="{{ route('admin.edutrustpay.update') }}">
                    @csrf
                    @method('PUT')

                    <div class="form-group">
                        <label for="endpoint">{{ __('Console address') }}</label>
                        <input type="url" name="endpoint" id="endpoint" class="form-control @error('endpoint') is-invalid @enderror"
                               value="{{ old('endpoint', $setting->endpoint) }}" placeholder="https://">
                        <small class="form-text text-muted">
                            {{ __('Given to you by the body. Reports are sent here; nothing is ever received from it.') }}
                        </small>
                        @error('endpoint')<span class="invalid-feedback d-block">{{ $message }}</span>@enderror
                    </div>

                    <div class="form-row">
                        <div class="form-group col-md-6">
                            <label for="institution_ref">{{ __('Institution reference') }}</label>
                            <input type="text" name="institution_ref" id="institution_ref"
                                   class="form-control @error('institution_ref') is-invalid @enderror"
                                   value="{{ old('institution_ref', $setting->institution_ref) }}"
                                   placeholder="00000000-0000-0000-0000-000000000000">
                            <small class="form-text text-muted">{{ __('Identifies this institution on the platform.') }}</small>
                            @error('institution_ref')<span class="invalid-feedback d-block">{{ $message }}</span>@enderror
                        </div>

                        <div class="form-group col-md-6">
                            <label for="key_id">{{ __('Key id') }}</label>
                            <input type="text" name="key_id" id="key_id"
                                   class="form-control @error('key_id') is-invalid @enderror"
                                   value="{{ old('key_id', $setting->key_id) }}" placeholder="etp_...">
                            @error('key_id')<span class="invalid-feedback d-block">{{ $message }}</span>@enderror
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="secret">{{ __('Secret') }}</label>
                        <input type="password" name="secret" id="secret" autocomplete="new-password"
                               class="form-control @error('secret') is-invalid @enderror"
                               placeholder="{{ $setting->hasSecret() ? __('Stored — leave blank to keep it') : __('Paste the secret you were given') }}">

                        {{-- A stored secret is never displayed. Blank means keep,
                             not clear — otherwise correcting a typo in the
                             endpoint would silently wipe a working credential. --}}
                        <small class="form-text text-muted">
                            @if ($setting->hasSecret())
                                {{ __('A secret is stored (:hint). It is never shown again — leave this blank unless you are replacing it.', ['hint' => $setting->secretHint()]) }}
                            @else
                                {{ __('The operator shows this once when the credential is issued. It cannot be recovered from the console afterwards, only replaced.') }}
                            @endif
                        </small>
                        @error('secret')<span class="invalid-feedback d-block">{{ $message }}</span>@enderror
                    </div>

                    <div class="form-group">
                        <div class="custom-control custom-checkbox">
                            <input type="hidden" name="enabled" value="0">
                            <input type="checkbox" class="custom-control-input" id="enabled" name="enabled" value="1"
                                   {{ old('enabled', $setting->enabled) ? 'checked' : '' }}>
                            <label class="custom-control-label" for="enabled">{{ __('Send reports for this institution') }}</label>
                        </div>
                    </div>

                    @can('edutrustpay-update')
                        <button type="submit" class="btn btn-primary btn-sm">{{ __('Save') }}</button>
                    @else
                        <p class="text-muted mb-0" style="font-size:0.85rem;">
                            {{ __('You can view these settings but not change them.') }}
                        </p>
                    @endcan
                </form>

                @can('edutrustpay-test')
                    <hr>
                    <form method="POST" action="{{ route('admin.edutrustpay.test') }}">
                        @csrf
                        <button type="submit" class="btn btn-outline-secondary btn-sm">{{ __('Test connection') }}</button>
                        <small class="text-muted ml-2">
                            {{ __('Sends a signed heartbeat. Proves the credentials still work — the most likely failure is a key rotated on the platform and never updated here.') }}
                        </small>
                    </form>
                @endcan
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        {{--
            What this institution reports, stated beside the credentials.

            Somebody looking at the body's console will see "not yet reporting"
            against several figures and should be able to find out why from this
            end, without having to ask.
        --}}
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0" style="font-size:0.95rem;">{{ __('What this institution reports') }}</h5>
            </div>
            <div class="card-body">
                <p class="mb-2">
                    @foreach ($capabilities as $capability)
                        <span class="badge badge-success mr-1 mb-1">{{ str_replace('_', ' ', $capability) }}</span>
                    @endforeach

                    @foreach ($notDeclared as $capability)
                        <span class="badge badge-light text-muted mr-1 mb-1"
                              title="{{ __('Not reported — shows on the console as “not yet reporting”, never as zero.') }}">
                            {{ str_replace('_', ' ', $capability) }}
                        </span>
                    @endforeach
                </p>

                <small class="text-muted d-block">
                    {{ __('Greyed items are not reported. On the console they show as “not yet reporting” rather than as zero — a missing figure and a zero figure are different facts. This list is set in code, not here, because it describes what this system can actually produce.') }}
                </small>

                @if (in_array('budget', $notDeclared, true))
                    <small class="text-warning d-block mt-2">
                        {{ __('Budget is not reported because this system has no monthly budget — budgets are annual and allocations quarterly at best. A twelfth of the annual figure would make every seasonal month look like a variance.') }}
                    </small>
                @endif
            </div>
        </div>

        <div class="card">
            <div class="card-body">
                <small class="text-muted">
                    {{ __('Contract version :version. Credentials are issued by the operator at the body and pasted here — this institution cannot create its own, or the platform would have to trust whatever it was told.', ['version' => $contractVersion]) }}
                </small>
            </div>
        </div>
    </div>
</div>

@endsection
