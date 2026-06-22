<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Applicant;
use App\Models\Application;
use App\Models\ApplicationSetting;
use App\Models\DegreeType;
use App\Models\Program;
use App\Models\Province;
use App\Models\Session;
use App\Models\Setting;
use App\Models\MailSetting;
use App\Services\DegreeTypeFormConfig;
use App\Traits\FileUploader;
use Carbon\Carbon;
use Flasher\Laravel\Facade\Flasher;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;

class ApplicationController extends Controller
{
    use FileUploader;

    protected $title, $route, $view, $path;

    public function __construct()
    {
        $this->title    = trans_choice('module_application', 1);
        $this->route    = 'application';
        $this->view     = 'application';
        $this->path     = 'student';
    }

    /* ===================================================================
     |  Helpers
     |===================================================================*/

    /** The logged-in applicant account. */
    protected function applicant(): ?Applicant
    {
        return Auth::guard('applicant')->user();
    }

    /** Abort unless the application belongs to the logged-in applicant. */
    protected function authorizeApplication(Application $application): void
    {
        abort_unless(
            (int) $application->applicant_id === (int) Auth::guard('applicant')->id(),
            403
        );
    }

    /** Resolve a field/section toggle for a degree type's form. */
    protected function fieldEnabled(?DegreeType $degreeType, string $slug): bool
    {
        return DegreeTypeFormConfig::fieldEnabled($degreeType, $slug);
    }

    /** The document checklist for a degree type's form. */
    protected function documentRequirements(?DegreeType $degreeType): array
    {
        return DegreeTypeFormConfig::documents($degreeType);
    }

    /* ===================================================================
     |  Hub & intake
     |===================================================================*/

    /** Legacy entry point — send to the applications hub. */
    public function index()
    {
        return redirect()->route('application.dashboard');
    }

    /** "My Account" hub: list all of the applicant's applications. */
    public function dashboard()
    {
        $applicant = $this->applicant();

        $applications = $applicant->applications()
            ->with(['program', 'degreeType', 'session', 'admissionFee.category', 'admissionFee.paymentReceipts'])
            ->orderBy('id', 'desc')
            ->get();

        $setting = Setting::where('status', '1')->first();
        $applicationSetting = ApplicationSetting::where('slug', 'admission')->first();

        return view('application.portal.dashboard', [
            'applicant' => $applicant,
            'applications' => $applications,
            'setting' => $setting,
            'applicationSetting' => $applicationSetting,
        ]);
    }

    /** Intake step: choose a degree type, open intake session and programme. */
    public function create()
    {
        $applicant = $this->applicant();

        $degreeTypes = DegreeType::where('status', 1)->orderBy('sort_order')->orderBy('title')->get();
        $sessions = Session::where('applications_open', 1)->orderBy('title', 'desc')->get();
        $programs = Program::where('status', '1')->orderBy('title', 'asc')->get(['id', 'title', 'degree_type_id', 'faculty_id']);

        // Per-degree intro/requirements + fee, for the live preview panel.
        $degreeMeta = [];
        foreach ($degreeTypes as $dt) {
            $degreeMeta[$dt->id] = DegreeTypeFormConfig::settings($dt);
        }

        $setting = Setting::where('status', '1')->first();
        $applicationSetting = ApplicationSetting::where('slug', 'admission')->first();

        return view('application.portal.create', [
            'applicant' => $applicant,
            'degreeTypes' => $degreeTypes,
            'sessions' => $sessions,
            'programs' => $programs,
            'degreeMeta' => $degreeMeta,
            'setting' => $setting,
            'applicationSetting' => $applicationSetting,
        ]);
    }

