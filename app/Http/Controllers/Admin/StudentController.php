<?php

namespace App\Http\Controllers\Admin;

use Illuminate\Support\Facades\Crypt;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Flasher\Laravel\Facade\Flasher;
use Illuminate\Support\Facades\DB;
use App\Imports\StudentsImport;
use App\Models\StudentRelative;
use App\Models\IdCardSetting;
use App\Models\StudentEnroll;
use App\Models\EnrollSubject;
use Illuminate\Http\Request;
use App\Traits\FileUploader;
use App\Models\MailSetting;
use App\Mail\SendPassword;
use App\Models\StatusType;
use App\Models\Province;
use App\Models\District;
use App\Models\Semester;
use App\Models\Document;
use App\Models\Session;
use App\Models\Program;
use App\Models\Section;
use App\Models\Student;
use App\Models\Faculty;
use App\Services\StaffAssignmentService;
use App\Models\Batch;
use App\Models\Grade;
use App\Models\Fee;
use App\Models\ResitRequest;

class StudentController extends Controller
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
        $this->title = trans_choice('module_student', 1);
        $this->route = 'admin.student';
        $this->view = 'admin.student';
        $this->path = 'student';
        $this->access = 'student';


        $this->middleware('permission:'.$this->access.'-view|'.$this->access.'-create|'.$this->access.'-edit|'.$this->access.'-delete|'.$this->access.'-card', ['only' => ['index','show','status','sendPassword']]);
        $this->middleware('permission:'.$this->access.'-create', ['only' => ['create','store']]);
        $this->middleware('permission:'.$this->access.'-edit', ['only' => ['edit','update','status']]);
        $this->middleware('permission:'.$this->access.'-delete', ['only' => ['destroy']]);
        $this->middleware('permission:'.$this->access.'-password-print', ['only' => ['printPassword','multiPrintPassword']]);
        $this->middleware('permission:'.$this->access.'-password-change', ['only' => ['passwordChange']]);
        $this->middleware('permission:'.$this->access.'-card', ['only' => ['index','card']]);
        $this->middleware('permission:'.$this->access.'-import', ['only' => ['index','import','importStore']]);
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

    $data['programs'] = collect();
    $data['sessions'] = collect();
    $data['semesters'] = collect();
    $data['sections'] = collect();
    $data['semesterOptions'] = [];
    $data['selected_semester_year'] = $selected_semester_year = $request->semester_year ?? '0';


        if(!empty($request->faculty) || $request->faculty != null){
            $data['selected_faculty'] = $faculty = $request->faculty;
        }
        else{
            $data['selected_faculty'] = $faculty = '0';
        }

        if(!empty($request->program) || $request->program != null){
            $data['selected_program'] = $program = $request->program;
        }
        else{
            $data['selected_program'] = $program = '0';
        }

        // Get current session for default loading
        $currentSession = Session::where('current', 1)->where('status', 1)->first();
        $data['current_session_id'] = $currentSession ? $currentSession->id : null;
        
        // Check if this is the first load (no filters applied)
        $isFirstLoad = !$request->has('faculty') && !$request->has('program') && !$request->has('session') && 
                       !$request->has('semester') && !$request->has('section') && !$request->has('status') && 
                       !$request->has('student_id') && !$request->has('enroll_status') && !$request->has('filtered');
        
        if($isFirstLoad && $currentSession){
            // Auto-apply current session filter on first load
            $data['selected_session'] = $session = $currentSession->id;
            $data['auto_loaded'] = true;
        }
        elseif(!empty($request->session) || $request->session != null){
            $data['selected_session'] = $session = $request->session;
        }
        else{
            $data['selected_session'] = $session = '0';
        }

        if(!empty($request->semester) || $request->semester != null){
            $data['selected_semester'] = $semester = $request->semester;
        }
        else{
            $data['selected_semester'] = $semester = '0';
        }

        if(!empty($request->section) || $request->section != null){
            $data['selected_section'] = $section = $request->section;
        }
        else{
            $data['selected_section'] = $section = '0';
        }

        if(!empty($request->status) || $request->status != null){
            $data['selected_status'] = $status = $request->status;
        }
        else{
            $data['selected_status'] = '0';
        }

        // Enrollment Status filter (1 = active, 0 = inactive, 'all' = both)
        if($request->has('enroll_status') && $request->enroll_status !== null && $request->enroll_status !== ''){
            $data['selected_enroll_status'] = $enroll_status = $request->enroll_status;
        }
        else{
            $data['selected_enroll_status'] = $enroll_status = '1'; // Default to active enrollments
        }

        if(!empty($request->student_id) || $request->student_id != null){
            $data['selected_student_id'] = $student_id = $request->student_id;
        }
        else{
            $data['selected_student_id'] = Null;
        }


        // Search Filter
        $facultyQuery = Faculty::where('status', '1')->orderBy('title', 'asc');
        $data['faculties'] = StaffAssignmentService::filterFaculties($facultyQuery)->get();
        $data['statuses'] = StatusType::where('status', '1')->orderBy('title', 'asc')->get();

        // On first load, populate sessions with all active sessions (to show current session)
        if($isFirstLoad){
            $data['sessions'] = Session::where('status', 1)->orderBy('id', 'desc')->get();
        }

        if(!empty($request->faculty) && $request->faculty != '0'){
        $data['programs'] = Program::where('faculty_id', $faculty)->where('status', '1')->orderBy('title', 'asc')->get();}

        if(!empty($request->program) && $request->program != '0'){
        $sessions = Session::where('status', 1);
        $sessions->with('programs')->whereHas('programs', function ($query) use ($program){
            $query->where('program_id', $program);
        });
        $data['sessions'] = $sessions->orderBy('id', 'desc')->get();

        $semesters = Semester::where('status', 1);
        $semesters->with('programs')->whereHas('programs', function ($query) use ($program){
            $query->where('program_id', $program);
        });
        $semesterCollection = $semesters->orderBy('year', 'asc')->orderBy('id', 'asc')->get();
        $data['semesters'] = $semesterCollection;
        $data['semesterOptions'] = $semesterCollection
            ->filter(function ($row) {
                return !is_null($row->year);
            })
            ->groupBy('year')
            ->sortKeys()
            ->map(function ($items) {
                return $items->map(function ($semesterItem) {
                    return [
                        'id' => $semesterItem->id,
                        'title' => $semesterItem->title,
                    ];
                })->values();
            })
            ->toArray();
        }

        if(!empty($request->program) && $request->program != '0' && !empty($request->semester) && $request->semester != '0'){
        $sections = Section::where('status', 1);
        $sections->with('semesterPrograms')->whereHas('semesterPrograms', function ($query) use ($program, $semester){
            $query->where('program_id', $program);
            $query->where('semester_id', $semester);
        });
        $data['sections'] = $sections->orderBy('title', 'asc')->get();}


        if($isFirstLoad || isset($request->faculty) || isset($request->program) || isset($request->session) || isset($request->semester) || isset($request->section) || isset($request->status) || isset($request->student_id) || isset($request->enroll_status) || isset($request->filtered)){
            // Enrollment Filter - each active matricule appears once
            $enrollments = StudentEnroll::with(['student', 'student.statuses', 'program', 'session', 'semester', 'section'])
                ->whereHas('student', function($query) {
                    $query->where('status', '1');
                });
            
            // Apply enrollment status filter
            if($enroll_status !== 'all'){
                $enrollments->where('status', $enroll_status);
            }
            
            if($faculty != 0){
                $enrollments->whereHas('program', function ($query) use ($faculty){
                    $query->where('faculty_id', $faculty);
                });
            }
            if($program != 0){
                $enrollments->where('program_id', $program);
            }
            if($session != 0){
                $enrollments->where('session_id', $session);
            }
            if($semester != 0){
                $enrollments->where('semester_id', $semester);
            }
            if($section != 0){
                $enrollments->where('section_id', $section);
            }
            if(!empty($request->status)){
                $enrollments->whereHas('student.statuses', function ($query) use ($status){
                    $query->where('status_type_id', $status);
                });
            }
            if(!empty($request->student_id)){
                $enrollments->where(function($query) use ($student_id) {
                    $query->where('matricule', 'LIKE', '%'.$student_id.'%')
                          ->orWhereHas('student', function($q) use ($student_id) {
                              $q->where('student_id', 'LIKE', '%'.$student_id.'%');
                          });
                });
            }
            
            $allEnrollments = $enrollments->orderBy('id', 'desc')->get();
            
            // Group by unique matricule - students with multiple active programs appear multiple times
            $uniqueMatricules = $allEnrollments->groupBy('matricule')->map(function($group) {
                return $group->sortByDesc('id')->first();
            })->values();

            // Array Sorting
            $data['rows'] = $uniqueMatricules->sortByDesc(function($enrollment){
               return $enrollment->matricule ?? $enrollment->student->student_id;
            })->all();
        }


        $data['print'] = IdCardSetting::where('slug', 'student-card')->first();


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


        $data['batches'] = Batch::where('status', '1')->orderBy('id', 'desc')->get();
        $facultyQuery = Faculty::where('status', '1')->orderBy('title', 'asc');
        $data['faculties'] = StaffAssignmentService::filterFaculties($facultyQuery)->get();
        $data['statuses'] = StatusType::where('status', '1')->orderBy('title', 'asc')->get();
        // $data['provinces'] = Province::where('status', '1')->orderBy('title', 'asc')->get();

        return view($this->view.'.create', $data);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    /**
     * Generate Student ID
     * Format: PAX + Last 2 digits of Batch + Faculty Shortcode + Sequential Number
     * Example: PAX25BF001, PAX26MGT001
     */
    public function generateId(Request $request)
    {
        try {
            $request->validate([
                'faculty_id' => 'required|exists:faculties,id',
                'batch_id' => 'required|exists:batches,id',
            ]);

            $studentId = Student::generateStudentId($request->faculty_id, $request->batch_id, $request->program_id);

            return response()->json([
                'success' => true,
                'student_id' => $studentId,
                'message' => 'Student ID generated successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }

    public function store(Request $request)
    {
        // Field Validation
        $request->validate([
            'student_id' => 'nullable|unique:students,student_id',
            'faculty' => 'required|exists:faculties,id',
            'batch' => 'required|exists:batches,id',
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

        // Auto-generate student_id if empty
        $studentId = $request->student_id;
        if (empty($studentId)) {
            try {
                $studentId = Student::generateStudentId($request->faculty, $request->batch, $request->program);
            } catch (\Exception $e) {
                Flasher::addError('Error generating student ID: ' . $e->getMessage());
                return redirect()->back()->withInput();
            }
        }

        // Random Password
        $password = str_random(8);

        // Insert Data
        try{
            DB::beginTransaction();

            $student = new Student;
            $student->student_id = $studentId;
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
            $student->photo = $this->uploadImage($request, 'photo', $this->path, 300, 300);
            $student->signature = $this->uploadImage($request, 'signature', $this->path, 300, 100);
            $student->status = '1';
            $student->created_by = Auth::guard('web')->user()->id;
            $student->save();


            // Attach Status
            $student->statuses()->attach($request->statuses);


            // Student Relatives
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
                $document->students()->attach($student->id);

                }
            }}


            // Student Enroll
            $enroll = new StudentEnroll();
            $enroll->student_id = $student->id;
            $enroll->session_id = $request->session;
            $enroll->semester_id = $request->semester;
            $enroll->program_id = $request->program;
            $enroll->section_id = $request->section;
            $enroll->created_by = Auth::guard('web')->user()->id;
            
            // Generate enrollment-specific matricule
            // For first enrollment, use student_id. For subsequent enrollments (different levels), generate new matricule
            try {
                $existingEnrollments = StudentEnroll::where('student_id', $student->id)->count();
                
                if ($existingEnrollments == 0) {
                    // First enrollment - use the student_id as matricule
                    $enroll->matricule = $student->student_id;
                } else {
                    // Subsequent enrollment (e.g., Masters after Bachelor) - generate new matricule
                    $enroll->matricule = Student::generateEnrollmentMatricule($student->id, $request->program, $request->batch);
                }
            } catch (\Exception $e) {
                Log::error('Matricule generation error: ' . $e->getMessage());
                // Fall back to student_id if generation fails
                $enroll->matricule = $student->student_id;
            }
            
            $enroll->save();


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

            DB::commit();


            Flasher::addSuccess(__('msg_created_successfully'), __('msg_success'));

            return redirect()->route($this->route.'.index');
        }
        catch(\Exception $e){

            Flasher::addError(__('msg_created_error'), __('msg_error'));

            return redirect()->back();
        }
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show(Student $student)
    {
        //
        $data['title'] = $this->title;
        $data['route'] = $this->route;
        $data['view'] = $this->view;
        $data['path'] = $this->path;
        $data['access'] = $this->access;

        // Load student with all necessary relationships
        $student->load([
            'batch',
            'program',
            // 'presentProvince',
            // 'presentDistrict',
            // 'permanentProvince',
            // 'permanentDistrict',
            'transport.transportRoute',
            'studentEnrolls.session',
            'studentEnrolls.semester',
            'studentEnrolls.section',
            'studentEnrolls.subjects',
            'studentEnrolls.subjectMarks.subject'
        ]);

        $data['row'] = $student;

        $data['fees'] = Fee::with('studentEnroll')->whereHas('studentEnroll', function ($query) use ($student){
                    $query->where('student_id', $student->id);
                })
                ->orderBy('id', 'desc')->get();

        $data['grades'] = Grade::where('status', '1')->orderBy('min_mark', 'desc')->get();

        // Get selected enrollment from request (for multi-enrollment students)
        $selectedEnrollmentId = request()->get('enrollment_id');
        $selectedProgramId = null;
        $selectedMatricule = null;
        if ($selectedEnrollmentId) {
            $selectedEnrollment = $student->studentEnrolls->firstWhere('id', $selectedEnrollmentId);
            if ($selectedEnrollment) {
                $selectedProgramId = $selectedEnrollment->program_id;
                $selectedMatricule = $selectedEnrollment->matricule;
            }
        }
        
        // If no enrollment selected, use the first enrollment's program
        if (!$selectedProgramId && $student->studentEnrolls->isNotEmpty()) {
            $latestEnrollment = $student->studentEnrolls->sortByDesc('id')->first();
            $selectedProgramId = $latestEnrollment->program_id;
            $selectedMatricule = $latestEnrollment->matricule;
        }

        // Prepare GPA trend data for selected enrollment only
        $data['gpa_trend'] = $this->prepareGPATrendData($student, $data['grades'], $selectedProgramId, $selectedMatricule);

        // Subject IDs with an active (in-progress) resit workflow for this student/program/matricule.
        // The transcript carry-over count excludes these so the page agrees with the academic-standings page.
        $activeResitSubjectIds = [];
        if ($selectedProgramId && $selectedMatricule) {
            $programEnrollIds = $student->studentEnrolls
                ->where('program_id', $selectedProgramId)
                ->where('matricule', $selectedMatricule)
                ->pluck('id');
            if ($programEnrollIds->isNotEmpty()) {
                $activeResitSubjectIds = ResitRequest::whereIn('student_enroll_id', $programEnrollIds)
                    ->whereIn('workflow_state', [
                        ResitRequest::STATE_REQUESTED,
                        ResitRequest::STATE_AWAITING_PAYMENT,
                        ResitRequest::STATE_FINANCE_REVIEW,
                        ResitRequest::STATE_APPROVED,
                        ResitRequest::STATE_SCHEDULED,
                    ])
                    ->pluck('subject_id')
                    ->all();
            }
        }
        $data['active_resit_subject_ids'] = $activeResitSubjectIds;

        return view($this->view.'.show', $data);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit(Student $student)
    {
        //
        $data['title'] = $this->title;
        $data['route'] = $this->route;
        $data['view'] = $this->view;
        $data['path'] = $this->path;


        /*
        $data['provinces'] = Province::where('status', '1')
                            ->orderBy('title', 'asc')->get();
        $data['present_districts'] = District::where('status', '1')
                            ->where('province_id', $student->present_province)
                            ->orderBy('title', 'asc')->get();
        $data['permanent_districts'] = District::where('status', '1')
                            ->where('province_id', $student->permanent_province)
                            ->orderBy('title', 'asc')->get();
        */
        $data['statuses'] = StatusType::where('status', '1')->get();
        $data['batches'] = Batch::where('status', '1')->orderBy('id', 'desc')->get();

        $data['row'] = $student;


        return view($this->view.'.edit', $data);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, Student $student)
    {
        // Field Validation
        $request->validate([
            'student_id' => 'required|unique:students,student_id,'.$student->id,
            'batch' => 'required',
            'first_name' => 'required',
            'last_name' => 'required',
            'email' => 'required|email|unique:students,email,'.$student->id,
            'phone' => 'required',
            'gender' => 'required',
            'dob' => 'required|date',
            'admission_date' => 'required|date',
            'photo' => 'nullable|image',
            'signature' => 'nullable|image',
        ]);

        // Update Data
        try{
            DB::beginTransaction();

            $student->student_id = $request->student_id;
            $student->batch_id = $request->batch;
            $student->admission_date = $request->admission_date;

            $student->first_name = $request->first_name;
            $student->last_name = $request->last_name;
            $student->father_name = $request->father_name;
            $student->mother_name = $request->mother_name;
            $student->father_occupation = $request->father_occupation;
            $student->mother_occupation = $request->mother_occupation;
            $student->email = $request->email;

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
            $student->school_transcript = $this->updateMultiMedia($request, 'school_transcript', $this->path, $student, 'school_transcript');
            $student->school_certificate = $this->updateMultiMedia($request, 'school_certificate', $this->path, $student, 'school_certificate');
            $student->collage_transcript = $this->updateMultiMedia($request, 'collage_transcript', $this->path, $student, 'collage_transcript');
            $student->collage_certificate = $this->updateMultiMedia($request, 'collage_certificate', $this->path, $student, 'collage_certificate');
            $student->photo = $this->updateImage($request, 'photo', $this->path, 300, 300, $student, 'photo');
            $student->signature = $this->updateImage($request, 'signature', $this->path, 300, 100, $student, 'signature');
            $student->updated_by = Auth::guard('web')->user()->id;
            $student->save();


            // Update Status
            $student->statuses()->sync($request->statuses);


            // Remove Old Relatives
            StudentRelative::where('student_id', $student->id)->delete();
            // Student Relatives
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
                $document->students()->sync($student->id);

                }
            }}

            DB::commit();


            Flasher::addSuccess(__('msg_updated_successfully'), __('msg_success'));

            return redirect()->back();
        }
        catch(\Exception $e){

            Flasher::addError(__('msg_updated_error'), __('msg_error'));

            return redirect()->back();
        }
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy(Student $student)
    {
        DB::beginTransaction();
        // Delete
        $this->deleteMultiMedia($this->path, $student, 'photo');
        $this->deleteMultiMedia($this->path, $student, 'signature');
        $this->deleteMultiMedia($this->path, $student, 'school_transcript');
        $this->deleteMultiMedia($this->path, $student, 'school_certificate');
        $this->deleteMultiMedia($this->path, $student, 'collage_transcript');
        $this->deleteMultiMedia($this->path, $student, 'collage_certificate');

        // Detach
        $student->relatives()->delete();
        $student->statuses()->detach();
        $student->documents()->detach();
        $student->contents()->detach();
        $student->notices()->detach();
        $student->member()->delete();
        $student->hostelRoom()->delete();
        $student->transport()->delete();
        $student->notes()->delete();

        $student->delete();
        DB::commit();

        Flasher::addSuccess(__('msg_deleted_successfully'), __('msg_success'));

        return redirect()->back();
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function status($id)
    {
        // Set Status
        $user = Student::where('id', $id)->firstOrFail();

        if($user->login == 1){
            $user->login = 0;
            $user->save();
        }
        else {
            $user->login = 1;
            $user->save();
        }

        Flasher::addSuccess(__('msg_updated_successfully'), __('msg_success'));

        return redirect()->back();
    }

    /**
     * Toggle the enrollment status (active/inactive).
     *
     * @param  int  $enrollment
     * @return \Illuminate\Http\Response
     */
    public function toggleEnrollStatus($enrollment)
    {
        $enroll = StudentEnroll::with(['student', 'program', 'session', 'semester', 'section'])
            ->where('id', $enrollment)
            ->firstOrFail();

        // Toggle status
        $enroll->status = $enroll->status == 1 ? 0 : 1;
        $enroll->save();

        $statusText = $enroll->status == 1 ? __('status_active') : __('status_inactive');
        
        Flasher::addSuccess(__('field_enrollment') . ' ' . __('field_status') . ' ' . __('msg_updated_successfully') . ' (' . $statusText . ')', __('msg_success'));

        return redirect()->back();
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function sendPassword($id)
    {
        //
        $user = Student::where('id', $id)->firstOrFail();

        $mail = MailSetting::where('status', '1')->first();

        if(isset($mail->sender_email) && isset($mail->sender_name)){

            $sendTo = $user->email;
            $receiver = $user->first_name.' '.$user->last_name;

            // Passing data to email template
            $data['name'] = $user->first_name.' '.$user->first_name;
            $data['student_id'] = $user->student_id;
            $data['email'] = $user->email;
            $data['password'] = Crypt::decryptString($user->password_text);

            // Mail Information
            $data['subject'] = __('msg_your_login_credentials');
            $data['from'] = $mail->sender_email;
            $data['sender'] = $mail->sender_name;


            // Send Mail
            Mail::to($sendTo, $receiver)->send(new SendPassword($data));


            Flasher::addSuccess(__('msg_sent_successfully'), __('msg_success'));
        }
        else{
            Flasher::addSuccess(__('msg_receiver_not_found'), __('msg_success'));
        }

        return redirect()->back();
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function printPassword($id)
    {
        //
        $data['title'] = $this->title;
        $data['route'] = $this->route;
        $data['view'] = $this->view;

        $data['rows'] = Student::where('id', $id)->get();

        return view($this->view.'.password-print', $data);
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function multiPrintPassword(Request $request)
    {
        //
        $data['title'] = $this->title;
        $data['route'] = $this->route;
        $data['view'] = $this->view;

        $students = explode(",",$request->students);

        // View
        $data['rows'] = Student::whereIn('id', $students)->orderBy('id', 'asc')->get();

        return view($this->view.'.password-print', $data);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function passwordChange(Request $request)
    {
        // Field Validation
        $request->validate([
            'student_id' => 'required',
            'password' => 'required|confirmed|min:8',
        ]);

        // Update Data
        $student = Student::findOrFail($request->student_id);
        $student->password = Hash::make($request->password);
        $student->password_text = Crypt::encryptString($request->password);
        $student->save();


        Flasher::addSuccess(__('msg_updated_successfully'), __('msg_success'));

        return redirect()->back();
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function card($id)
    {
        //
        $data['title']     = $this->title;
        $data['route']     = $this->route;
        $data['view']      = $this->view;
        $data['path']      = $this->path;

        // Get enrollments for this student instead of student directly
        // The print view expects StudentEnroll objects with ->student relationship
        $data['rows'] = StudentEnroll::with(['student', 'program', 'session', 'semester', 'section'])
            ->whereHas('student', function($query) use ($id) {
                $query->where('id', $id);
            })
            ->where('status', '1') // Only active enrollments
            ->orderBy('id', 'desc')
            ->get();

        $data['print'] = IdCardSetting::where('slug', 'student-card')->firstOrFail();

        return view('admin.id-card.print', $data);
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function import(Request $request)
    {
        //
        $data['title']     = $this->title;
        $data['route']     = $this->route;
        $data['view']      = $this->view;
        $data['access']    = $this->access;

        //
        $data['batches'] = Batch::where('status', '1')
                        ->orderBy('id', 'desc')->get();

        return view($this->view.'.import', $data);
    }

    /**
    * @return \Illuminate\Support\Collection
    */
    public function importStore(Request $request)
    {
        // Field Validation
        $request->validate([
            'batch' => 'required',
            'program' => 'required',
            'session' => 'required',
            'semester' => 'required',
            'section' => 'required',
            'import' => 'required|file|mimes:xlsx',
        ]);


        // Passing Data
        $data['batch'] = $request->batch;
        $data['program'] = $request->program;
        $data['session'] = $request->session;
        $data['semester'] = $request->semester;
        $data['section'] = $request->section;

        Excel::import(new StudentsImport($data), $request->file('import'));


        Flasher::addSuccess(__('msg_updated_successfully'), __('msg_success'));

        return redirect()->back();
    }

    /**
     * Prepare GPA trend data for visualization
     *
     * @param  \App\Models\Student  $student
     * @param  \Illuminate\Support\Collection  $grades
     * @param  int|null  $programId
     * @param  string|null  $matricule
     * @return array
     */
    private function prepareGPATrendData($student, $grades, $programId = null, $matricule = null)
    {
        $trend_data = [];
        $cumulative_quality_points = 0;
        $cumulative_credits_attempted = 0; // For CGPA calculation (all courses)
        $cumulative_credits_earned = 0; // For display (only passed courses)

        // Filter enrollments by program and matricule if specified
        $enrollments = $student->studentEnrolls;
        if ($programId) {
            $enrollments = $enrollments->where('program_id', $programId);
        }
        if ($matricule) {
            $enrollments = $enrollments->where('matricule', $matricule);
        }

        // Get unique semester combinations
        $semester_items = [];
        $semester_keys = [];

        foreach ($enrollments as $enroll) {
            if (isset($enroll->session) && isset($enroll->semester)) {
                $semester_key = $enroll->session->title . '|' . $enroll->semester->title;
                if (!in_array($semester_key, $semester_keys)) {
                    $semester_items[] = [
                        'session' => $enroll->session->title,
                        'semester' => $enroll->semester->title,
                        'key' => $semester_key
                    ];
                    $semester_keys[] = $semester_key;
                }
            }
        }

        // Calculate GPA for each semester
        foreach ($semester_items as $semester_item) {
            $semester_credits_attempted = 0; // All courses for CGPA
            $semester_credits_earned = 0; // Only passed courses
            $semester_quality_points = 0;

            foreach ($enrollments as $enroll) {
                if (isset($enroll->semester) && isset($enroll->session) &&
                    $semester_item['semester'] == $enroll->semester->title &&
                    $semester_item['session'] == $enroll->session->title) {

                    if (isset($enroll->subjectMarks)) {
                        foreach ($enroll->subjectMarks as $mark) {
                            // Check if marks are published and visible using model accessor
                            // (handles is_published_override, workflow_state, and publish date/time)
                            $isVisible = $mark->is_visible_to_student;

                            if ($isVisible && isset($mark->subject)) {
                                $marks_per = round($mark->total_marks);
                                $credit_hour = (float) $mark->subject->credit_hour;

                                foreach ($grades as $grade) {
                                    if ($marks_per >= $grade->min_mark && $marks_per <= $grade->max_mark) {
                                        $grade_point = (float) $grade->point;
                                        $quality_points = $grade_point * $credit_hour;

                                        // Count all credits for CGPA calculation
                                        $semester_credits_attempted += $credit_hour;
                                        $semester_quality_points += $quality_points;
                                        
                                        // Only count credits earned for PASSED courses (>=50%)
                                        if ($marks_per >= 50) {
                                            $semester_credits_earned += $credit_hour;
                                        }
                                        break;
                                    }
                                }
                            }
                        }
                    }
                }
            }

            // Calculate semester GPA (uses attempted credits)
            $semester_gpa = $semester_credits_attempted > 0 ? $semester_quality_points / $semester_credits_attempted : 0;

            // Update cumulative values
            $cumulative_credits_attempted += $semester_credits_attempted;
            $cumulative_credits_earned += $semester_credits_earned;
            $cumulative_quality_points += $semester_quality_points;
            $cumulative_gpa = $cumulative_credits_attempted > 0 ? $cumulative_quality_points / $cumulative_credits_attempted : 0;

            // Store data point (only if semester has credits to avoid empty semesters in chart)
            if ($semester_credits_attempted > 0) {
                $trend_data[] = [
                    'label' => $semester_item['session'] . ' - ' . $semester_item['semester'],
                    'semester_gpa' => round($semester_gpa, 2),
                    'cumulative_gpa' => round($cumulative_gpa, 2),
                    'credits' => round($semester_credits_earned, 2), // Only passed courses
                    'cumulative_credits' => round($cumulative_credits_earned, 2) // Total passed courses
                ];
            }
        }

        return $trend_data;
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

                    // Calculate due date based on configured due_days or default based on semester type
                    if ($feeConfig->due_days) {
                        // Use configured due days
                        $daysToAdd = $feeConfig->due_days;
                    } else {
                        // Default: First semester: 30 days, Second semester: 60 days (to give more time)
                        $daysToAdd = ($yearSemester->semester_type == 1) ? 30 : 60;
                    }
                    $dueDate = now()->addDays($daysToAdd)->format('Y-m-d');

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

    /**
     * Impersonate a student
     *
     * @param int $id
     * @return \Illuminate\Http\Response
     */
    public function impersonate($id)
    {
        // Require student-edit permission to impersonate, or custom permission if created later
        if (!Auth::guard('web')->user()->can('student-edit')) {
            abort(403, 'Unauthorized action.');
        }

        $student = Student::findOrFail($id);

        // Store the original admin ID in the session
        session()->put('impersonate_admin_id', Auth::guard('web')->user()->id);

        // Log the admin into the student guard
        Auth::guard('student')->loginUsingId($student->id);

        // Redirect to the student dashboard
        return redirect()->route('student.dashboard.index');
    }
}
