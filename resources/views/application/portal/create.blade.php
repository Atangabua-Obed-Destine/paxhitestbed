@extends('application.portal.layout')

@section('content')
@php
    $currency = $setting->currency_symbol ?? 'FCFA';
@endphp

<div class="row g-4">
    <div class="col-12">
        <div class="portal-card p-4 mb-1">
            <h3 class="mb-1">{{ __('Create a New Application') }}</h3>
            <p class="mb-0 text-muted">{{ __('Tell us what you would like to apply for. Choose a degree type, the intake you are applying for, and your preferred programme. The application form will then be tailored to your selection.') }}</p>
        </div>
    </div>

    <div class="col-lg-8">
        <div class="portal-card p-4">
            @if($degreeTypes->isEmpty() || $sessions->isEmpty())
                <div class="alert alert-warning mb-0">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    {{ __('Applications are not open at the moment. Please check back later or contact the Admissions Office.') }}
                </div>
            @else
            <form action="{{ route('application.store') }}" method="post" class="needs-validation" novalidate>
                @csrf

                {{-- Degree type --}}
                <div class="mb-4">
                    <label class="form-label fw-bold">{{ __('What type of programme are you applying for?') }} <span class="text-danger">*</span></label>
                    <div class="row g-2" id="degreeTypeCards">
                        @foreach($degreeTypes as $dt)
                            <div class="col-md-6">
                                <label class="degree-card d-block p-3 border rounded h-100" style="cursor:pointer;">
                                    <input class="form-check-input me-2 degree-type-radio" type="radio" name="degree_type_id"
                                           value="{{ $dt->id }}" {{ old('degree_type_id') == $dt->id ? 'checked' : '' }} required>
                                    <strong>{{ $dt->title }}</strong>
                                    @if($dt->shortcode)<span class="badge badge-light ms-1">{{ $dt->shortcode }}</span>@endif
                                    @if($dt->level)<div class="small text-muted mt-1">{{ $dt->level }}</div>@endif
                                </label>
                            </div>
                        @endforeach
                    </div>
                    <div class="invalid-feedback d-block" id="degreeTypeError" style="display:none!important;">{{ __('Please select a degree type.') }}</div>
                </div>

                {{-- Intake session --}}
                <div class="mb-4">
                    <label for="session_id" class="form-label fw-bold">{{ __('Which intake are you applying for?') }} <span class="text-danger">*</span></label>
                    <select name="session_id" id="session_id" class="form-control" required>
                        <option value="">{{ __('Select an intake') }}</option>
                        @foreach($sessions as $s)
                            <option value="{{ $s->id }}" {{ old('session_id') == $s->id ? 'selected' : '' }}>{{ $s->title }}</option>
                        @endforeach
                    </select>
                </div>

                {{-- Programme choices --}}
                <div class="mb-3">
                    <label for="program" class="form-label fw-bold">{{ __('Preferred Programme (1st choice)') }} <span class="text-danger">*</span></label>
                    <select name="program" id="program" class="form-control" required disabled>
                        <option value="">{{ __('Select a degree type first') }}</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label for="second_program_choice_id" class="form-label">{{ __('2nd choice (optional)') }}</label>
                    <select name="second_program_choice_id" id="second_program_choice_id" class="form-control" disabled>
                        <option value="">{{ __('None') }}</option>
                    </select>
                </div>
                <div class="mb-4">
                    <label for="third_program_choice_id" class="form-label">{{ __('3rd choice (optional)') }}</label>
                    <select name="third_program_choice_id" id="third_program_choice_id" class="form-control" disabled>
                        <option value="">{{ __('None') }}</option>
                    </select>
                </div>

                <div class="d-flex justify-content-between">
                    <a href="{{ route('application.dashboard') }}" class="btn btn-light">{{ __('Cancel') }}</a>
                    <button type="submit" class="btn btn-primary">
                        {{ __('Start Application') }} <i class="fas fa-arrow-right ms-1"></i>
                    </button>
                </div>
            </form>
            @endif
        </div>
    </div>

    <div class="col-lg-4">
        <div class="portal-card p-4" id="degreeInfoPanel" style="display:none;">
            <div class="border-top border-3 border-primary mb-3"></div>
            <h5 id="degreeInfoTitle" class="mb-2"></h5>
            <div id="degreeInfoRequirements" class="small text-muted mb-3"></div>
            <div id="degreeInfoFee" class="alert alert-info small mb-0"></div>
        </div>
        <div class="portal-card p-4 mt-4">
            <h6>{{ __('Already applied before?') }}</h6>
            <p class="small text-muted mb-2">{{ __('All your applications appear together in My Account so you can track each one.') }}</p>
            <a href="{{ route('application.dashboard') }}" class="btn btn-outline-secondary btn-sm w-100">{{ __('Back to My Account') }}</a>
        </div>
    </div>
</div>

@push('scripts')
<script>
    (function () {
        var PROGRAMS = @json($programs);
        var DEGREE_META = @json($degreeMeta);
        var currency = @json($currency);

        var programSelects = ['program', 'second_program_choice_id', 'third_program_choice_id'];

        function populatePrograms(degreeTypeId) {
            var matching = PROGRAMS.filter(function (p) { return String(p.degree_type_id) === String(degreeTypeId); });

            programSelects.forEach(function (id, idx) {
                var sel = document.getElementById(id);
                if (!sel) return;
                var firstLabel = idx === 0 ? @json(__('Select a programme')) : @json(__('None'));
                sel.innerHTML = '<option value="">' + firstLabel + '</option>';
                matching.forEach(function (p) {
                    var opt = document.createElement('option');
                    opt.value = p.id;
                    opt.textContent = p.title;
                    sel.appendChild(opt);
                });
                sel.disabled = matching.length === 0;
            });
        }

        function showMeta(degreeTypeId) {
            var panel = document.getElementById('degreeInfoPanel');
            var meta = DEGREE_META[degreeTypeId];
            var card = document.querySelector('.degree-type-radio[value="' + degreeTypeId + '"]');
            var title = card ? card.parentElement.querySelector('strong').textContent : '';
            if (!meta) { panel.style.display = 'none'; return; }

            document.getElementById('degreeInfoTitle').textContent = title;
            var req = document.getElementById('degreeInfoRequirements');
            req.innerHTML = meta.requirements_html ? meta.requirements_html : @json(__('Complete the tailored application form for this programme type.'));

            var feeBox = document.getElementById('degreeInfoFee');
            if (meta.fee_enabled) {
                feeBox.style.display = '';
                feeBox.innerHTML = '<i class="fas fa-info-circle me-1"></i>' + @json(__('Application fee:')) + ' <strong>' +
                    Number(meta.fee_amount).toLocaleString() + ' ' + currency + '</strong>';
            } else {
                feeBox.style.display = 'none';
            }
            panel.style.display = '';
        }

        document.querySelectorAll('.degree-type-radio').forEach(function (radio) {
            radio.addEventListener('change', function () {
                document.querySelectorAll('.degree-card').forEach(function (c) { c.classList.remove('border-primary', 'shadow-sm'); });
                this.parentElement.classList.add('border-primary', 'shadow-sm');
                populatePrograms(this.value);
                showMeta(this.value);
            });
        });

        // Restore on validation error
        var checked = document.querySelector('.degree-type-radio:checked');
        if (checked) {
            checked.parentElement.classList.add('border-primary', 'shadow-sm');
            populatePrograms(checked.value);
            showMeta(checked.value);
            var oldProgram = @json(old('program'));
            if (oldProgram) document.getElementById('program').value = oldProgram;
        }
    })();
</script>
@endpush
@endsection