    /** Create a new application shell from the intake step, then open its form. */
    public function store(Request $request)
    {
        $applicant = $this->applicant();

        $request->merge([
            'second_program_choice_id' => $request->input('second_program_choice_id') ?: null,
            'third_program_choice_id' => $request->input('third_program_choice_id') ?: null,
        ]);

        $validated = $request->validate([
            'degree_type_id' => ['required', 'exists:degree_types,id'],
            'session_id' => ['required', 'exists:sessions,id'],
            'program' => ['required', 'exists:programs,id'],
            'second_program_choice_id' => ['nullable', 'different:program', 'exists:programs,id'],
            'third_program_choice_id' => ['nullable', 'different:program', 'different:second_program_choice_id', 'exists:programs,id'],
        ]);

        // Intake session must currently be open for applications.
        $session = Session::find($validated['session_id']);
        if (!$session || !$session->applications_open) {
            Flasher::addError(__('This intake is not currently open for applications.'), __('msg_error'));
            return redirect()->back()->withInput();
        }

        // Programme must belong to the selected degree type.
        $program = Program::find($validated['program']);
        if (!$program || (int) $program->degree_type_id !== (int) $validated['degree_type_id']) {
            Flasher::addError(__('The selected programme does not belong to the chosen degree type.'), __('msg_error'));
            return redirect()->back()->withInput();
        }

        try {
            DB::beginTransaction();

            $application = new Application();
            $application->applicant_id = $applicant->id;
            $application->degree_type_id = $validated['degree_type_id'];
            $application->session_id = $validated['session_id'];
            $application->program_id = $validated['program'];
            $application->first_program_choice_id = $validated['program'];
            $application->second_program_choice_id = $validated['second_program_choice_id'] ?? null;
            $application->third_program_choice_id = $validated['third_program_choice_id'] ?? null;
            $application->academic_year = $session->title;

            // Prefill identity from the account.
            $application->first_name = $applicant->first_name;
            $application->last_name = $applicant->last_name;
            $application->email = $applicant->email;
            $application->phone = $applicant->phone;

            $application->status = 0;
            $application->stage = 'draft';
            $application->progress = 0;
            $application->save();

            $application->registration_no = intval(10000000) + $application->id;
            $application->save();

            DB::commit();

            return redirect()->route('application.edit', $application);
        } catch (\Exception $e) {
            DB::rollBack();
            report($e);
            Flasher::addError(__('msg_created_error'), __('msg_error'));
            return redirect()->back()->withInput();
        }
    }

    /* ===================================================================
     |  Per-application form
     |===================================================================*/

    /** The multi-step application form for one application (draft only). */
    public function edit(Application $application)
    {
        $this->authorizeApplication($application);

        if ($application->stage !== 'draft') {
            return redirect()->route('application.dashboard');
        }

        $degreeType = $application->degreeType;

        // Make the apply form's field() helper resolve per this degree type.
        app()->instance('applicant.degree_type', $degreeType);

        $application->load(['guardians', 'academicHistories', 'languages', 'program', 'documents']);

        $provinces = Province::where('status', '1')
            ->with(['districts' => function ($query) {
                $query->where('status', '1')->orderBy('title', 'asc');
            }])
            ->orderBy('title', 'asc')
            ->get();

        // Programmes constrained to this application's degree type.
        $programs = Program::where('status', '1')
            ->where('degree_type_id', $application->degree_type_id)
            ->orderBy('title', 'asc')
            ->get();

        $data = [
            'title' => $this->title,
            'route' => $this->route,
            'path' => $this->path,
            'application' => $application,
            'degreeType' => $degreeType,
            'settings' => DegreeTypeFormConfig::settings($degreeType),
            'programs' => $programs,
            'faculties' => \App\Models\Faculty::where('status', '1')->orderBy('title', 'asc')->get(),
            'religions' => \App\Models\Religion::where('status', '1')->orderBy('title', 'asc')->get(),
            'religions_json' => \App\Models\Religion::where('status', '1')->orderBy('title', 'asc')->get()->keyBy('id')->toJson(),
            'sessions' => Session::where('status', '1')->orderBy('title', 'desc')->get(),
            'provinces' => $provinces,
            'present_districts' => [],
            'permanent_districts' => [],
            'applicationSetting' => ApplicationSetting::where('slug', 'admission')->where('status', '1')->first(),
            'documentRequirements' => $this->documentRequirements($degreeType),
            'guardianTypes' => ['Parent', 'Sponsor', 'Guardian'],
            'fluencyOptions' => ['excellent', 'good', 'fair', 'minimal'],
        ];

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

        return view('application.apply', $data);
    }

