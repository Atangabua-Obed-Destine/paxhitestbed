@extends('application.portal.layout')

@section('content')
@php
    $currency = $setting->currency_symbol ?? 'FCFA';
@endphp

<div class="row g-4">
    <div class="col-12">
        <div class="portal-card position-relative mb-2" style="background: linear-gradient(135deg, #182b49 0%, #667eea 100%);">
            <!-- Decorative background elements -->
            <div style="position: absolute; top: -50%; right: -10%; width: 50%; height: 200%; background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, rgba(255,255,255,0) 70%); transform: rotate(-30deg); pointer-events: none;"></div>
            
            <div class="p-5 position-relative z-index-1">
                <h3 class="mb-2 text-white fw-bold">{{ __('Start a New Application') }}</h3>
                <p class="mb-0 text-white-50" style="max-width: 600px; font-size: 1.05rem;">
                    {{ __('Tell us what you would like to apply for. Choose a degree type, academic year, and preferred programme to generate your tailored application form.') }}
                </p>
            </div>
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
                <div class="mb-5">
                    <label class="form-label fw-bold text-dark mb-3">{{ __('1. What type of programme are you applying for?') }} <span class="text-danger">*</span></label>
                    <div class="row g-3" id="degreeTypeCards">
                        @foreach($degreeTypes as $dt)
                            <div class="col-md-6">
                                <label class="degree-card d-block p-4 border rounded-3 h-100 position-relative transition-all" style="cursor:pointer; background-color: #f8fafc;">
                                    <input class="form-check-input me-2 degree-type-radio position-absolute top-50 start-0 translate-middle-y ms-3" type="radio" name="degree_type_id"
                                           value="{{ $dt->id }}" {{ old('degree_type_id') == $dt->id ? 'checked' : '' }} required style="opacity: 0;">
                                    
                                    <div class="d-flex align-items-center">
                                        <div class="radio-custom me-3 rounded-circle border border-2 d-flex align-items-center justify-content-center" style="width: 24px; height: 24px; border-color: #cbd5e1;">
                                            <div class="radio-custom-inner rounded-circle" style="width: 12px; height: 12px; background-color: transparent; transition: background-color 0.2s;"></div>
                                        </div>
                                        <div>
                                            <strong class="d-block text-dark fs-5 mb-1">{{ $dt->title }}</strong>
                                            @if($dt->shortcode)<span class="badge bg-light text-secondary border me-2">{{ $dt->shortcode }}</span>@endif
                                            @if($dt->level)<span class="small text-muted">{{ $dt->level }}</span>@endif
                                        </div>
                                    </div>
                                </label>
                            </div>
                        @endforeach
                    </div>
                    <div class="invalid-feedback d-block mt-2" id="degreeTypeError" style="display:none!important;">{{ __('Please select a degree type.') }}</div>
                </div>

                {{-- Intake session --}}
                <div class="mb-4">
                    <label for="session_id" class="form-label fw-bold text-dark">{{ __('2. Which academic year are you applying for?') }} <span class="text-danger">*</span></label>
                    <select name="session_id" id="session_id" class="form-select form-select-lg bg-light border-0" required>
                        <option value="">{{ __('Select an academic year') }}</option>
                        @foreach($sessions as $s)
                            <option value="{{ $s->id }}" {{ old('session_id') == $s->id ? 'selected' : '' }}>{{ $s->title }}</option>
                        @endforeach
                    </select>
                </div>

                {{-- Programme choices --}}
                <div class="mb-4">
                    <label class="form-label fw-bold text-dark">{{ __('3. Select your preferred programmes') }}</label>
                    <div class="bg-light p-4 rounded-3">
                        <div class="mb-3">
                            <label for="program" class="form-label text-muted fw-semibold">{{ __('1st Choice (Primary)') }} <span class="text-danger">*</span></label>
                            <select name="program" id="program" class="form-select border-0 shadow-sm" required disabled>
                                <option value="">{{ __('Select a degree type first') }}</option>
                            </select>
                        </div>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="second_program_choice_id" class="form-label text-muted">{{ __('2nd Choice (Optional)') }}</label>
                                <select name="second_program_choice_id" id="second_program_choice_id" class="form-select border-0 shadow-sm" disabled>
                                    <option value="">{{ __('None') }}</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label for="third_program_choice_id" class="form-label text-muted">{{ __('3rd Choice (Optional)') }}</label>
                                <select name="third_program_choice_id" id="third_program_choice_id" class="form-select border-0 shadow-sm" disabled>
                                    <option value="">{{ __('None') }}</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="d-flex justify-content-between align-items-center mt-5 pt-3 border-top">
                    <a href="{{ route('application.dashboard') }}" class="btn btn-light rounded-pill px-4">{{ __('Cancel') }}</a>
                    <button type="submit" class="btn btn-primary rounded-pill px-5 py-2 fw-bold">
                        {{ __('Start Application') }} <i class="fas fa-arrow-right ms-2"></i>
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

@push('styles')
<style>
    .degree-card {
        transition: all 0.3s ease;
        border: 2px solid transparent !important;
    }
    .degree-card:hover {
        background-color: #fff !important;
        box-shadow: 0 5px 15px rgba(0,0,0,0.05);
        transform: translateY(-2px);
    }
    /* When the hidden radio inside is checked */
    .degree-card:has(input:checked) {
        background-color: #f0f4ff !important;
        border-color: #667eea !important;
        box-shadow: 0 5px 15px rgba(102, 126, 234, 0.15);
    }
    .degree-card:has(input:checked) .radio-custom {
        border-color: #667eea !important;
    }
    .degree-card:has(input:checked) .radio-custom-inner {
        background-color: #667eea !important;
    }
</style>
@endpush

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
