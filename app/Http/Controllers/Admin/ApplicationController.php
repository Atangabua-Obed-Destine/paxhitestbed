<?php

namespace App\Http\Controllers\Admin;

use Illuminate\Support\Facades\Crypt;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Flasher\Laravel\Facade\Flasher;
use Illuminate\Support\Facades\DB;
use App\Models\StudentRelative;
use App\Models\StudentEnroll;
use App\Models\EnrollSubject;
use Illuminate\Http\Request;
use App\Traits\FileUploader;
use App\Models\Application;
use App\Models\Province;
use App\Models\District;
use App\Models\Document;
use App\Models\Field;
use App\Models\Program;
use App\Models\Student;
use App\Models\Batch;
use App\Models\StatusType;
use App\Models\Setting;
use Carbon\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\File;
use App\Support\ApplicationDocumentRequirements;
use Illuminate\Support\Facades\Mail;
use App\Models\MailSetting;
use App\Mail\SendPassword;

class ApplicationController extends Controller
{
    use FileUploader;

    protected $title, $route, $view, $path, $access;

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        // Module Data
        $this->title = trans_choice('module_application', 1);
        $this->route = 'admin.application';
        $this->view = 'admin.application';
        $this->path = 'student';
        $this->access = 'application';


        $this->middleware('permission:'.$this->access.'-view|'.$this->access.'-create|'.$this->access.'-edit|'.$this->access.'-delete', ['only' => ['index','show']]);
        $this->middleware('permission:'.$this->access.'-create', ['only' => ['create','store']]);
        $this->middleware('permission:'.$this->access.'-edit', ['only' => ['edit','update']]);
        $this->middleware('permission:'.$this->access.'-delete', ['only' => ['destroy']]);
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        //
        $data['title'] = $this->title;
        $data['route'] = $this->route;
        $data['view'] = $this->view;
        $data['path'] = $this->path;
        $data['access'] = $this->access;


        if(!empty($request->batch) || $request->batch != null){
            $data['selected_batch'] = $batch = $request->batch;
        }
        else{
            $data['selected_batch'] = '0';
        }

        if(!empty($request->program) || $request->program != null){
            $data['selected_program'] = $program = $request->program;
        }
        else{
            $data['selected_program'] = '0';
        }

        if(!empty($request->degree_type) || $request->degree_type != null){
            $data['selected_degree_type'] = $degreeType = $request->degree_type;
        }
        else{
            $data['selected_degree_type'] = '0';
        }

        if(!empty($request->session) || $request->session != null){
            $data['selected_session'] = $sessionId = $request->session;
        }
        else{
            $data['selected_session'] = '0';
        }

        if(!empty($request->status) || $request->status != null){
            $data['selected_status'] = $status = $request->status;
        }
        else{
            $data['selected_status'] = $status = '99';
        }

        if(!empty($request->start_date) || $request->start_date != null){
            $data['selected_start_date'] = $start_date = $request->start_date;
        }
        else{
            $data['selected_start_date'] = $start_date = date('Y-m-d', strtotime(Carbon::now()->subYear()));
        }

        if(!empty($request->end_date) || $request->end_date != null){
            $data['selected_end_date'] = $end_date = $request->end_date;
        }
        else{
            $data['selected_end_date'] = $end_date = date('Y-m-d', strtotime(Carbon::today()));
        }

        if(!empty($request->registration_no) || $request->registration_no != null){
            $data['selected_registration_no'] = $registration_no = $request->registration_no;
        }
        else{
            $data['selected_registration_no'] = Null;
        }

        // Free-text search across applicant name, email and phone.
        if(!empty($request->applicant)){
            $data['selected_applicant'] = $applicantQuery = trim($request->applicant);
        }
        else{
            $data['selected_applicant'] = null;
            $applicantQuery = null;
        }


        // Search Filter
        $data['batches'] = Batch::where('status', '1')->orderBy('id', 'desc')->get();
        $data['programs'] = Program::where('status', '1')->orderBy('title', 'asc')->get();
        $data['degreeTypes'] = \App\Models\DegreeType::where('status', 1)->orderBy('title', 'asc')->get();
        $data['sessions'] = \App\Models\Session::orderBy('title', 'desc')->get();


        if(isset($request->program) || isset($request->status) || isset($request->registration_no) || isset($request->degree_type) || isset($request->session) || !empty($applicantQuery)){
            // Application Filter
            $applications = Application::with(['admissionFee.paymentReceipts', 'degreeType', 'session', 'applicant', 'program'])
                        ->whereDate('apply_date', '>=', $start_date)
                        ->whereDate('apply_date', '<=', $end_date);
                        if(!empty($request->batch)){
                            $applications->where('batch_id', $batch);
                        }
                        if(!empty($request->program)){
                            $applications->where('program_id', $program);
                        }
                        if(!empty($request->degree_type)){
                            $applications->where('degree_type_id', $degreeType);
                        }
                        if(!empty($request->session)){
                            $applications->where('session_id', $sessionId);
                        }
                        if(!empty($request->registration_no)){
                            $applications->where('registration_no', 'LIKE', '%'.$registration_no.'%');
                        }
                        if(!empty($applicantQuery)){
                            $like = '%'.$applicantQuery.'%';
                            $applications->where(function ($q) use ($like) {
                                $q->where('first_name', 'LIKE', $like)
                                  ->orWhere('last_name', 'LIKE', $like)
                                  ->orWhereRaw("CONCAT_WS(' ', first_name, last_name) LIKE ?", [$like])
                                  ->orWhere('email', 'LIKE', $like)
                                  ->orWhere('phone', 'LIKE', $like)
                                  ->orWhereHas('applicant', function ($sub) use ($like) {
                                      $sub->where('first_name', 'LIKE', $like)
                                          ->orWhere('last_name', 'LIKE', $like)
                                          ->orWhere('email', 'LIKE', $like)
                                          ->orWhere('phone', 'LIKE', $like);
                                  });
                            });
                        }
                        if(!empty($request->status) || $request->status != null){
                            $applications->where('status', $status);
                        }
            $data['rows'] = $applications->orderBy('registration_no', 'desc')->get();
        }


        return view($this->view.'.index', $data);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        // Field Validation
        $request->validate([
            'student_id' => 'nullable|string|max:50',
            'batch' => 'required',
            'program' => 'required',
            'session' => 'required',
            'semester' => 'required',
            'section' => 'required',
            'first_name' => 'required',
            'last_name' => 'required',
            'email' => 'required|email|unique:students,email',
            'phone' => 'required',
            'gender' => 'required',
            'dob' => 'required|date',
            'admission_date' => 'required|date',
            'photo' => 'nullable|image',
            'signature' => 'nullable|image',
        ]);

        // Generate student_id if empty, or use provided value
        $studentId = $request->student_id;
        
        if (empty(trim($studentId ?? ''))) {
            // Auto-generate student ID
            try {
                $faculty = Program::find($request->program)->faculty_id ?? null;
                if (!$faculty) {
                    throw new \Exception('Unable to determine faculty from program');
                }
                $studentId = Student::generateStudentId($faculty, $request->batch, $request->program);
            } catch (\Exception $e) {
                Flasher::addError('Error generating student ID: ' . $e->getMessage());
                return redirect()->back()->withInput();
            }
        }