    /** Submit an application (was the singleton store()). */
    public function update(Request $request, Application $application)
    {
        $this->authorizeApplication($application);

        if ($application->stage !== 'draft') {
            return redirect()->route('application.dashboard');
        }

        $degreeType = $application->degreeType;
        $documentRequirements = $this->documentRequirements($degreeType);

        $academicYearEnabled = $this->fieldEnabled($degreeType, 'application_academic_year');
        $secondChoiceEnabled = $this->fieldEnabled($degreeType, 'application_program_choice_second');
        $thirdChoiceEnabled = $this->fieldEnabled($degreeType, 'application_program_choice_third');
        $birthCityEnabled = $this->fieldEnabled($degreeType, 'application_birth_city');
        $birthDivisionEnabled = $this->fieldEnabled($degreeType, 'application_birth_division');
        $birthRegionEnabled = $this->fieldEnabled($degreeType, 'application_birth_region');
        $birthCountryEnabled = $this->fieldEnabled($degreeType, 'application_birth_country');
        $studiedEnglishEnabled = $this->fieldEnabled($degreeType, 'application_studied_in_english');
        $instructionLanguageEnabled = $this->fieldEnabled($degreeType, 'application_instruction_language_secondary');
        $guardiansEnabled = $this->fieldEnabled($degreeType, 'application_guardians');
        $academicHistoryEnabled = $this->fieldEnabled($degreeType, 'application_academic_history');
        $languageEnabled = $this->fieldEnabled($degreeType, 'application_language_proficiency');
        $documentChecklistEnabled = $this->fieldEnabled($degreeType, 'application_document_checklist');
        $declarationEnabled = $this->fieldEnabled($degreeType, 'application_declaration');

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
            'present_address' => ['nullable', 'string', 'max:255'],
            'permanent_province' => ['nullable', 'string', 'max:191'],
            'permanent_district' => ['nullable', 'string', 'max:191'],
            'permanent_village' => ['nullable', 'string', 'max:191'],
            'permanent_address' => ['nullable', 'string', 'max:255'],
            'postal_address_line1' => ['nullable', 'string', 'max:255'],
            'postal_address_line2' => ['nullable', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:191'],
            'alternate_phone' => ['nullable', 'string', 'max:191'],
            'email' => ['required', 'email', 'max:191'],
            'mother_tongue' => ['nullable', 'string', 'max:191'],
            'studied_in_english' => ['nullable', 'boolean'],
            'photo' => [($application->photo ? 'nullable' : 'required'), 'image', 'max:5120'],
            'signature' => ['nullable', 'image', 'max:2048'],
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
            $existingDocs = $application->documents()->pluck('file_path', 'document_type')->filter()->toArray();
            foreach ($documentRequirements as $key => $document) {
                $isRequired = $document['required'] && !isset($existingDocs[$key]);
                $rules["documents.$key.file"] = [
                    $isRequired ? 'required' : 'nullable',
                    'file', 'mimes:jpg,jpeg,png,pdf', 'max:10240',
                ];
                $rules["documents.$key.note"] = ['nullable', 'string', 'max:500'];
            }
        }

        $validated = $request->validate($rules);

        try {
            DB::beginTransaction();

            // Programme must stay within this application's degree type.
            $program = Program::find($validated['program']);
            if ($program && $application->degree_type_id && (int) $program->degree_type_id !== (int) $application->degree_type_id) {
                DB::rollBack();
                return redirect()->back()->withInput()->withErrors([
                    'program' => __('The selected programme does not belong to this application\'s degree type.'),
                ]);
            }

            $application->program_id = $validated['program'];
            $application->first_program_choice_id = $validated['program'];
            $application->second_program_choice_id = $validated['second_program_choice_id'] ?? null;
            $application->third_program_choice_id = $validated['third_program_choice_id'] ?? null;
            $application->apply_date = Carbon::today();
            $application->academic_year = $validated['academic_year'] ?? $application->academic_year;

            $application->first_name = $validated['first_name'];
            $application->last_name = $validated['last_name'];
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

            $application->portal_meta = [
                'agreed_to_terms' => $request->boolean('agree_terms'),
                'submitted_ip' => $request->ip(),
                'submitted_user_agent' => substr((string) $request->userAgent(), 0, 255),
                'guardian_entries' => count($validated['guardians'] ?? []),
                'academic_history_entries' => count($validated['academic_history'] ?? []),
                'language_entries' => count($validated['languages'] ?? []),
            ];

            $application->save();

            // Admission fee — from this degree type's settings (fallback to env).
            $feeSettings = DegreeTypeFormConfig::settings($degreeType);
            if ($feeSettings['fee_enabled'] && !$application->admission_fee_id) {
                $admissionFeeCategory = \App\Models\FeesCategory::where('is_admission', 1)->where('status', 1)->first();
                if ($admissionFeeCategory) {
                    $tempEnroll = new \App\Models\StudentEnroll();
                    $tempEnroll->student_id = $application->id;
                    $tempEnroll->program_id = $application->program_id;
                    $tempEnroll->session_id = null;
                    $tempEnroll->semester_id = null;
                    $tempEnroll->section_id = null;
                    $tempEnroll->status = 0;
                    $tempEnroll->save();

                    $admissionFee = new \App\Models\Fee();
                    $admissionFee->student_enroll_id = $tempEnroll->id;
                    $admissionFee->category_id = $admissionFeeCategory->id;
                    $admissionFee->fee_amount = $feeSettings['fee_amount'];
                    $admissionFee->discount_amount = 0;
                    $admissionFee->fine_amount = 0;
                    $admissionFee->paid_amount = 0;
                    $admissionFee->assign_date = now();
                    $admissionFee->due_date = now()->addDays($feeSettings['fee_due_days']);
                    $admissionFee->status = 0;
                    $admissionFee->note = 'Admission fee - Auto-assigned';
                    $admissionFee->save();

                    $application->admission_fee_id = $admissionFee->id;
                    $application->save();
                }
            }

            if ($guardiansEnabled) {
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
                $application->languages()->delete();
                foreach ($validated['languages'] ?? [] as $language) {
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

            if ($documentChecklistEnabled) {
                foreach ($documentRequirements as $key => $document) {
                    $filePath = $this->uploadMedia($request, "documents.$key.file", $this->path);
                    $note = $request->input("documents.$key.note");
                    $existingDoc = $application->documents()->where('document_type', $key)->first();

                    if (!$filePath && $existingDoc) {
                        if ($note !== null) {
                            $existingDoc->update(['notes' => $note]);
                        }
                        continue;
                    }
                    if (!$filePath && !$existingDoc && !$document['required']) {
                        continue;
                    }
                    if ($filePath && isset($document['assign_to_column'])) {
                        $application->{$document['assign_to_column']} = $filePath;
                    }
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

    /** Save a draft for one application (lenient). */
    public function saveDraft(Request $request, Application $application)
    {
        $this->authorizeApplication($application);

        if ($application->stage !== 'draft') {
            return response()->json([
                'success' => false,
                'message' => __('Application has already been submitted and cannot be modified.'),
            ], 400);
        }

        $degreeType = $application->degreeType;

        try {
            DB::beginTransaction();

            $guardiansEnabled = $this->fieldEnabled($degreeType, 'application_guardians');
            $academicHistoryEnabled = $this->fieldEnabled($degreeType, 'application_academic_history');
            $languageEnabled = $this->fieldEnabled($degreeType, 'application_language_proficiency');
            $documentChecklistEnabled = $this->fieldEnabled($degreeType, 'application_document_checklist');
            $studiedEnglishEnabled = $this->fieldEnabled($degreeType, 'application_studied_in_english');
            $documentRequirements = $this->documentRequirements($degreeType);

            $rules = [
                'program' => ['nullable', 'exists:programs,id'],
                'second_program_choice_id' => ['nullable', 'exists:programs,id'],
                'third_program_choice_id' => ['nullable', 'exists:programs,id'],
                'first_name' => ['nullable', 'string', 'max:191'],
                'last_name' => ['nullable', 'string', 'max:191'],
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
                'email' => ['nullable', 'email', 'max:191'],
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

            if ($languageEnabled) {
                $rules['languages'] = ['nullable', 'array'];
                $rules['languages.*.language'] = ['nullable', 'string', 'max:100'];
                $rules['languages.*.years_of_study'] = ['nullable', 'integer', 'min:0', 'max:50'];
                $rules['languages.*.fluency_level'] = ['nullable', 'in:excellent,good,fair,minimal'];
            }

            $validated = $request->validate($rules);

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

            foreach ([
                'first_name', 'last_name', 'nationality', 'national_id', 'national_id_issue_place',
                'passport_no', 'passport_issue_country', 'country', 'present_province', 'present_district',
                'present_village', 'present_address', 'permanent_province', 'permanent_district', 'permanent_village',
                'permanent_address', 'postal_address_line1', 'postal_address_line2', 'phone', 'alternate_phone',
                'mother_tongue', 'instruction_language_secondary', 'academic_year', 'birth_city', 'birth_division',
                'birth_region', 'birth_country', 'registration_fee_bank', 'registration_fee_reference', 'declaration_name',
            ] as $field) {
                if ($request->has($field)) {
                    $application->$field = $request->input($field) ?: null;
                }
            }

            if ($request->filled('gender')) {
                $application->gender = (int) $validated['gender'];
            }
            if ($request->filled('dob')) {
                $application->dob = $validated['dob'];
            }
            if ($request->has('national_id_issue_date')) {
                $application->national_id_issue_date = $request->input('national_id_issue_date') ?: null;
            }
            if ($request->has('passport_issue_date')) {
                $application->passport_issue_date = $request->input('passport_issue_date') ?: null;
            }
            if ($request->has('declaration_signed_date')) {
                $application->declaration_signed_date = $request->input('declaration_signed_date') ?: null;
            }
            if ($request->filled('email')) {
                $application->email = $validated['email'];
            }
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
            if ($studiedEnglishEnabled && $request->has('studied_in_english')) {
                $application->studied_in_english = $request->boolean('studied_in_english');
            }

            if ($request->hasFile('photo')) {
                $application->photo = $this->uploadImage($request, 'photo', $this->path, 300, 300);
            }
            if ($request->hasFile('signature')) {
                $application->signature = $this->uploadImage($request, 'signature', $this->path, 300, 100);
            }

            $progress = $this->calculateDraftProgress($application, $guardiansEnabled, $academicHistoryEnabled, $languageEnabled);
            $application->draft_progress = $progress;
            $application->draft_last_saved_at = now();
            $application->save();

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
                                    'is_optional' => !($document['required'] ?? false),
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

    protected function calculateDraftProgress($application, $guardiansEnabled, $academicHistoryEnabled, $languageEnabled): int
    {
        $totalFields = 0;
        $filledFields = 0;

        $coreFields = ['program_id', 'first_name', 'last_name', 'gender', 'dob', 'email', 'phone', 'photo'];
        $totalFields += count($coreFields) * 2;
        foreach ($coreFields as $field) {
            if (!empty($application->$field)) {
                $filledFields += 2;
            }
        }

        $addressFields = ['country', 'present_province', 'present_district', 'present_address', 'nationality'];
        $totalFields += count($addressFields);
        foreach ($addressFields as $field) {
            if (!empty($application->$field)) {
                $filledFields++;
            }
        }

        $optionalFields = ['national_id', 'passport_no', 'birth_city', 'religion', 'mother_tongue'];
        $totalFields += count($optionalFields);
        foreach ($optionalFields as $field) {
            if (!empty($application->$field)) {
                $filledFields++;
            }
        }

        if ($guardiansEnabled) {
            $totalFields += 5;
            $filledFields += min($application->guardians()->count(), 5);
        }
        if ($academicHistoryEnabled) {
            $totalFields += 3;
            $filledFields += min($application->academicHistories()->count(), 3);
        }
        if ($languageEnabled) {
            $totalFields += 2;
            $filledFields += min($application->languages()->count(), 2);
        }

        return $totalFields > 0 ? round(($filledFields / $totalFields) * 100) : 0;
    }

    /** Resubmit documents an admin flagged for a specific application. */
    public function resubmitDocuments(Request $request, Application $application)
    {
        $this->authorizeApplication($application);

        $documentsNeedingResubmission = $application->documents()
            ->where('needs_resubmission', true)
            ->whereNull('resubmitted_at')
            ->pluck('document_type')
            ->toArray();

        if (empty($documentsNeedingResubmission)) {
            return redirect()->route('application.dashboard')->with('info', __('No documents require resubmission.'));
        }

        $rules = [];
        foreach ($documentsNeedingResubmission as $documentType) {
            $rules["documents.$documentType"] = ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:10240'];
        }
        $request->validate($rules);

        try {
            DB::beginTransaction();

            $resubmittedDocuments = [];
            foreach ($documentsNeedingResubmission as $documentType) {
                if ($request->hasFile("documents.$documentType")) {
                    $document = $application->documents()->where('document_type', $documentType)->first();
                    if ($document) {
                        $newFilePath = $this->uploadMedia($request, "documents.$documentType", $this->path);
                        if ($newFilePath) {
                            $document->update([
                                'file_path' => $newFilePath,
                                'is_received' => false,
                                'resubmitted_at' => now(),
                            ]);
                            $resubmittedDocuments[] = $documentType;
                        }
                    }
                }
            }

            $remainingDocuments = $application->documents()
                ->where('needs_resubmission', true)
                ->whereNull('resubmitted_at')
                ->count();

            if ($remainingDocuments === 0 && $application->stage === 'documents_required') {
                $application->stage = 'under_review';
                $application->save();
                $application->recordStatus('under_review', __('All requested documents have been resubmitted. Your application is now under review.'), $application->status, __('application_stage.under_review'), null, 'applicant');
            } else {
                $application->recordStatus($application->stage, __('Documents resubmitted: ') . implode(', ', $resubmittedDocuments), $application->status, null, null, 'applicant');
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

    /** Timeline for one application. */
    public function timeline(Application $application)
    {
        $this->authorizeApplication($application);

        $application->load([
            'program', 'degreeType', 'session',
            'statusUpdates' => function ($query) {
                $query->where('is_visible_to_applicant', true)->orderBy('created_at', 'asc');
            },
        ]);

        return view('application.portal.timeline', [
            'application' => $application,
            'timeline' => $application->statusUpdates,
        ]);
    }

    /** Upload an admission-fee payment receipt for one application. */
    public function uploadAdmissionFeeReceipt(Request $request, Application $application)
    {
        $this->authorizeApplication($application);

        if (!$application->admissionFee) {
            Flasher::addError(__('No admission fee found for your application.'));
            return redirect()->route('application.dashboard');
        }

        $request->validate([
            'payment_date' => 'required|date|before_or_equal:today',
            'amount' => 'required|numeric|min:0.01',
            'payment_reference' => 'required|string|max:255',
            'payment_method' => 'required|in:1,2,3,4,5,6',
            'receipt_file' => 'required|file|mimes:pdf,jpg,jpeg,png|max:2048',
            'student_note' => 'nullable|string|max:500',
        ]);

        $receiptPath = $request->hasFile('receipt_file')
            ? $this->uploadMedia($request, 'receipt_file', $this->path)
            : null;

        $paymentReceipt = new \App\Models\PaymentReceipt();
        $paymentReceipt->fee_id = $application->admissionFee->id;
        $paymentReceipt->student_id = $application->id;
        $paymentReceipt->receipt_file = $receiptPath;
        $paymentReceipt->payment_reference = $request->payment_reference;
        $paymentReceipt->payment_date = $request->payment_date;
        $paymentReceipt->amount = $request->amount;
        $paymentReceipt->payment_method = $request->payment_method;
        $paymentReceipt->student_note = $request->student_note;
        $paymentReceipt->verification_status = 'pending';
        $paymentReceipt->save();

        Flasher::addSuccess(__('Payment receipt uploaded successfully! Your payment will be verified by our administration team shortly.'));
        return redirect()->route('application.dashboard');
    }

    /* ===================================================================
     |  Auth (account = Applicant)
     |===================================================================*/

    public function loginForm()
    {
        if (Auth::guard('applicant')->check()) {
            return redirect()->route('application.dashboard');
        }

        return view('application.portal.login', ['title' => __('Application Portal Login')]);
    }

    public function authenticate(Request $request)
    {
        $credentials = $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        if (Auth::guard('applicant')->attempt($credentials, $request->boolean('remember'))) {
            $request->session()->regenerate();
            $user = Auth::guard('applicant')->user();
            $user->portal_last_login_at = now();
            $user->save();

            return redirect()->intended(route('application.dashboard'));
        }

        return back()->withErrors(['email' => __('auth.failed')])->onlyInput('email');
    }

    public function logout(Request $request)
    {
        Auth::guard('applicant')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('application.login');
    }

    public function registerForm()
    {
        if (Auth::guard('applicant')->check()) {
            return redirect()->route('application.dashboard');
        }

        return view('application.portal.register', ['title' => __('Application Portal Registration')]);
    }

    public function register(Request $request)
    {
        $request->validate([
            'first_name' => ['required', 'string', 'max:191'],
            'last_name' => ['required', 'string', 'max:191'],
            'email' => ['required', 'string', 'email', 'max:191', 'unique:applicants,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'agree_terms' => ['accepted'],
        ]);

        try {
            DB::beginTransaction();

            $applicant = new Applicant();
            $applicant->first_name = $request->first_name;
            $applicant->last_name = $request->last_name;
            $applicant->email = $request->email;
            $applicant->phone = $request->phone;
            $applicant->password = Hash::make($request->password);
            $applicant->save();

            DB::commit();

            Auth::guard('applicant')->login($applicant);
            $applicant->portal_last_login_at = now();
            $applicant->save();

            Flasher::addSuccess(__('Account created successfully. You can now start an application.'), __('msg_success'));
            return redirect()->route('application.dashboard');
        } catch (\Exception $e) {
            DB::rollBack();
            report($e);
            Flasher::addError(__('Registration failed: ') . $e->getMessage(), __('msg_error'));
            return back()->withInput();
        }
    }

    public function showForgotPasswordForm()
    {
        if (Auth::guard('applicant')->check()) {
            return redirect()->route('application.dashboard');
        }

        return view('application.portal.passwords.email', ['title' => __('Forgot Password - Application Portal')]);
    }

    public function sendResetLinkEmail(Request $request)
    {
        $request->validate(['email' => 'required|email']);

        $applicant = Applicant::where('email', $request->email)->first();
        $mail = MailSetting::where('status', '1')->first();

        if (!$applicant) {
            return redirect()->back()->with('info', __('If an account with that email exists, we have sent a password reset link.'));
        }
        if (!$mail || !$mail->sender_email || !$mail->sender_name) {
            return redirect()->back()->with('error', __('Email service is not configured. Please contact support.'));
        }

        try {
            $token = bin2hex(random_bytes(32));

            DB::table('password_resets')->where('email', $applicant->email)->delete();
            DB::table('password_resets')->insert([
                'email' => $applicant->email,
                'token' => $token,
                'created_at' => now(),
            ]);

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

            Mail::to($applicant->email)->send(new \App\Mail\ApplicantForgotPassword($data));

            return redirect()->back()->with('success', __('We have sent a password reset link to your email address. Please check your inbox (and spam folder).'));
        } catch (\Exception $e) {
            report($e);
            return redirect()->back()->with('error', __('Failed to send reset email. Please try again later.'));
        }
    }

    public function showResetForm($token, $email)
    {
        if (Auth::guard('applicant')->check()) {
            return redirect()->route('application.dashboard');
        }

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

    public function resetPassword(Request $request)
    {
        $request->validate([
            'token' => 'required',
            'email' => 'required|email',
            'password' => 'required|min:8|confirmed',
        ]);

        $passwordReset = DB::table('password_resets')
            ->where('email', $request->email)
            ->where('token', $request->token)
            ->where('created_at', '>', now()->subMinutes(60))
            ->first();

        if (!$passwordReset) {
            return redirect()->back()->with('error', __('This password reset link is invalid or has expired.'));
        }

        $applicant = Applicant::where('email', $request->email)->first();
        if (!$applicant) {
            return redirect()->back()->with('error', __('Account not found.'));
        }

        try {
            $applicant->password = Hash::make($request->password);
            $applicant->save();

            DB::table('password_resets')->where('email', $request->email)->delete();

            Flasher::addSuccess(__('Your password has been reset successfully! You can now sign in.'), __('msg_success'));
            return redirect()->route('application.login');
        } catch (\Exception $e) {
            report($e);
            return redirect()->back()->with('error', __('Failed to reset password. Please try again.'));
        }
    }
}
