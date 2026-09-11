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
use App\Support\ApplicationQualificationCards;
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

    /**
     * Checklist documents that are collected inside Applicant Information →
     * Identification, next to the identity data they corroborate, rather than
     * on the Documents step. The Documents step then reconciles them instead of
     * asking for them a second time. Keys not present in a degree type's
     * checklist are simply ignored.
     */
    protected const IDENTITY_DOCUMENT_KEYS = ['birth_certificate', 'national_id_card', 'national_id_card_back'];

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

    /**
     * Is there anything worth saving in a submitted qualification row?
     *
     * A prescribed card is always rendered, so an applicant who has not reached
     * it yet posts it back empty. Testing institution_name alone was too strict:
     * someone who filled the qualification and awarding body but not yet the
     * school would have silently lost both.
     */
    protected function academicRowHasContent(array $history): bool
    {
        return ApplicationQualificationCards::rowHasContent($history);
    }

    /**
     * Ensure a Fee row exists for this application's admission fee (when enabled
     * for its degree type). Idempotent — safe to call multiple times.
     * Returns the Fee or null if the degree type charges no fee.
     */
    protected function ensureAdmissionFee(Application $application): ?\App\Models\Fee
    {
        $degreeType = $application->degreeType;
        $feeSettings = DegreeTypeFormConfig::settings($degreeType);
        if (empty($feeSettings['fee_enabled'])) {
            return null;
        }

        // If a Fee already exists, keep it — but sync the amount / due date to the
        // latest per-degree-type configuration while the fee is still unpaid.
        // Once anything has been paid against it, we leave the amount alone to
        // preserve the payment history.
        if ($application->admission_fee_id) {
            $existing = $application->admissionFee()->first();
            if ($existing) {
                // Ensure the applicant link is set even on rows created before
                // fees.applicant_id existed.
                if (is_null($existing->applicant_id)) {
                    $existing->applicant_id = $application->id;
                    $existing->save();
                }
                if ((float) $existing->paid_amount <= 0) {
                    $newAmount = (float) $feeSettings['fee_amount'];
                    if ((float) $existing->fee_amount !== $newAmount) {
                        $existing->fee_amount = $newAmount;
                        $existing->due_date = now()->addDays($feeSettings['fee_due_days']);
                        $existing->save();
                    }
                }
            }
            return $existing;
        }

        $admissionFeeCategory = \App\Models\FeesCategory::where('is_admission', 1)->where('status', 1)->first();
        if (!$admissionFeeCategory) {
            return null;
        }

        // A placeholder StudentEnroll so the Fee row is well-formed. When the
        // admin converts the application to a Student, Admin\ApplicationController@store
        // adopts this Fee onto the real enrollment and cleans up the stub.
        // student_id is intentionally null — no Student row exists yet.
        $tempEnroll = new \App\Models\StudentEnroll();
        $tempEnroll->student_id = null;
        $tempEnroll->program_id = $application->program_id;
        $tempEnroll->session_id = null;
        $tempEnroll->semester_id = null;
        $tempEnroll->section_id = null;
        $tempEnroll->status = 0;
        $tempEnroll->save();

        $fee = new \App\Models\Fee();
        $fee->student_enroll_id = $tempEnroll->id;
        $fee->applicant_id = $application->id;
        $fee->category_id = $admissionFeeCategory->id;
        $fee->fee_amount = $feeSettings['fee_amount'];
        $fee->discount_amount = 0;
        $fee->fine_amount = 0;
        $fee->paid_amount = 0;
        $fee->assign_date = now();
        $fee->due_date = now()->addDays($feeSettings['fee_due_days']);
        $fee->status = 0;
        $fee->note = 'Admission fee - Auto-assigned (application #' . $application->registration_no . ')';
        $fee->save();

        $application->admission_fee_id = $fee->id;
        $application->save();

        return $fee;
    }

    /**
     * Whether the applicant has fully paid the admission fee for this application.
     * Returns true when no fee is required at all, or when the fee is settled (status=1).
     */
    protected function admissionFeeIsSettled(Application $application): bool
    {
        $degreeType = $application->degreeType;
        $feeSettings = DegreeTypeFormConfig::settings($degreeType);
        if (empty($feeSettings['fee_enabled'])) {
            return true;
        }

        $fee = $application->admissionFee()->first();
        if (!$fee) {
            return false;
        }
        return (int) $fee->status === 1;
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

            // No fee is raised here. Starting a form is not the same as owing
            // money for it: raising one at intake put an unpaid fee on the
            // admission fees report for everyone who ever opened the wizard,
            // including people who abandoned it on the first step. The fee is
            // raised when the applicant finishes and reaches the payment step —
            // see readiness().

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
    /**
     * The furthest wizard step the applicant may jump straight to, 1-based.
     *
     * ApplicationCompleteness tags every unmet requirement with the step it
     * belongs to, so the first step still missing something is the natural
     * ceiling: everything before it is genuinely done and safe to revisit, and
     * the applicant can open that step to finish it. With nothing missing the
     * whole wizard opens, including payment.
     *
     * This is a navigation convenience, not a control: moving forward still
     * validates and saves, and payment is gated independently in
     * uploadAdmissionFeeReceipt() against the same service.
     */
    protected function wizardUnlockedThrough(Application $application): int
    {
        $totalSteps = 8;

        $steps = array_column(\App\Services\ApplicationCompleteness::missing($application), 'step');
        $steps = array_filter($steps, function ($step) {
            return is_numeric($step) && $step > 0;
        });

        if ($steps === []) {
            return $totalSteps;
        }

        return max(1, min((int) min($steps), $totalSteps));
    }

    public function edit(Application $application)
    {
        $this->authorizeApplication($application);

        if ($application->stage !== 'draft') {
            return redirect()->route('application.dashboard');
        }

        $degreeType = $application->degreeType;

        // Deliberately no fee here either: simply viewing the form billed the
        // applicant 21,000 FCFA, which is how the fees report filled with
        // unpaid rows nobody had agreed to. See readiness().

        // Warn (but don't block) if the intake has been closed since the draft was created.
        $intake = $application->session;
        $intakeClosed = !$intake || !$intake->applications_open;
        if ($intakeClosed) {
            Flasher::addWarning(__('This intake is currently closed. You may keep editing your draft, but submission will be rejected until the intake reopens.'), __('msg_warning'));
        }

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

        // Split the checklist three ways: identity documents on the
        // Identification tab of step 1, qualification documents inside their
        // card on step 3, and only what is left over on the Documents step.
        $documentRequirements = $this->documentRequirements($degreeType);
        $qualificationCards = ApplicationQualificationCards::build(
            $application,
            $degreeType,
            static::IDENTITY_DOCUMENT_KEYS,
            old('academic_history')
        );
        $identityDocuments = $qualificationCards['identityDocuments'];
        $remainingDocuments = $qualificationCards['remainingDocuments'];

        // Admission-fee context for the Payment step.
        $feeSettings = DegreeTypeFormConfig::settings($degreeType);
        $admissionFee = $application->admissionFee()->first();
        // No fee row yet means the applicant has not finished the form — the
        // fee is raised when they reach the payment step. Fall back to the
        // configured amount so the step shows what it will cost rather than 0.
        $admissionFeeBalance = $admissionFee
            ? max(0, ($admissionFee->fee_amount + $admissionFee->fine_amount - $admissionFee->discount_amount) - $admissionFee->paid_amount)
            : (float) ($feeSettings['fee_amount'] ?? 0);
        $latestReceipt = $admissionFee
            ? $admissionFee->paymentReceipts()->orderByDesc('id')->first()
            : null;

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
            'documentRequirements' => $documentRequirements,
            'identityDocuments' => $identityDocuments,
            'remainingDocuments' => $remainingDocuments,
            'qualificationCards' => $qualificationCards['cards'],
            'qualificationExtras' => $qualificationCards['extras'],
            'qualificationDocuments' => $qualificationCards['qualificationDocuments'],
            'guardianTypes' => ['Parent', 'Sponsor', 'Guardian'],
            'fluencyOptions' => ['excellent', 'good', 'fair', 'minimal'],
            'admissionFeeRequired' => !$this->admissionFeeIsSettled($application),
            'admissionFee' => $admissionFee,
            'admissionFeeBalance' => $admissionFeeBalance,
            'admissionFeeSettings' => $feeSettings,
            'latestPaymentReceipt' => $latestReceipt,
            // How far the sidebar may be clicked, from what is actually SAVED.
            // The wizard used to track this in a JavaScript variable that reset
            // to zero on every page load, so a returning applicant with a
            // finished form was told to "complete the preceding steps" and had
            // to walk through all eight again to reach payment.
            'wizardUnlockedThrough' => $this->wizardUnlockedThrough($application),
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

    /**
     * Printable copy of the applicant's own application.
     *
     * Deliberately renders the SAME view the admin preview uses, so what the
     * applicant prints and what the admissions office reads can never drift
     * apart. That view is self-contained — no admin routes or guards — the only
     * difference here is that access is scoped to the owning applicant.
     */
    public function printPreview(Request $request, Application $application)
    {
        $this->authorizeApplication($application);

        $application->load([
            'program', 'batch',
            'preferredProgramFirst', 'preferredProgramSecond', 'preferredProgramThird',
            'guardians', 'academicHistories', 'languages', 'documents', 'religionDetail',
        ]);

        $degreeType = $application->degreeType;

        $data = [
            'title' => $this->title,
            'row' => $application,
            'path' => $this->path,
            'setting' => Setting::where('status', '1')->first(),
            'documentRequirements' => DegreeTypeFormConfig::documents($degreeType),
            'fieldEnabled' => function (string $slug) use ($degreeType): bool {
                return DegreeTypeFormConfig::fieldEnabled($degreeType, $slug);
            },
        ];

        if (!$request->boolean('download')) {
            return view('admin.application.preview', $data);
        }

        // Same document, streamed as a real PDF (dompdf, as used for acceptance
        // letters). If it cannot be rendered, fall back to the printable HTML
        // rather than showing the applicant an error page.
        try {
            $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('admin.application.preview', $data);
            $pdf->setPaper('a4', 'portrait');
            $pdf->setOption('isHtml5ParserEnabled', true);
            $pdf->setOption('isRemoteEnabled', true);

            return $pdf->download('application-' . $application->registration_no . '.pdf');
        } catch (\Throwable $e) {
            report($e);
            return view('admin.application.preview', $data);
        }
    }

    /** Submit an application (was the singleton store()). */
    public function update(Request $request, Application $application)
    {
        $this->authorizeApplication($application);

        if ($application->stage !== 'draft') {
            return redirect()->route('application.dashboard');
        }

        // Intake window must still be open at submission time (not only at create).
        $intake = $application->session;
        if (!$intake || !$intake->applications_open) {
            Flasher::addError(__('This intake is no longer open for applications. Please contact the admissions office.'), __('msg_error'));
            return redirect()->route('application.edit', $application);
        }

        // Hard block: the admission fee must be fully paid before submission.
        // (For degree types where no fee is required, admissionFeeIsSettled() returns true.)
        $this->ensureAdmissionFee($application);
        if (!$this->admissionFeeIsSettled($application)) {
            Flasher::addError(__('Please complete your admission fee payment before submitting the application.'), __('msg_error'));
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
            'other_names' => ['nullable', 'string', 'max:191'],
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
            'national_id_expiry_date' => ['nullable', 'date'],
            'passport_no' => ['nullable', 'string', 'max:191'],
            'passport_issue_date' => ['nullable', 'date'],
            'passport_issue_country' => ['nullable', 'string', 'max:191'],
            'passport_expiry_date' => ['nullable', 'date'],
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
            // Optional, like the identity document: a missing photograph must not
            // be what stops an application being submitted and paid for.
            'photo' => ['nullable', 'image', 'max:5120'],
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
            $rules['academic_history.*.qualification_key'] = ['nullable', 'string', 'max:100'];
            $rules['academic_history.*.institution_name'] = ['required', 'string', 'max:255'];
            $rules['academic_history.*.awarding_body'] = ['nullable', 'string', 'max:191'];
            $rules['academic_history.*.institution_same_as_awarding_body'] = ['nullable', 'boolean'];
            $rules['academic_history.*.city'] = ['nullable', 'string', 'max:191'];
            $rules['academic_history.*.country'] = ['nullable', 'string', 'max:191'];
            $rules['academic_history.*.instruction_language'] = ['nullable', 'string', 'max:191'];
            $rules['academic_history.*.start_year'] = ['nullable', 'integer', 'digits:4'];
            // Cannot finish before starting; caught here rather than showing a
            // qualification that ran backwards on the review page.
            $rules['academic_history.*.end_year'] = ['nullable', 'integer', 'digits:4', 'gte:academic_history.*.start_year'];
            $rules['academic_history.*.certificate_obtained'] = ['nullable', 'string', 'max:191'];
            $rules['academic_history.*.certificate_file'] = ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:10240'];
            $rules['academic_history.*.existing_certificate_file'] = ['nullable', 'string', 'max:255'];
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
            $application->other_names = $validated['other_names'] ?? null;
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
            $application->national_id_expiry_date = $validated['national_id_expiry_date'] ?? null;
            $application->passport_no = $validated['passport_no'] ?? null;
            $application->passport_issue_date = $validated['passport_issue_date'] ?? null;
            $application->passport_issue_country = $validated['passport_issue_country'] ?? null;
            $application->passport_expiry_date = $validated['passport_expiry_date'] ?? null;

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
            // Stamped once, at submission. The admin register filters on this
            // date, so leaving it null hides the application from admissions.
            $application->apply_date = $application->apply_date ?: now()->toDateString();

            $application->portal_meta = [
                'agreed_to_terms' => $request->boolean('agree_terms'),
                'submitted_ip' => $request->ip(),
                'submitted_user_agent' => substr((string) $request->userAgent(), 0, 255),
                'guardian_entries' => count($validated['guardians'] ?? []),
                'academic_history_entries' => count($validated['academic_history'] ?? []),
                'language_entries' => count($validated['languages'] ?? []),
            ];

            $application->save();

            // Safety net: if the applicant reached here without a Fee row
            // (e.g. legacy drafts created before fee-at-intake), create it now.
            $this->ensureAdmissionFee($application);

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
                    if (!$this->academicRowHasContent($history)) {
                        continue;
                    }
                    $application->academicHistories()->create([
                        // Binds the row back to its card on the next render.
                        'qualification_key' => $history['qualification_key'] ?? null,
                        'institution_name' => $history['institution_name'] ?? null,
                        'awarding_body' => $history['awarding_body'] ?? null,
                        'institution_same_as_awarding_body' => !empty($history['institution_same_as_awarding_body']),
                        'city' => $history['city'] ?? null,
                        'country' => $history['country'] ?? null,
                        'instruction_language' => $history['instruction_language'] ?? null,
                        'start_year' => $history['start_year'] ?? null,
                        'end_year' => $history['end_year'] ?? null,
                        'certificate_obtained' => $history['certificate_obtained'] ?? null,
                        // A newly attached certificate wins; otherwise keep the one the
                        // form carried back, since these rows are deleted and rewritten.
                        'certificate_file' => $this->uploadMedia($request, "academic_history.$order.certificate_file", $this->path)
                            ?: ($history['existing_certificate_file'] ?? null),
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
                'other_names' => ['nullable', 'string', 'max:191'],
                'last_name' => ['nullable', 'string', 'max:191'],
                'gender' => ['nullable', 'in:1,2,3'],
                'dob' => ['nullable', 'date', 'before:today'],
                'religion' => ['nullable', 'string', 'max:191'],
                'religion_other' => ['nullable', 'string', 'max:191'],
                'nationality' => ['nullable', 'string', 'max:191'],
                'national_id' => ['nullable', 'string', 'max:191'],
                'national_id_issue_date' => ['nullable', 'date'],
                'national_id_issue_place' => ['nullable', 'string', 'max:191'],
                'national_id_expiry_date' => ['nullable', 'date'],
                'passport_no' => ['nullable', 'string', 'max:191'],
                'passport_issue_date' => ['nullable', 'date'],
                'passport_issue_country' => ['nullable', 'string', 'max:191'],
                'passport_expiry_date' => ['nullable', 'date'],
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
                $rules['academic_history.*.qualification_key'] = ['nullable', 'string', 'max:100'];
                $rules['academic_history.*.institution_name'] = ['nullable', 'string', 'max:255'];
                $rules['academic_history.*.awarding_body'] = ['nullable', 'string', 'max:191'];
                $rules['academic_history.*.institution_same_as_awarding_body'] = ['nullable', 'boolean'];
                $rules['academic_history.*.city'] = ['nullable', 'string', 'max:100'];
                $rules['academic_history.*.country'] = ['nullable', 'string', 'max:100'];
                $rules['academic_history.*.instruction_language'] = ['nullable', 'string', 'max:100'];
                // A draft may legitimately be half-filled, so years are only
                // sanity-checked here, never required.
                $rules['academic_history.*.start_year'] = ['nullable', 'integer', 'digits:4'];
                $rules['academic_history.*.end_year'] = ['nullable', 'integer', 'digits:4'];
                $rules['academic_history.*.certificate_obtained'] = ['nullable', 'string', 'max:191'];
                $rules['academic_history.*.certificate_file'] = ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:10240'];
                $rules['academic_history.*.existing_certificate_file'] = ['nullable', 'string', 'max:255'];
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
                'first_name', 'other_names', 'last_name', 'nationality', 'national_id', 'national_id_issue_place',
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
            foreach (['national_id_expiry_date', 'passport_expiry_date'] as $expiryField) {
                if ($request->has($expiryField)) {
                    $application->$expiryField = $request->input($expiryField) ?: null;
                }
            }
            if ($request->has('passport_issue_date')) {
                $application->passport_issue_date = $request->input('passport_issue_date') ?: null;
            }
            if ($request->has('declaration_signed_date')) {
                $application->declaration_signed_date = $request->input('declaration_signed_date') ?: null;
            }
            // The declaration tick is kept in portal_meta, and used to be written
            // only at the moment of submission. Now that approving the admission
            // fee submits the application by itself, the applicant has to have
            // agreed before they pay — so a draft has to remember the answer.
            if ($request->has('agree_terms')) {
                $meta = $application->portal_meta;
                $meta = is_array($meta) ? $meta : (array) json_decode((string) $meta, true);
                $agreed = $request->boolean('agree_terms');
                $meta['agreed_to_terms'] = $agreed;
                $meta['agreed_at'] = $agreed ? now()->toDateTimeString() : null;
                $meta['agreed_ip'] = $agreed ? $request->ip() : null;
                $application->portal_meta = $meta;
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
                    if (!$this->academicRowHasContent($history)) {
                        continue;
                    }
                    $application->academicHistories()->create([
                        // Binds the row back to its card on the next render.
                        'qualification_key' => $history['qualification_key'] ?? null,
                        'institution_name' => $history['institution_name'] ?? null,
                        'awarding_body' => $history['awarding_body'] ?? null,
                        'institution_same_as_awarding_body' => !empty($history['institution_same_as_awarding_body']),
                        'city' => $history['city'] ?? null,
                        'country' => $history['country'] ?? null,
                        'instruction_language' => $history['instruction_language'] ?? null,
                        'start_year' => $history['start_year'] ?? null,
                        'end_year' => $history['end_year'] ?? null,
                        'certificate_obtained' => $history['certificate_obtained'] ?? null,
                        // A newly attached certificate wins; otherwise keep the one the
                        // form carried back, since these rows are deleted and rewritten.
                        'certificate_file' => $this->uploadMedia($request, "academic_history.$order.certificate_file", $this->path)
                            ?: ($history['existing_certificate_file'] ?? null),
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
                            // Mirror onto the legacy column exactly as update()
                            // does. Draft saves skipped this, which left
                            // school_certificate / collage_certificate empty
                            // until a full submit — and now that certificates
                            // are uploaded from their qualification card, a
                            // draft save is the usual way they arrive.
                            if (isset($document['assign_to_column'])) {
                                $application->{$document['assign_to_column']} = $filePath;
                            }
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

            // Progress is computed only after the guardian / academic history /
            // language rows have been rewritten above — computing it earlier would
            // count the previous save's relations and lag a step behind.
            $progress = $this->calculateDraftProgress($application, $guardiansEnabled, $academicHistoryEnabled, $languageEnabled);
            $application->draft_progress = $progress;
            $application->save();

            DB::commit();

            // Normally the fee is approved last and the observer on Fee submits
            // the application. The order reverses when the fee was taken at the
            // counter before the applicant had finished — the walk-in desk is not
            // bound by the completeness gate that the applicant's own payment
            // routes are. Whichever of the two happens second submits, so ask here
            // as well. It is a no-op unless the fee really is already settled.
            $autoSubmitted = app(\App\Services\ApplicationSubmissionService::class)
                ->autoSubmit($application, __('admission fee was approved'));

            return response()->json([
                'success' => true,
                'message' => $autoSubmitted
                    ? __('Your application is complete and your fee is paid, so it has been submitted.')
                    : __('Draft saved successfully. You can continue your application later.'),
                'last_saved' => now()->format('F j, Y g:i A'),
                'progress' => $progress,
                'auto_submitted' => $autoSubmitted,
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
            // Score against the cards this degree type actually prescribes.
            // The old fixed target of 3 left an applicant permanently short of
            // 100% when only two qualifications were ever asked for.
            $cardCount = count(DegreeTypeFormConfig::qualifications($application->degreeType));
            $target = max($cardCount, 1);
            $totalFields += $target;
            $filledFields += min($application->academicHistories()->count(), $target);
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
    /** What is still owed on a fee: charged, plus fine, less discount and payments. */
    protected function admissionFeeBalance(?\App\Models\Fee $fee): float
    {
        if (!$fee) {
            return 0.0;
        }

        $due = (float) $fee->fee_amount + (float) $fee->fine_amount - (float) $fee->discount_amount;

        return max(round($due - (float) $fee->paid_amount, 2), 0.0);
    }

    /**
     * What is still outstanding on this application, as JSON.
     *
     * The payment step decides whether to offer the payment controls, and that
     * decision is made from saved data. Since the wizard saves over AJAX, the
     * decision taken when the page was rendered goes stale as soon as the
     * applicant fills anything in — it used to insist on details they had just
     * entered until they reloaded the page. This lets the step re-ask.
     */
    public function readiness(Application $application)
    {
        $this->authorizeApplication($application);

        $missing = \App\Services\ApplicationCompleteness::missing($application);

        // This is the moment the fee is owed: the form is finished and the
        // applicant has reached the payment step. Raising it here rather than at
        // intake keeps the admission fees report to people who actually intend
        // to pay. ensureAdmissionFee() is idempotent, so arriving at the step
        // repeatedly does not raise it twice.
        if ($missing === []) {
            $this->ensureAdmissionFee($application);
            $application->refresh();
        }

        $fee = $application->admissionFee()->first();

        return response()->json([
            'complete' => $missing === [],
            'missing' => $missing,
            'fee_settled' => $this->admissionFeeIsSettled($application),
            // The payment step was rendered before this fee existed, so it has
            // no id to pay against. Hand it back rather than make the applicant
            // reload to discover it.
            'fee_id' => $fee->id ?? null,
            'balance' => $fee ? $this->admissionFeeBalance($fee) : null,
        ]);
    }

    public function uploadAdmissionFeeReceipt(Request $request, Application $application)
    {
        $this->authorizeApplication($application);

        if (!$application->admissionFee) {
            if ($request->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => __('No admission fee found for your application.'),
                ], 422);
            }
            Flasher::addError(__('No admission fee found for your application.'));
            return redirect()->route('application.dashboard');
        }

        // Nothing may be paid for until the form behind it is finished. Approving
        // this fee submits the application outright, so an unfinished one would be
        // sent to admissions with no chance to catch it.
        if ($blockers = \App\Services\ApplicationCompleteness::missingLabels($application)) {
            $message = __('Please complete your application before paying. Still outstanding: :items', [
                'items' => implode(', ', $blockers),
            ]);
            if ($request->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => $message,
                    'missing' => $blockers,
                ], 422);
            }
            Flasher::addError($message);
            return redirect()->route('application.edit', $application);
        }

        $request->validate([
            'payment_date' => 'required|date|before_or_equal:today',
            'payment_reference' => 'required|string|max:255',
            // Every method the applicant is offered, except cash (2). This form
            // records proof of a payment made elsewhere; cash is handed over at
            // the Finance Office, which records it directly against the fee, so
            // a cash receipt uploaded here would be a claim nobody can verify.
            // 7 (Orange Money) and 8 (Other) were previously missing from this
            // rule while being offered in the form, so choosing either failed
            // validation with no explanation.
            'payment_method' => 'required|in:1,3,4,5,6,7,8',
            'receipt_file' => 'required|file|mimes:pdf,jpg,jpeg,png|max:2048',
            'student_note' => 'nullable|string|max:500',
        ]);

        $receiptPath = $request->hasFile('receipt_file')
            ? $this->uploadMedia($request, 'receipt_file', $this->path)
            : null;

        $paymentReceipt = new \App\Models\PaymentReceipt();
        $paymentReceipt->fee_id = $application->admissionFee->id;
        // Applicants have no Student row yet — track them via applicant_id.
        // student_id stays NULL until an admin converts them into a Student.
        $paymentReceipt->applicant_id = $application->id;
        $paymentReceipt->student_id = null;
        $paymentReceipt->receipt_file = $receiptPath;
        $paymentReceipt->payment_reference = $request->payment_reference;
        $paymentReceipt->payment_date = $request->payment_date;
        // Taken from the fee, never from the form. The admission fee is set by
        // the institution, so a posted amount is at best redundant and at worst
        // an applicant declaring their own figure against a receipt.
        $paymentReceipt->amount = $this->admissionFeeBalance($application->admissionFee);
        $paymentReceipt->payment_method = $request->payment_method;
        $paymentReceipt->student_note = $request->student_note;
        $paymentReceipt->verification_status = 'pending';
        $paymentReceipt->save();

        $message = __('Payment receipt uploaded successfully! Your payment will be verified by our administration team shortly.');

        // The wizard's payment step posts this over AJAX (it cannot nest a form
        // inside the application form), so answer in kind when JSON is wanted.
        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => $message,
                'fee_settled' => $this->admissionFeeIsSettled($application->fresh()),
            ]);
        }

        Flasher::addSuccess($message);
        return redirect()->route('application.dashboard');
    }

    /* ===================================================================
     |  Auth (account = Applicant)
     |===================================================================*/

    /**
     * The admissions front door.
     *
     * Every "Apply Online" button on the public site lands here. Most people
     * arriving have never applied before and have no account, so this page
     * leads with starting an application and offers signing in second, rather
     * than the other way round.
     */
    public function startPage()
    {
        if (Auth::guard('applicant')->check()) {
            return redirect()->route('application.dashboard');
        }

        // The same query create() uses to populate its intake list, so this
        // page can never advertise a session the application form would refuse.
        $openSessions = Session::where('applications_open', 1)
            ->orderBy('title', 'desc')
            ->get();

        $applicationSetting = ApplicationSetting::status();

        // What an applicant must bring, and what it costs, is configured per
        // degree type under Academic > Degree Type > Form Configuration. Read
        // it through DegreeTypeFormConfig, the same service the wizard and the
        // admin preview use, so this page cannot become a third answer that
        // drifts from the other two.
        $degreeTypes = DegreeType::where('status', 1)
            ->orderBy('sort_order')
            ->orderBy('title')
            ->get()
            ->map(function (DegreeType $degreeType) {
                $documents = collect(DegreeTypeFormConfig::documents($degreeType));
                $settings  = DegreeTypeFormConfig::settings($degreeType);

                return [
                    'id'        => $degreeType->id,
                    'title'     => $degreeType->title,
                    'slug'      => $degreeType->slug,
                    'intro'     => $settings['intro_html'],
                    'blurb'     => $settings['requirements_html'],
                    'required'  => $documents->filter(fn ($d) => !empty($d['required']))->values(),
                    'optional'  => $documents->filter(fn ($d) => empty($d['required']))->values(),
                    'fee'       => $settings['fee_enabled'] ? (float) $settings['fee_amount'] : null,
                    'feeDays'   => (int) $settings['fee_due_days'],
                    'feeNotes'  => $settings['fee_instructions'],
                ];
            });

        return view('application.portal.start', [
            'title' => __('Apply') . ' | ' . institution_name(),
            'setting' => Setting::where('status', '1')->first(),
            'applicationSetting' => $applicationSetting,
            'openSessions' => $openSessions,
            'isOpen' => $applicationSetting && $openSessions->isNotEmpty(),
            'degreeTypes' => $degreeTypes,
        ]);
    }

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
            $user = Auth::guard('applicant')->user();

            // A disabled account is refused here, after the password has been
            // checked, so the reason only ever reaches the account's owner and
            // never tells a stranger which emails have an account.
            if ($user->disabled_at) {
                Auth::guard('applicant')->logout();

                return back()
                    ->withErrors(['email' => __('This account has been disabled. Please contact the admissions office.')])
                    ->onlyInput('email');
            }

            $request->session()->regenerate();
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

        return view('application.portal.register', [
            'title' => __('Start your application') . ' | ' . institution_name(),
            // Linked only when the page actually exists. A terms link that
            // 404s is worse than no link at all on a form people must agree to.
            'termsPage' => \App\Models\Web\Page::where('slug', 'terms-and-conditions')->first(),
        ]);
    }

    public function register(Request $request)
    {
        $request->validate([
            'first_name' => ['required', 'string', 'max:191'],
            'last_name' => ['required', 'string', 'max:191'],
            'email' => ['required', 'string', 'email', 'max:191', 'unique:applicants,email', 'confirmed'],
            'email_confirmation' => ['required', 'string', 'email', 'max:191'],
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

    /**
     * Stop impersonating an applicant and go back to the Applicants screen.
     *
     * Deliberately outside the applicant middleware group: an administrator has
     * to be able to get out even if the account was disabled while they were
     * inside it, which is exactly when they would be looking.
     */
    public function leaveImpersonation(Request $request)
    {
        if (!$request->session()->has('impersonate_applicant_admin_id')) {
            return redirect()->route('application.login');
        }

        $applicant = Auth::guard('applicant')->user();

        $request->session()->forget('impersonate_applicant_admin_id');
        Auth::guard('applicant')->logout();

        if ($applicant) {
            $applicant->customAuditLog(
                'impersonation_ended',
                sprintf('An administrator stopped signing in as applicant %s', $applicant->email)
            );
        }

        return redirect()->route('admin.applicant.index');
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

        if (!$applicant) {
            return redirect()->back()->with('info', __('If an account with that email exists, we have sent a password reset link.'));
        }

        // The admin Applicants screen sends this same link through the same
        // service, so both produce the same email and the same kind of token.
        $result = app(\App\Services\ApplicantPasswordReset::class)->send($applicant);

        if ($result === \App\Services\ApplicantPasswordReset::MAIL_NOT_CONFIGURED) {
            return redirect()->back()->with('error', __('Email service is not configured. Please contact support.'));
        }

        if ($result === \App\Services\ApplicantPasswordReset::FAILED) {
            return redirect()->back()->with('error', __('Failed to send reset email. Please try again later.'));
        }

        return redirect()->back()->with('success', __('We have sent a password reset link to your email address. Please check your inbox (and spam folder).'));
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
