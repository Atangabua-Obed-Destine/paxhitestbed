@extends('admin.layouts.master')
@section('title', __('fixed_asset_details'))

@section('content')
<!-- Main content -->
<section class="content">
    <div class="container-fluid">
        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">{{ __('fixed_asset_details') }}: {{ $asset->asset_code }}</h3>
                        <div class="card-tools">
                            @can('fixed-assets-edit')
                            @if($asset->status == 'active')
                            <a href="{{ route('admin.fixed-assets.edit', $asset->id) }}" class="btn btn-primary btn-sm mr-2">
                                <i class="fas fa-edit"></i> {{ __('edit') }}
                            </a>
                            @endif
                            @endcan
                            <a href="{{ route('admin.fixed-assets.index') }}" class="btn btn-secondary btn-sm">
                                <i class="fas fa-arrow-left"></i> {{ __('back') }}
                            </a>
                        </div>
                    </div>
                    <!-- /.card-header -->
                    <div class="card-body">
                        <div class="row">
                            <!-- Basic Information -->
                            <div class="col-md-6">
                                <div class="card">
                                    <div class="card-header bg-info">
                                        <h5 class="card-title mb-0"><i class="fas fa-info-circle"></i> {{ __('basic_information') }}</h5>
                                    </div>
                                    <div class="card-body">
                                        <table class="table table-borderless table-sm">
                                            <tr>
                                                <th width="40%">{{ __('asset_code') }}</th>
                                                <td><strong>{{ $asset->asset_code }}</strong></td>
                                            </tr>
                                            <tr>
                                                <th>{{ __('asset_name') }}</th>
                                                <td>{{ $asset->name }}</td>
                                            </tr>
                                            @if($asset->name_fr)
                                            <tr>
                                                <th>{{ __('asset_name_fr') }}</th>
                                                <td>{{ $asset->name_fr }}</td>
                                            </tr>
                                            @endif
                                            <tr>
                                                <th>{{ __('category') }}</th>
                                                <td><span class="badge badge-info">{{ $asset->category->name ?? 'N/A' }}</span></td>
                                            </tr>
                                            <tr>
                                                <th>{{ __('serial_number') }}</th>
                                                <td>{{ $asset->serial_number ?? '-' }}</td>
                                            </tr>
                                            <tr>
                                                <th>{{ __('location') }}</th>
                                                <td>{{ $asset->location ?? '-' }}</td>
                                            </tr>
                                            <tr>
                                                <th>{{ __('vendor') }}</th>
                                                <td>{{ $asset->vendor ?? '-' }}</td>
                                            </tr>
                                            <tr>
                                                <th>{{ __('status_title') }}</th>
                                                <td>
                                                    @if($asset->status == 'active')
                                                    <span class="badge badge-success">{{ __('active') }}</span>
                                                    @elseif($asset->status == 'disposed')
                                                    <span class="badge badge-danger">{{ __('disposed') }}</span>
                                                    @else
                                                    <span class="badge badge-warning">{{ __($asset->status) }}</span>
                                                    @endif
                                                </td>
                                            </tr>
                                        </table>
                                    </div>
                                </div>
                            </div>

                            <!-- Financial Information -->
                            <div class="col-md-6">
                                <div class="card">
                                    <div class="card-header bg-success">
                                        <h5 class="card-title mb-0"><i class="fas fa-money-bill-wave"></i> {{ __('financial_information') }}</h5>
                                    </div>
                                    <div class="card-body">
                                        <table class="table table-borderless table-sm">
                                            <tr>
                                                <th width="40%">{{ __('acquisition_date') }}</th>
                                                <td>{{ \Carbon\Carbon::parse($asset->acquisition_date)->format('d M Y') }}</td>
                                            </tr>
                                            <tr>
                                                <th>{{ __('acquisition_cost') }}</th>
                                                <td><strong>{{ number_format($asset->acquisition_cost, 0, ',', ' ') }} FCFA</strong></td>
                                            </tr>
                                            <tr>
                                                <th>{{ __('salvage_value') }}</th>
                                                <td>{{ number_format($asset->salvage_value, 0, ',', ' ') }} FCFA</td>
                                            </tr>
                                            <tr>
                                                <th>{{ __('accumulated_depreciation') }}</th>
                                                <td class="text-danger">{{ number_format($asset->accumulated_depreciation, 0, ',', ' ') }} FCFA</td>
                                            </tr>
                                            <tr class="table-primary">
                                                <th>{{ __('net_book_value') }}</th>
                                                <td><strong>{{ number_format($asset->net_book_value, 0, ',', ' ') }} FCFA</strong></td>
                                            </tr>
                                            <tr>
                                                <th>{{ __('depreciation_method') }}</th>
                                                <td><span class="badge badge-secondary">{{ __(str_replace('_', ' ', $asset->depreciation_method)) }}</span></td>
                                            </tr>
                                            <tr>
                                                <th>{{ __('useful_life') }}</th>
                                                <td>{{ $asset->useful_life_years }} {{ __('years') }}</td>
                                            </tr>
                                            <tr>
                                                <th>{{ __('depreciation_start_date') }}</th>
                                                <td>{{ $asset->depreciation_start_date ? \Carbon\Carbon::parse($asset->depreciation_start_date)->format('d M Y') : '-' }}</td>
                                            </tr>
                                        </table>
                                    </div>
                                </div>
                            </div>

                            <!-- Depreciation Progress -->
                            <div class="col-md-12">
                                <div class="card">
                                    <div class="card-header bg-warning">
                                        <h5 class="card-title mb-0"><i class="fas fa-chart-line"></i> {{ __('depreciation_progress') }}</h5>
                                    </div>
                                    <div class="card-body">
                                        @php
                                            $depreciable = $asset->acquisition_cost - $asset->salvage_value;
                                            $percentage = $depreciable > 0 ? ($asset->accumulated_depreciation / $depreciable) * 100 : 0;
                                            $percentage = min(100, $percentage);
                                        @endphp
                                        <div class="progress" style="height: 30px;">
                                            <div class="progress-bar bg-warning" role="progressbar" style="width: {{ $percentage }}%;" 
                                                 aria-valuenow="{{ $percentage }}" aria-valuemin="0" aria-valuemax="100">
                                                {{ number_format($percentage, 1) }}% {{ __('depreciated') }}
                                            </div>
                                        </div>
                                        <div class="row mt-3">
                                            <div class="col-md-4 text-center">
                                                <small class="text-muted">{{ __('depreciable_amount') }}</small>
                                                <h5>{{ number_format($depreciable, 0, ',', ' ') }} FCFA</h5>
                                            </div>
                                            <div class="col-md-4 text-center">
                                                <small class="text-muted">{{ __('depreciated_amount') }}</small>
                                                <h5 class="text-danger">{{ number_format($asset->accumulated_depreciation, 0, ',', ' ') }} FCFA</h5>
                                            </div>
                                            <div class="col-md-4 text-center">
                                                <small class="text-muted">{{ __('remaining_to_depreciate') }}</small>
                                                <h5 class="text-success">{{ number_format($asset->net_book_value - $asset->salvage_value, 0, ',', ' ') }} FCFA</h5>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Insurance Information -->
                            @if($asset->insurance_value || $asset->insurance_policy_number || $asset->warranty_expiry_date)
                            <div class="col-md-6">
                                <div class="card">
                                    <div class="card-header bg-primary">
                                        <h5 class="card-title mb-0"><i class="fas fa-shield-alt"></i> {{ __('insurance_warranty') }}</h5>
                                    </div>
                                    <div class="card-body">
                                        <table class="table table-borderless table-sm">
                                            @if($asset->insurance_value)
                                            <tr>
                                                <th>{{ __('insurance_value') }}</th>
                                                <td>{{ number_format($asset->insurance_value, 0, ',', ' ') }} FCFA</td>
                                            </tr>
                                            @endif
                                            @if($asset->insurance_policy_number)
                                            <tr>
                                                <th>{{ __('policy_number') }}</th>
                                                <td>{{ $asset->insurance_policy_number }}</td>
                                            </tr>
                                            @endif
                                            @if($asset->warranty_expiry_date)
                                            <tr>
                                                <th>{{ __('warranty_expiry') }}</th>
                                                <td>
                                                    {{ \Carbon\Carbon::parse($asset->warranty_expiry_date)->format('d M Y') }}
                                                    @if(\Carbon\Carbon::parse($asset->warranty_expiry_date)->isPast())
                                                    <span class="badge badge-danger">{{ __('expired') }}</span>
                                                    @else
                                                    <span class="badge badge-success">{{ __('valid') }}</span>
                                                    @endif
                                                </td>
                                            </tr>
                                            @endif
                                        </table>
                                    </div>
                                </div>
                            </div>
                            @endif

                            <!-- Description -->
                            @if($asset->description)
                            <div class="col-md-{{ ($asset->insurance_value || $asset->insurance_policy_number || $asset->warranty_expiry_date) ? '6' : '12' }}">
                                <div class="card">
                                    <div class="card-header bg-secondary">
                                        <h5 class="card-title mb-0"><i class="fas fa-file-alt"></i> {{ __('description') }}</h5>
                                    </div>
                                    <div class="card-body">
                                        {{ $asset->description }}
                                    </div>
                                </div>
                            </div>
                            @endif

                            <!-- Disposal Information -->
                            @if($asset->status == 'disposed')
                            <div class="col-md-12">
                                <div class="card border-danger">
                                    <div class="card-header bg-danger">
                                        <h5 class="card-title mb-0"><i class="fas fa-sign-out-alt"></i> {{ __('disposal_information') }}</h5>
                                    </div>
                                    <div class="card-body">
                                        <div class="row">
                                            <div class="col-md-3">
                                                <strong>{{ __('disposal_date') }}:</strong><br>
                                                {{ $asset->disposal_date ? \Carbon\Carbon::parse($asset->disposal_date)->format('d M Y') : '-' }}
                                            </div>
                                            <div class="col-md-3">
                                                <strong>{{ __('disposal_method') }}:</strong><br>
                                                {{ $asset->disposal_method ? __($asset->disposal_method) : '-' }}
                                            </div>
                                            <div class="col-md-3">
                                                <strong>{{ __('disposal_proceeds') }}:</strong><br>
                                                {{ $asset->disposal_proceeds ? number_format($asset->disposal_proceeds, 0, ',', ' ') . ' FCFA' : '-' }}
                                            </div>
                                            <div class="col-md-3">
                                                <strong>{{ __('gain_loss') }}:</strong><br>
                                                @php
                                                    $gainLoss = ($asset->disposal_proceeds ?? 0) - $asset->net_book_value;
                                                @endphp
                                                <span class="{{ $gainLoss >= 0 ? 'text-success' : 'text-danger' }}">
                                                    {{ number_format($gainLoss, 0, ',', ' ') }} FCFA
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            @endif

                            <!-- Depreciation Schedule -->
                            <div class="col-md-12">
                                <div class="card">
                                    <div class="card-header">
                                        <h5 class="card-title mb-0"><i class="fas fa-calendar-alt"></i> {{ __('depreciation_schedule') }}</h5>
                                    </div>
                                    <div class="card-body">
                                        @if($asset->depreciationSchedules && $asset->depreciationSchedules->count() > 0)
                                        <div class="table-responsive">
                                            <table class="table table-bordered table-sm">
                                                <thead>
                                                    <tr>
                                                        <th>{{ __('period') }}</th>
                                                        <th class="text-right">{{ __('depreciation_amount') }}</th>
                                                        <th class="text-right">{{ __('accumulated') }}</th>
                                                        <th class="text-right">{{ __('net_book_value') }}</th>
                                                        <th>{{ __('status_title') }}</th>
                                                        <th>{{ __('posted_date') }}</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    @foreach($asset->depreciationSchedules->take(12) as $schedule)
                                                    <tr>
                                                        <td>{{ \Carbon\Carbon::parse($schedule->depreciation_date)->format('M Y') }}</td>
                                                        <td class="text-right">{{ number_format($schedule->depreciation_amount, 0, ',', ' ') }}</td>
                                                        <td class="text-right">{{ number_format($schedule->accumulated_depreciation, 0, ',', ' ') }}</td>
                                                        <td class="text-right">{{ number_format($schedule->net_book_value, 0, ',', ' ') }}</td>
                                                        <td>
                                                            @if($schedule->is_posted)
                                                            <span class="badge badge-success">{{ __('posted') }}</span>
                                                            @else
                                                            <span class="badge badge-warning">{{ __('pending') }}</span>
                                                            @endif
                                                        </td>
                                                        <td>{{ $schedule->posted_date ? \Carbon\Carbon::parse($schedule->posted_date)->format('d M Y') : '-' }}</td>
                                                    </tr>
                                                    @endforeach
                                                </tbody>
                                            </table>
                                        </div>
                                        @if($asset->depreciationSchedules->count() > 12)
                                        <p class="text-muted mt-2">{{ __('showing_recent_12_entries') }}. {{ __('total') }}: {{ $asset->depreciationSchedules->count() }} {{ __('entries') }}.</p>
                                        @endif
                                        @else
                                        <p class="text-muted">{{ __('no_depreciation_recorded') }}</p>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- /.card-body -->
                </div>
                <!-- /.card -->
            </div>
            <!-- /.col -->
        </div>
        <!-- /.row -->
    </div>
    <!-- /.container-fluid -->
</section>
<!-- /.content -->
@endsection
