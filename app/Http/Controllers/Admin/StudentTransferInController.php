<?php

namespace App\Http\Controllers\Admin;

use Illuminate\Support\Facades\Crypt;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Flasher\Laravel\Facade\Flasher;
use Illuminate\Support\Facades\DB;
use App\Models\StudentRelative;
use App\Models\StudentTransfer;
use App\Models\TransferCreadit;
use App\Models\StudentEnroll;
use App\Models\EnrollSubject;
use Illuminate\Http\Request;
use App\Traits\FileUploader;
use App\Traits\SecureFileUpload;
use App\Models\StatusType;
use App\Models\Province;
use App\Models\Semester;
use App\Models\Document;
use App\Models\Session;
use App\Models\Subject;
use App\Models\Student;
use App\Models\Batch;
use App\Support\ApplicationDocumentRequirements;
use App\Models\ProgramSemesterFee;
use App\Models\Fee;
use Illuminate\Support\Facades\Log;

class StudentTransferInController extends Controller
{
    use FileUploader, SecureFileUpload;

    protected $title, $route, $view, $path, $access;

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        // Module Data
        $this->title = trans_choice('module_transfer_in', 1);
        $this->route = 'admin.student-transfer-in';
        $this->view = 'admin.student-transfer-in';
        $this->path = 'student';
        $this->access = 'student-transfer-in';


        $this->middleware('permission:'.$this->access.'-view|'.$this->access.'-create|'.$this->access.'-edit', ['only' => ['index','show']]);
        $this->middleware('permission:'.$this->access.'-create', ['only' => ['create','store']]);
        $this->middleware('permission:'.$this->access.'-edit', ['only' => ['edit','update']]);
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        //
        $data['title'] = $this->title;
        $data['route'] = $this->route;
        $data['view'] = $this->view;
        $data['path'] = $this->path;
        $data['access'] = $this->access;

        $data['rows'] = StudentTransfer::with('student.studentEnrolls')->where('status', '0')->orderBy('id', 'desc')->get();

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
        $data['title'] = $this->title;
        $data['route'] = $this->route;
        $data['view'] = $this->view;
        $data['path'] = $this->path;
        $data['access'] = $this->access;

        $data['batches'] = Batch::where('status', '1')->orderBy('id', 'desc')->get();
        $data['faculties'] = \App\Models\Faculty::where('status', '1')->orderBy('title', 'asc')->get();
        $data['sessions'] = Session::where('status', '1')->orderBy('id', 'desc')->get();
        $data['semesters'] = Semester::where('status', '1')->orderBy('id', 'asc')->get();
        $data['provinces'] = Province::where('status', '1')->orderBy('title', 'asc')->get();
        $data['subjects'] = Subject::where('status', '1')->orderBy('code', 'asc')->get();
        $data['statuses'] = StatusType::where('status', '1')->get();
        $data['religions'] = \App\Models\Religion::where('status', '1')->orderBy('title', 'asc')->get();
        $data['guardianTypes'] = ['Parent', 'Sponsor', 'Guardian'];
        $data['fluencyOptions'] = ['excellent', 'good', 'fair', 'minimal'];
        $data['documentRequirements'] = ApplicationDocumentRequirements::all();

