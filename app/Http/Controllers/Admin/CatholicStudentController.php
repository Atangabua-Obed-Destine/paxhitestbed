<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\StudentEnroll;
use App\Models\Religion;
use App\Models\Student;
use App\Models\Program;
use App\Models\Batch;
use App\Models\Faculty;
use App\Models\Session;
use Yajra\DataTables\Facades\DataTables;
use Illuminate\Support\Facades\DB;

class CatholicStudentController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        // Module Data
        $this->title = trans_choice('module_catholic_student', 1);
        $this->route = 'admin.catholic-student';
        $this->view = 'admin.catholic-student';
        $this->path = 'catholic-student';
        $this->access = 'catholic-student';

        $this->middleware('permission:'.$this->access.'-view|'.$this->access.'-edit', ['only' => ['index','show']]);
        $this->middleware('permission:'.$this->access.'-edit', ['only' => ['edit','update']]);
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        //
        $data['title'] = $this->title;
        $data['route'] = $this->route;
        $data['view'] = $this->view;
        $data['path'] = $this->path;
        $data['access'] = $this->access;

        // Get Catholic religion ID
        $catholicReligion = Religion::where('is_catholic', 1)->first();
        
        if (!$catholicReligion) {
            return redirect()->back()->with('error', 'Catholic religion not found in system. Please configure religions first.');
        }

        // Base query for active enrollments with Catholic students
        // We check the religion in the STUDENTS table, not student_enrolls
        $baseQuery = StudentEnroll::where('status', 1)
            ->whereHas('student', function($q) use ($catholicReligion) {
                $q->where('religion', $catholicReligion->id);
            });

        // Get statistics
        $data['total_catholic'] = (clone $baseQuery)->count();

        $data['baptised_count'] = (clone $baseQuery)->whereHas('student', function($q) {
            $q->where('is_catholic_baptised', 1);
        })->count();

        $data['confirmed_count'] = (clone $baseQuery)->whereHas('student', function($q) {
            $q->where('is_confirmed', 1);
        })->count();

        $data['communion_count'] = (clone $baseQuery)->whereHas('student', function($q) {
            $q->where('has_first_communion', 1);
        })->count();

        $data['baptised_percentage'] = $data['total_catholic'] > 0 ? round(($data['baptised_count'] / $data['total_catholic']) * 100, 1) : 0;
        $data['confirmed_percentage'] = $data['total_catholic'] > 0 ? round(($data['confirmed_count'] / $data['total_catholic']) * 100, 1) : 0;
        $data['communion_percentage'] = $data['total_catholic'] > 0 ? round(($data['communion_count'] / $data['total_catholic']) * 100, 1) : 0;

        // For filters
        $data['faculties'] = Faculty::where('status', 1)->orderBy('title')->get();
        $data['programs'] = Program::where('status', 1)->orderBy('title')->get();
        $data['sessions'] = Session::where('status', 1)->orderBy('id', 'desc')->get();
        $data['batches'] = Batch::where('status', 1)->orderBy('id', 'desc')->get();

        // Get all Catholic students for client-side DataTables
        $data['students'] = (clone $baseQuery)
            ->with(['student', 'program.faculty', 'session', 'semester'])
            ->orderBy('matricule', 'asc')
            ->get();

        return view($this->view.'.index', $data);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        // Validate
        $request->validate([
            'is_catholic_baptised' => 'nullable|boolean',
            'is_confirmed' => 'nullable|boolean',
            'has_first_communion' => 'nullable|boolean',
        ]);

        // Update Student Model instead of StudentEnroll
        $enroll = StudentEnroll::with('student')->findOrFail($id);
        
        if ($enroll->student) {
            $enroll->student->is_catholic_baptised = $request->boolean('is_catholic_baptised');
            $enroll->student->is_confirmed = $request->boolean('is_confirmed');
            $enroll->student->has_first_communion = $request->boolean('has_first_communion');
            $enroll->student->save();
        }

        return response()->json([
            'success' => true,
            'message' => 'Sacrament status updated successfully!'
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }

    /**
     * Export to Excel
     */
    public function export(Request $request)
    {
        // Get Catholic religion ID
        $catholicReligion = Religion::where('is_catholic', 1)->first();
        
        if (!$catholicReligion) {
            return redirect()->back()->with('error', 'Catholic religion not found.');
        }

        $query = StudentEnroll::with(['student', 'program.faculty', 'session', 'semester'])
            ->where('status', 1)
            ->whereHas('student', function($q) use ($catholicReligion) {
                $q->where('religion', $catholicReligion->id);
            })
            ->orderBy('matricule', 'asc');

        // Apply filters
        if ($request->filled('filter_faculty')) {
            $query->whereHas('program', function($q) use ($request) {
                $q->where('faculty_id', $request->filter_faculty);
            });
        }

        if ($request->filled('filter_program')) {
            $query->where('program_id', $request->filter_program);
        }

        if ($request->filled('filter_session')) {
            $query->where('session_id', $request->filter_session);
        }

        if ($request->filled('filter_baptised')) {
            $query->whereHas('student', function($q) use ($request) {
                $q->where('is_catholic_baptised', $request->filter_baptised);
            });
        }

        if ($request->filled('filter_confirmed')) {
            $query->whereHas('student', function($q) use ($request) {
                $q->where('is_confirmed', $request->filter_confirmed);
            });
        }

        if ($request->filled('filter_communion')) {
            $query->whereHas('student', function($q) use ($request) {
                $q->where('has_first_communion', $request->filter_communion);
            });
        }

        $students = $query->get();

        $filename = 'catholic_students_' . date('Y-m-d_His') . '.csv';
        
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ];

        $callback = function() use ($students) {
            $file = fopen('php://output', 'w');
            
            // Add CSV headers
            fputcsv($file, [
                'Matricule',
                'Student Name',
                'Faculty',
                'Program',
                'Session',
                'Baptised',
                'Confirmed',
                'First Holy Communion'
            ]);

            // Add data rows
            foreach ($students as $student) {
                fputcsv($file, [
                    $student->matricule ?? ($student->student ? $student->student->student_id : 'N/A'),
                    $student->student ? $student->student->first_name . ' ' . $student->student->last_name : 'N/A',
                    $student->program && $student->program->faculty ? $student->program->faculty->title : 'N/A',
                    $student->program ? $student->program->title : 'N/A',
                    $student->session ? $student->session->title : 'N/A',
                    ($student->student && $student->student->is_catholic_baptised) ? 'Yes' : 'No',
                    ($student->student && $student->student->is_confirmed) ? 'Yes' : 'No',
                    ($student->student && $student->student->has_first_communion) ? 'Yes' : 'No',
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * Print view
     */
    public function print(Request $request)
    {
        // Get Catholic religion ID
        $catholicReligion = Religion::where('is_catholic', 1)->first();
        
        if (!$catholicReligion) {
            return redirect()->back()->with('error', 'Catholic religion not found.');
        }

        $query = StudentEnroll::with(['student', 'program.faculty', 'session', 'semester'])
            ->where('status', 1)
            ->whereHas('student', function($q) use ($catholicReligion) {
                $q->where('religion', $catholicReligion->id);
            })
            ->orderBy('matricule', 'asc');

        // Apply filters
        if ($request->filled('filter_faculty')) {
            $query->whereHas('program', function($q) use ($request) {
                $q->where('faculty_id', $request->filter_faculty);
            });
        }

        if ($request->filled('filter_program')) {
            $query->where('program_id', $request->filter_program);
        }

        if ($request->filled('filter_session')) {
            $query->where('session_id', $request->filter_session);
        }

        if ($request->filled('filter_baptised')) {
            $query->whereHas('student', function($q) use ($request) {
                $q->where('is_catholic_baptised', $request->filter_baptised);
            });
        }

        if ($request->filled('filter_confirmed')) {
            $query->whereHas('student', function($q) use ($request) {
                $q->where('is_confirmed', $request->filter_confirmed);
            });
        }

        if ($request->filled('filter_communion')) {
            $query->whereHas('student', function($q) use ($request) {
                $q->where('has_first_communion', $request->filter_communion);
            });
        }

        $data['students'] = $query->get();
        $data['title'] = 'Catholic Students Report';
        $data['print_date'] = date('F d, Y');

        return view($this->view.'.print', $data);
    }
}
