<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Application;
use App\Models\ApplicationSetting;
use App\Models\Field;
use App\Models\Program;
use App\Models\Province;
use App\Models\Setting;
use App\Models\MailSetting;
use App\Traits\FileUploader;
use Carbon\Carbon;
use Flasher\Laravel\Facade\Flasher;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Validation\Rule;
use App\Support\ApplicationDocumentRequirements;

class ApplicationController extends Controller
{
    use FileUploader;

    protected $title, $route, $view, $path;

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        // Module Data
        $this->title    = trans_choice('module_application', 1);
        $this->route    = 'application';
        $this->view     = 'application';
        $this->path     = 'student';
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        if (!Auth::guard('applicant')->check()) {
            return redirect()->route('application.login');
        }

        $application = Auth::guard('applicant')->user();
        if ($application->stage !== 'draft') {
            return redirect()->route('application.dashboard');
        }

        // Load relationships for pre-populating the draft form
        $application->load(['guardians', 'academicHistories', 'languages', 'program', 'documents']);

        $data['title']  = $this->title;
        $data['route']  = $this->route;
        $data['path']   = $this->path;
        $data['application'] = $application; // Pass user data to view
        $provinces = Province::where('status', '1')
            ->with(['districts' => function ($query) {
                $query->where('status', '1')->orderBy('title', 'asc');
            }])
            ->orderBy('title', 'asc')
            ->get();

        $data['programs'] = Program::where('status', '1')->orderBy('title', 'asc')->get();
        $data['faculties'] = \App\Models\Faculty::where('status', '1')->orderBy('title', 'asc')->get();
        $data['religions'] = \App\Models\Religion::where('status', '1')->orderBy('title', 'asc')->get();
        $data['religions_json'] = \App\Models\Religion::where('status', '1')->orderBy('title', 'asc')->get()->keyBy('id')->toJson();
        $data['sessions'] = \App\Models\Session::where('status', '1')->orderBy('title', 'desc')->get();
        $data['provinces'] = $provinces;
        $data['districtOptions'] = $provinces
            ->flatMap(function (Province $province) {
                return $province->districts->map(function ($district) use ($province) {
                    return [
                        'province_id' => $province->id,
                        'id' => $district->id,
                        'title' => $district->title,
                    ];
                });
            })
            ->values()
            ->toArray();
        $data['present_districts'] = [];
        $data['permanent_districts'] = [];
        $data['applicationSetting'] = ApplicationSetting::where('slug', 'admission')->where('status', '1')->firstOrFail();
        $data['documentRequirements'] = $this->documentRequirements();
        $data['guardianTypes'] = ['Parent', 'Sponsor', 'Guardian'];
        $data['fluencyOptions'] = ['excellent', 'good', 'fair', 'minimal'];

        return view('application.apply', $data);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $documentRequirements = $this->documentRequirements();

        $academicYearEnabled = $this->fieldEnabled('application_academic_year');
        $secondChoiceEnabled = $this->fieldEnabled('application_program_choice_second');
        $thirdChoiceEnabled = $this->fieldEnabled('application_program_choice_third');
        $birthCityEnabled = $this->fieldEnabled('application_birth_city');
        $birthDivisionEnabled = $this->fieldEnabled('application_birth_division');
        $birthRegionEnabled = $this->fieldEnabled('application_birth_region');
        $birthCountryEnabled = $this->fieldEnabled('application_birth_country');
        $registrationFeeBankEnabled = $this->fieldEnabled('application_registration_fee_bank');
        $registrationFeeReferenceEnabled = $this->fieldEnabled('application_registration_fee_reference');
        $studiedEnglishEnabled = $this->fieldEnabled('application_studied_in_english');
        $instructionLanguageEnabled = $this->fieldEnabled('application_instruction_language_secondary');
        $guardiansEnabled = $this->fieldEnabled('application_guardians');
        $academicHistoryEnabled = $this->fieldEnabled('application_academic_history');
        $languageEnabled = $this->fieldEnabled('application_language_proficiency');
        $documentChecklistEnabled = $this->fieldEnabled('application_document_checklist');
        $declarationEnabled = $this->fieldEnabled('application_declaration');

        $request->merge([
            'second_program_choice_id' => $request->input('second_program_choice_id') ?: null,
            'third_program_choice_id' => $request->input('third_program_choice_id') ?: null,
            'permanent_province' => $request->input('permanent_province') ?: null,
            'permanent_district' => $request->input('permanent_district') ?: null,
        ]);

        if ($guardiansEnabled) {
            $normalizedGuardians = collect($request->input('guardians', []))->map(function ($guardian) {
                $guardian = is_array($guardian) ? $guardian : [];
                $guardian['is_primary'] = !empty($guardian['is_primary']);
                return $guardian;
            })->toArray();
            $request->merge(['guardians' => $normalizedGuardians]);
        }

        if ($languageEnabled) {
            $normalizedLanguages = collect($request->input('languages', []))->map(function ($language) {
                $language = is_array($language) ? $language : [];
                if (array_key_exists('years_of_study', $language) && $language['years_of_study'] === '') {
                    $language['years_of_study'] = null;
                }
                return $language;
            })->toArray();
            $request->merge(['languages' => $normalizedLanguages]);
        }