        return view($this->view.'.create', $data);
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
            'student_id' => 'required|unique:students,student_id',
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
            'photo' => 'nullable|image',
            'signature' => 'nullable|image',
            'transfer_id' => 'required',
            'university_name' => 'required',
            'date' => 'required|date',
        ]);

        // Random Password
        $password = str_random(8);

        // Insert Data
        DB::beginTransaction();
        
        $student = new Student;
        $student->student_id = $request->student_id;
        $student->batch_id = $request->batch;
        $student->program_id = $request->program;
        $student->admission_date = $request->admission_date;

        $student->first_name = $request->first_name;
        $student->last_name = $request->last_name;
        $student->father_name = $request->father_name;
        $student->mother_name = $request->mother_name;
        $student->father_occupation = $request->father_occupation;
        $student->mother_occupation = $request->mother_occupation;
        $student->email = $request->email;
        $student->password = Hash::make($password);
        $student->password_text = Crypt::encryptString($password);

        $student->country = $request->country;
        $student->present_province = $request->present_province;
        $student->present_district = $request->present_district;
        $student->present_village = $request->present_village;
        $student->present_address = $request->present_address;
        $student->permanent_province = $request->permanent_province;
        $student->permanent_district = $request->permanent_district;
        $student->permanent_village = $request->permanent_village;
        $student->permanent_address = $request->permanent_address;

        $student->gender = $request->gender;
        $student->dob = $request->dob;
        $student->phone = $request->phone;
        $student->emergency_phone = $request->emergency_phone;

        $student->religion = $request->religion;
        $student->is_catholic_baptised = $request->boolean('is_catholic_baptised');
        $student->is_confirmed = $request->boolean('is_confirmed');
        $student->has_first_communion = $request->boolean('has_first_communion');
        $student->caste = $request->caste;
        $student->mother_tongue = $request->mother_tongue;
        $student->marital_status = $request->marital_status;
        $student->blood_group = $request->blood_group;
        $student->nationality = $request->nationality;
        $student->national_id = $request->national_id;
        $student->passport_no = $request->passport_no;

        $student->school_name = $request->school_name;
        $student->school_exam_id = $request->school_exam_id;
        $student->school_graduation_year = $request->school_graduation_year;
        $student->school_graduation_point = $request->school_graduation_point;
        $student->collage_name = $request->collage_name;
        $student->collage_exam_id = $request->collage_exam_id;
        $student->collage_graduation_year = $request->collage_graduation_year;
        $student->collage_graduation_point = $request->collage_graduation_point;
        $student->school_transcript = $this->uploadMedia($request, 'school_transcript', $this->path);
        $student->school_certificate = $this->uploadMedia($request, 'school_certificate', $this->path);
        $student->collage_transcript = $this->uploadMedia($request, 'collage_transcript', $this->path);
        $student->collage_certificate = $this->uploadMedia($request, 'collage_certificate', $this->path);
        
        // Use secure upload for photo and signature
        try {
            if($request->hasFile('photo')){
                $student->photo = $this->secureImageUpload($request, 'photo', $this->path, 300, 300, 5120);
            }
            if($request->hasFile('signature')){
                $student->signature = $this->secureImageUpload($request, 'signature', $this->path, 300, 100, 2048);
            }
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->with('error', 'File upload error: ' . $e->getMessage())->withInput();
        }
        $student->status = '1';
        $student->is_transfer = '1';
        $student->created_by = Auth::guard('web')->user()->id;
        $student->save();


        // Attach Status
        $student->statuses()->attach($request->statuses);


        // Student Relatives (Old structure - keep for backward compatibility)
        if(is_array($request->relations)){
        foreach($request->relations as $key =>$relation){
            if($relation != '' && $relation != null){
            // Insert Data
            $relation = new StudentRelative;
            $relation->student_id = $student->id;
            $relation->relation = $request->relations[$key];
            $relation->name = $request->relative_names[$key];
            $relation->occupation = $request->occupations[$key];
            // $relation->email = $request->relative_emails[$key];
            $relation->phone = $request->relative_phones[$key];
            $relation->address = $request->addresses[$key];
            $relation->save();
            }
        }}

        // Guardians (New structure from application form)
        if($request->has('guardians') && is_array($request->guardians)){
            foreach($request->guardians as $guardian){
                if(isset($guardian['full_name']) && !empty($guardian['full_name'])){
                    $relative = new StudentRelative;
                    $relative->student_id = $student->id;
                    $relative->relation = $guardian['relationship'] ?? $guardian['type'] ?? 'Guardian';
                    $relative->name = $guardian['full_name'];
                    $relative->occupation = $guardian['occupation'] ?? null;
                    $relative->email = $guardian['email'] ?? null;
                    $relative->phone = $guardian['phone_primary'] ?? null;
                    $relative->address = trim(($guardian['address_line1'] ?? '') . ' ' . ($guardian['address_line2'] ?? '') . ' ' . ($guardian['city'] ?? '') . ' ' . ($guardian['state'] ?? '') . ' ' . ($guardian['country'] ?? ''));
                    $relative->save();
                }
            }
        }

        // Academic History (Store as JSON in students table)
        if($request->has('academic_history') && is_array($request->academic_history)){
            $academicData = array_filter($request->academic_history, function($item){
                return !empty($item['institution_name']);
            });
            
            if(!empty($academicData)){
                $student->academic_history = json_encode($academicData);
                $student->save();
            }
        }

        // Languages (Store as JSON in students table)
        if($request->has('languages') && is_array($request->languages)){
            $languageData = array_filter($request->languages, function($item){
                return !empty($item['language']);
            });
            
            if(!empty($languageData)){
                $student->languages = json_encode($languageData);
                $student->save();
            }
        }


        // Student Documents (from document checklist - new structure with secure upload)
        if($request->has('documents') && is_array($request->documents)){
            foreach($request->documents as $docKey => $docData){
                // Check if this is the new structure (documents[key][file])
                if(is_array($docData) && isset($docData['file'])){
                    $fileInput = $docData['file'];
                    $note = $docData['note'] ?? null;
                    
                    if($fileInput && $fileInput->isValid()){
                        try {
                            // Create temporary request for secure upload
                            $tempRequest = new Request();
                            $tempRequest->files->set('temp_file', $fileInput);
                            
                            // Use secure upload (10MB max for documents)
                            $secureFilename = $this->secureUpload($tempRequest, 'temp_file', $this->path, 10240);
                            
                            if($secureFilename){
                                // Insert Data
                                $document = new Document;
                                $document->title = ucfirst(str_replace('_', ' ', $docKey));
                                $document->attach = $secureFilename;
                                if($note){
                                    $document->description = $note;
                                }
                                $document->save();

                                // Attach to student
                                $document->students()->attach($student->id);
                            }
                        } catch (\Exception $e) {
                            // Log error but don't fail the entire registration
                            Log::error("Document upload failed for {$docKey}: " . $e->getMessage());
                            continue;
                        }
                    }
                }
            }
        }


        // Student Enroll
        $enroll = new StudentEnroll();
        $enroll->student_id = $student->id;
        $enroll->session_id = $request->session;
        $enroll->semester_id = $request->semester;
        $enroll->program_id = $request->program;
        $enroll->section_id = $request->section;
        $enroll->created_by = Auth::guard('web')->user()->id;
        
        // Assign matricule (use student_id for transfer students initially)
        $enroll->matricule = $student->student_id;
        
        $enroll->save();


        // Assign Subjects
        $enrollSubject = EnrollSubject::where('program_id', $request->program)->where('semester_id', $request->semester)->where('section_id', $request->section)->first();

        if(isset($enrollSubject)){
            foreach($enrollSubject->subjects as $subject){
                // Attach Subject
                $enroll->subjects()->attach($subject->id);
            }
        }

        // Auto-assign fees for current and future semesters only (Transfer-In Smart Logic)
        $this->autoAssignTransferInFees($enroll);


        //Student Transfer Info
        $transfer = new StudentTransfer;
        $transfer->student_id = $student->id;
        $transfer->transfer_id = $request->transfer_id;
        $transfer->university_name = $request->university_name;
        $transfer->date = $request->date;
        $transfer->note = $request->note;
        $transfer->status = '0';
        $transfer->created_by = Auth::guard('web')->user()->id;
        $transfer->save();


        // Transfer Credits
        if(is_array($request->t_subjects)){
        foreach($request->t_subjects as $key => $t_subject)
        {
            $creadit = new TransferCreadit;
            $creadit->student_id = $student->id;
            $creadit->program_id = $request->program;
            $creadit->session_id = $request->t_sessions[$key];
            $creadit->semester_id = $request->t_semesters[$key];
            $creadit->subject_id = $request->t_subjects[$key];
            $creadit->marks = $request->marks[$key];
            $creadit->save();
        }}
        DB::commit();


        Flasher::addSuccess(__('msg_created_successfully'), __('msg_success'));

        return redirect()->route($this->route.'.index');
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        //
        $data['title'] = $this->title;
        $data['route'] = $this->route;
        $data['view'] = $this->view;
        $data['path'] = $this->path;
        $data['access'] = $this->access;


        $data['sessions'] = Session::where('status', '1')->orderBy('id', 'desc')->get();
        $data['semesters'] = Semester::where('status', '1')->orderBy('id', 'asc')->get();
        $data['subjects'] = Subject::where('status', '1')->orderBy('code', 'asc')->get();

        $data['row'] = StudentTransfer::find($id);


        return view($this->view.'.edit', $data);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        // Field Validation
        $request->validate([
            'transfer_id' => 'required',
            'university_name' => 'required',
            'date' => 'required|date',
        ]);


        DB::beginTransaction();
        // Update Data
        $transfer = StudentTransfer::find($id);
        $transfer->transfer_id = $request->transfer_id;
        $transfer->university_name = $request->university_name;
        $transfer->date = $request->date;
        $transfer->note = $request->note;
        $transfer->updated_by = Auth::guard('web')->user()->id;
        $transfer->save();


        // Transfer Credits
        if(is_array($request->t_subjects)){
        foreach($request->t_subjects as $key => $t_subject)
        {
            if(isset($request->t_creadit_id[$key])){

                $creadit = TransferCreadit::find($request->t_creadit_id[$key]);
                $creadit->program_id = $request->t_programs[$key];
                $creadit->session_id = $request->t_sessions[$key];
                $creadit->semester_id = $request->t_semesters[$key];
                $creadit->subject_id = $request->t_subjects[$key];
                $creadit->marks = $request->marks[$key];
                $creadit->save();
            }
            else{

                $creadit = new TransferCreadit;
                $creadit->student_id = $request->student_id;
                $creadit->program_id = $request->t_programs[$key];
                $creadit->session_id = $request->t_sessions[$key];
                $creadit->semester_id = $request->t_semesters[$key];
                $creadit->subject_id = $request->t_subjects[$key];
                $creadit->marks = $request->marks[$key];
                $creadit->save();
            }
        }}
        DB::commit();


        Flasher::addSuccess(__('msg_updated_successfully'), __('msg_success'));

        return redirect()->back();
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        //
    }

    /**
     * Auto-assign fees for transfer-in students (current and future semesters only)
     *
     * @param  \App\Models\StudentEnroll  $enrollment
     * @return void
     */
    private function autoAssignTransferInFees($enrollment)
    {
        try {
            // Get the semester
            $semester = Semester::find($enrollment->semester_id);
            
            // Skip if semester not found or is resit
            if (!$semester || $semester->is_resit) {
                Log::info("Skipping fee assignment - semester is resit or not found");
                return;
            }

            $academicYear = $semester->year;
            $currentSemesterType = $semester->semester_type;

            Log::info("Starting transfer-in fee assignment for Year {$academicYear}, Current Semester Type: {$currentSemesterType}");

            // Get all regular semesters for this academic year
            $yearSemesters = Semester::where('year', $academicYear)
                ->where('is_resit', 0)
                ->where('status', 1)
                ->whereHas('programs', function($query) use ($enrollment) {
                    $query->where('program_id', $enrollment->program_id);
                })
                ->orderBy('semester_type', 'asc')
                ->get();

            if ($yearSemesters->isEmpty()) {
                Log::warning("No semesters found for Year {$academicYear} and Program {$enrollment->program_id}");
                return;
            }

            Log::info("Found {$yearSemesters->count()} year semesters", [
                'semesters' => $yearSemesters->pluck('title', 'id')->toArray()
            ]);

            // Get all student's enrollments for duplicate checking
            $studentEnrollments = StudentEnroll::where('student_id', $enrollment->student_id)
                ->where('program_id', $enrollment->program_id)
                ->pluck('id')
                ->toArray();

            $feesAssigned = 0;
            $feesSkipped = 0;

            foreach ($yearSemesters as $yearSemester) {
                // SMART LOGIC: Only assign fees for current and future semesters
                // Skip past semesters (semester_type < current semester_type)
                if ($yearSemester->semester_type < $currentSemesterType) {
                    Log::info("Skipping past semester: {$yearSemester->title} (Type {$yearSemester->semester_type} < Current {$currentSemesterType})");
                    continue;
                }

                Log::info("Processing semester: {$yearSemester->title} (Type {$yearSemester->semester_type})");

                // Get configured fees for this semester
                $configuredFees = ProgramSemesterFee::where('program_id', $enrollment->program_id)
                    ->where('semester_id', $yearSemester->id)
                    ->where('status', 1)
                    ->with('feesCategory')
                    ->get();

                if ($configuredFees->isEmpty()) {
                    Log::info("No configured fees for semester {$yearSemester->id}");
                    continue;
                }

                foreach ($configuredFees as $feeConfig) {
                    // Check if this fee already exists for this student in this year
                    $existingFee = Fee::whereIn('student_enroll_id', $studentEnrollments)
                        ->where('category_id', $feeConfig->fees_category_id)
                        ->whereHas('studentEnroll.semester', function($query) use ($yearSemester) {
                            $query->where('year', $yearSemester->year);
                        })
                        ->first();

                    if ($existingFee) {
                        Log::info("Fee already exists for {$feeConfig->feesCategory->title} in Year {$academicYear} - skipping");
                        $feesSkipped++;
                        continue;
                    }

                    // Calculate due date based on configured due_days or default
                    if ($feeConfig->due_days) {
                        $daysToAdd = $feeConfig->due_days;
                    } else {
                        // Default: Current semester: 30 days, Future semester: 60 days
                        $daysToAdd = ($yearSemester->semester_type == $currentSemesterType) ? 30 : 60;
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

                    // Create the fee
                    $fee = new Fee();
                    $fee->student_enroll_id = $enrollment->id;
                    $fee->category_id = $feeConfig->fees_category_id;
                    $fee->fee_amount = $feeConfig->amount;
                    $fee->discount_amount = 0;
                    $fee->fine_amount = $fineAmount;
                    $fee->paid_amount = 0;
                    $fee->status = 0; // Unpaid
                    $fee->due_date = now()->addDays($daysToAdd)->format('Y-m-d');
                    $fee->note = "Auto-assigned for {$yearSemester->title} (Year {$academicYear}) - Transfer In";
                    $fee->created_by = Auth::guard('web')->user()->id;
                    $fee->save();

                    $feesAssigned++;
                    Log::info("Fee created for year semester {$yearSemester->title}", [
                        'fee_id' => $fee->id,
                        'category' => $feeConfig->feesCategory->title,
                        'amount' => $feeConfig->amount,
                        'due_date' => $fee->due_date
                    ]);
                }
            }

            Log::info("Transfer-in fee assignment completed", [
                'fees_assigned' => $feesAssigned,
                'fees_skipped' => $feesSkipped
            ]);

        } catch (\Exception $e) {
            Log::error("Error auto-assigning transfer-in fees: " . $e->getMessage(), [
                'enrollment_id' => $enrollment->id ?? null,
                'trace' => $e->getTraceAsString()
            ]);
        }
    }
}
