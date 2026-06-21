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
                        <!-- Filter Form -->
                        <form class="needs-validation" action="{{ route($route.'.index') }}" method="get">
                            <div class="row gx-2">
                                <div class="form-group col-md-3">
                                    <label for="status">{{ __('field_status') }}</label>
                                    <select class="form-control" name="status" id="status">
                                        <option value="">{{ __('all') }}</option>
                                        <option value="active" {{ $selected_status == 'active' ? 'selected' : '' }}>Active</option>
                                        <option value="completed" {{ $selected_status == 'completed' ? 'selected' : '' }}>Completed</option>
                                        <option value="cancelled" {{ $selected_status == 'cancelled' ? 'selected' : '' }}>Cancelled</option>
                                        <option value="defaulted" {{ $selected_status == 'defaulted' ? 'selected' : '' }}>Defaulted</option>
                                    </select>
                                </div>

                                <div class="form-group col-md-3">
                                    <label for="student">{{ __('field_student') }}</label>
                                    <input type="text" class="form-control" name="student" id="student" value="{{ $search_student }}" placeholder="{{ __('search_by_name_or_id') }}">
                                </div>

                                <div class="form-group col-md-3">
                                    <button type="submit" class="btn btn-info btn-filter"><i class="fas fa-search"></i> {{ __('btn_filter') }}</button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- [ Payment Plans List ] start -->
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header">
                        <h5>{{ __('payment_plans') }}</h5>
                        @can($access.'.create')
                        <a href="{{ route($route.'.create') }}" class="btn btn-primary btn-sm float-end">
                            <i class="fas fa-plus"></i> {{ __('btn_create_payment_plan') }}
                        </a>
                        @endcan
                    </div>
                    <div class="card-block">
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>{{ __('field_student') }}</th>
                                        <th>{{ __('field_fee') }}</th>
                                        <th>{{ __('field_total_amount') }}</th>
                                        <th>{{ __('field_installments') }}</th>
                                        <th>{{ __('field_paid') }}</th>
                                        <th>{{ __('field_remaining') }}</th>
                                        <th>{{ __('field_progress') }}</th>
                                        <th>{{ __('field_status') }}</th>
                                        <th>{{ __('field_action') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($rows as $key => $row)
                                    <tr>
                                        <td>{{ $key + 1 }}</td>
                                        <td>
                                            <strong>#{{ $row->student->student_id }}</strong><br>
                                            {{ $row->student->first_name }} {{ $row->student->last_name }}
                                        </td>
                                        <td>
                                            {{ $row->fee->category->title ?? '' }}<br>
                                            <small class="text-muted">{{ $row->fee->studentEnroll->program->title ?? '' }}</small>
                                        </td>
                                        <td>{{ number_format($row->total_amount, 2) }} {!! $setting->currency_symbol !!}</td>
                                        <td>
                                            <span class="badge badge-info">{{ $row->installments_count }} {{ __('installments') }}</span>
                                        </td>
                                        <td class="text-success">
                                            <strong>{{ number_format($row->total_paid, 2) }} {!! $setting->currency_symbol !!}</strong>
                                        </td>
                                        <td class="text-danger">
                                            <strong>{{ number_format($row->remaining_balance, 2) }} {!! $setting->currency_symbol !!}</strong>
                                        </td>
                                        <td>
                                            <div class="progress" style="height: 20px;">
                                                <div class="progress-bar" role="progressbar" style="width: {{ $row->progress_percentage }}%;" aria-valuenow="{{ $row->progress_percentage }}" aria-valuemin="0" aria-valuemax="100">
                                                    {{ round($row->progress_percentage) }}%
                                                </div>
                                            </div>
                                        </td>
                                        <td>{!! $row->status_badge !!}</td>
                                        <td>
                                            @can($access.'.show')
                                            <a href="{{ route($route.'.show', $row->id) }}" class="btn btn-sm btn-info" title="{{ __('btn_view') }}">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            @endcan

                                            @if($row->isActive())
                                                @can($access.'.edit')
                                                <a href="{{ route($route.'.edit', $row->id) }}" class="btn btn-sm btn-warning" title="{{ __('btn_edit') }}">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                @endcan

                                                @can($access.'.cancel')
                                                <button type="button" class="btn btn-sm btn-danger" data-bs-toggle="modal" data-bs-target="#cancelModal-{{ $row->id }}" title="{{ __('btn_cancel') }}">
                                                    <i class="fas fa-ban"></i>
                                                </button>
                                                @include('admin.payment-plan.cancel')
                                                @endcan
                                            @endif

                                            @if($row->installments->count() == 0)
                                                @can($access.'.destroy')
                                                <button type="button" class="btn btn-sm btn-danger" data-bs-toggle="modal" data-bs-target="#deleteModal-{{ $row->id }}" title="{{ __('btn_delete') }}">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                                @include('admin.payment-plan.delete')
                                                @endcan
                                            @endif
                                        </td>
                                    </tr>
                                    @empty
                                    <tr>
                                        <td colspan="10" class="text-center">{{ __('no_data_found') }}</td>
                                    </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>

                        <!-- Pagination -->
                        <div class="mt-3">
                            {{ $rows->appends(request()->query())->links() }}
                        </div>
                    </div>
                </div>
            </div>
            <!-- [ Payment Plans List ] end -->
        </div>
        <!-- [ Main Content ] end -->
    </div>
</div>
<!-- End Content-->

@endsection