        // Check if student_id already exists and regenerate if necessary
        $attempts = 0;
        $maxAttempts = 10;
        
        while (Student::where('student_id', $studentId)->exists() && $attempts < $maxAttempts) {
            // Student ID already exists, regenerate it
            try {
                $faculty = Program::find($request->program)->faculty_id ?? null;
                if (!$faculty) {
                    throw new \Exception('Unable to determine faculty from program');
                }
                
                $studentId = Student::generateStudentId($faculty, $request->batch, $request->program);
                $attempts++;
            } catch (\Exception $e) {
                Flasher::addError('Error generating unique student ID: ' . $e->getMessage());
                return redirect()->back()->withInput();
            }
        }
        
        if ($attempts >= $maxAttempts) {
            Flasher::addError('Unable to generate unique student ID after multiple attempts. Please try again.');
            return redirect()->back()->withInput();
        }

        // Random Password
        $password = str_random(8);
        $data = Application::where('registration_no', $request->registration_no)->firstOrFail();

        // Insert Data
        try{
            DB::beginTransaction();

            $application = new Student;
            $application->student_id = $studentId; // Use the validated/regenerated student_id
            $application->registration_no = $request->registration_no;
            $application->batch_id = $request->batch;
            $application->program_id = $request->program;
            $application->admission_date = $request->admission_date;

            $application->first_name = $request->first_name;
            $application->last_name = $request->last_name;
            $application->father_name = $request->father_name;
            $application->mother_name = $request->mother_name;
            $application->father_occupation = $request->father_occupation;
            $application->mother_occupation = $request->mother_occupation;
            $application->email = $request->email;
            $application->password = Hash::make($password);
            $application->password_text = Crypt::encryptString($password);

            $application->country = $request->country;
            $application->present_province = $request->present_province;
            $application->present_district = $request->present_district;
            $application->present_village = $request->present_village;
            $application->present_address = $request->present_address;
            $application->permanent_province = $request->permanent_province;
            $application->permanent_district = $request->permanent_district;
            $application->permanent_village = $request->permanent_village;
            $application->permanent_address = $request->permanent_address;

            $application->gender = $request->gender;
            $application->dob = $request->dob;
            $application->phone = $request->phone;
            $application->emergency_phone = $request->emergency_phone;

            $application->religion = $request->religion;
            $application->is_catholic_baptised = $request->boolean('is_catholic_baptised');
            $application->is_confirmed = $request->boolean('is_confirmed');
            $application->has_first_communion = $request->boolean('has_first_communion');
            $application->caste = $request->caste;
            $application->mother_tongue = $request->mother_tongue;
            $application->marital_status = $request->marital_status;
            $application->blood_group = $request->blood_group;
            $application->nationality = $request->nationality;
            $application->national_id = $request->national_id;
            $application->passport_no = $request->passport_no;

            $application->school_name = $request->school_name;
            $application->school_exam_id = $request->school_exam_id;
            $application->school_graduation_year = $request->school_graduation_year;
            $application->school_graduation_point = $request->school_graduation_point;
            $application->collage_name = $request->collage_name;
            $application->collage_exam_id = $request->collage_exam_id;
            $application->collage_graduation_year = $request->collage_graduation_year;
            $application->collage_graduation_point = $request->collage_graduation_point;
            if($request->hasFile('school_transcript')){
            $application->school_transcript = $this->uploadMedia($request, 'school_transcript', $this->path);
            }
            else{
            $application->school_transcript = $data->school_transcript;
            }
            if($request->hasFile('school_certificate')){
            $application->school_certificate = $this->uploadMedia($request, 'school_certificate', $this->path);
            }
            else{
            $application->school_certificate = $data->school_certificate;
            }
            if($request->hasFile('collage_transcript')){
            $application->collage_transcript = $this->uploadMedia($request, 'collage_transcript', $this->path);
            }
            else{
            $application->collage_transcript = $data->collage_transcript;
            }
            if($request->hasFile('collage_certificate')){
            $application->collage_certificate = $this->uploadMedia($request, 'collage_certificate', $this->path);
            }
            else{
            $application->collage_certificate = $data->collage_certificate;
            }
            if($request->hasFile('photo')){
            $application->photo = $this->uploadImage($request, 'photo', $this->path, 300, 300);
            }
            else{
            $application->photo = $data->photo;
            }
            if($request->hasFile('signature')){
            $application->signature = $this->uploadImage($request, 'signature', $this->path, 300, 100);
            }
            else{
            $application->signature = $data->signature;
            }
            $application->status = '1';
            $application->created_by = Auth::guard('web')->user()->id;
            $application->save();

            // Send Login Credentials
            $mail = MailSetting::where('status', '1')->first();

            if(isset($mail->sender_email) && isset($mail->sender_name)){
                $sendTo = $application->email;
                $receiver = $application->first_name.' '.$application->last_name;

                // Passing data to email template
                $mailData['name'] = $application->first_name.' '.$application->last_name;
                $mailData['student_id'] = $application->student_id;
                $mailData['email'] = $application->email;
                $mailData['password'] = $password;

                // Mail Information
                $mailData['subject'] = __('msg_your_login_credentials');
                $mailData['from'] = $mail->sender_email;
                $mailData['sender'] = $mail->sender_name;

                // Send Mail
                try {
                    Mail::to($sendTo, $receiver)->send(new SendPassword($mailData));
                } catch (\Exception $e) {
                    // Log error but don't fail the transaction
                    \Illuminate\Support\Facades\Log::error("Failed to send credential email: " . $e->getMessage());
                }
            }


            // Attach Status
            $application->statuses()->attach($request->statuses);


            // Student Relatives
            if(is_array($request->relations)){
            foreach($request->relations as $key =>$relation){
                if($relation != '' && $relation != null){
                // Insert Data
                $relation = new StudentRelative;
                $relation->student_id = $application->id;
                $relation->relation = $request->relations[$key];
                $relation->name = $request->relative_names[$key];
                $relation->occupation = $request->occupations[$key];
                // $relation->email = $request->relative_emails[$key];
                $relation->phone = $request->relative_phones[$key];
                $relation->address = $request->addresses[$key];
                $relation->save();
                }
            }}


            // Student Documents
            if(is_array($request->documents)){
            $documents = $request->file('documents');
            foreach($documents as $key =>$attach){

                // Valid extension check
                $valid_extensions = array('JPG','JPEG','jpg','jpeg','png','gif','ico','svg','webp','pdf','doc','docx','txt','zip','rar','csv','xls','xlsx','ppt','pptx','mp3','avi','mp4','mpeg','3gp','mov','ogg','mkv');
                $file_ext = $attach->getClientOriginalExtension();
                if(in_array($file_ext, $valid_extensions, true))
                {

                //Upload Files
                $filename = $attach->getClientOriginalName();
                $extension = $attach->getClientOriginalExtension();
                $fileNameToStore = str_replace([' ','-','&','#','$','%','^',';',':'],'_',$filename).'_'.time().'.'.$extension;

                // Move file inside public/uploads/ directory
                $attach->move('uploads/'.$this->path.'/', $fileNameToStore);

                // Insert Data
                $document = new Document;
                $document->title = $request->titles[$key];
                $document->attach = $fileNameToStore;
                $document->save();

                // Attach
                $document->students()->attach($application->id);

                }
            }}


            // Student Enroll
            $enroll = new StudentEnroll();
            $enroll->student_id = $application->id;
            $enroll->program_id = $request->program;
            $enroll->session_id = $request->session;
            $enroll->semester_id = $request->semester;
            $enroll->section_id = $request->section;
            
            // For the FIRST enrollment (new student creation), use the same student_id as matricule
            // This ensures consistency between students.student_id and student_enrolls.matricule
            // Only subsequent enrollments (program changes, level upgrades) should generate new matricule
            $enroll->matricule = $application->student_id;
            
            $enroll->created_by = Auth::guard('web')->user()->id;
            $enroll->save();

            // Adopt the admission fee (originally created against a stub StudentEnroll
            // during online submission) onto this real enrollment, then drop the stub.
            if ($data->admission_fee_id) {
                $admissionFee = \App\Models\Fee::find($data->admission_fee_id);
                if ($admissionFee && (int) $admissionFee->student_enroll_id !== (int) $enroll->id) {
                    $stubEnrollId = $admissionFee->student_enroll_id;
                    $admissionFee->student_enroll_id = $enroll->id;
                    $admissionFee->save();

                    if ($stubEnrollId) {
                        $stub = StudentEnroll::find($stubEnrollId);
                        // Only delete if it really is the placeholder we created
                        // (status = 0 with null session/semester/section).
                        if ($stub
                            && (int) $stub->status === 0
                            && is_null($stub->session_id)
                            && is_null($stub->semester_id)
                            && is_null($stub->section_id)) {
                            $stub->subjects()->detach();
                            $stub->delete();
                        }
                    }
                }
            }


            // Assign Subjects
            $enrollSubject = EnrollSubject::where('program_id', $request->program)->where('semester_id', $request->semester)->where('section_id', $request->section)->first();

            if(isset($enrollSubject)){
                foreach($enrollSubject->subjects as $subject){
                    // Attach Subject
                    $enroll->subjects()->attach($subject->id);
                }
            }

            // Auto-assign fees from program semester fee configuration
            $this->autoAssignProgramSemesterFees($enroll);

            // Application Status Update
            $data->status = '2';
            $data->updated_by = Auth::guard('web')->user()->id;
            $data->save();

            $data->recordStatus(
                'decision_approved',
                __('Application approved and student record created.'),
                2,
                __('application_stage.decision_approved'),
                Auth::guard('web')->id(),
                'admin'
            );

            DB::commit();

            // Email the acceptance letter (PDF) configured for this degree type, if enabled.
            // Sent after commit so the student + enrollment are fully persisted; never breaks the flow.
            $letterSent = false;
            $letterError = null;
            try {
                $letterSent = (bool) app(\App\Services\AcceptanceLetterService::class)->sendTo($application);
            } catch (\Throwable $e) {
                $letterError = $e->getMessage();
                \Illuminate\Support\Facades\Log::error('Acceptance letter send failed: ' . $letterError);
            }

            if ($letterSent) {
                Flasher::addSuccess(__('Acceptance letter emailed to') . ' ' . $application->email, __('msg_success'));
            } elseif ($letterError) {
                Flasher::addWarning(__('Student created, but the acceptance letter email failed to send. You can resend it from the application page.'), __('msg_warning'));
            } else {
                // Not sent because template disabled, mail not configured, or student has no email.
                Flasher::addInfo(__('Student created. Acceptance letter was not emailed (template disabled, mail not configured, or missing email).'), __('msg_info'));
            }

            Flasher::addSuccess(__('msg_created_successfully'), __('msg_success'));

            return redirect()->route($this->route.'.index');
        }
        catch(\Exception $e){

            Flasher::addError(__('msg_created_error'), __('msg_error'));

            return redirect()->back();
        }
    }

    /**
     * Find the Student created from this application (linked by registration_no).
     */
    private function convertedStudent(Application $application)
    {
        return \App\Models\Student::where('registration_no', $application->registration_no)->first();
    }

    /**
     * Notify the applicant that one or more of their documents need resubmission.
     * Silent on failure — the status-update record on the timeline is the source of truth.
     */
    private function sendDocumentsResubmissionMail(Application $application, array $documents): void
    {
        $recipient = $application->applicant->email ?? $application->email;
        if (!$recipient) {
            return;
        }

        $mail = MailSetting::where('status', '1')->first();
        if (!$mail || !$mail->sender_email || !$mail->sender_name) {
            return;
        }

        try {
            $data = [
                'from' => $mail->sender_email,
                'sender' => $mail->sender_name,
                'subject' => __('Action required on your application') . ' #' . $application->registration_no,
                'first_name' => $application->applicant->first_name ?? $application->first_name,
                'registration_no' => $application->registration_no,
                'documents' => $documents,
                'portal_url' => route('application.dashboard'),
            ];

            Mail::to($recipient)->send(new \App\Mail\ApplicantDocumentsResubmission($data));
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Applicant documents-resubmission mail failed: ' . $e->getMessage());
        }
    }

    /**
     * Download the acceptance-letter PDF for the student created from this application.
     */
    public function downloadAcceptanceLetter(Application $application, \App\Services\AcceptanceLetterService $service)
    {
        $student = $this->convertedStudent($application);
        if (!$student) {
            Flasher::addError(__('Convert this application to a student first.'), __('msg_error'));
            return redirect()->back();
        }

        $pdf = $service->pdf($student);
        if (!$pdf) {
            Flasher::addError(__('No acceptance letter is configured for this degree type. Enable it under the degree type\'s Form Configuration.'), __('msg_error'));
            return redirect()->back();
        }

        return $pdf->download('Acceptance-Letter-' . $student->student_id . '.pdf');
    }

    /**
     * Re-send the acceptance-letter email (with PDF) to the student created from this application.
     */
    public function resendAcceptanceLetter(Application $application, \App\Services\AcceptanceLetterService $service)
    {
        $student = $this->convertedStudent($application);
        if (!$student) {
            Flasher::addError(__('Convert this application to a student first.'), __('msg_error'));
            return redirect()->back();
        }

        if ($service->sendTo($student)) {
            Flasher::addSuccess(__('Acceptance letter sent to') . ' ' . $student->email, __('msg_success'));
        } else {
            Flasher::addError(__('Could not send the acceptance letter. Check the degree type template is enabled and mail is configured.'), __('msg_error'));
        }

        return redirect()->back();
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show(Application $application)
    {
        //
        $data['title'] = $this->title;

        $application->load([
            'statusUpdates' => function ($query) {
                $query->orderBy('created_at', 'desc');
            },
            'program',
            'batch',
            'preferredProgramFirst',
            'preferredProgramSecond',
            'preferredProgramThird',
            'guardians',
            'academicHistories',
            'languages',
            'documents',
            'boardReview.signatures',
            // 'presentProvince',
            // 'presentDistrict',
            // 'permanentProvince',
            // 'permanentDistrict',
            'religionDetail',
            'admissionFee.category',
            'admissionFee.paymentReceipts',
        ]);

        $data['row'] = $application;
        $data['timeline'] = $application->statusUpdates;
        $data['view'] = $this->view;
        $data['path'] = $this->path;
        $data['access'] = $this->access;
        $data['documentRequirements'] = ApplicationDocumentRequirements::all();
        $data['setting'] = Setting::where('status', '1')->first();

        return view($this->view.'.show', $data);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit(Application $application)
    {
        $application->load([
            'statusUpdates' => function ($query) {
                $query->orderBy('created_at', 'desc');
            },
            'program',
            'batch',
            'preferredProgramFirst',
            'preferredProgramSecond',
            'preferredProgramThird',
            'guardians',
            'academicHistories',
            'languages',
            'documents',
            'boardReview.signatures',
            // 'presentProvince',
            // 'presentDistrict',
            // 'permanentProvince',
            // 'permanentDistrict',
            'admissionFee.category',
            'admissionFee.paymentReceipts',
        ]);

        $data = [
            'title' => $this->title,
            'route' => $this->route,
            'view' => $this->view,
            'path' => $this->path,
            'row' => $application,
            'timeline' => $application->statusUpdates,
            'provinces' => Province::where('status', '1')->orderBy('title', 'asc')->get(),
            'present_districts' => District::where('status', '1')
                ->where('province_id', $application->present_province)
                ->orderBy('title', 'asc')
                ->get(),
            'permanent_districts' => District::where('status', '1')
                ->where('province_id', $application->permanent_province)
                ->orderBy('title', 'asc')
                ->get(),
            'faculties' => \App\Models\Faculty::where('status', '1')->orderBy('title', 'asc')->get(),
            'programs' => Program::where('status', '1')->orderBy('title', 'asc')->get(),
            'religions' => \App\Models\Religion::where('status', '1')->orderBy('title', 'asc')->get(),
            'religions_json' => \App\Models\Religion::where('status', '1')->orderBy('title', 'asc')->get()->keyBy('id')->toJson(),
            'batches' => Batch::where('status', '1')->orderBy('id', 'desc')->get(),
            'statuses' => StatusType::where('status', '1')->orderBy('title')->get(),
            'documentRequirements' => ApplicationDocumentRequirements::all(),
            'guardianTypes' => ['Parent', 'Sponsor', 'Guardian'],
            'fluencyOptions' => ['excellent', 'good', 'fair', 'minimal'],
            'setting' => Setting::where('status', '1')->first(),
        ];

        return view($this->view.'.edit', $data);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, Application $application)
    {
        $documentRequirements = ApplicationDocumentRequirements::all();
        $newResubmissionRequests = [];

        $fieldStatusCache = [];
        $fieldEnabled = function (string $slug) use (&$fieldStatusCache): bool {
            if (!array_key_exists($slug, $fieldStatusCache)) {
                $fieldStatusCache[$slug] = (int) optional(Field::field($slug))->status === 1;
            }

            return $fieldStatusCache[$slug];
        };

        $request->merge([
            'second_program_choice_id' => $request->input('second_program_choice_id') ?: null,
            'third_program_choice_id' => $request->input('third_program_choice_id') ?: null,
            'permanent_province' => $request->input('permanent_province') ?: null,
            'permanent_district' => $request->input('permanent_district') ?: null,
        ]);

        if ($fieldEnabled('application_guardians')) {
            $normalizedGuardians = collect($request->input('guardians', []))->map(function ($guardian) {
                $guardian = is_array($guardian) ? $guardian : [];
                $guardian['is_primary'] = !empty($guardian['is_primary']);
                if (array_key_exists('id', $guardian) && $guardian['id'] === '') {
                    $guardian['id'] = null;
                }

                return $guardian;
            })->toArray();

            $request->merge(['guardians' => $normalizedGuardians]);
        }

        if ($fieldEnabled('application_language_proficiency')) {
            $normalizedLanguages = collect($request->input('languages', []))->map(function ($language) {
                $language = is_array($language) ? $language : [];
                if (array_key_exists('years_of_study', $language) && $language['years_of_study'] === '') {
                    $language['years_of_study'] = null;
                }
                if (array_key_exists('id', $language) && $language['id'] === '') {
                    $language['id'] = null;
                }

                return $language;
            })->toArray();

            $request->merge(['languages' => $normalizedLanguages]);
        }

        if ($fieldEnabled('application_academic_history')) {
            $normalizedHistory = collect($request->input('academic_history', []))->map(function ($history) {
                $history = is_array($history) ? $history : [];
                foreach (['date_from', 'date_to'] as $dateField) {
                    if (array_key_exists($dateField, $history) && $history[$dateField] === '') {
                        $history[$dateField] = null;
                    }
                }
                if (array_key_exists('id', $history) && $history['id'] === '') {
                    $history['id'] = null;
                }

                return $history;
            })->toArray();

            $request->merge(['academic_history' => $normalizedHistory]);
        }

        $academicYearEnabled = $fieldEnabled('application_academic_year');
        $secondChoiceEnabled = $fieldEnabled('application_program_choice_second');
        $thirdChoiceEnabled = $fieldEnabled('application_program_choice_third');
        $birthCityEnabled = $fieldEnabled('application_birth_city');
        $birthDivisionEnabled = $fieldEnabled('application_birth_division');
        $birthRegionEnabled = $fieldEnabled('application_birth_region');
        $birthCountryEnabled = $fieldEnabled('application_birth_country');
        $registrationFeeBankEnabled = $fieldEnabled('application_registration_fee_bank');
        $registrationFeeReferenceEnabled = $fieldEnabled('application_registration_fee_reference');
        $studiedEnglishEnabled = $fieldEnabled('application_studied_in_english');
        $instructionLanguageEnabled = $fieldEnabled('application_instruction_language_secondary');
        $guardiansEnabled = $fieldEnabled('application_guardians');
        $academicHistoryEnabled = $fieldEnabled('application_academic_history');
        $languageEnabled = $fieldEnabled('application_language_proficiency');
        $documentChecklistEnabled = $fieldEnabled('application_document_checklist');
        $boardReviewEnabled = $fieldEnabled('application_board_review');

        $rules = [
            'program' => ['required', 'exists:programs,id'],
            'first_name' => ['required', 'string', 'max:191'],
            'last_name' => ['required', 'string', 'max:191'],
            'gender' => ['required', Rule::in([1, 2, 3])],
            'dob' => ['required', 'date', 'before:tomorrow'],
            'religion' => ['nullable', 'string', 'max:191'],
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
            'email' => ['required', 'email', 'max:191', Rule::unique('applications', 'email')->ignore($application->id)],
            'mother_tongue' => ['nullable', 'string', 'max:191'],
            'studied_in_english' => ['nullable', 'boolean'],
            'photo' => ['nullable', 'image', 'max:5120'],
            'signature' => ['nullable', 'image', 'max:2048'],
            'stage' => ['required', Rule::in(array_keys(Application::stageLabelMap()))],
            'status' => ['required', Rule::in([0, 1, 2])],
            'progress' => ['nullable', 'integer', 'min:0', 'max:100'],
            'documents' => ['nullable', 'array'],
        ];

        $rules['second_program_choice_id'] = $secondChoiceEnabled
            ? ['nullable', 'different:program', 'exists:programs,id']
            : ['nullable'];
        $rules['third_program_choice_id'] = $thirdChoiceEnabled
            ? ['nullable', 'different:program', 'different:second_program_choice_id', 'exists:programs,id']
            : ['nullable'];

        $rules['academic_year'] = [$academicYearEnabled ? 'required' : 'nullable', 'string', 'max:191'];
        $rules['birth_city'] = [$birthCityEnabled ? 'required' : 'nullable', 'string', 'max:191'];
        $rules['birth_division'] = [$birthDivisionEnabled ? 'required' : 'nullable', 'string', 'max:191'];
        $rules['birth_region'] = [$birthRegionEnabled ? 'required' : 'nullable', 'string', 'max:191'];
        $rules['birth_country'] = [$birthCountryEnabled ? 'required' : 'nullable', 'string', 'max:191'];
        $rules['registration_fee_bank'] = [$registrationFeeBankEnabled ? 'required' : 'nullable', 'string', 'max:191'];
        $rules['registration_fee_reference'] = [$registrationFeeReferenceEnabled ? 'required' : 'nullable', 'string', 'max:191'];

        if ($instructionLanguageEnabled || $studiedEnglishEnabled) {
            $instructionRule = ['nullable', 'string', 'max:191'];
            if ($instructionLanguageEnabled && $studiedEnglishEnabled) {
                $instructionRule[] = 'required_if:studied_in_english,0';
            }
            $rules['instruction_language_secondary'] = $instructionRule;
        } else {
            $rules['instruction_language_secondary'] = ['nullable', 'string', 'max:191'];
        }

        if ($guardiansEnabled) {
            $rules['guardians'] = ['nullable', 'array'];
            $rules['guardians.*.id'] = ['nullable', 'exists:application_guardians,id'];
            $rules['guardians.*.full_name'] = ['nullable', 'string', 'max:191'];
            $rules['guardians.*.relationship'] = ['nullable', 'string', 'max:191'];
            $rules['guardians.*.type'] = ['nullable', 'string', 'max:191'];
            $rules['guardians.*.occupation'] = ['nullable', 'string', 'max:191'];
            $rules['guardians.*.email'] = ['nullable', 'email', 'max:191'];
            $rules['guardians.*.phone_primary'] = ['nullable', 'string', 'max:191'];
            $rules['guardians.*.phone_secondary'] = ['nullable', 'string', 'max:191'];
            $rules['guardians.*.address_line1'] = ['nullable', 'string', 'max:255'];
            $rules['guardians.*.address_line2'] = ['nullable', 'string', 'max:255'];
            $rules['guardians.*.city'] = ['nullable', 'string', 'max:191'];
            $rules['guardians.*.state'] = ['nullable', 'string', 'max:191'];
            $rules['guardians.*.country'] = ['nullable', 'string', 'max:191'];
            $rules['guardians.*.is_primary'] = ['nullable', 'boolean'];
        }

        if ($academicHistoryEnabled) {
            $rules['academic_history'] = ['nullable', 'array'];
            $rules['academic_history.*.id'] = ['nullable', 'exists:application_academic_histories,id'];
            $rules['academic_history.*.institution_name'] = ['nullable', 'string', 'max:255'];
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
            $rules['languages.*.id'] = ['nullable', 'exists:application_languages,id'];
            $rules['languages.*.language'] = ['nullable', 'string', 'max:191'];
            $rules['languages.*.years_of_study'] = ['nullable', 'integer', 'min:0', 'max:60'];
            $rules['languages.*.fluency_level'] = ['nullable', 'string', 'max:50'];
        }

        if ($documentChecklistEnabled) {
            foreach ($documentRequirements as $key => $documentConfig) {
                $rules["documents.$key.is_received"] = ['nullable', 'boolean'];
                $rules["documents.$key.note"] = ['nullable', 'string', 'max:500'];
                $rules["documents.$key.notes"] = ['nullable', 'string', 'max:500'];
                $rules["documents.$key.file"] = ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:10240'];
                $rules["documents.$key.needs_resubmission"] = ['nullable'];
                $rules["documents.$key.rejection_reason"] = ['nullable', 'string', 'max:500'];
            }
        }

        if ($boardReviewEnabled) {
            $rules['board_review'] = ['nullable', 'array'];
            $rules['board_review.meets_university_requirements'] = ['nullable', 'boolean'];
            $rules['board_review.meets_program_requirements'] = ['nullable', 'boolean'];
            $rules['board_review.first_choice_decision'] = ['nullable', 'string', 'max:191'];
            $rules['board_review.second_choice_decision'] = ['nullable', 'string', 'max:191'];
            $rules['board_review.third_choice_decision'] = ['nullable', 'string', 'max:191'];
            $rules['board_review.observation'] = ['nullable', 'string'];
            $rules['board_review.rejection_reason'] = ['nullable', 'string'];
            $rules['board_review.reviewed_by'] = ['nullable', 'integer'];
            $rules['board_review.reviewed_at'] = ['nullable', 'date'];
        }

        $validated = $request->validate($rules);

        try {
            DB::beginTransaction();

            $previousStatus = (int) $application->status;
            $previousStage = $application->stage;

            $application->program_id = $validated['program'];
            $application->first_program_choice_id = $validated['program'];
            $application->second_program_choice_id = $validated['second_program_choice_id'] ?? null;
            $application->third_program_choice_id = $validated['third_program_choice_id'] ?? null;
            $application->academic_year = $academicYearEnabled ? ($validated['academic_year'] ?? null) : null;

            $application->first_name = $validated['first_name'];
            $application->last_name = $validated['last_name'];
            $application->gender = (int) $validated['gender'];
            $application->dob = $validated['dob'];

            $application->birth_city = $birthCityEnabled ? ($validated['birth_city'] ?? null) : null;
            $application->birth_division = $birthDivisionEnabled ? ($validated['birth_division'] ?? null) : null;
            $application->birth_region = $birthRegionEnabled ? ($validated['birth_region'] ?? null) : null;
            $application->birth_country = $birthCountryEnabled ? ($validated['birth_country'] ?? null) : null;

            $application->religion = $validated['religion'] ?? null;
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
            $application->instruction_language_secondary = $instructionLanguageEnabled ? ($validated['instruction_language_secondary'] ?? null) : null;

            $application->registration_fee_bank = $registrationFeeBankEnabled ? ($validated['registration_fee_bank'] ?? null) : null;
            $application->registration_fee_reference = $registrationFeeReferenceEnabled ? ($validated['registration_fee_reference'] ?? null) : null;

            $application->stage = $validated['stage'];
            $application->status = (int) $validated['status'];
            $application->progress = $validated['progress'] ?? (Application::stageProgressMap()[$application->stage] ?? $application->progress);

            if ($request->hasFile('photo')) {
                $newPhoto = $this->uploadImage($request, 'photo', $this->path, 300, 300);
                if ($newPhoto) {
                    $this->removeExistingUpload($application->photo);
                    $application->photo = $newPhoto;
                }
            }

            if ($request->hasFile('signature')) {
                $newSignature = $this->uploadImage($request, 'signature', $this->path, 300, 100);
                if ($newSignature) {
                    $this->removeExistingUpload($application->signature);
                    $application->signature = $newSignature;
                }
            }

            if ($previousStatus !== (int) $application->status) {
                if ((int) $application->status === 2) {
                    $application->decision_at = now();
                    if ($application->stage === 'decision_approved') {
                        $application->completed_at = now();
                    }
                } elseif ((int) $application->status === 0) {
                    $application->decision_at = now();
                }
            }

            $application->updated_by = Auth::guard('web')->id();

            $application->save();

            if ($guardiansEnabled) {
                $guardians = collect($validated['guardians'] ?? [])->filter(function ($guardian) {
                    return filled($guardian['full_name'] ?? null);
                })->values();

                $keptGuardianIds = [];

                foreach ($guardians as $index => $guardianData) {
                    $guardianModel = $application->guardians()->find($guardianData['id'] ?? null);

                    if (!$guardianModel) {
                        $guardianModel = $application->guardians()->make();
                    }

                        $guardianModel->full_name = $guardianData['full_name'];
                        $guardianModel->relationship = $guardianData['relationship'] ?? null;
                        $guardianModel->type = $guardianData['type'] ?? null;
                        $guardianModel->occupation = $guardianData['occupation'] ?? null;
                        $guardianModel->email = $guardianData['email'] ?? null;
                        $guardianModel->phone_primary = $guardianData['phone_primary'] ?? null;
                        $guardianModel->phone_secondary = $guardianData['phone_secondary'] ?? null;
                        $guardianModel->address_line1 = $guardianData['address_line1'] ?? null;
                        $guardianModel->address_line2 = $guardianData['address_line2'] ?? null;
                        $guardianModel->city = $guardianData['city'] ?? null;
                        $guardianModel->state = $guardianData['state'] ?? null;
                        $guardianModel->country = $guardianData['country'] ?? null;
                        $guardianModel->is_primary = isset($guardianData['is_primary']) ? (bool) $guardianData['is_primary'] : ($index === 0);
                        $guardianModel->save();

                        $keptGuardianIds[] = $guardianModel->id;
                }

                if (!empty($keptGuardianIds)) {
                    $application->guardians()->whereNotIn('id', $keptGuardianIds)->delete();
                } else {
                    $application->guardians()->delete();
                }
            }

            if ($academicHistoryEnabled) {
                $histories = collect($validated['academic_history'] ?? [])->filter(function ($history) {
                    return filled($history['institution_name'] ?? null);
                })->values();

                $keptHistoryIds = [];

                foreach ($histories as $order => $historyData) {
                    $historyModel = $application->academicHistories()->find($historyData['id'] ?? null);

                    if (!$historyModel) {
                        $historyModel = $application->academicHistories()->make();
                    }

                    $historyModel->institution_name = $historyData['institution_name'];
                    $historyModel->city = $historyData['city'] ?? null;
                    $historyModel->country = $historyData['country'] ?? null;
                    $historyModel->instruction_language = $historyData['instruction_language'] ?? null;
                    $historyModel->date_from = $historyData['date_from'] ?? null;
                    $historyModel->date_to = $historyData['date_to'] ?? null;
                    $historyModel->certificate_obtained = $historyData['certificate_obtained'] ?? null;
                    $historyModel->gce_ol_detail = $historyData['gce_ol_detail'] ?? null;
                    $historyModel->gce_al_detail = $historyData['gce_al_detail'] ?? null;
                    $historyModel->probatoire_detail = $historyData['probatoire_detail'] ?? null;
                    $historyModel->baccalaureate_detail = $historyData['baccalaureate_detail'] ?? null;
                    $historyModel->notes = $historyData['notes'] ?? null;
                    $historyModel->display_order = $order;
                    $historyModel->save();

                    $keptHistoryIds[] = $historyModel->id;
                }

                if (!empty($keptHistoryIds)) {
                    $application->academicHistories()->whereNotIn('id', $keptHistoryIds)->delete();
                } else {
                    $application->academicHistories()->delete();
                }
            }

            if ($languageEnabled) {
                $languages = collect($validated['languages'] ?? [])->filter(function ($language) {
                    return filled($language['language'] ?? null);
                })->values();

                $keptLanguageIds = [];

                foreach ($languages as $languageData) {
                    $languageModel = $application->languages()->find($languageData['id'] ?? null);

                    if (!$languageModel) {
                        $languageModel = $application->languages()->make();
                    }

                    $languageModel->language = $languageData['language'];
                    $languageModel->years_of_study = $languageData['years_of_study'] ?? null;
                    $languageModel->fluency_level = $languageData['fluency_level'] ?? null;
                    $languageModel->save();

                    $keptLanguageIds[] = $languageModel->id;
                }

                if (!empty($keptLanguageIds)) {
                    $application->languages()->whereNotIn('id', $keptLanguageIds)->delete();
                } else {
                    $application->languages()->delete();
                }
            }

            if ($documentChecklistEnabled) {
                $documentsNeedingResubmission = [];
                $newResubmissionRequests = [];
                
                foreach ($documentRequirements as $key => $documentConfig) {
                    $documentInput = $validated['documents'][$key] ?? [];
                    $documentModel = $application->documents()->firstOrNew(['document_type' => $key]);

                    $uploadedFile = $this->uploadMedia($request, "documents.$key.file", $this->path);
                    if ($uploadedFile) {
                        $this->removeExistingUpload($documentModel->file_path);
                        if (isset($documentConfig['assign_to_column'])) {
                            $this->removeExistingUpload($application->{$documentConfig['assign_to_column']});
                            $application->{$documentConfig['assign_to_column']} = $uploadedFile;
                        }
                        $documentModel->file_path = $uploadedFile;
                    } elseif (!$documentModel->file_path && isset($documentConfig['assign_to_column'])) {
                        $documentModel->file_path = $application->{$documentConfig['assign_to_column']};
                    }

                    $documentModel->is_received = isset($documentInput['is_received'])
                        ? (bool) $documentInput['is_received']
                        : (bool) $documentModel->file_path;
                    $documentModel->is_optional = !$documentConfig['required'];
                    $documentModel->notes = $documentInput['notes'] ?? $documentModel->notes;
                    
                    // Handle resubmission request
                    $requestResubmission = !empty($documentInput['needs_resubmission']);
                    $wasAlreadyRequestingResubmission = $documentModel->needs_resubmission && !$documentModel->resubmitted_at;
                    
                    if ($requestResubmission && !$wasAlreadyRequestingResubmission) {
                        // New resubmission request
                        $documentModel->needs_resubmission = true;
                        $documentModel->rejection_reason = $documentInput['rejection_reason'] ?? null;
                        $documentModel->rejected_at = now();
                        $documentModel->rejected_by = auth()->id();
                        $documentModel->resubmitted_at = null;
                        $documentsNeedingResubmission[] = $documentConfig['label'];
                        $newResubmissionRequests[] = [
                            'label' => $documentConfig['label'],
                            'reason' => $documentInput['rejection_reason'] ?? null,
                        ];
                    } elseif ($requestResubmission && $wasAlreadyRequestingResubmission) {
                        // Update existing resubmission request reason
                        $documentModel->rejection_reason = $documentInput['rejection_reason'] ?? $documentModel->rejection_reason;
                    } elseif (!$requestResubmission && $documentModel->needs_resubmission) {
                        // Clear resubmission request (admin unchecked it)
                        $documentModel->needs_resubmission = false;
                        $documentModel->rejection_reason = null;
                        $documentModel->rejected_at = null;
                        $documentModel->rejected_by = null;
                    }
                    
                    $documentModel->save();
                }

                // Update application stage if documents need resubmission
                if (!empty($documentsNeedingResubmission) && $application->stage !== 'documents_required') {
                    $application->stage = 'documents_required';
                    $application->recordStatus(
                        'documents_required',
                        __('The following documents require resubmission: ') . implode(', ', $documentsNeedingResubmission),
                        $application->status,
                        __('application_stage.documents_required'),
                        null,
                        auth()->user()->name ?? 'Admin'
                    );
                }

                $application->save();
            }

            if ($boardReviewEnabled) {
                $boardReviewInput = $validated['board_review'] ?? [];

                $hasBoardData = array_key_exists('meets_university_requirements', $boardReviewInput)
                    || array_key_exists('meets_program_requirements', $boardReviewInput)
                    || collect($boardReviewInput)
                        ->except(['meets_university_requirements', 'meets_program_requirements'])
                        ->filter(function ($value) {
                            return !is_null($value) && $value !== '';
                        })->isNotEmpty();

                if ($hasBoardData) {
                    $boardReview = $application->boardReview ?: $application->boardReview()->make();
                    $boardReview->meets_university_requirements = !empty($boardReviewInput['meets_university_requirements']);
                    $boardReview->meets_program_requirements = !empty($boardReviewInput['meets_program_requirements']);
                    $boardReview->first_choice_decision = $boardReviewInput['first_choice_decision'] ?? null;
                    $boardReview->second_choice_decision = $boardReviewInput['second_choice_decision'] ?? null;
                    $boardReview->third_choice_decision = $boardReviewInput['third_choice_decision'] ?? null;
                    $boardReview->observation = $boardReviewInput['observation'] ?? null;
                    $boardReview->rejection_reason = $boardReviewInput['rejection_reason'] ?? null;
                    $boardReview->reviewed_by = $boardReviewInput['reviewed_by'] ?? null;
                    $boardReview->reviewed_at = !empty($boardReviewInput['reviewed_at'])
                        ? Carbon::parse($boardReviewInput['reviewed_at'])
                        : null;
                    $boardReview->save();
                } elseif ($application->boardReview) {
                    $application->boardReview()->delete();
                }
            }

            DB::commit();

            // Notify applicant if new document resubmissions were requested.
            if (!empty($newResubmissionRequests)) {
                $this->sendDocumentsResubmissionMail($application, $newResubmissionRequests);
            }

            Flasher::addSuccess(__('msg_updated_successfully'), __('msg_success'));

            return redirect()->route($this->route.'.show', $application->id);
        } catch (\Throwable $e) {
            DB::rollBack();
            report($e);

            Flasher::addError(__('msg_updated_error'), __('msg_error'));

            return redirect()->back()->withInput();
        }
    }

    /**
     * Preview the application for printing/downloading.
     *
     * @param  Application  $application
     * @return \Illuminate\Http\Response
     */
    public function preview(Application $application)
    {
        $data['title'] = $this->title;
        $data['row'] = $application;
        $data['path'] = $this->path;
        $data['setting'] = Setting::where('status', '1')->first();
        $data['documentRequirements'] = ApplicationDocumentRequirements::all();
        
        // Load necessary relationships
        $application->load([
            'program',
            'batch',
            'preferredProgramFirst',
            'preferredProgramSecond',
            'preferredProgramThird',
            'guardians',
            'academicHistories',
            'languages',
            'documents',
            // 'presentProvince',
            // 'presentDistrict',
            // 'permanentProvince',
            // 'permanentDistrict',
            'religionDetail',
        ]);

        // Field Status Cache
        $fieldStatusCache = [];
        $data['fieldEnabled'] = function (string $slug) use (&$fieldStatusCache): bool {
            if (!array_key_exists($slug, $fieldStatusCache)) {
                $fieldStatusCache[$slug] = (int) optional(Field::field($slug))->status === 1;
            }

            return $fieldStatusCache[$slug];
        };

        return view($this->view.'.preview', $data);
    }

    public function storeStatusUpdate(Request $request, Application $application)
    {
        $stageOptions = array_keys(Application::stageLabelMap());

        $validated = $request->validate([
            'stage' => ['required', Rule::in($stageOptions)],
            'title' => ['nullable', 'string', 'max:255'],
            'note' => ['nullable', 'string', 'max:2000'],
            'status' => ['nullable', Rule::in([0, 1, 2])],
            'is_visible_to_applicant' => ['nullable', 'boolean'],
            'progress' => ['nullable', 'integer', 'min:0', 'max:100'],
        ]);

        $visible = array_key_exists('is_visible_to_applicant', $validated)
            ? (bool) $validated['is_visible_to_applicant']
            : true;

        $status = $validated['status'] ?? null;
        $title = $validated['title'] ?? null;
        $note = $validated['note'] ?? null;

        $application->recordStatus(
            $validated['stage'],
            $note,
            $status,
            $title,
            Auth::guard('web')->id(),
            'admin',
            $visible
        );

        if (array_key_exists('progress', $validated) && !is_null($validated['progress'])) {
            $application->progress = $validated['progress'];
            $application->save();
        }

        Flasher::addSuccess(__('Application status updated'), __('msg_success'));

        return redirect()->back();
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy(Application $application)
    {
        DB::beginTransaction();
        // Delete
        $this->deleteMultiMedia($this->path, $application, 'photo');
        $this->deleteMultiMedia($this->path, $application, 'signature');
        $this->deleteMultiMedia($this->path, $application, 'school_transcript');
        $this->deleteMultiMedia($this->path, $application, 'school_certificate');
        $this->deleteMultiMedia($this->path, $application, 'collage_transcript');
        $this->deleteMultiMedia($this->path, $application, 'collage_certificate');

        $application->delete();
        DB::commit();

        Flasher::addSuccess(__('msg_deleted_successfully'), __('msg_success'));

        return redirect()->back();
    }

    /**
     * Auto-assign fees from program semester fee configuration
     * 
     * @param StudentEnroll $enrollment
     * @return void
     */
    private function autoAssignProgramSemesterFees($enrollment)
    {
        try {
            // Only assign fees for regular (non-resit) semesters
            $semester = \App\Models\Semester::find($enrollment->semester_id);
            if ($semester && $semester->is_resit) {
                \Illuminate\Support\Facades\Log::info("Skipping fee assignment - resit semester", [
                    'enrollment_id' => $enrollment->id,
                    'semester_id' => $enrollment->semester_id
                ]);
                return;
            }

            if (!$semester) {
                \Illuminate\Support\Facades\Log::warning("Semester not found", [
                    'enrollment_id' => $enrollment->id,
                    'semester_id' => $enrollment->semester_id
                ]);
                return;
            }

            // Get the academic year from current semester
            $academicYear = $semester->year;
            
            \Illuminate\Support\Facades\Log::info("Starting year-based fee assignment", [
                'enrollment_id' => $enrollment->id,
                'current_semester' => $semester->title,
                'academic_year' => $academicYear
            ]);

            // Find ALL regular semesters for this academic year and program
            $yearSemesters = \App\Models\Semester::where('year', $academicYear)
                ->where('is_resit', 0)
                ->where('status', 1)
                ->whereHas('programs', function($query) use ($enrollment) {
                    $query->where('program_id', $enrollment->program_id);
                })
                ->orderBy('semester_type', 'asc')
                ->get();

            if ($yearSemesters->isEmpty()) {
                \Illuminate\Support\Facades\Log::info("No semesters found for year", [
                    'program_id' => $enrollment->program_id,
                    'year' => $academicYear
                ]);
                return;
            }

            \Illuminate\Support\Facades\Log::info("Found year semesters", [
                'year' => $academicYear,
                'semester_count' => $yearSemesters->count(),
                'semesters' => $yearSemesters->pluck('title', 'id')->toArray()
            ]);

            $feesCreated = 0;
            $feesSkipped = 0;
            $semestersProcessed = [];

            // Get all student's enrollments for this program to check existing fees
            $studentEnrollments = \App\Models\StudentEnroll::where('student_id', $enrollment->student_id)
                ->where('program_id', $enrollment->program_id)
                ->pluck('id')
                ->toArray();

            // Process fees for each semester of the year
            foreach ($yearSemesters as $yearSemester) {
                // Get configured fees for this semester
                $configuredFees = \App\Models\ProgramSemesterFee::where('program_id', $enrollment->program_id)
                    ->where('semester_id', $yearSemester->id)
                    ->where('status', 1)
                    ->with('feesCategory')
                    ->get();

                if ($configuredFees->isEmpty()) {
                    \Illuminate\Support\Facades\Log::info("No fees configured for semester", [
                        'semester_id' => $yearSemester->id,
                        'semester_title' => $yearSemester->title
                    ]);
                    continue;
                }

                $semesterFeesCreated = 0;

                foreach ($configuredFees as $feeConfig) {
                    // Check if this fee category was already assigned to ANY enrollment of this student for this program
                    // This prevents duplicate fees across different enrollments in the same year
                    $existingFee = \App\Models\Fee::whereIn('student_enroll_id', $studentEnrollments)
                        ->where('category_id', $feeConfig->fees_category_id)
                        ->whereHas('studentEnroll.semester', function($query) use ($yearSemester) {
                            $query->where('year', $yearSemester->year);
                        })
                        ->first();

                    if ($existingFee) {
                        \Illuminate\Support\Facades\Log::info("Fee already exists for year - skipping", [
                            'student_id' => $enrollment->student_id,
                            'category_id' => $feeConfig->fees_category_id,
                            'category_title' => $feeConfig->feesCategory->title ?? 'Unknown',
                            'year' => $yearSemester->year,
                            'existing_enrollment_id' => $existingFee->student_enroll_id
                        ]);
                        $feesSkipped++;
                        continue;
                    }

                    // Calculate due date based on configured due_month/due_day or default based on semester type
                    if ($feeConfig->due_month && $feeConfig->due_day) {
                        $year = $semester->year ?? date('Y');
                        $dueDate = \Carbon\Carbon::createFromDate($year, $feeConfig->due_month, $feeConfig->due_day)->format('Y-m-d');
                    } else {
                        // Default: First semester: 30 days, Second semester: 60 days (to give more time)
                        $daysToAdd = ($yearSemester->semester_type == 1) ? 30 : 60;
                        $dueDate = now()->addDays($daysToAdd)->format('Y-m-d');
                    }

                    // Calculate fine amount if configured
                    $fineAmount = 0;
                    if ($feeConfig->fine_amount && $feeConfig->fine_type) {
                        if ($feeConfig->fine_type == 'fixed') {
                            $fineAmount = $feeConfig->fine_amount;
                        } else if ($feeConfig->fine_type == 'percentage') {
                            $fineAmount = ($feeConfig->amount * $feeConfig->fine_amount) / 100;
                        }
                    }

                    // Create Fee record assigned to current enrollment but for the respective semester
                    $fee = new \App\Models\Fee();
                    $fee->student_enroll_id = $enrollment->id;
                    $fee->category_id = $feeConfig->fees_category_id;
                    $fee->fee_amount = $feeConfig->amount;
                    $fee->fine_amount = $fineAmount;
                    $fee->assign_date = now()->format('Y-m-d');
                    $fee->due_date = $dueDate;
                    $fee->note = "Auto-assigned for {$yearSemester->title} (Year {$academicYear})";
                    $fee->status = 0; // Unpaid
                    $fee->created_by = Auth::guard('web')->user()->id ?? null;
                    $fee->save();

                    $feesCreated++;
                    $semesterFeesCreated++;
                    
                    \Illuminate\Support\Facades\Log::info("Fee created for year semester", [
                        'fee_id' => $fee->id,
                        'enrollment_id' => $enrollment->id,
                        'semester' => $yearSemester->title,
                        'category' => $feeConfig->feesCategory->title ?? 'Unknown',
                        'amount' => $feeConfig->amount,
                        'due_date' => $dueDate
                    ]);
                }

                if ($semesterFeesCreated > 0 || $configuredFees->isNotEmpty()) {
                    $semestersProcessed[] = [
                        'semester' => $yearSemester->title,
                        'fees_created' => $semesterFeesCreated,
                        'fees_configured' => $configuredFees->count()
                    ];
                }
            }

            if ($feesCreated > 0 || $feesSkipped > 0) {
                \Illuminate\Support\Facades\Log::info("Year-based fee assignment completed", [
                    'enrollment_id' => $enrollment->id,
                    'academic_year' => $academicYear,
                    'fees_created' => $feesCreated,
                    'fees_skipped' => $feesSkipped,
                    'semesters_processed' => $semestersProcessed
                ]);
            }

        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Error auto-assigning year fees: ' . $e->getMessage(), [
                'enrollment_id' => $enrollment->id ?? 'unknown',
                'exception' => $e->getTraceAsString()
            ]);
            // Don't throw exception - fee assignment failure shouldn't block student creation
        }
    }
}
