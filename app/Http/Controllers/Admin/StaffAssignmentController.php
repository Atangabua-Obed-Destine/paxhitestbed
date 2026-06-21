<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\StaffAssignment;
use App\User;
use App\Models\Faculty;
use App\Models\Program;
use App\Models\Subject;
use App\Models\ClassRoutine;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class StaffAssignmentController extends Controller
{
    /**
     * Constructor
     */
    public function __construct()
    {
        // Permission middleware
        $this->middleware('permission:staff-assignment-index', ['only' => ['index']]);
        $this->middleware('permission:staff-assignment-create', ['only' => ['create', 'store', 'getPrograms', 'getCourses']]);
        $this->middleware('permission:staff-assignment-edit', ['only' => ['edit', 'update', 'getPrograms', 'getCourses']]);
        $this->middleware('permission:staff-assignment-delete', ['only' => ['destroy']]);
    }
    
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $data['title'] = __('Staff Assignments');
        $data['route'] = 'admin.staff-assignment';
        
        // Get all staff with their assignments
        $data['assignments'] = User::with(['staffAssignments.assignable', 'staffAssignments.creator'])
            ->whereHas('roles', function($query) {
                // Exclude students and applicants
                $query->whereNotIn('name', ['Student', 'Applicant']);
            })
            ->whereHas('staffAssignments')
            ->paginate(20);
        
        return view('admin.staff-assignment.index', $data);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $data['title'] = __('Assign Staff');
        $data['route'] = 'admin.staff-assignment';
        
        // Get all staff members (exclude students and applicants)
        $data['staff'] = User::with('roles')
            ->whereHas('roles', function($query) {
                $query->whereNotIn('name', ['Student', 'Applicant']);
            })
            ->where('status', 1)
            ->orderBy('first_name')
            ->get();
        
        $data['faculties'] = Faculty::where('status', 1)->orderBy('title')->get();
        
        return view('admin.staff-assignment.create', $data);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
            'assignments' => 'required|array|min:1',
            'assignments.*.faculty_id' => 'required|exists:faculties,id',
            'assignments.*.program_ids' => 'nullable|array',
            'assignments.*.program_ids.*' => 'exists:programs,id',
            'assignments.*.course_ids' => 'nullable|array',
            'assignments.*.course_ids.*' => 'exists:subjects,id',
        ]);
        
        DB::beginTransaction();
        try {
            $userId = $request->user_id;
            $createdBy = Auth::id();
            $createdAssignments = [];
            
            // Check for class routine conflicts
            $hasClassRoutine = ClassRoutine::whereHas('teacher', function($query) use ($userId) {
                $query->where('id', $userId);
            })->exists();
            
            foreach ($request->assignments as $assignment) {
                $facultyId = $assignment['faculty_id'];
                $programIds = $assignment['program_ids'] ?? [];
                $courseIds = $assignment['course_ids'] ?? [];
                
                // Assign faculty
                $facultyAssignment = StaffAssignment::updateOrCreate([
                    'user_id' => $userId,
                    'assignable_type' => Faculty::class,
                    'assignable_id' => $facultyId,
                ], [
                    'created_by' => $createdBy,
                ]);
                $createdAssignments[] = 'Faculty: ' . $facultyAssignment->assignable->title;
                
                // Assign programs
                foreach ($programIds as $programId) {
                    $programAssignment = StaffAssignment::updateOrCreate([
                        'user_id' => $userId,
                        'assignable_type' => Program::class,
                        'assignable_id' => $programId,
                    ], [
                        'created_by' => $createdBy,
                    ]);
                    $createdAssignments[] = 'Program: ' . $programAssignment->assignable->title;
                }
                
                // Assign courses
                foreach ($courseIds as $courseId) {
                    $courseAssignment = StaffAssignment::updateOrCreate([
                        'user_id' => $userId,
                        'assignable_type' => Subject::class,
                        'assignable_id' => $courseId,
                    ], [
                        'created_by' => $createdBy,
                    ]);
                    $createdAssignments[] = 'Course: ' . $courseAssignment->assignable->title;
                }
            }
            
            DB::commit();
            
            $message = __('Staff assignments created successfully!');
            if ($hasClassRoutine) {
                $message .= ' ' . __('Note: This staff has existing class routines which will now be restricted to assigned areas only.');
            }
            
            return redirect()->route('admin.staff-assignment.index')->with('success', $message);
            
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', __('Error creating assignments: ') . $e->getMessage())->withInput();
        }
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $userId)
    {
        $data['title'] = __('Edit Staff Assignment');
        $data['route'] = 'admin.staff-assignment';
        
        $data['user'] = User::with('staffAssignments.assignable')->findOrFail($userId);
        
        // Get current assignments grouped by faculty
        $assignments = StaffAssignment::where('user_id', $userId)->get();
        $data['currentAssignments'] = [
            'faculties' => $assignments->where('assignable_type', Faculty::class)->pluck('assignable_id')->toArray(),
            'programs' => $assignments->where('assignable_type', Program::class)->pluck('assignable_id')->toArray(),
            'courses' => $assignments->where('assignable_type', Subject::class)->pluck('assignable_id')->toArray(),
        ];
        
        $data['faculties'] = Faculty::where('status', 1)->orderBy('title')->get();
        
        // Check for class routine
        $data['hasClassRoutine'] = ClassRoutine::whereHas('teacher', function($query) use ($userId) {
            $query->where('id', $userId);
        })->exists();
        
        return view('admin.staff-assignment.edit', $data);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $userId)
    {
        $request->validate([
            'assignments' => 'required|array|min:1',
            'assignments.*.faculty_id' => 'required|exists:faculties,id',
            'assignments.*.program_ids' => 'nullable|array',
            'assignments.*.program_ids.*' => 'exists:programs,id',
            'assignments.*.course_ids' => 'nullable|array',
            'assignments.*.course_ids.*' => 'exists:subjects,id',
            'overwrite_routine' => 'nullable|boolean',
        ]);
        
        DB::beginTransaction();
        try {
            $createdBy = Auth::id();
            
            // Check for class routine conflicts
            $hasClassRoutine = ClassRoutine::whereHas('teacher', function($query) use ($userId) {
                $query->where('id', $userId);
            })->exists();
            
            // If has class routine and no overwrite confirmation, return error
            if ($hasClassRoutine && !$request->overwrite_routine) {
                return back()->with('warning', __('This staff has existing class routines. Please confirm if you want to restrict their access.'))->withInput();
            }
            
            // Delete old assignments
            StaffAssignment::where('user_id', $userId)->delete();
            
            // Create new assignments
            foreach ($request->assignments as $assignment) {
                $facultyId = $assignment['faculty_id'];
                $programIds = $assignment['program_ids'] ?? [];
                $courseIds = $assignment['course_ids'] ?? [];
                
                // Assign faculty
                StaffAssignment::create([
                    'user_id' => $userId,
                    'assignable_type' => Faculty::class,
                    'assignable_id' => $facultyId,
                    'created_by' => $createdBy,
                ]);
                
                // Assign programs
                foreach ($programIds as $programId) {
                    StaffAssignment::create([
                        'user_id' => $userId,
                        'assignable_type' => Program::class,
                        'assignable_id' => $programId,
                        'created_by' => $createdBy,
                    ]);
                }
                
                // Assign courses
                foreach ($courseIds as $courseId) {
                    StaffAssignment::create([
                        'user_id' => $userId,
                        'assignable_type' => Subject::class,
                        'assignable_id' => $courseId,
                        'created_by' => $createdBy,
                    ]);
                }
            }
            
            DB::commit();
            
            return redirect()->route('admin.staff-assignment.index')->with('success', __('Staff assignments updated successfully!'));
            
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', __('Error updating assignments: ') . $e->getMessage())->withInput();
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $userId)
    {
        try {
            // Check for class routine
            $hasClassRoutine = ClassRoutine::whereHas('teacher', function($query) use ($userId) {
                $query->where('id', $userId);
            })->exists();
            
            StaffAssignment::where('user_id', $userId)->delete();
            
            $message = __('Staff assignments removed successfully!');
            if ($hasClassRoutine) {
                $message .= ' ' . __('Note: This staff will now have default role-based access to all areas.');
            }
            
            return back()->with('success', $message);
            
        } catch (\Exception $e) {
            return back()->with('error', __('Error removing assignments: ') . $e->getMessage());
        }
    }
    
    /**
     * Get programs for a faculty (AJAX)
     */
    public function getPrograms(Request $request)
    {
        $facultyId = $request->faculty_id;
        $programs = Program::where('faculty_id', $facultyId)
            ->where('status', 1)
            ->orderBy('title')
            ->get(['id', 'title']);
        
        return response()->json($programs);
    }
    
    /**
     * Get courses for programs (AJAX)
     */
    public function getCourses(Request $request)
    {
        $programIds = $request->program_ids ?? [];
        $courses = Subject::whereIn('program_id', $programIds)
            ->where('status', 1)
            ->orderBy('title')
            ->get(['id', 'title', 'code']);
        
        return response()->json($courses);
    }
}
