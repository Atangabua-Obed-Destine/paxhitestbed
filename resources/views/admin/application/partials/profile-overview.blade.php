@php
    $field = fn(string $slug) => \App\Models\Field::field($slug);
@endphp

<div class="col-md-12">
    <div class="card user-card user-card-1">
        <div class="card-body pb-0">
            <div class="media user-about-block align-items-center mt-0 mb-3">
                <div class="position-relative d-inline-block">
                    @if(is_file('uploads/'.$path.'/'.$row->photo))
                    <img src="{{ asset('uploads/'.$path.'/'.$row->photo) }}" class="img-radius img-fluid wid-80" alt="{{ __('field_photo') }}" onerror="this.src='{{ asset('dashboard/images/user/avatar-2.jpg') }}';">
                    @else
                    <img src="{{ asset('dashboard/images/user/avatar-2.jpg') }}" class="img-radius img-fluid wid-80" alt="{{ __('field_photo') }}">
                    @endif
                    <div class="certificated-badge">
                        <i class="fas fa-certificate text-primary bg-icon"></i>
                        <i class="fas fa-check front-icon text-white"></i>
                    </div>
                </div>
                <div class="media-body ms-3">
                    <h6 class="mb-1">{{ $row->first_name }} {{ $row->last_name }}</h6>
                    @if(isset($row->registration_no))
                    <p class="mb-0 text-muted">#{{ $row->registration_no }}</p>
                    @endif
                </div>
            </div>
        </div>
        <ul class="list-group list-group-flush">
            <li class="list-group-item">
                <span class="f-w-500"><i class="far fa-envelope m-r-10"></i>{{ __('field_email') }} : </span>
                <span class="float-end">{{ $row->email }}</span>
            </li>
            <li class="list-group-item">
                <span class="f-w-500"><i class="fas fa-phone-alt m-r-10"></i>{{ __('field_phone') }} : </span>
                <span class="float-end">{{ $row->phone }}</span>
            </li>
            <li class="list-group-item">
                <span class="f-w-500"><i class="fas fa-graduation-cap m-r-10"></i>{{ __('field_program') }} : </span>
                <span class="float-end">{{ $row->program->title ?? '' }}</span>
            </li>
            <li class="list-group-item">
                <span class="f-w-500"><i class="far fa-calendar-alt m-r-10"></i>{{ __('field_apply_date') }} : </span>
                <span class="float-end">
                    @if(isset($setting->date_format))
                    {{ date($setting->date_format, strtotime($row->apply_date)) }}
                    @else
                    {{ date('Y-m-d', strtotime($row->apply_date)) }}
                    @endif
                </span>
            </li>
            <li class="list-group-item">
                <span class="f-w-500"><i class="far fa-question-circle m-r-10"></i>{{ __('field_status') }} : </span>
                <span class="float-end">
                    @if( $row->status == 1 )
                    <span class="badge badge-pill badge-primary">{{ __('status_pending') }}</span>
                    @elseif( $row->status == 2 )
                    <span class="badge badge-pill badge-success">{{ __('status_approved') }}</span>
                    @else
                    <span class="badge badge-pill badge-danger">{{ __('status_rejected') }}</span>
                    @endif
                </span>
            </li>
            <li class="list-group-item border-bottom-0 text-center">
                <a href="{{ route('admin.application.preview', $row->id) }}" target="_blank" class="btn btn-primary w-100"><i class="fas fa-print me-2"></i> {{ __('Preview / Download Application') }}</a>
            </li>
        </ul>
    </div>
</div>
