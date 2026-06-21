<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\IdCardSetting;
use Illuminate\Http\Request;
use App\Models\Semester;
use App\Models\Session;
use App\Models\Program;
use App\Models\Section;
use App\Models\Faculty;
use App\Services\StaffAssignmentService;
use App\Models\Student;
use App\Traits\FileUploader;
use ZipArchive;
use Intervention\Image\Facades\Image;

class StudentIdCardController extends Controller
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
        $this->title = trans_choice('module_id_card', 1);
        $this->route = 'admin.id-card';
        $this->view = 'admin.id-card';
        $this->path = 'student';
        $this->access = 'student';


        $this->middleware('permission:'.$this->access.'-card');
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

        if(!empty($request->session) || $request->session != null){
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

        if(!empty($request->student_id) || $request->student_id != null){
            $data['selected_student_id'] = $student_id = $request->student_id;
        }
        else{
            $data['selected_student_id'] = Null;
        }


        // Search Filter
        $facultyQuery = Faculty::where('status', '1')->orderBy('title', 'asc');
        $data['faculties'] = StaffAssignmentService::filterFaculties($facultyQuery)->get();


        if(!empty($request->faculty) && $request->faculty != '0'){
        $data['programs'] = Program::where('faculty_id', $faculty)->where('status', '1')->orderBy('title', 'asc')->get();}

        if(!empty($request->program) && $request->program != '0'){
        $sessions = Session::where('status', 1);
        $sessions->with('programs')->whereHas('programs', function ($query) use ($program){
            $query->where('program_id', $program);
        });
        $data['sessions'] = $sessions->orderBy('id', 'desc')->get();}

        if(!empty($request->program) && $request->program != '0'){
        $semesters = Semester::where('status', 1);
        $semesters->with('programs')->whereHas('programs', function ($query) use ($program){
            $query->where('program_id', $program);
        });
        $data['semesters'] = $semesters->orderBy('id', 'asc')->get();}

        if(!empty($request->program) && $request->program != '0' && !empty($request->semester) && $request->semester != '0'){
        $sections = Section::where('status', 1);
        $sections->with('semesterPrograms')->whereHas('semesterPrograms', function ($query) use ($program, $semester){
            $query->where('program_id', $program);
            $query->where('semester_id', $semester);
        });
        $data['sections'] = $sections->orderBy('title', 'asc')->get();}

        // Always load enrollments (with optional filters)
        // Enrollment Filter - each active matricule appears once
        $enrollments = \App\Models\StudentEnroll::with(['student', 'program', 'session', 'semester', 'section'])
            ->whereHas('student', function($query) {
                $query->where('status', '1');
            })
            ->where('status', '1');
        
        if($faculty != 0 && $faculty != '0'){
            $enrollments->whereHas('program', function ($query) use ($faculty){
                $query->where('faculty_id', $faculty);
            });
        }
        if($program != 0 && $program != '0'){
            $enrollments->where('program_id', $program);
        }
        if($session != 0 && $session != '0'){
            $enrollments->where('session_id', $session);
        }
        if($semester != 0 && $semester != '0'){
            $enrollments->where('semester_id', $semester);
        }
        if($section != 0 && $section != '0'){
            $enrollments->where('section_id', $section);
        }
        if(!empty($request->student_id) && $request->student_id != '#'){
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

        $data['rows'] = $uniqueMatricules->sortBy(function($enrollment) {
            return $enrollment->matricule ?? $enrollment->student->student_id;
        })->all();


        $data['print'] = IdCardSetting::where('slug', 'student-card')->first();


        return view($this->view.'.index', $data);
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function print($id)
    {
        //
        $data['title'] = $this->title;
        $data['route'] = $this->route;
        $data['view'] = $this->view;
        $data['path'] = $this->path;

        // View - $id is now enrollment_id
        $enrollment = \App\Models\StudentEnroll::with(['student', 'program', 'session', 'semester', 'section'])
            ->where('id', $id)
            ->first();
        
        if ($enrollment) {
            $data['rows'] = collect([$enrollment]);
        } else {
            $data['rows'] = collect();
        }
        
        $data['print'] = IdCardSetting::where('slug', 'student-card')->firstOrFail();

        return view($this->view.'.print', $data);
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function multiPrint(Request $request)
    {
        //
        $data['title'] = $this->title;
        $data['route'] = $this->route;
        $data['view'] = $this->view;
        $data['path'] = $this->path;

        $enrollmentIds = explode(",",$request->students);

        // View - these are now enrollment IDs
        $data['rows'] = \App\Models\StudentEnroll::with(['student', 'program', 'session', 'semester', 'section'])
            ->whereIn('id', $enrollmentIds)
            ->orderBy('id', 'asc')
            ->get();
        $data['print'] = IdCardSetting::where('slug', 'student-card')->firstOrFail();

        return view($this->view.'.print', $data);
    }

    /**
     * Update student photo via AJAX
     *
     * @param Request $request
     * @param int $id Student ID
     * @return \Illuminate\Http\JsonResponse
     */
    public function updatePhoto(Request $request, $id)
    {
        try {
            $student = Student::findOrFail($id);
            
            if (!$request->hasFile('photo')) {
                return response()->json([
                    'success' => false,
                    'message' => __('field_photo') . ' ' . __('required_field')
                ], 422);
            }
            
            // Validate file
            $request->validate([
                'photo' => 'required|image|mimes:jpg,jpeg,png,gif,webp|max:3072'
            ]);
            
            // Use the FileUploader trait to update the image
            $student->photo = $this->updateImage($request, 'photo', $this->path, 300, 300, $student, 'photo');
            $student->save();
            
            return response()->json([
                'success' => true,
                'message' => __('alert_update'),
                'photo_url' => asset('uploads/student/' . $student->photo)
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Download single ID card as image
     *
     * @param int $id Enrollment ID
     * @return \Illuminate\Http\Response
     */
    public function download($id)
    {
        $data['title'] = $this->title;
        $data['route'] = $this->route;
        $data['view'] = $this->view;
        $data['path'] = $this->path;

        // Get enrollment
        $enrollment = \App\Models\StudentEnroll::with(['student', 'program.faculty', 'session', 'semester', 'section'])
            ->where('id', $id)
            ->first();
        
        if (!$enrollment || !$enrollment->student) {
            return redirect()->back()->with('error', __('Student not found'));
        }

        $data['rows'] = collect([$enrollment]);
        $data['print'] = IdCardSetting::where('slug', 'student-card')->firstOrFail();
        $data['download_mode'] = true;

        return view($this->view.'.download', $data);
    }

    /**
     * Download multiple ID cards as ZIP file
     *
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function downloadZip(Request $request)
    {
        $enrollmentIds = $request->input('students', '');
        
        if (empty($enrollmentIds)) {
            return response()->json([
                'success' => false,
                'message' => __('No students selected')
            ], 400);
        }

        $ids = explode(',', $enrollmentIds);
        
        // Get enrollments
        $enrollments = \App\Models\StudentEnroll::with(['student', 'program.faculty', 'session', 'semester', 'section'])
            ->whereIn('id', $ids)
            ->get();

        if ($enrollments->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => __('No valid students found')
            ], 404);
        }

        $print = IdCardSetting::where('slug', 'student-card')->first();

        // Return data for client-side ZIP generation
        $studentsData = [];
        foreach ($enrollments as $enrollment) {
            $student = $enrollment->student;
            if (!$student) continue;

            $displayMatricule = $enrollment->matricule ?? $student->student_id;
            $validityYears = $enrollment->program->validity_years ?? 4;
            $currentYear = date('Y');
            $expiryYear = $currentYear + $validityYears;

            $studentsData[] = [
                'id' => $enrollment->id,
                'matricule' => $displayMatricule,
                'name' => $student->first_name . ' ' . $student->last_name,
                'dob' => date('d/m/Y', strtotime($student->dob)),
                'gender' => $student->gender == 1 ? __('gender_male') : ($student->gender == 2 ? __('gender_female') : __('gender_other')),
                'faculty' => $enrollment->program->faculty->shortcode ?? $enrollment->program->faculty->title ?? '',
                'photo' => $student->photo && file_exists(public_path('uploads/student/'.$student->photo)) 
                    ? asset('uploads/student/'.$student->photo) 
                    : asset('dashboard/images/user.jpg'),
                'validity' => $currentYear . '-' . $expiryYear,
                'prefix' => $print->prefix ?? '',
            ];
        }

        return response()->json([
            'success' => true,
            'students' => $studentsData
        ]);
    }
}