        $rules = [
            'program' => ['required', 'exists:programs,id'],
            'first_name' => ['required', 'string', 'max:191'],
            'last_name' => ['required', 'string', 'max:191'],
            'other_names' => ['nullable', 'string', 'max:191'],
            'gender' => ['required', Rule::in([1, 2, 3])],
            'dob' => ['required', 'date', 'before:today'],
            'religion' => ['nullable', 'string', 'max:191'],
            'religion_other' => ['nullable', 'string', 'max:191', 'required_if:religion,other'],
            'is_catholic_baptised' => ['nullable', 'boolean'],
            'is_confirmed' => ['nullable', 'boolean'],
            'has_first_communion' => ['nullable', 'boolean'],
            'nationality' => ['required', 'string', 'max:191'],
            'national_id' => ['nullable', 'string', 'max:191'],
            'national_id_issue_date' => ['nullable', 'date'],
            'national_id_issue_place' => ['nullable', 'string', 'max:191'],
            'passport_no' => ['nullable', 'string', 'max:191'],
            'passport_issue_date' => ['nullable', 'date'],
            'passport_issue_country' => ['nullable', 'string', 'max:191'],
            'country' => ['required', 'string', 'max:191'],
            'present_province' => ['required', 'string', 'max:191'],
            'present_district' => ['required', 'string', 'max:191'],
            'present_village' => ['nullable', 'string', 'max:191'],
            'present_address' => ['required', 'string', 'max:255'],
            'permanent_province' => ['nullable', 'string', 'max:191'],
            'permanent_district' => ['nullable', 'string', 'max:191'],
            'permanent_village' => ['nullable', 'string', 'max:191'],
            'permanent_address' => ['nullable', 'string', 'max:255'],
            'postal_address_line1' => ['nullable', 'string', 'max:255'],
            'postal_address_line2' => ['nullable', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:191'],
            'alternate_phone' => ['nullable', 'string', 'max:191'],
            'email' => ['required', 'email', 'max:191', Rule::unique('applications', 'email')->ignore(Auth::guard('applicant')->id())],
            'mother_tongue' => ['nullable', 'string', 'max:191'],
            'studied_in_english' => ['nullable', 'boolean'],
            'photo' => ['required', 'image', 'max:5120'],
            'signature' => ['nullable', 'image', 'max:2048'],
            // 'password' => ['required', 'confirmed', 'min:8'],
            'agree_terms' => [$declarationEnabled ? 'accepted' : 'nullable'],
        ];

        if ($secondChoiceEnabled) {
            $rules['second_program_choice_id'] = ['nullable', 'different:program', 'exists:programs,id'];
        }

        if ($thirdChoiceEnabled) {
            $rules['third_program_choice_id'] = ['nullable', 'different:program', 'different:second_program_choice_id', 'exists:programs,id'];
        }

        $rules['academic_year'] = [$academicYearEnabled ? 'required' : 'nullable', 'string', 'max:191'];
        $rules['birth_city'] = [$birthCityEnabled ? 'required' : 'nullable', 'string', 'max:191'];
        $rules['birth_division'] = [$birthDivisionEnabled ? 'required' : 'nullable', 'string', 'max:191'];
        $rules['birth_region'] = [$birthRegionEnabled ? 'required' : 'nullable', 'string', 'max:191'];
        $rules['birth_country'] = [$birthCountryEnabled ? 'required' : 'nullable', 'string', 'max:191'];

        $rules['registration_fee_bank'] = ['nullable', 'string', 'max:191'];
        $rules['registration_fee_reference'] = ['nullable', 'string', 'max:191'];

        if ($instructionLanguageEnabled || $studiedEnglishEnabled) {
            $instructionRule = ['nullable', 'string', 'max:191'];
            if ($instructionLanguageEnabled && $studiedEnglishEnabled) {
                $instructionRule[] = 'required_if:studied_in_english,0';
            }
            $rules['instruction_language_secondary'] = $instructionRule;
        }

        if ($declarationEnabled) {
            $rules['declaration_name'] = ['required', 'string', 'max:191'];
            $rules['declaration_signed_date'] = ['required', 'date'];
        } else {
            $rules['declaration_name'] = ['nullable', 'string', 'max:191'];
            $rules['declaration_signed_date'] = ['nullable', 'date'];
        }

        if ($guardiansEnabled) {
            $rules['guardians'] = ['required', 'array', 'min:1'];
            $rules['guardians.*.full_name'] = ['required', 'string', 'max:191'];
            $rules['guardians.*.relationship'] = ['nullable', 'string', 'max:191'];
            $rules['guardians.*.type'] = ['required', 'string', 'max:191'];
            $rules['guardians.*.occupation'] = ['nullable', 'string', 'max:191'];
            $rules['guardians.*.email'] = ['nullable', 'email', 'max:191'];
            $rules['guardians.*.phone_primary'] = ['required', 'string', 'max:191'];
            $rules['guardians.*.phone_secondary'] = ['nullable', 'string', 'max:191'];
            $rules['guardians.*.address_line1'] = ['nullable', 'string', 'max:255'];
            $rules['guardians.*.address_line2'] = ['nullable', 'string', 'max:255'];
            $rules['guardians.*.city'] = ['nullable', 'string', 'max:191'];
            $rules['guardians.*.state'] = ['nullable', 'string', 'max:191'];
            $rules['guardians.*.country'] = ['nullable', 'string', 'max:191'];
            $rules['guardians.*.is_primary'] = ['nullable', 'boolean'];
        }

        if ($academicHistoryEnabled) {
            $rules['academic_history'] = ['required', 'array', 'min:1'];
            $rules['academic_history.*.institution_name'] = ['required', 'string', 'max:255'];
            $rules['academic_history.*.city'] = ['nullable', 'string', 'max:191'];
            $rules['academic_history.*.country'] = ['nullable', 'string', 'max:191'];
            $rules['academic_history.*.instruction_language'] = ['nullable', 'string', 'max:191'];
            $rules['academic_history.*.date_from'] = ['nullable', 'date'];
            $rules['academic_history.*.date_to'] = ['nullable', 'date'];
            $rules['academic_history.*.certificate_obtained'] = ['nullable', 'string', 'max:191'];
            $rules['academic_history.*.gce_ol_detail'] = ['nullable', 'string', 'max:191'];
            $rules['academic_history.*.gce_al_detail'] = ['nullable', 'string', 'max:191'];
            $rules['academic_history.*.probatoire_detail'] = ['nullable', 'string', 'max:191'];
            $rules['academic_history.*.baccalaureate_detail'] = ['nullable', 'string', 'max:191'];
            $rules['academic_history.*.notes'] = ['nullable', 'string'];
        }

        if ($languageEnabled) {
            $rules['languages'] = ['nullable', 'array'];
            $rules['languages.*.language'] = ['required_with:languages.*.years_of_study,languages.*.fluency_level', 'nullable', 'string', 'max:191'];
            $rules['languages.*.years_of_study'] = ['nullable', 'integer', 'min:0', 'max:60'];
            $rules['languages.*.fluency_level'] = ['nullable', 'string', 'max:50'];
        }

        if ($documentChecklistEnabled) {
            // Get existing uploaded documents to skip validation for already-uploaded required files
            $application = Auth::guard('applicant')->user();
            $existingDocs = $application->documents()->pluck('file_path', 'document_type')->filter()->toArray();

            foreach ($documentRequirements as $key => $document) {
                // If required but already uploaded in draft, make file optional
                $isRequired = $document['required'] && !isset($existingDocs[$key]);
                
                $rules["documents.$key.file"] = [
                    $isRequired ? 'required' : 'nullable',
                    'file',
                    'mimes:jpg,jpeg,png,pdf',
                    'max:10240',
                ];
                $rules["documents.$key.note"] = ['nullable', 'string', 'max:500'];
            }
        }

        $validated = $request->validate($rules);

        try {
            DB::beginTransaction();

            $application = Auth::guard('applicant')->user();
            
            // Update existing application instead of creating new one
            $application->program_id = $validated['program'];
            $application->first_program_choice_id = $validated['program'];
            $application->second_program_choice_id = $validated['second_program_choice_id'] ?? null;
            $application->third_program_choice_id = $validated['third_program_choice_id'] ?? null;
            $application->apply_date = Carbon::today();
            $application->academic_year = $validated['academic_year'] ?? null;

            $application->first_name = $validated['first_name'];
            $application->last_name = $validated['last_name'];
            $application->other_names = $validated['other_names'] ?? null;
            $application->gender = (int) $validated['gender'];
            $application->dob = $validated['dob'];
            $application->birth_city = $validated['birth_city'] ?? null;
            $application->birth_division = $validated['birth_division'] ?? null;
            $application->birth_region = $validated['birth_region'] ?? null;
            $application->birth_country = $validated['birth_country'] ?? null;

            $application->religion = $validated['religion'] ?? null;
            if ($application->religion === 'other' && !empty($validated['religion_other'])) {
                $application->religion = $validated['religion_other'];
            }
            $application->is_catholic_baptised = $request->boolean('is_catholic_baptised');
            $application->is_confirmed = $request->boolean('is_confirmed');
            $application->has_first_communion = $request->boolean('has_first_communion');
            $application->nationality = $validated['nationality'];
            $application->national_id = $validated['national_id'] ?? null;
            $application->national_id_issue_date = $validated['national_id_issue_date'] ?? null;
            $application->national_id_issue_place = $validated['national_id_issue_place'] ?? null;
            $application->passport_no = $validated['passport_no'] ?? null;
            $application->passport_issue_date = $validated['passport_issue_date'] ?? null;
            $application->passport_issue_country = $validated['passport_issue_country'] ?? null;

            $application->country = $validated['country'];
            $application->present_province = $validated['present_province'];
            $application->present_district = $validated['present_district'];
            $application->present_village = $validated['present_village'] ?? null;
            $application->present_address = $validated['present_address'];
            $application->permanent_province = $validated['permanent_province'] ?? null;
            $application->permanent_district = $validated['permanent_district'] ?? null;
            $application->permanent_village = $validated['permanent_village'] ?? null;
            $application->permanent_address = $validated['permanent_address'] ?? null;
            $application->postal_address_line1 = $validated['postal_address_line1'] ?? null;
            $application->postal_address_line2 = $validated['postal_address_line2'] ?? null;

            $application->phone = $validated['phone'];
            $application->alternate_phone = $validated['alternate_phone'] ?? null;
            $application->email = $validated['email'];

            $application->mother_tongue = $validated['mother_tongue'] ?? null;
            $application->studied_in_english = $studiedEnglishEnabled ? $request->boolean('studied_in_english') : null;
            $application->instruction_language_secondary = $validated['instruction_language_secondary'] ?? null;

            $application->registration_fee_bank = $validated['registration_fee_bank'] ?? null;
            $application->registration_fee_reference = $validated['registration_fee_reference'] ?? null;

            if ($request->hasFile('photo')) {
                $application->photo = $this->uploadImage($request, 'photo', $this->path, 300, 300);
            }
            if ($request->hasFile('signature')) {
                $application->signature = $this->uploadImage($request, 'signature', $this->path, 300, 100);
            }

            $application->declaration_name = $validated['declaration_name'] ?? null;
            $application->declaration_signed_date = $validated['declaration_signed_date'] ?? null;

            $application->status = 1;
            $application->stage = 'submitted';
            $application->progress = Application::stageProgressMap()['submitted'];
            // Password is already set during registration

            $application->portal_meta = [
                'agreed_to_terms' => $request->boolean('agree_terms'),
                'submitted_ip' => $request->ip(),
                'submitted_user_agent' => substr((string) $request->userAgent(), 0, 255),
                'guardian_entries' => count($validated['guardians'] ?? []),
                'academic_history_entries' => count($validated['academic_history'] ?? []),
                'language_entries' => count($validated['languages'] ?? []),
            ];

            $application->save();

            // Auto-assign admission fee (only if admission fee requirement is enabled)
            $feeEnabledValue = env('ADMISSION_FEE_ENABLED', 'true');
            $admissionFeeEnabled = in_array(strtolower($feeEnabledValue), ['true', '1', 'yes', 'on']);
            
            if ($admissionFeeEnabled) {
                $admissionFeeCategory = \App\Models\FeesCategory::where('is_admission', 1)
                    ->where('status', 1)
                    ->first();

                if ($admissionFeeCategory) {
                    // Create a temporary StudentEnroll for the applicant
                    $tempEnroll = new \App\Models\StudentEnroll();
                    $tempEnroll->student_id = $application->id;
                    $tempEnroll->program_id = $application->program_id;
                    $tempEnroll->session_id = null; // Will be assigned when admitted
                    $tempEnroll->semester_id = null;
                    $tempEnroll->section_id = null;
                    $tempEnroll->status = 0; // Inactive until admitted
                    $tempEnroll->save();

                    // Create the admission fee
                    $feeAmount = (float) env('ADMISSION_FEE_AMOUNT', 15000);
                    $dueDays = (int) env('ADMISSION_FEE_DUE_DAYS', 30);
                    
                    $admissionFee = new \App\Models\Fee();
                    $admissionFee->student_enroll_id = $tempEnroll->id;
                    $admissionFee->category_id = $admissionFeeCategory->id;
                    $admissionFee->fee_amount = $feeAmount;
                    $admissionFee->discount_amount = 0;
                    $admissionFee->fine_amount = 0;
                    $admissionFee->paid_amount = 0;
                    $admissionFee->assign_date = now();
                    $admissionFee->due_date = now()->addDays($dueDays);
                    $admissionFee->status = 0; // Unpaid
                    $admissionFee->note = 'Admission fee - Auto-assigned';
                    $admissionFee->save();

                    // Link the fee to the application
                    $application->admission_fee_id = $admissionFee->id;
                    $application->save();
                }
            }

            if ($guardiansEnabled) {
                // Clear existing guardians
                $application->guardians()->delete();
                
                foreach ($validated['guardians'] ?? [] as $index => $guardian) {
                    if (!filled($guardian['full_name'] ?? null)) {
                        continue;
                    }

                    $application->guardians()->create([
                        'full_name' => $guardian['full_name'],
                        'relationship' => $guardian['relationship'] ?? null,
                        'type' => $guardian['type'] ?? null,
                        'occupation' => $guardian['occupation'] ?? null,
                        'email' => $guardian['email'] ?? null,
                        'phone_primary' => $guardian['phone_primary'] ?? null,
                        'phone_secondary' => $guardian['phone_secondary'] ?? null,
                        'address_line1' => $guardian['address_line1'] ?? null,
                        'address_line2' => $guardian['address_line2'] ?? null,
                        'city' => $guardian['city'] ?? null,
                        'state' => $guardian['state'] ?? null,
                        'country' => $guardian['country'] ?? null,
                        'is_primary' => isset($guardian['is_primary']) ? (bool) $guardian['is_primary'] : $index === 0,
                    ]);
                }
            }

            if ($academicHistoryEnabled) {
                // Clear existing academic history
                $application->academicHistories()->delete();

                foreach ($validated['academic_history'] ?? [] as $order => $history) {
                    if (!filled($history['institution_name'] ?? null)) {
                        continue;
                    }

                    $application->academicHistories()->create([
                        'institution_name' => $history['institution_name'],
                        'city' => $history['city'] ?? null,
                        'country' => $history['country'] ?? null,
                        'instruction_language' => $history['instruction_language'] ?? null,
                        'date_from' => $history['date_from'] ?? null,
                        'date_to' => $history['date_to'] ?? null,
                        'certificate_obtained' => $history['certificate_obtained'] ?? null,
                        'gce_ol_detail' => $history['gce_ol_detail'] ?? null,
                        'gce_al_detail' => $history['gce_al_detail'] ?? null,
                        'probatoire_detail' => $history['probatoire_detail'] ?? null,
                        'baccalaureate_detail' => $history['baccalaureate_detail'] ?? null,
                        'notes' => $history['notes'] ?? null,
                        'display_order' => $order,
                    ]);
                }
            }

            if ($languageEnabled) {
                // Clear existing languages
                $application->languages()->delete();

                if (!empty($validated['languages'] ?? [])) {
                    foreach ($validated['languages'] as $language) {
                        if (!filled($language['language'] ?? null)) {
                            continue;
                        }

                        $application->languages()->create([
                            'language' => $language['language'],
                            'years_of_study' => $language['years_of_study'] ?? null,
                            'fluency_level' => $language['fluency_level'] ?? null,
                        ]);
                    }
                }
            }

            if ($documentChecklistEnabled) {
                foreach ($documentRequirements as $key => $document) {
                    $filePath = $this->uploadMedia($request, "documents.$key.file", $this->path);
                    $note = $request->input("documents.$key.note");

                    // Check if document already exists (from draft)
                    $existingDoc = $application->documents()->where('document_type', $key)->first();

                    // If no new file uploaded and document exists, just update notes if provided
                    if (!$filePath && $existingDoc) {
                        if ($note !== null) {
                            $existingDoc->update(['notes' => $note]);
                        }
                        continue;
                    }

                    // Skip if no file (new or existing) and optional
                    if (!$filePath && !$existingDoc && !$document['required']) {
                        continue;
                    }

                    if ($filePath && isset($document['assign_to_column'])) {
                        $application->{$document['assign_to_column']} = $filePath;
                    }

                    // Use updateOrCreate to avoid duplicates
                    $application->documents()->updateOrCreate(
                        ['document_type' => $key],
                        [
                            'file_path' => $filePath ?: ($existingDoc->file_path ?? null),
                            'is_received' => (bool) ($filePath ?: ($existingDoc->file_path ?? null)),
                            'is_optional' => !$document['required'],
                            'notes' => $note,
                        ]
                    );
                }
            }

            $application->save();

            $application->recordStatus('submitted', __('Your application has been received.'), 1, __('application_stage.submitted'), null, 'system');

            DB::commit();

            Flasher::addSuccess(__('msg_sent_successfully'), __('msg_success'));

            return redirect()->route('application.dashboard')->with('success', __('msg_sent_successfully'));
        } catch (\Exception $e) {
            DB::rollBack();
            report($e);

            Flasher::addError(__('msg_created_error'), __('msg_error'));

            return redirect()->back()->withInput();
        }
    }

