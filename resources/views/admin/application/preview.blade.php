<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title }} - {{ $row->registration_no }}</title>
    <link rel="stylesheet" href="{{ asset('dashboard/css/style.css') }}">
    <style>
        body {
            background: #fff;
            color: #000;
            font-family: 'Times New Roman', Times, serif;
            font-size: 12pt;
        }
        .container {
            max-width: 210mm; /* A4 width */
            margin: 0 auto;
            padding: 20px;
        }
        .header {
            text-align: center;
            margin-bottom: 20px;
            border-bottom: 2px solid #000;
            padding-bottom: 10px;
            position: relative;
            min-height: 170px;
        }
        .logo {
            max-height: 80px;
            margin-bottom: 10px;
        }
        .university-name {
            font-size: 18pt;
            font-weight: bold;
            text-transform: uppercase;
            margin: 0;
        }
        .doc-title {
            font-size: 16pt;
            font-weight: bold;
            margin-top: 10px;
            text-decoration: underline;
        }
        .photo-container {
            position: absolute;
            top: 10px;
            right: 0;
            width: 35mm;
            height: 45mm;
            border: 1px solid #000;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
        }
        .photo-container img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .section-title {
            background-color: #e9ecef;
            padding: 5px 10px;
            font-weight: bold;
            margin-top: 20px;
            margin-bottom: 10px;
            border: 1px solid #000;
            text-transform: uppercase;
            font-size: 11pt;
            -webkit-print-color-adjust: exact;
        }
        .info-row {
            display: flex;
            margin-bottom: 5px;
            page-break-inside: avoid;
        }
        .info-label {
            font-weight: bold;
            width: 35%;
            padding-right: 10px;
        }
        .info-value {
            width: 65%;
            border-bottom: 1px dotted #999;
        }
        .table-custom {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 10px;
            font-size: 10pt;
        }
        .table-custom th, .table-custom td {
            border: 1px solid #000;
            padding: 5px;
            text-align: left;
        }
        .table-custom th {
            background-color: #f8f9fa;
            font-weight: bold;
        }
        .footer {
            margin-top: 30px;
            border-top: 1px solid #000;
            padding-top: 10px;
            font-size: 9pt;
            text-align: center;
        }
        .signature-box {
            margin-top: 40px;
            display: flex;
            justify-content: space-between;
        }
        .signature-line {
            width: 40%;
            border-top: 1px solid #000;
            text-align: center;
            padding-top: 5px;
        }
        @media print {
            .no-print {
                display: none !important;
            }
            body {
                margin: 0;
                padding: 0;
            }
            .container {
                width: 100%;
                max-width: none;
                padding: 0;
            }
            .section-title {
                background-color: #e9ecef !important;
                -webkit-print-color-adjust: exact;
            }
        }
    </style>
