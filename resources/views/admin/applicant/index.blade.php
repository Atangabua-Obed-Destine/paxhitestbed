@extends('admin.layouts.master')
@section('title', $title)
@section('content')

{{--
    Applicant accounts: the logins people create on the application portal.
    An account can hold several applications; this screen is about the login.
--}}

<style>
    .applicant-table td, .applicant-table th { vertical-align: middle; font-size: .85rem; }
    .applicant-apps { margin: 0; padding: 0; list-style: none; font-size: .78rem; }
    .applicant-apps li + li { margin-top: 2px; }
    .stat-tile { border: 1px solid #e6e9ef; border-radius: 6px; padding: 10px 14px; background: #fafbfc; }
    .stat-tile .n { font-size: 1.35rem; font-weight: 700; font-variant-numeric: tabular-nums; }
    .stat-tile .l { font-size: .75rem; color: #6c757d; text-transform: uppercase; letter-spacing: .3px; }
    .badge-account-active { background: #e6f4ec; color: #1f6b46; }
    .badge-account-disabled { background: #fbe9e7; color: #a33a2b; }
    .badge-stage { background: #eef1f6; color: #3d4756; font-weight: 500; }
</style>

<div class="main-body">
    <div class="page-wrapper">
        <div class="row">
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header">
                        <h5>{{ __('Applicants') }}</h5>
                        <p class="text-muted mb-0" style="font-size:.85rem;">
                            {{ __('Accounts people sign in with on the application portal. One account can hold several applications.') }}
                        </p>
                    </div>

                    <div class="card-block">
                        <div class="row g-2 mb-3">
                            <div class="col-6 col-md-3"><div class="stat-tile"><div class="n">{{ number_format($stats['total']) }}</div><div class="l">{{ __('Accounts') }}</div></div></div>
                            <div class="col-6 col-md-3"><div class="stat-tile"><div class="n">{{ number_format($stats['no_application']) }}</div><div class="l">{{ __('No application yet') }}</div></div></div>
                            <div class="col-6 col-md-3"><div class="stat-tile"><div class="n">{{ number_format($stats['never_logged_in']) }}</div><div class="l">{{ __('Never signed in') }}</div></div></div>
                            <div class="col-6 col-md-3"><div class="stat-tile"><div class="n">{{ number_format($stats['disabled']) }}</div><div class="l">{{ __('Disabled') }}</div></div></div>
                        </div>

                        <form method="get" action="{{ route($route.'.index') }}" class="row g-2 align-items-end">
                            <div class="col-md-5">
                                <label for="q" class="form-label">{{ __('Search') }}</label>
                                <input type="text" id="q" name="q" value="{{ $search }}" class="form-control" placeholder="{{ __('Name, email or phone') }}">
                            </div>
                            <div class="col-md-3">
                                <label for="status" class="form-label">{{ __('Show') }}</label>
                                <select id="status" name="status" class="form-control">
                                    <option value="all" @selected($status === 'all')>{{ __('All accounts') }}</option>
                                    <option value="active" @selected($status === 'active')>{{ __('Active') }}</option>
                                    <option value="disabled" @selected($status === 'disabled')>{{ __('Disabled') }}</option>
                                    <option value="no_application" @selected($status === 'no_application')>{{ __('No application yet') }}</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> {{ __('btn_search') }}</button>
                                <a href="{{ route($route.'.index') }}" class="btn btn-info"><i class="fas fa-sync-alt"></i> {{ __('btn_refresh') }}</a>
                            </div>
                        </form>
                    </div>

                    <div class="card-block">
                        <div class="table-responsive">
                            <table class="table table-hover applicant-table">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>{{ __('Name') }}</th>
                                        <th>{{ __('Email') }}</th>
                                        <th>{{ __('Phone') }}</th>
                                        <th>{{ __('Applications') }}</th>
                                        <th>{{ __('Last signed in') }}</th>
                                        <th>{{ __('Account') }}</th>
                                        <th class="text-end">{{ __('Actions') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($rows as $applicant)
                                    <tr>
                                        <td>{{ $rows->firstItem() + $loop->index }}</td>
                                        <td>{{ $applicant->full_name ?: '—' }}</td>
                                        <td>{{ $applicant->email }}</td>
                                        <td>{{ $applicant->phone ?: '—' }}</td>
                                        <td>
                                            @if($applicant->applications_count)
                                                <ul class="applicant-apps">
                                                    @foreach($applicant->applications as $application)
                                                        <li>
                                                            @can('application-view')
                                                                <a href="{{ route('admin.application.show', $application->id) }}">{{ $application->registration_no ?: '#'.$application->id }}</a>
                                                            @else
                                                                {{ $application->registration_no ?: '#'.$application->id }}
                                                            @endcan
                                                            — {{ $application->program->title ?? __('No programme') }}
                                                            <span class="badge badge-stage">{{ ucfirst(str_replace('_', ' ', $application->stage ?? '')) }}</span>
                                                        </li>
                                                    @endforeach
                                                </ul>
                                            @else
                                                <span class="text-muted">{{ __('None yet') }}</span>
                                            @endif
                                        </td>
                                        <td>{{ $applicant->portal_last_login_at ? $applicant->portal_last_login_at->format('d M Y, H:i') : __('Never') }}</td>
                                        <td>
                                            @if($applicant->disabled_at)
                                                <span class="badge badge-account-disabled" title="{{ $applicant->disabled_reason }}">
                                                    {{ __('Disabled') }} {{ $applicant->disabled_at->format('d M Y') }}
                                                </span>
                                                @if($applicant->disabled_reason)
                                                    <div class="text-muted" style="font-size:.75rem;">{{ $applicant->disabled_reason }}</div>
                                                @endif
                                            @else
                                                <span class="badge badge-account-active">{{ __('Active') }}</span>
                                            @endif
                                        </td>
                                        <td class="text-end text-nowrap">
                                            @can('applicant-edit')
                                                <button type="button" class="btn btn-icon btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#editApplicant-{{ $applicant->id }}" title="{{ __('Edit account details') }}">
                                                    <i class="far fa-edit"></i>
                                                </button>
                                            @endcan
                                            @can('applicant-password-change')
                                                <button type="button" class="btn btn-icon btn-info btn-sm" data-bs-toggle="modal" data-bs-target="#passwordApplicant-{{ $applicant->id }}" title="{{ __('Change password') }}">
                                                    <i class="fas fa-key"></i>
                                                </button>
                                            @endcan
                                            @can('applicant-impersonate')
                                                @unless($applicant->disabled_at)
                                                    {{-- A form, not a link: starting a session as someone
                                                         else should not be something another page can
                                                         trigger by being visited. --}}
                                                    <form method="post" action="{{ route('admin.applicant.impersonate', $applicant->id) }}" target="_blank" class="d-inline">
                                                        @csrf
                                                        <button type="submit" class="btn btn-icon btn-dark btn-sm" title="{{ __('Sign in as this applicant — opens the portal in a new tab') }}">
                                                            <i class="fas fa-user-secret"></i>
                                                        </button>
                                                    </form>
                                                @endunless
                                            @endcan
                                            @can('applicant-edit')
                                                @unless($applicant->disabled_at)
                                                    <button type="button" class="btn btn-icon btn-secondary btn-sm" data-bs-toggle="modal" data-bs-target="#resetApplicant-{{ $applicant->id }}" title="{{ __('Email a reset link') }}">
                                                        <i class="fas fa-envelope"></i>
                                                    </button>
                                                @endunless
                                                <button type="button" class="btn btn-icon btn-sm {{ $applicant->disabled_at ? 'btn-success' : 'btn-outline-danger' }}" data-bs-toggle="modal" data-bs-target="#statusApplicant-{{ $applicant->id }}" title="{{ $applicant->disabled_at ? __('Enable account') : __('Disable account') }}">
                                                    <i class="fas {{ $applicant->disabled_at ? 'fa-user-check' : 'fa-user-slash' }}"></i>
                                                </button>
                                            @endcan

                                            @include($view.'.modals', ['applicant' => $applicant])
                                        </td>
                                    </tr>
                                    @empty
                                    <tr>
                                        <td colspan="8" class="text-center text-muted py-4">{{ __('No applicant accounts match.') }}</td>
                                    </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>

                        {{ $rows->links() }}
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@endsection