    /**
     * Save application as draft for continuing later.
     * This method uses lenient validation to allow partial data.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function saveDraft(Request $request)
    {
        if (!Auth::guard('applicant')->check()) {
            return response()->json([
                'success' => false,
                'message' => __('Please login to continue.'),
                'redirect' => route('application.login')
            ], 401);
        }

        $application = Auth::guard('applicant')->user();

        // Only allow saving drafts for applications in draft stage
        if ($application->stage !== 'draft') {
            return response()->json([
                'success' => false,
                'message' => __('Application has already been submitted and cannot be modified.'),
            ], 400);
        }

        try {
            DB::beginTransaction();

            // Check field toggles
            $guardiansEnabled = $this->fieldEnabled('application_guardians');
            $academicHistoryEnabled = $this->fieldEnabled('application_academic_history');
            $languageEnabled = $this->fieldEnabled('application_language_proficiency');
            $documentChecklistEnabled = $this->fieldEnabled('application_document_checklist');
            $studiedEnglishEnabled = $this->fieldEnabled('application_studied_in_english');
            $documentRequirements = $this->documentRequirements();

            // Basic lenient validation - only validate format, not required
            $rules = [
                'program' => ['nullable', 'exists:programs,id'],
                'second_program_choice_id' => ['nullable', 'exists:programs,id'],
                'third_program_choice_id' => ['nullable', 'exists:programs,id'],
                'first_name' => ['nullable', 'string', 'max:191'],
                'last_name' => ['nullable', 'string', 'max:191'],
                'other_names' => ['nullable', 'string', 'max:191'],
                'gender' => ['nullable', 'in:1,2,3'],
                'dob' => ['nullable', 'date', 'before:today'],
                'religion' => ['nullable', 'string', 'max:191'],
                'religion_other' => ['nullable', 'string', 'max:191'],
                'nationality' => ['nullable', 'string', 'max:191'],
                'national_id' => ['nullable', 'string', 'max:191'],
                'national_id_issue_date' => ['nullable', 'date'],
                'national_id_issue_place' => ['nullable', 'string', 'max:191'],
                'passport_no' => ['nullable', 'string', 'max:191'],
                'passport_issue_date' => ['nullable', 'date'],
                'passport_issue_country' => ['nullable', 'string', 'max:191'],
                'country' => ['nullable', 'string', 'max:191'],
                'present_province' => ['nullable', 'string', 'max:191'],
                'present_district' => ['nullable', 'string', 'max:191'],
                'present_village' => ['nullable', 'string', 'max:191'],
                'present_address' => ['nullable', 'string', 'max:255'],
                'permanent_province' => ['nullable', 'string', 'max:191'],
                'permanent_district' => ['nullable', 'string', 'max:191'],
                'permanent_village' => ['nullable', 'string', 'max:191'],
                'permanent_address' => ['nullable', 'string', 'max:255'],
                'postal_address_line1' => ['nullable', 'string', 'max:255'],
                'postal_address_line2' => ['nullable', 'string', 'max:255'],
                'phone' => ['nullable', 'string', 'max:191'],
                'alternate_phone' => ['nullable', 'string', 'max:191'],
                'email' => ['nullable', 'email', 'max:191', Rule::unique('applications', 'email')->ignore($application->id)],
                'mother_tongue' => ['nullable', 'string', 'max:191'],
                'studied_in_english' => ['nullable', 'boolean'],
                'instruction_language_secondary' => ['nullable', 'string', 'max:191'],
                'academic_year' => ['nullable', 'string', 'max:191'],
                'birth_city' => ['nullable', 'string', 'max:191'],
                'birth_division' => ['nullable', 'string', 'max:191'],
                'birth_region' => ['nullable', 'string', 'max:191'],
                'birth_country' => ['nullable', 'string', 'max:191'],
                'registration_fee_bank' => ['nullable', 'string', 'max:191'],
                'registration_fee_reference' => ['nullable', 'string', 'max:191'],
                'declaration_name' => ['nullable', 'string', 'max:191'],
                'declaration_signed_date' => ['nullable', 'date'],
                'photo' => ['nullable', 'image', 'max:5120'],
                'signature' => ['nullable', 'image', 'max:2048'],
            ];

            // Validate guardians if enabled
            if ($guardiansEnabled) {
                $rules['guardians'] = ['nullable', 'array'];
                $rules['guardians.*.full_name'] = ['nullable', 'string', 'max:191'];
                $rules['guardians.*.type'] = ['nullable', 'string', 'max:50'];
                $rules['guardians.*.relationship'] = ['nullable', 'string', 'max:50'];
                $rules['guardians.*.occupation'] = ['nullable', 'string', 'max:191'];
                $rules['guardians.*.email'] = ['nullable', 'email', 'max:191'];
                $rules['guardians.*.phone_primary'] = ['nullable', 'string', 'max:50'];
                $rules['guardians.*.phone_secondary'] = ['nullable', 'string', 'max:50'];
                $rules['guardians.*.address_line1'] = ['nullable', 'string', 'max:255'];
                $rules['guardians.*.address_line2'] = ['nullable', 'string', 'max:255'];
                $rules['guardians.*.city'] = ['nullable', 'string', 'max:100'];
                $rules['guardians.*.state'] = ['nullable', 'string', 'max:100'];
                $rules['guardians.*.country'] = ['nullable', 'string', 'max:100'];
            }

            // Validate academic history if enabled
            if ($academicHistoryEnabled) {
                $rules['academic_history'] = ['nullable', 'array'];
                $rules['academic_history.*.institution_name'] = ['nullable', 'string', 'max:255'];
                $rules['academic_history.*.city'] = ['nullable', 'string', 'max:100'];
                $rules['academic_history.*.country'] = ['nullable', 'string', 'max:100'];
                $rules['academic_history.*.instruction_language'] = ['nullable', 'string', 'max:100'];
                $rules['academic_history.*.date_from'] = ['nullable', 'date'];
                $rules['academic_history.*.date_to'] = ['nullable', 'date'];
                $rules['academic_history.*.certificate_obtained'] = ['nullable', 'string', 'max:191'];
                $rules['academic_history.*.gce_ol_detail'] = ['nullable', 'string', 'max:500'];
                $rules['academic_history.*.gce_al_detail'] = ['nullable', 'string', 'max:500'];
                $rules['academic_history.*.probatoire_detail'] = ['nullable', 'string', 'max:500'];
                $rules['academic_history.*.baccalaureate_detail'] = ['nullable', 'string', 'max:500'];
                $rules['academic_history.*.notes'] = ['nullable', 'string', 'max:500'];
            }

            // Validate languages if enabled
            if ($languageEnabled) {
                $rules['languages'] = ['nullable', 'array'];
                $rules['languages.*.language'] = ['nullable', 'string', 'max:100'];
                $rules['languages.*.years_of_study'] = ['nullable', 'integer', 'min:0', 'max:50'];
                $rules['languages.*.fluency_level'] = ['nullable', 'in:excellent,good,fair,minimal'];
            }

            $validated = $request->validate($rules);

            // Update basic fields (only if provided)
            if ($request->filled('program')) {
                $application->program_id = $validated['program'];
                $application->first_program_choice_id = $validated['program'];
            }
            if ($request->has('second_program_choice_id')) {
                $application->second_program_choice_id = $request->input('second_program_choice_id') ?: null;
            }
            if ($request->has('third_program_choice_id')) {
                $application->third_program_choice_id = $request->input('third_program_choice_id') ?: null;
            }

            // Personal Information
            if ($request->filled('first_name')) {
                $application->first_name = $validated['first_name'];
            }
            if ($request->filled('last_name')) {
                $application->last_name = $validated['last_name'];
            }
            if ($request->has('other_names')) {
                $application->other_names = $request->input('other_names') ?: null;
            }
            if ($request->filled('gender')) {
                $application->gender = (int) $validated['gender'];
            }
            if ($request->filled('dob')) {
                $application->dob = $validated['dob'];
            }
            if ($request->has('nationality')) {
                $application->nationality = $request->input('nationality') ?: null;
            }

            // Birth details
            if ($request->has('birth_city')) {
                $application->birth_city = $request->input('birth_city') ?: null;
            }
            if ($request->has('birth_division')) {
                $application->birth_division = $request->input('birth_division') ?: null;
            }
            if ($request->has('birth_region')) {
                $application->birth_region = $request->input('birth_region') ?: null;
            }
            if ($request->has('birth_country')) {
                $application->birth_country = $request->input('birth_country') ?: null;
            }

            // Religion & Catholic sacraments
            if ($request->has('religion')) {
                $religion = $request->input('religion');
                if ($religion === 'other' && $request->filled('religion_other')) {
                    $religion = $request->input('religion_other');
                }
                $application->religion = $religion ?: null;
            }
            $application->is_catholic_baptised = $request->boolean('is_catholic_baptised');
            $application->is_confirmed = $request->boolean('is_confirmed');
            $application->has_first_communion = $request->boolean('has_first_communion');

            // ID & Passport
            if ($request->has('national_id')) {
                $application->national_id = $request->input('national_id') ?: null;
            }
            if ($request->has('national_id_issue_date')) {
                $application->national_id_issue_date = $request->input('national_id_issue_date') ?: null;
            }
            if ($request->has('national_id_issue_place')) {
                $application->national_id_issue_place = $request->input('national_id_issue_place') ?: null;
            }
            if ($request->has('passport_no')) {
                $application->passport_no = $request->input('passport_no') ?: null;
            }
            if ($request->has('passport_issue_date')) {
                $application->passport_issue_date = $request->input('passport_issue_date') ?: null;
            }
            if ($request->has('passport_issue_country')) {
                $application->passport_issue_country = $request->input('passport_issue_country') ?: null;
            }

            // Address
            if ($request->has('country')) {
                $application->country = $request->input('country') ?: null;
            }
            if ($request->has('present_province')) {
                $application->present_province = $request->input('present_province') ?: null;
            }
            if ($request->has('present_district')) {
                $application->present_district = $request->input('present_district') ?: null;
            }
            if ($request->has('present_village')) {
                $application->present_village = $request->input('present_village') ?: null;
            }
            if ($request->has('present_address')) {
                $application->present_address = $request->input('present_address') ?: null;
            }
            if ($request->has('permanent_province')) {
                $application->permanent_province = $request->input('permanent_province') ?: null;
            }
            if ($request->has('permanent_district')) {
                $application->permanent_district = $request->input('permanent_district') ?: null;
            }
            if ($request->has('permanent_village')) {
                $application->permanent_village = $request->input('permanent_village') ?: null;
            }
            if ($request->has('permanent_address')) {
                $application->permanent_address = $request->input('permanent_address') ?: null;
            }
            if ($request->has('postal_address_line1')) {
                $application->postal_address_line1 = $request->input('postal_address_line1') ?: null;
            }
            if ($request->has('postal_address_line2')) {
                $application->postal_address_line2 = $request->input('postal_address_line2') ?: null;
            }

            // Contact
            if ($request->has('phone')) {
                $application->phone = $request->input('phone') ?: null;
            }
            if ($request->has('alternate_phone')) {
                $application->alternate_phone = $request->input('alternate_phone') ?: null;
            }
            if ($request->filled('email')) {
                $application->email = $validated['email'];
            }

            // Language
            if ($request->has('mother_tongue')) {
                $application->mother_tongue = $request->input('mother_tongue') ?: null;
            }
            if ($studiedEnglishEnabled && $request->has('studied_in_english')) {
                $application->studied_in_english = $request->boolean('studied_in_english');
            }
            if ($request->has('instruction_language_secondary')) {
                $application->instruction_language_secondary = $request->input('instruction_language_secondary') ?: null;
            }

            // Academic year & Registration Fee
            if ($request->has('academic_year')) {
                $application->academic_year = $request->input('academic_year') ?: null;
            }
            if ($request->has('registration_fee_bank')) {
                $application->registration_fee_bank = $request->input('registration_fee_bank') ?: null;
            }
            if ($request->has('registration_fee_reference')) {
                $application->registration_fee_reference = $request->input('registration_fee_reference') ?: null;
            }

            // Declaration
            if ($request->has('declaration_name')) {
                $application->declaration_name = $request->input('declaration_name') ?: null;
            }
            if ($request->has('declaration_signed_date')) {
                $application->declaration_signed_date = $request->input('declaration_signed_date') ?: null;
            }

            // Handle photo upload
            if ($request->hasFile('photo')) {
                $application->photo = $this->uploadImage($request, 'photo', $this->path, 300, 300);
            }

            // Handle signature upload
            if ($request->hasFile('signature')) {
                $application->signature = $this->uploadImage($request, 'signature', $this->path, 300, 100);
            }

            // Calculate draft progress (estimate based on filled fields)
            $progress = $this->calculateDraftProgress($application, $guardiansEnabled, $academicHistoryEnabled, $languageEnabled);
            $application->draft_progress = $progress;
            $application->draft_last_saved_at = now();

            $application->save();

            // Save guardians if enabled and provided
            if ($guardiansEnabled && $request->has('guardians')) {
                $application->guardians()->delete();
                foreach ($request->input('guardians', []) as $index => $guardian) {
                    if (!filled($guardian['full_name'] ?? null)) {
                        continue;
                    }
                    $application->guardians()->create([
                        'full_name' => $guardian['full_name'],
                        'relationship' => $guardian['relationship'] ?? null,
                        'type' => $guardian['type'] ?? null,
                        'occupation' => $guardian['occupation'] ?? null,
                        'email' => $guardian['email'] ?? null,
                        'phone_primary' => $guardian['phone_primary'] ?? null,
                        'phone_secondary' => $guardian['phone_secondary'] ?? null,
                        'address_line1' => $guardian['address_line1'] ?? null,
                        'address_line2' => $guardian['address_line2'] ?? null,
                        'city' => $guardian['city'] ?? null,
                        'state' => $guardian['state'] ?? null,
                        'country' => $guardian['country'] ?? null,
                        'is_primary' => isset($guardian['is_primary']) ? (bool) $guardian['is_primary'] : $index === 0,
                    ]);
                }
            }

            // Save academic history if enabled and provided
            if ($academicHistoryEnabled && $request->has('academic_history')) {
                $application->academicHistories()->delete();
                foreach ($request->input('academic_history', []) as $order => $history) {
                    if (!filled($history['institution_name'] ?? null)) {
                        continue;
                    }
                    $application->academicHistories()->create([
                        'institution_name' => $history['institution_name'],
                        'city' => $history['city'] ?? null,
                        'country' => $history['country'] ?? null,
                        'instruction_language' => $history['instruction_language'] ?? null,
                        'date_from' => $history['date_from'] ?? null,
                        'date_to' => $history['date_to'] ?? null,
                        'certificate_obtained' => $history['certificate_obtained'] ?? null,
                        'gce_ol_detail' => $history['gce_ol_detail'] ?? null,
                        'gce_al_detail' => $history['gce_al_detail'] ?? null,
                        'probatoire_detail' => $history['probatoire_detail'] ?? null,
                        'baccalaureate_detail' => $history['baccalaureate_detail'] ?? null,
                        'notes' => $history['notes'] ?? null,
                        'display_order' => $order,
                    ]);
                }
            }

            // Save languages if enabled and provided
            if ($languageEnabled && $request->has('languages')) {
                $application->languages()->delete();
                foreach ($request->input('languages', []) as $language) {
                    if (!filled($language['language'] ?? null)) {
                        continue;
                    }
                    $application->languages()->create([
                        'language' => $language['language'],
                        'years_of_study' => $language['years_of_study'] ?? null,
                        'fluency_level' => $language['fluency_level'] ?? null,
                    ]);
                }
            }

            // Save documents if enabled and files provided
            if ($documentChecklistEnabled) {
                foreach ($documentRequirements as $key => $document) {
                    if ($request->hasFile("documents.$key.file")) {
                        $filePath = $this->uploadMedia($request, "documents.$key.file", $this->path);
                        $note = $request->input("documents.$key.note");

                        if ($filePath) {
                            $application->documents()->updateOrCreate(
                                ['document_type' => $key],
                                [
                                    'file_path' => $filePath,
                                    'notes' => $note,
                                    'is_received' => true,
                                    'is_optional' => $document['optional'] ?? false,
                                ]
                            );
                        }
                    }
                }
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => __('Draft saved successfully. You can continue your application later.'),
                'last_saved' => now()->format('F j, Y g:i A'),
                'progress' => $progress,
            ]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => __('Validation error. Please check your input.'),
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            DB::rollBack();
            report($e);
            return response()->json([
                'success' => false,
                'message' => __('An error occurred while saving your draft. Please try again.'),
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * Calculate draft completion progress based on filled fields.
     */
    protected function calculateDraftProgress($application, $guardiansEnabled, $academicHistoryEnabled, $languageEnabled): int
    {
        $totalFields = 0;
        $filledFields = 0;

        // Core required fields (weight: higher importance)
        $coreFields = ['program_id', 'first_name', 'last_name', 'gender', 'dob', 'email', 'phone', 'photo'];
        $totalFields += count($coreFields) * 2; // Double weight
        foreach ($coreFields as $field) {
            if (!empty($application->$field)) {
                $filledFields += 2;
            }
        }

        // Address fields
        $addressFields = ['country', 'present_province', 'present_district', 'present_address', 'nationality'];
        $totalFields += count($addressFields);
        foreach ($addressFields as $field) {
            if (!empty($application->$field)) {
                $filledFields++;
            }
        }

        // Optional but tracked fields
        $optionalFields = ['other_names', 'national_id', 'passport_no', 'birth_city', 'religion', 'mother_tongue'];
        $totalFields += count($optionalFields);
        foreach ($optionalFields as $field) {
            if (!empty($application->$field)) {
                $filledFields++;
            }
        }

        // Guardians (if enabled)
        if ($guardiansEnabled) {
            $totalFields += 5;
            $guardianCount = $application->guardians()->count();
            $filledFields += min($guardianCount, 5);
        }

        // Academic history (if enabled)
        if ($academicHistoryEnabled) {
            $totalFields += 3;
            $historyCount = $application->academicHistories()->count();
            $filledFields += min($historyCount, 3);
        }

        // Languages (if enabled)
        if ($languageEnabled) {
            $totalFields += 2;
            $langCount = $application->languages()->count();
            $filledFields += min($langCount, 2);
        }

        return $totalFields > 0 ? round(($filledFields / $totalFields) * 100) : 0;
    }