</head>
<body>
    <div class="no-print" style="position: fixed; top: 0; left: 0; width: 100%; background: #333; color: #fff; padding: 10px; text-align: center; z-index: 1000;">
        <button onclick="window.print()" style="padding: 8px 20px; cursor: pointer; background: #fff; border: none; font-weight: bold;">Print / Save as PDF</button>
        <button onclick="window.close()" style="padding: 8px 20px; cursor: pointer; background: #f44336; color: #fff; border: none; margin-left: 10px;">Close</button>
    </div>

    <div class="container" style="margin-top: 60px;">
        <div class="header">
            @if(isset($setting->logo_path) && is_file(public_path('uploads/setting/'.$setting->logo_path)))
                <img src="{{ asset('uploads/setting/'.$setting->logo_path) }}" class="logo" alt="Logo">
            @endif
            <h1 class="university-name">{{ $setting->title ?? 'University Name' }}</h1>
            <div class="doc-title">{{ __('Application Form') }}</div>
            
            <div class="photo-container">
                @if(is_file(public_path('uploads/student/'.$row->photo)))
                    <img src="{{ asset('uploads/student/'.$row->photo) }}" alt="Photo">
                @else
                    <span>PHOTO</span>
                @endif
            </div>
        </div>

        <div class="section-title">1. {{ __('Programme Selection') }}</div>
        <div class="row">
            <div class="col-6">
                <div class="info-row">
                    <span class="info-label">{{ __('Registration No') }}:</span>
                    <span class="info-value">{{ $row->registration_no }}</span>
                </div>
                <div class="info-row">
                    <span class="info-label">{{ __('Application Date') }}:</span>
                    <span class="info-value">{{ isset($row->apply_date) ? date('d M, Y', strtotime($row->apply_date)) : '' }}</span>
                </div>
                @if($fieldEnabled('application_academic_year'))
                <div class="info-row">
                    <span class="info-label">{{ __('Academic Year Applied For') }}:</span>
                    <span class="info-value">{{ $row->academic_year }}</span>
                </div>
                @endif
            </div>
            <div class="col-6">
                <div class="info-row">
                    <span class="info-label">{{ __('First Choice Programme') }}:</span>
                    <span class="info-value">{{ $row->program->title ?? '' }}</span>
                </div>
                @if($fieldEnabled('application_program_choice_second') && $row->preferredProgramSecond)
                <div class="info-row">
                    <span class="info-label">{{ __('Second Choice Programme') }}:</span>
                    <span class="info-value">{{ $row->preferredProgramSecond->title }}</span>
                </div>
                @endif
                @if($fieldEnabled('application_program_choice_third') && $row->preferredProgramThird)
                <div class="info-row">
                    <span class="info-label">{{ __('Third Choice Programme') }}:</span>
                    <span class="info-value">{{ $row->preferredProgramThird->title }}</span>
                </div>
                @endif
            </div>
        </div>

        <div class="section-title">2. {{ __('Personal Information') }}</div>
        <div class="row">
            <div class="col-6">
                <div class="info-row">
                    <span class="info-label">{{ __('field_first_name') }}:</span>
                    <span class="info-value">{{ $row->first_name }}</span>
                </div>
                <div class="info-row">
                    <span class="info-label">{{ __('field_last_name') }}:</span>
                    <span class="info-value">{{ $row->last_name }}</span>
                </div>
                @if($fieldEnabled('application_other_names'))
                <div class="info-row">
                    <span class="info-label">{{ __('Other Names (if any)') }}:</span>
                    <span class="info-value">{{ $row->other_names }}</span>
                </div>
                @endif
                <div class="info-row">
                    <span class="info-label">{{ __('field_gender') }}:</span>
                    <span class="info-value">
                        @if($row->gender == 1) {{ __('gender_male') }} @elseif($row->gender == 2) {{ __('gender_female') }} @else {{ __('gender_other') }} @endif
                    </span>
                </div>
                <div class="info-row">
                    <span class="info-label">{{ __('field_dob') }}:</span>
                    <span class="info-value">{{ isset($row->dob) ? date('d M, Y', strtotime($row->dob)) : '' }}</span>
                </div>
            </div>
            <div class="col-6">
                <div class="info-row">
                    <span class="info-label">{{ __('field_nationality') }}:</span>
                    <span class="info-value">{{ $row->nationality }}</span>
                </div>
                @if($fieldEnabled('application_religion'))
                <div class="info-row">
                    <span class="info-label">{{ __('field_religion') }}:</span>
                    <span class="info-value">{{ $row->religionDetail->title ?? $row->religion }}</span>
                </div>
                @endif
                <div class="info-row">
                    <span class="info-label">{{ __('field_marital_status') }}:</span>
                    <span class="info-value">{{ ucfirst($row->marital_status) }}</span>
                </div>
                <div class="info-row">
                    <span class="info-label">{{ __('field_blood_group') }}:</span>
                    <span class="info-value">{{ $row->blood_group }}</span>
                </div>
                @if($fieldEnabled('application_mother_tongue'))
                <div class="info-row">
                    <span class="info-label">{{ __('field_mother_tongue') }}:</span>
                    <span class="info-value">{{ $row->mother_tongue }}</span>
                </div>
                @endif
            </div>
        </div>

        @if($fieldEnabled('application_birth_city') || $fieldEnabled('application_birth_division') || $fieldEnabled('application_birth_region') || $fieldEnabled('application_birth_country'))
        <div class="row mt-2">
            <div class="col-12">
                <strong>{{ __('Birth Details') }}:</strong>
                <div class="row">
                    @if($fieldEnabled('application_birth_city'))
                    <div class="col-3">
                        <div class="info-row">
                            <span class="info-label">{{ __('City / Town') }}:</span>
                            <span class="info-value">{{ $row->birth_city }}</span>
                        </div>
                    </div>
                    @endif
                    @if($fieldEnabled('application_birth_division'))
                    <div class="col-3">
                        <div class="info-row">
                            <span class="info-label">{{ __('Division') }}:</span>
                            <span class="info-value">{{ $row->birth_division }}</span>
                        </div>
                    </div>
                    @endif
                    @if($fieldEnabled('application_birth_region'))
                    <div class="col-3">
                        <div class="info-row">
                            <span class="info-label">{{ __('Region') }}:</span>
                            <span class="info-value">{{ $row->birth_region }}</span>
                        </div>
                    </div>
                    @endif
                    @if($fieldEnabled('application_birth_country'))
                    <div class="col-3">
                        <div class="info-row">
                            <span class="info-label">{{ __('Country') }}:</span>
                            <span class="info-value">{{ $row->birth_country }}</span>
                        </div>
                    </div>
                    @endif
                </div>
            </div>
        </div>
        @endif

        @if($fieldEnabled('application_catholic_baptised') && ($row->is_catholic_baptised || $row->is_confirmed || $row->has_first_communion))
        <div class="row mt-2">
            <div class="col-12">
                <strong>{{ __('Catholic Sacraments') }}:</strong>
                <div class="row">
                    <div class="col-4">
                        <span class="info-label">{{ __('Baptised') }}:</span>
                        <span>{{ $row->is_catholic_baptised ? __('Yes') : __('No') }}</span>
                    </div>
                    <div class="col-4">
                        <span class="info-label">{{ __('Confirmed') }}:</span>
                        <span>{{ $row->is_confirmed ? __('Yes') : __('No') }}</span>
                    </div>
                    <div class="col-4">
                        <span class="info-label">{{ __('First Communion') }}:</span>
                        <span>{{ $row->has_first_communion ? __('Yes') : __('No') }}</span>
                    </div>
                </div>
            </div>
        </div>
        @endif

        <div class="row mt-2">
            <div class="col-12">
                <strong>{{ __('Official Identification') }}:</strong>
                <div class="row">
                    <div class="col-6">
                        <div class="info-row">
                            <span class="info-label">{{ __('National Identity Card Number') }}:</span>
                            <span class="info-value">{{ $row->national_id }}</span>
                        </div>
                        @if($fieldEnabled('application_national_id_issue_date'))
                        <div class="info-row">
                            <span class="info-label">{{ __('National ID Issue Date') }}:</span>
                            <span class="info-value">{{ isset($row->national_id_issue_date) ? date('d M, Y', strtotime($row->national_id_issue_date)) : '' }}</span>
                        </div>
                        @endif
                        @if($fieldEnabled('application_national_id_issue_place'))
                        <div class="info-row">
                            <span class="info-label">{{ __('National ID Issue Place') }}:</span>
                            <span class="info-value">{{ $row->national_id_issue_place }}</span>
                        </div>
                        @endif
                    </div>
                    <div class="col-6">
                        <div class="info-row">
                            <span class="info-label">{{ __('Passport Number') }}:</span>
                            <span class="info-value">{{ $row->passport_no }}</span>
                        </div>
                        @if($fieldEnabled('application_passport_issue_date'))
                        <div class="info-row">
                            <span class="info-label">{{ __('Passport Issue Date') }}:</span>
                            <span class="info-value">{{ isset($row->passport_issue_date) ? date('d M, Y', strtotime($row->passport_issue_date)) : '' }}</span>
                        </div>
                        @endif
                        @if($fieldEnabled('application_passport_issue_country'))
                        <div class="info-row">
                            <span class="info-label">{{ __('Passport Issue Country') }}:</span>
                            <span class="info-value">{{ $row->passport_issue_country }}</span>
                        </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <div class="section-title">3. {{ __('Address & Contact Information') }}</div>
        <div class="row">
            <div class="col-6">
                <div class="info-row">
                    <span class="info-label">{{ __('field_email') }}:</span>
                    <span class="info-value">{{ $row->email }}</span>
                </div>
                <div class="info-row">
                    <span class="info-label">{{ __('field_phone') }}:</span>
                    <span class="info-value">{{ $row->phone }}</span>
                </div>
                @if($fieldEnabled('application_phone_alternate'))
                <div class="info-row">
                    <span class="info-label">{{ __('field_phone_alternate') }}:</span>
                    <span class="info-value">{{ $row->phone_alternate }}</span>
                </div>
                @endif
                <div class="info-row">
                    <span class="info-label">{{ __('field_emergency_contact_name') }}:</span>
                    <span class="info-value">{{ $row->emergency_contact_name }}</span>
                </div>
                <div class="info-row">
                    <span class="info-label">{{ __('field_emergency_contact_phone') }}:</span>
                    <span class="info-value">{{ $row->emergency_contact_phone }}</span>
                </div>
            </div>
            <div class="col-6">
                @if($fieldEnabled('application_present_address'))
                <div class="info-row">
                    <span class="info-label">{{ __('field_present_address') }}:</span>
                    <span class="info-value">
                        {{ $row->present_address }}
                        @if($row->present_village) <br>{{ $row->present_village }} @endif
                        @if($row->present_district) <br>{{ $row->present_district }} @endif
                        @if($row->present_province) <br>{{ $row->present_province }} @endif
                        @if($row->country) <br>{{ $row->country }} @endif
                    </span>
                </div>
                @endif
                @if($fieldEnabled('application_permanent_address'))
                <div class="info-row">
                    <span class="info-label">{{ __('field_permanent_address') }}:</span>
                    <span class="info-value">
                        {{ $row->permanent_address }}
                        @if($row->permanent_village) <br>{{ $row->permanent_village }} @endif
                        @if($row->permanent_district) <br>{{ $row->permanent_district }} @endif
                        @if($row->permanent_province) <br>{{ $row->permanent_province }} @endif
                    </span>
                </div>
                @endif
                @if($fieldEnabled('application_postal_address'))
                <div class="info-row">
                    <span class="info-label">{{ __('field_postal_address') }}:</span>
                    <span class="info-value">
                        {{ $row->postal_address }}
                        @if($row->postal_city) <br>{{ $row->postal_city }} @endif
                        @if($row->postal_state) <br>{{ $row->postal_state }} @endif
                        @if($row->postal_country) <br>{{ $row->postal_country }} @endif
                    </span>
                </div>
                @endif
            </div>
        </div>

        @if($fieldEnabled('application_guardians') && $row->guardians->count() > 0)
        <div class="section-title">4. {{ __('Guardian Information') }}</div>
        <table class="table-custom">
            <thead>
                <tr>
                    <th>{{ __('field_guardian_name') }}</th>
                    <th>{{ __('field_guardian_relation') }}</th>
                    <th>{{ __('field_guardian_phone') }}</th>
                    <th>{{ __('field_guardian_email') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach($row->guardians as $guardian)
                <tr>
                    <td>{{ $guardian->full_name }}</td>
                    <td>{{ $guardian->relationship }}</td>
                    <td>{{ $guardian->phone_primary }}</td>
                    <td>{{ $guardian->email }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
        @endif

        @if($fieldEnabled('application_academic_history') && $row->academicHistories->count() > 0)
        <div class="section-title">5. {{ __('Academic History') }}</div>
        <table class="table-custom">
            <thead>
                <tr>
                    <th>{{ __('field_institution') }}</th>
                    <th>{{ __('Dates') }}</th>
                    <th>{{ __('field_qualification') }}</th>
                    <th>{{ __('Details') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach($row->academicHistories as $history)
                <tr>
                    <td>{{ $history->institution_name }}</td>
                    <td>{{ $history->date_from }} - {{ $history->date_to }}</td>
                    <td>{{ $history->certificate_obtained }}</td>
                    <td>{{ $history->notes }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
        @endif

        @if($fieldEnabled('application_language_proficiency') && $row->languages->count() > 0)
        <div class="section-title">6. {{ __('Language Proficiency') }}</div>
        <table class="table-custom">
            <thead>
                <tr>
                    <th>{{ __('Language') }}</th>
                    <th>{{ __('Reading') }}</th>
                    <th>{{ __('Writing') }}</th>
                    <th>{{ __('Speaking') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach($row->languages as $language)
                <tr>
                    <td>{{ $language->language }}</td>
                    <td>{{ $language->reading }}</td>
                    <td>{{ $language->writing }}</td>
                    <td>{{ $language->speaking }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
        @endif

        <div class="section-title">7. {{ __('Declaration') }}</div>
        <p style="font-size: 10pt; text-align: justify;">
            {{ __('I hereby declare that the information provided in this application is true and correct to the best of my knowledge. I understand that any false information may result in the rejection of my application or dismissal from the institution.') }}
        </p>

        <div class="signature-box">
            <div class="signature-line">
                @if(is_file(public_path('uploads/student/'.$row->signature)))
                    <img src="{{ asset('uploads/student/'.$row->signature) }}" style="max-height: 40px;">
                @endif
                <br>
                {{ __('Applicant\'s Signature') }}
            </div>
            <div class="signature-line">
                <br>
                {{ __('Date') }}
            </div>
        </div>

        @if($row->documents->count() > 0)
        <div class="section-title" style="page-break-before: always;">8. {{ __('Submitted Documents') }}</div>
        <div class="documents-container">
            @foreach($row->documents as $document)
                <div class="document-item" style="margin-bottom: 30px; page-break-inside: avoid; border: 1px solid #ccc; padding: 10px;">
                    @php
                        $docTitle = null;
                        if (!empty($document->document_type) && isset($documentRequirements[$document->document_type])) {
                            $docTitle = $documentRequirements[$document->document_type]['label'];
                        }
                        if (empty($docTitle)) {
                            $docTitle = $document->document_type ?? 'Document';
                        }

                        // Use file_path instead of attach for ApplicationDocument model
                        $fileName = $document->file_path;
                        $extension = pathinfo($fileName, PATHINFO_EXTENSION);
                        $isImage = in_array(strtolower($extension), ['jpg', 'jpeg', 'png', 'gif', 'webp']);
                        $filePath = 'uploads/'.$path.'/'.$fileName;
                        $absPath = public_path($filePath);
                    @endphp

                    <h4 style="margin-top: 0; border-bottom: 1px solid #eee; padding-bottom: 5px;">{{ $docTitle }}</h4>
                    
                    @if($isImage && is_file($absPath))
                        <div style="text-align: center;">
                            <img src="{{ asset($filePath) }}" style="max-width: 100%; max-height: 800px; object-fit: contain;">
                        </div>
                    @elseif(strtolower($extension) == 'pdf')
                         <div style="padding: 40px; text-align: center; background: #f9f9f9;">
                            <p style="font-size: 14pt; font-weight: bold;">{{ __('PDF Document') }}</p>
                            <p>{{ $fileName }}</p>
                            <p><em>({{ __('PDF content cannot be displayed in this print view') }})</em></p>
                         </div>
                    @else
                        <div style="padding: 40px; text-align: center; background: #f9f9f9;">
                            <p><strong>{{ __('File') }}:</strong> {{ $fileName }}</p>
                            @if(!is_file($absPath))
                                <p style="color: red; font-size: 0.8em;">({{ __('File not found') }}: {{ $filePath }})</p>
                            @endif
                        </div>
                    @endif
                </div>
            @endforeach
        </div>
        @endif

        <div class="footer">
            <p>{{ __('Generated on') }} {{ date('d M, Y H:i') }} | {{ $setting->title ?? 'University System' }}</p>
        </div>
    </div>
</body>
</html>