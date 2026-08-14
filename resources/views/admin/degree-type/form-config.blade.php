@extends('admin.layouts.master')
@section('title', $title)
@section('content')

<div class="main-body">
    <div class="page-wrapper">
        <div class="row">
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">
                            {{ __('Application Form Configuration') }}:
                            <span class="badge badge-primary">{{ $degreeType->title }}</span>
                        </h5>
                        <a href="{{ route($route.'.index') }}" class="btn btn-sm btn-secondary">
                            <i class="fas fa-arrow-left"></i> {{ __('Back') }}
                        </a>
                    </div>

                    <form action="{{ route('admin.degree-type.form-config.save', $degreeType) }}" method="post">
                        @csrf
                        <div class="card-block">
                            <p class="text-muted">
                                {{ __('Control exactly what applicants for this degree type see and must provide. Toggles, documents, fee and intro text below apply only to') }}
                                <strong>{{ $degreeType->title }}</strong>.
                            </p>

                            <ul class="nav nav-tabs" role="tablist">
                                <li class="nav-item"><a class="nav-link active" data-bs-toggle="tab" href="#tab-sections" role="tab">{{ __('Sections & Fields') }}</a></li>
                                <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#tab-documents" role="tab">{{ __('Documents') }}</a></li>
                                <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#tab-settings" role="tab">{{ __('Fee & Intro') }}</a></li>
                                <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#tab-letter" role="tab">{{ __('Acceptance Letter') }}</a></li>
                            </ul>

                            <div class="tab-content pt-3">
                                {{-- SECTIONS & FIELDS --}}
                                <div class="tab-pane fade show active" id="tab-sections" role="tabpanel">
                                    <div class="row">
                                        @foreach($fields as $f)
                                            @php
                                                $label = ucwords(str_replace('_', ' ', preg_replace('/^application_/', '', $f->slug)));
                                                $isOn = array_key_exists($f->slug, $fieldMap) ? (int)$fieldMap[$f->slug] === 1 : ((int)$f->status === 1);
                                            @endphp
                                            <div class="col-md-6 mb-2">
                                                <div class="d-flex justify-content-between align-items-center border rounded px-3 py-2">
                                                    <span>{{ $label }}</span>
                                                    <input type="hidden" name="all_slugs[]" value="{{ $f->slug }}">
                                                    <div class="switch d-inline">
                                                        <input type="checkbox" id="fld-{{ $f->id }}" name="fields[{{ $f->slug }}]" value="1" {{ $isOn ? 'checked' : '' }}>
                                                        <label for="fld-{{ $f->id }}" class="cr"></label>
                                                    </div>
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>

                                {{-- DOCUMENTS --}}
                                <div class="tab-pane fade" id="tab-documents" role="tabpanel">
                                    <div class="table-responsive">
                                        <table class="table table-bordered align-middle">
                                            <thead>
                                                <tr>
                                                    <th style="width:30%">{{ __('Document') }}</th>
                                                    <th>{{ __('Description') }}</th>
                                                    <th class="text-center">{{ __('Required') }}</th>
                                                    <th class="text-center">{{ __('Enabled') }}</th>
                                                    <th class="text-center">{{ __('Order') }}</th>
                                                    <th class="text-center">{{ __('Delete') }}</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @forelse($documents as $doc)
                                                    <tr>
                                                        <td>
                                                            <input type="text" class="form-control form-control-sm" name="doc[{{ $doc->id }}][label]" value="{{ $doc->label }}">
                                                            <small class="text-muted">{{ $doc->doc_key }}</small>
                                                        </td>
                                                        <td><input type="text" class="form-control form-control-sm" name="doc[{{ $doc->id }}][description]" value="{{ $doc->description }}"></td>
                                                        <td class="text-center"><input type="checkbox" name="doc[{{ $doc->id }}][required]" value="1" {{ $doc->required ? 'checked' : '' }}></td>
                                                        <td class="text-center"><input type="checkbox" name="doc[{{ $doc->id }}][status]" value="1" {{ $doc->status ? 'checked' : '' }}></td>
                                                        <td class="text-center"><input type="number" class="form-control form-control-sm" style="width:75px;margin:auto;" name="doc[{{ $doc->id }}][sort_order]" value="{{ $doc->sort_order }}"></td>
                                                        <td class="text-center"><input type="checkbox" name="doc_delete[]" value="{{ $doc->id }}"></td>
                                                    </tr>
                                                @empty
                                                    <tr><td colspan="6" class="text-center text-muted">{{ __('No documents configured.') }}</td></tr>
                                                @endforelse
                                            </tbody>
                                        </table>
                                    </div>

                                    <h6 class="mt-3">{{ __('Add new documents') }}</h6>
                                    <table class="table table-sm">
                                        <thead><tr><th>{{ __('Label') }}</th><th>{{ __('Key (optional)') }}</th><th class="text-center">{{ __('Required') }}</th></tr></thead>
                                        <tbody>
                                            @for($i = 0; $i < 3; $i++)
                                            <tr>
                                                <td><input type="text" class="form-control form-control-sm" name="newdoc_label[]" placeholder="{{ __('e.g. Bachelor\'s Degree Transcript') }}"></td>
                                                <td><input type="text" class="form-control form-control-sm" name="newdoc_key[]" placeholder="bachelor_transcript"></td>
                                                <td class="text-center"><input type="checkbox" name="newdoc_required[{{ $i }}]" value="1"></td>
                                            </tr>
                                            @endfor
                                        </tbody>
                                    </table>
                                </div>

                                {{-- SETTINGS --}}
                                <div class="tab-pane fade" id="tab-settings" role="tabpanel">
                                    <div class="row">
                                        <div class="form-group col-md-12">
                                            <label>{{ __('Intro text (shown at top of the form)') }}</label>
                                            <textarea class="form-control" name="intro_html" rows="3">{{ optional($settings)->intro_html }}</textarea>
                                        </div>
                                        <div class="form-group col-md-12">
                                            <label>{{ __('Entry requirements text') }}</label>
                                            <textarea class="form-control" name="requirements_html" rows="3">{{ optional($settings)->requirements_html }}</textarea>
                                        </div>
                                        <div class="form-group col-md-3">
                                            <label class="d-block">{{ __('Charge Application Fee') }}</label>
                                            <div class="switch d-inline">
                                                <input type="checkbox" id="fee_enabled" name="fee_enabled" value="1" {{ (optional($settings)->fee_enabled ?? true) ? 'checked' : '' }}>
                                                <label for="fee_enabled" class="cr"></label>
                                            </div>
                                        </div>
                                        <div class="form-group col-md-3">
                                            <label>{{ __('Fee Amount') }}</label>
                                            <input type="number" step="0.01" class="form-control" name="fee_amount" value="{{ optional($settings)->fee_amount }}">
                                        </div>
                                        <div class="form-group col-md-3">
                                            <label>{{ __('Due in (days)') }}</label>
                                            <input type="number" class="form-control" name="fee_due_days" value="{{ optional($settings)->fee_due_days }}">
                                        </div>
                                        <div class="form-group col-md-12">
                                            <label>{{ __('Payment Instructions') }}</label>
                                            <textarea class="form-control" name="fee_instructions" rows="2">{{ optional($settings)->fee_instructions }}</textarea>
                                        </div>
                                    </div>
                                </div>

                                {{-- ACCEPTANCE LETTER --}}
                                <div class="tab-pane fade" id="tab-letter" role="tabpanel">
                                    <div class="row">
                                        <div class="col-md-12 mb-2">
                                            <div class="d-flex justify-content-between align-items-center">
                                                <div class="form-group mb-0">
                                                    <label class="d-block">{{ __('Send Acceptance Letter on Admission') }}</label>
                                                    <div class="switch d-inline">
                                                        <input type="checkbox" id="acceptance_letter_enabled" name="acceptance_letter_enabled" value="1" {{ (optional($settings)->acceptance_letter_enabled) ? 'checked' : '' }}>
                                                        <label for="acceptance_letter_enabled" class="cr"></label>
                                                    </div>
                                                    <span class="ms-2 text-muted">{{ __('When on, the letter below is emailed (as a PDF) to the applicant the moment their application is converted to a student.') }}</span>
                                                </div>
                                                <a href="{{ route('admin.degree-type.acceptance-letter.preview', $degreeType) }}" target="_blank" class="btn btn-outline-secondary btn-sm">
                                                    <i class="fas fa-file-pdf"></i> {{ __('Preview PDF') }}
                                                </a>
                                            </div>
                                        </div>
                                        <div class="form-group col-md-12">
                                            <label>{{ __('Letter Body') }}</label>
                                            <textarea class="form-control texteditor" name="acceptance_letter_html" rows="12">{{ optional($settings)->acceptance_letter_html }}</textarea>
                                            <div class="alert alert-secondary mt-2 mb-0">
                                                <strong>{{ __('Placeholders') }}:</strong>
                                                [name] [first_name] [last_name] [student_id] [matricule] [program] [degree_type] [faculty] [intake] [admission_date] [date] [institution] [address] [email] [phone] [dob] [place_of_birth] [fee_breakdown] [fee_breakdown_total] [fee_breakdown_total_words] [payment_deadlines]
                                                <br><small class="text-muted">{{ __('[fee_breakdown] inserts the Year-1 first-installment fee table from the programme\'s fee configuration (add your own heading above it).') }}</small>
                                                <br><small class="text-muted">{{ __('[payment_deadlines] inserts a formatted list of all Year-1 regular installment deadlines with spelled-out amounts.') }}</small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="card-footer">
                            <button type="submit" class="btn btn-success"><i class="fas fa-check"></i> {{ __('btn_save') }}</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

@endsection