    /**
     * Handle document resubmission from applicant portal.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function resubmitDocuments(Request $request)
    {
        $application = Auth::guard('applicant')->user();

        // Get documents that need resubmission
        $documentsNeedingResubmission = $application->documents()
            ->where('needs_resubmission', true)
            ->whereNull('resubmitted_at')
            ->pluck('document_type')
            ->toArray();

        if (empty($documentsNeedingResubmission)) {
            return redirect()->route('application.dashboard')
                ->with('info', __('No documents require resubmission.'));
        }

        // Build validation rules
        $rules = [];
        foreach ($documentsNeedingResubmission as $documentType) {
            $rules["documents.$documentType"] = ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:10240'];
        }

        $request->validate($rules, [
            'documents.*.required' => __('Please upload a file for all requested documents.'),
            'documents.*.file' => __('The uploaded item must be a valid file.'),
            'documents.*.mimes' => __('Only JPG, PNG, and PDF files are accepted.'),
            'documents.*.max' => __('File size must not exceed 10MB.'),
        ]);

        try {
            DB::beginTransaction();

            $resubmittedDocuments = [];

            foreach ($documentsNeedingResubmission as $documentType) {
                if ($request->hasFile("documents.$documentType")) {
                    $document = $application->documents()->where('document_type', $documentType)->first();

                    if ($document) {
                        // Upload new file
                        $newFilePath = $this->uploadMedia($request, "documents.$documentType", $this->path);

                        if ($newFilePath) {
                            // Optionally remove old file (uncomment if desired)
                            // if ($document->file_path && is_file(public_path('uploads/'.$this->path.'/'.$document->file_path))) {
                            //     unlink(public_path('uploads/'.$this->path.'/'.$document->file_path));
                            // }

                            $document->update([
                                'file_path' => $newFilePath,
                                'is_received' => false, // Reset to pending review
                                'resubmitted_at' => now(),
                            ]);

                            $resubmittedDocuments[] = $documentType;
                        }
                    }
                }
            }

            // Check if all documents requiring resubmission have been handled
            $remainingDocuments = $application->documents()
                ->where('needs_resubmission', true)
                ->whereNull('resubmitted_at')
                ->count();

            // If all documents are resubmitted, update application stage
            if ($remainingDocuments === 0 && $application->stage === 'documents_required') {
                $application->stage = 'under_review';
                $application->save();

                $application->recordStatus(
                    'under_review',
                    __('All requested documents have been resubmitted. Your application is now under review.'),
                    $application->status,
                    __('application_stage.under_review'),
                    null,
                    'applicant'
                );
            } else {
                // Record partial resubmission
                $application->recordStatus(
                    $application->stage,
                    __('Documents resubmitted: ') . implode(', ', $resubmittedDocuments),
                    $application->status,
                    null,
                    null,
                    'applicant'
                );
            }

            DB::commit();

            Flasher::addSuccess(__('Documents have been resubmitted successfully. Our team will review them shortly.'));

            return redirect()->route('application.dashboard');

        } catch (\Exception $e) {
            DB::rollBack();
            report($e);

            Flasher::addError(__('An error occurred while uploading your documents. Please try again.'));

            return redirect()->route('application.dashboard');
        }
    }

    /**
     * Document checklist configuration displayed on the applicant wizard.
     */
    protected function documentRequirements(): array
    {
        return ApplicationDocumentRequirements::all();
    }

