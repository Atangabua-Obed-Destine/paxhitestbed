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

@php $run = session('etp_run'); @endphp

<div class="row">
    <div class="col-lg-8">

        {{--
            Is it sending?

            Reports go out through scheduled jobs, and the scheduler only runs if
            this computer has been told to start it every minute. If nobody did,
            nothing fails and nothing is logged: the institution just goes quiet
            on the console. This card says which it is, lets someone send
            everything now, and shows what the console answered.
        --}}
        <div class="card" id="sending">
            <div class="card-header">
                <h5 class="mb-0" style="font-size:0.95rem;">{{ __('Is it sending?') }}</h5>
                <small class="text-muted">{{ __('Reports go to the console on their own every night, if the scheduler is running on this computer. You can also send everything now.') }}</small>
            </div>
            <div class="card-body">

                @if ($run)
                    <div class="alert {{ $run['ok'] ? 'alert-success' : 'alert-danger' }}" style="font-size:0.9rem;">
                        <strong>{{ $run['ok'] ? __('Sent') : __('Something is wrong') }}</strong>
                        <div>{{ $run['message'] }}</div>
                        <div class="mt-2">
                            @foreach ($run['steps'] as $step)
                                <details class="mb-1">
                                    <summary style="cursor:pointer;">
                                        {!! $step['ok'] ? '<span class="text-success">&#10003;</span>' : '<span class="text-danger">&#10007;</span>' !!}
                                        {{ $step['label'] }}
                                    </summary>
                                    <pre class="mb-0 mt-1 p-2 bg-light border" style="font-size:0.75rem; white-space:pre-wrap; max-height:260px; overflow:auto;">{{ $step['output'] !== '' ? $step['output'] : __('(nothing to say)') }}</pre>
                                </details>
                            @endforeach
                        </div>
                    </div>
                @endif

                <table class="table table-sm mb-3" style="font-size:0.88rem;">
                    <tr>
                        <th style="width:42%; border-top:0;">{{ __('Automatic sending (scheduler)') }}</th>
                        <td style="border-top:0;">
                            @if ($scheduler === 'running')
                                <span class="badge badge-success">{{ __('Running') }}</span>
                                <small class="text-muted ml-1">{{ __('last ran :when', ['when' => $schedulerSeen->diffForHumans()]) }}</small>
                            @elseif ($scheduler === 'stopped')
                                <span class="badge badge-danger">{{ __('Stopped') }}</span>
                                <small class="text-muted ml-1">{{ __('last ran :when. Nothing goes out on its own until it runs again.', ['when' => $schedulerSeen->diffForHumans()]) }}</small>
                            @else
                                <span class="badge badge-warning">{{ __('Not set up on this computer') }}</span>
                                <small class="text-muted ml-1">{{ __('Reports only go out when someone presses “Send reports now”.') }}</small>
                            @endif
                        </td>
                    </tr>
                    <tr>
                        <th>{{ __('Last received by the console') }}</th>
                        <td>
                            @if ($lastDelivered)
                                {{ \Carbon\Carbon::parse($lastDelivered)->diffForHumans() }}
                                <small class="text-muted">({{ \Carbon\Carbon::parse($lastDelivered)->format('d M Y, H:i') }})</small>
                            @else
                                <span class="text-danger">{{ __('Never') }}</span>
                            @endif
                        </td>
                    </tr>
                    <tr>
                        <th>{{ __('Waiting to go') }}</th>
                        <td>
                            @if ($waiting)
                                <span class="text-warning font-weight-bold">{{ $waiting }}</span>
                                <small class="text-muted">{{ __('kept here and tried again on the next delivery, so nothing is lost') }}</small>
                            @else
                                {{ __('Nothing') }}
                            @endif
                        </td>
                    </tr>
                    <tr>
                        <th>{{ __('Refused by the console') }}</th>
                        <td>
                            @if ($problems->isNotEmpty())
                                <span class="text-danger font-weight-bold">{{ $problems->count() }}</span>
                                <small class="d-block text-danger">{{ \App\Http\Controllers\Admin\EdutrustPayController::explain($problems->first()) }}</small>
                            @else
                                {{ __('Nothing') }}
                            @endif
                        </td>
                    </tr>
                </table>

                @if ($scheduler !== 'running')
                    <details class="mb-3" @if ($scheduler === 'never') open @endif>
                        <summary style="cursor:pointer; font-size:0.88rem;" class="font-weight-bold">{{ __('How to start the scheduler on this computer (once)') }}</summary>
                        <div class="mt-2" style="font-size:0.85rem;">
                            @if ($schedulerSetup['windows'])
                                <p class="mb-1">{{ __('Open “Command Prompt” as Administrator (right-click, Run as administrator), paste this line and press Enter:') }}</p>
                            @else
                                <p class="mb-1">{{ __('On the server, run “sudo crontab -u www-data -e”, add this line at the bottom and save:') }}</p>
                            @endif
                            <pre class="p-2 bg-light border mb-1" style="font-size:0.78rem; white-space:pre-wrap; user-select:all;">{{ $schedulerSetup['command'] }}</pre>
                            <p class="text-muted mb-0">{{ __('It starts the scheduler every minute, and the scheduler decides what is due: reports at 03:00, delivery every hour, a check-in at 05:30. Come back here a minute later: this page should say “Running”.') }}</p>
                        </div>
                    </details>
                @endif

                @can('edutrustpay-test')
                    @if ($reporting)
                        <form method="POST" action="{{ route('admin.edutrustpay.send-now') }}"
                              onsubmit="var b=this.querySelector('button'); b.disabled=true; b.innerText='{{ __('Sending… this can take a minute') }}';">
                            @csrf
                            <button type="submit" class="btn btn-primary btn-sm">{{ __('Send reports now') }}</button>
                            <small class="text-muted ml-2">
                                {{ __('Runs the nightly jobs now: builds the last 3 months’ reports, daybooks and budget sheets, sends a check-in and delivers everything waiting. Months that have not changed are not sent again.') }}
                            </small>
                        </form>
                    @else
                        <button type="button" class="btn btn-primary btn-sm" disabled>{{ __('Send reports now') }}</button>
                        <small class="text-muted ml-2">{{ __('Fill in the connection below, tick “Send reports for this institution” and save first.') }}</small>
                    @endif
                @endcan

                @if ($recent->isNotEmpty())
                    <h6 class="mt-4 mb-2" style="font-size:0.88rem;">{{ __('Last items sent') }}</h6>
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered mb-0" style="font-size:0.8rem;">
                            <thead class="thead-light">
                                <tr>
                                    <th>{{ __('What') }}</th>
                                    <th>{{ __('Month') }}</th>
                                    <th>{{ __('State') }}</th>
                                    <th>{{ __('When') }}</th>
                                    <th>{{ __('Console\'s answer') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($recent as $item)
                                    <tr>
                                        <td>{{ \App\Http\Controllers\Admin\EdutrustPayController::kindLabel($item->kind) }}@if ($item->sequence > 1 && $item->kind !== 'heartbeat') <small class="text-muted">{{ __('version :n', ['n' => $item->sequence]) }}</small>@endif</td>
                                        <td>{{ $item->period ? \Carbon\Carbon::parse($item->period . '-01')->format('F Y') : '—' }}</td>
                                        <td>
                                            @if ($item->status === 'delivered')
                                                <span class="badge badge-success">{{ __('received') }}</span>
                                            @elseif ($item->status === 'pending')
                                                <span class="badge badge-warning">{{ __('waiting') }}</span>
                                            @else
                                                <span class="badge badge-danger">{{ __('refused') }}</span>
                                            @endif
                                        </td>
                                        <td style="white-space:nowrap;">{{ ($item->delivered_at ?? $item->updated_at)?->diffForHumans() }}</td>
                                        <td class="text-muted">{{ $item->status === 'delivered' ? '' : \App\Http\Controllers\Admin\EdutrustPayController::explain($item) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h5 class="mb-0" style="font-size:0.95rem;">{{ __('Connection') }}</h5>
                <small class="text-muted">
                    {{ __('This institution PUSHES a signed summary of each month, and its full daybook, to the console. The daybook includes the names of the people on each line, so students\' and suppliers\' names leave this system. Nothing reaches in here: outbound only, no inbound endpoint, no access to this database from that side.') }}
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
                        {{ __('Budget is not reported yet. This institution has budgets, but the reporting contract still needs a year-to-date budget block. A twelfth of the annual figure would make every seasonal month look like a variance, so nothing is sent rather than something misleading.') }}
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
