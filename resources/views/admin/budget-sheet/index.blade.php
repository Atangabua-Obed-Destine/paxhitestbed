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
                                    <h4>{{ __('Income & Expenditure Sheet') }}</h4>
                                    <span>{{ __('Plan the year line by line, and see what has actually happened against it.') }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="page-body">
                    <div class="row">
                        <div class="col-md-8">
                            <div class="card">
                                <div class="card-header"><h5>{{ __('Sheets') }}</h5></div>
                                <div class="card-block table-border-style">
                                    <div class="table-responsive">
                                        <table class="table table-striped">
                                            <thead>
                                                <tr>
                                                    <th>{{ __('Period') }}</th>
                                                    <th>{{ __('Covering') }}</th>
                                                    <th class="text-right">{{ __('Opening balance') }}</th>
                                                    <th>{{ __('Status') }}</th>
                                                    <th></th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @forelse($budgets as $row)
                                                    <tr>
                                                        <td>{{ $row->title }}</td>
                                                        <td>
                                                            {{ optional($row->start_date)->format('d M Y') }}
                                                            &ndash;
                                                            {{ optional($row->end_date)->format('d M Y') }}
                                                        </td>
                                                        <td class="text-right">{{ number_format($row->opening_balance) }}</td>
                                                        <td><span class="badge badge-secondary">{{ ucfirst($row->status) }}</span></td>
                                                        <td class="text-right">
                                                            <a href="{{ route('admin.budget-sheet.show', $row->id) }}" class="btn btn-sm btn-primary">
                                                                {{ __('Open') }}
                                                            </a>
                                                            @if($row->status === 'draft')
                                                                <form action="{{ route('admin.budget-sheet.delete', $row->id) }}" method="post" class="d-inline"
                                                                      onsubmit="return confirm('{{ __('Delete this sheet and any figures on it?') }}')">
                                                                    @csrf
                                                                    <button type="submit" class="btn btn-sm btn-outline-danger">
                                                                        {{ __('Delete') }}
                                                                    </button>
                                                                </form>
                                                            @endif
                                                        </td>
                                                    </tr>
                                                @empty
                                                    <tr>
                                                        <td colspan="5" class="text-center text-muted">
                                                            {{ __('No sheet yet. Create one for the coming year.') }}
                                                        </td>
                                                    </tr>
                                                @endforelse
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-4">
                            <div class="card">
                                <div class="card-header"><h5>{{ __('New sheet') }}</h5></div>
                                <div class="card-block">
                                    <form action="{{ route('admin.budget-sheet.store') }}" method="post">
                                        @csrf
                                        <div class="form-group">
                                            <label>{{ __('Period') }} <span>*</span></label>
                                            <input type="text" class="form-control" name="title"
                                                   value="{{ old('title', date('Y') . '/' . (date('Y') + 1)) }}" required>
                                        </div>
                                        <div class="form-group">
                                            <label>{{ __('Starts') }} <span>*</span></label>
                                            <input type="date" class="form-control" name="start_date"
                                                   value="{{ old('start_date') }}" required>
                                        </div>
                                        <div class="form-group">
                                            <label>{{ __('Ends') }} <span>*</span></label>
                                            <input type="date" class="form-control" name="end_date"
                                                   value="{{ old('end_date') }}" required>
                                        </div>
                                        <div class="form-group">
                                            <label>{{ __('Opening balance') }}</label>
                                            <input type="number" step="0.01" class="form-control" name="opening_balance"
                                                   value="{{ old('opening_balance') }}"
                                                   placeholder="{{ __('Carried forward automatically if left blank') }}">
                                            <small class="text-muted">
                                                {{ __('Left blank, this is taken from the closing balance of the previous sheet.') }}
                                            </small>
                                        </div>
                                        {{-- Retyping 66 lines is both laborious and how figures
                                             get mistyped. A budget is nearly always last year
                                             plus a judgement about what changes. --}}
                                        <hr>
                                        <div class="form-group">
                                            <label>{{ __('Start from') }}</label>
                                            <select class="form-control" name="seed_from" id="seed_from">
                                                <option value="">{{ __('An empty sheet') }}</option>
                                                <option value="actual" selected>{{ __('Last year’s actual figures') }}</option>
                                                <option value="budget">{{ __('Last year’s budget') }}</option>
                                            </select>
                                            <small class="text-muted">
                                                {{ __('Actual is the better base for a forecast — a plan that was never met is a poor starting point for the next one. Every figure can still be edited afterwards.') }}
                                            </small>
                                        </div>
                                        <div class="form-group" id="uplift_group">
                                            <label>{{ __('Adjust by') }}</label>
                                            <div class="input-group">
                                                <input type="number" step="0.1" class="form-control" name="uplift_percent"
                                                       value="{{ old('uplift_percent', 0) }}" min="-100" max="1000">
                                                <span class="input-group-text">%</span>
                                            </div>
                                            <small class="text-muted">
                                                {{ __('Applied to every carried-forward line, for known inflation or fee changes. Leave at 0 to copy figures unchanged.') }}
                                            </small>
                                        </div>

                                        <button type="submit" class="btn btn-primary btn-block">{{ __('Create sheet') }}</button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>
</div>
@endsection