    /**
     * Determine if an application field toggle is currently enabled.
     */
    protected function fieldEnabled(string $slug): bool
    {
        static $fieldCache = [];

        if (!array_key_exists($slug, $fieldCache)) {
            $fieldCache[$slug] = (int) optional(Field::field($slug))->status === 1;
        }

        return $fieldCache[$slug];
    }

    public function loginForm()
    {
        if (Auth::guard('applicant')->check()) {
            return redirect()->route('application.dashboard');
        }

        return view('application.portal.login', [
            'title' => __('Application Portal Login'),
        ]);
    }

    public function authenticate(Request $request)
    {
        $credentials = $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        if (Auth::guard('applicant')->attempt($credentials, $request->boolean('remember'))) {
            $request->session()->regenerate();
            /** @var \App\Models\Application $user */
            $user = Auth::guard('applicant')->user();
            $user->portal_last_login_at = now();
            $user->save();

            return redirect()->intended(route('application.dashboard'));
        }

        return back()->withErrors([
            'email' => __('auth.failed'),
        ])->onlyInput('email');
    }

    public function logout(Request $request)
    {
        Auth::guard('applicant')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('application.login');
    }

    public function dashboard()
    {
        $application = Auth::guard('applicant')->user()->load([
            'program',
            'guardians',
            'academicHistories',
            'languages',
            'documents',
            'religionDetail',
            // 'presentProvince',
            // 'presentDistrict',
            // 'permanentProvince',
            // 'permanentDistrict',
            'admissionFee.category',
            'admissionFee.paymentReceipts',
            'statusUpdates' => function ($query) {
                $query->where('is_visible_to_applicant', true)
                    ->orderBy('created_at', 'desc');
            },
        ]);

        // Get system settings for currency
        $setting = Setting::where('status', '1')->first();

        return view('application.portal.dashboard', [
            'application' => $application,
            'timeline' => $application->statusUpdates,
            'setting' => $setting,
        ]);
    }

    public function timeline()
    {
        $application = Auth::guard('applicant')->user()->load([
            'statusUpdates' => function ($query) {
                $query->where('is_visible_to_applicant', true)
                    ->orderBy('created_at', 'asc');
            },
        ]);

        return view('application.portal.timeline', [
            'application' => $application,
            'timeline' => $application->statusUpdates,
        ]);
    }

    /**
     * Upload admission fee payment receipt
     */
    public function uploadAdmissionFeeReceipt(Request $request)
    {
        $application = Auth::guard('applicant')->user();

        // Check if application has admission fee
        if (!$application->admissionFee) {
            Flasher::error(__('No admission fee found for your application.'));
            return redirect()->route('application.dashboard');
        }

        // Validate request
        $request->validate([
            'payment_date' => 'required|date|before_or_equal:today',
            'amount' => 'required|numeric|min:0.01',
            'payment_reference' => 'required|string|max:255',
            'payment_method' => 'required|in:1,2,3,4,5,6',
            'receipt_file' => 'required|file|mimes:pdf,jpg,jpeg,png|max:2048',
            'student_note' => 'nullable|string|max:500',
        ]);

        // Upload receipt file
        $receiptPath = null;
        if ($request->hasFile('receipt_file')) {
            $receiptPath = $this->uploadMedia($request, 'receipt_file', $this->path);
        }

        // Create payment receipt record
        $paymentReceipt = new \App\Models\PaymentReceipt();
        $paymentReceipt->fee_id = $application->admissionFee->id;
        $paymentReceipt->student_id = $application->id; // Using application ID as student_id for applicants
        $paymentReceipt->receipt_file = $receiptPath;
        $paymentReceipt->payment_reference = $request->payment_reference;
        $paymentReceipt->payment_date = $request->payment_date;
        $paymentReceipt->amount = $request->amount;
        $paymentReceipt->payment_method = $request->payment_method;
        $paymentReceipt->student_note = $request->student_note;
        $paymentReceipt->verification_status = 'pending';
        $paymentReceipt->save();

        Flasher::success(__('Payment receipt uploaded successfully! Your payment will be verified by our administration team shortly.'));
        
        return redirect()->route('application.dashboard');
    }

    public function registerForm()
    {
        if (Auth::guard('applicant')->check()) {
            return redirect()->route('application.dashboard');
        }

        return view('application.portal.register', [
            'title' => __('Application Portal Registration'),
        ]);
    }

    public function register(Request $request)
    {
        $request->validate([
            'first_name' => ['required', 'string', 'max:191'],
            'last_name' => ['required', 'string', 'max:191'],
            'email' => ['required', 'string', 'email', 'max:191', 'unique:applications'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'agree_terms' => ['accepted'],
        ]);

        try {
            DB::beginTransaction();

            $application = new Application();
            $application->first_name = $request->first_name;
            $application->last_name = $request->last_name;
            $application->email = $request->email;
            $application->password = Hash::make($request->password);
            $application->status = 0; // Inactive/Draft
            $application->stage = 'draft';
            $application->progress = 0;
            
            // Set default values for required fields that are nullable in DB now
            $application->gender = null;
            $application->dob = null;
            $application->program_id = null;
            $application->first_program_choice_id = null;
            $application->country = null;
            $application->present_province = null;
            $application->present_district = null;
            $application->present_address = null;
            $application->phone = null;
            $application->photo = null;

            $application->save();

            // Generate registration number
            $application->registration_no = intval(10000000) + $application->id;
            $application->save();

            DB::commit();

            Auth::guard('applicant')->login($application);
            $application->portal_last_login_at = now();
            $application->save();

            Flasher::addSuccess(__('Account created successfully. Please complete your application.'), __('msg_success'));

            return redirect()->route('application.index');

        } catch (\Exception $e) {
            DB::rollBack();
            report($e);
            Flasher::addError(__('Registration failed: ') . $e->getMessage(), __('msg_error'));
            return back()->withInput();
        }
    }

    /**
     * Show the forgot password form.
     *
     * @return \Illuminate\Http\Response
     */
    public function showForgotPasswordForm()
    {
        if (Auth::guard('applicant')->check()) {
            return redirect()->route('application.dashboard');
        }

        return view('application.portal.passwords.email', [
            'title' => __('Forgot Password - Application Portal'),
        ]);
    }

    /**
     * Send a password reset link to the applicant.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function sendResetLinkEmail(Request $request)
    {
        // Validate email
        $request->validate([
            'email' => 'required|email',
        ]);

        // Find the applicant
        $applicant = Application::where('email', $request->email)->first();
        $mail = MailSetting::where('status', '1')->first();

        if (!$applicant) {
            // Don't reveal if email exists or not for security
            return redirect()->back()->with('info', __('If an account with that email exists, we have sent a password reset link.'));
        }

        if (!$mail || !$mail->sender_email || !$mail->sender_name) {
            return redirect()->back()->with('error', __('Email service is not configured. Please contact support.'));
        }

        try {
            // Generate a secure token
            $token = bin2hex(random_bytes(32));
            
            // Store token in password_resets table
            DB::table('password_resets')
                ->where('email', $applicant->email)
                ->delete(); // Remove any existing tokens for this email
            
            DB::table('password_resets')->insert([
                'email' => $applicant->email,
                'token' => $token,
                'created_at' => now(),
            ]);

            // Prepare email data
            $data = [
                'first_name' => $applicant->first_name,
                'last_name' => $applicant->last_name,
                'email' => $applicant->email,
                'token' => $token,
                'subject' => __('Application Portal - Password Reset Request'),
                'from' => $mail->sender_email,
                'sender' => $mail->sender_name,
                'reset_url' => route('application.password.reset', [$token, $applicant->email]),
            ];

            // Send email using the custom mailable
            Mail::to($applicant->email)->send(new \App\Mail\ApplicantForgotPassword($data));

            return redirect()->back()->with('success', __('We have sent a password reset link to your email address. Please check your inbox (and spam folder).'));

        } catch (\Exception $e) {
            report($e);
            return redirect()->back()->with('error', __('Failed to send reset email. Please try again later.'));
        }
    }

    /**
     * Show the password reset form.
     *
     * @param  string  $token
     * @param  string  $email
     * @return \Illuminate\Http\Response
     */
    public function showResetForm($token, $email)
    {
        if (Auth::guard('applicant')->check()) {
            return redirect()->route('application.dashboard');
        }

        // Verify the token exists and is not expired (60 minutes)
        $passwordReset = DB::table('password_resets')
            ->where('email', $email)
            ->where('token', $token)
            ->where('created_at', '>', now()->subMinutes(60))
            ->first();

        if (!$passwordReset) {
            return redirect()->route('application.password.request')
                ->with('error', __('This password reset link is invalid or has expired. Please request a new one.'));
        }

        return view('application.portal.passwords.reset', [
            'title' => __('Reset Password - Application Portal'),
            'token' => $token,
            'email' => $email,
        ]);
    }

    /**
     * Reset the applicant's password.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function resetPassword(Request $request)
    {
        // Validate input
        $request->validate([
            'token' => 'required',
            'email' => 'required|email',
            'password' => 'required|min:8|confirmed',
        ]);

        // Verify the token
        $passwordReset = DB::table('password_resets')
            ->where('email', $request->email)
            ->where('token', $request->token)
            ->where('created_at', '>', now()->subMinutes(60))
            ->first();

        if (!$passwordReset) {
            return redirect()->back()->with('error', __('This password reset link is invalid or has expired.'));
        }

        // Find the applicant
        $applicant = Application::where('email', $request->email)->first();

        if (!$applicant) {
            return redirect()->back()->with('error', __('Account not found.'));
        }

        try {
            // Update password
            $applicant->password = Hash::make($request->password);
            
            // Also store encrypted password text if the field exists
            if (isset($applicant->password_text)) {
                $applicant->password_text = Crypt::encryptString($request->password);
            }
            
            $applicant->save();

            // Delete the used token
            DB::table('password_resets')
                ->where('email', $request->email)
                ->delete();

            Flasher::addSuccess(__('Your password has been reset successfully! You can now sign in.'), __('msg_success'));

            return redirect()->route('application.login');

        } catch (\Exception $e) {
            report($e);
            return redirect()->back()->with('error', __('Failed to reset password. Please try again.'));
        }
    }
}
