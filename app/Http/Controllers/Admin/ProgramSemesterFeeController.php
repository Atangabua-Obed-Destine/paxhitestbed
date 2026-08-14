<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ProgramSemesterFee;
use App\Models\ProgramSemesterFeeBreakdown;
use App\Models\Program;
use App\Models\Semester;
use App\Models\FeesCategory;
use App\Models\Faculty;
use App\Models\EnrollSubject;
use App\Services\StaffAssignmentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Flasher\Laravel\Facade\Flasher;

class ProgramSemesterFeeController extends Controller
{
    protected $title, $route, $view, $path, $access;

    public function __construct()
    {
        $this->title = 'Program Semester Fee Configuration';
        $this->route = 'admin.program-semester-fee';
        $this->view = 'admin.program-semester-fee';
        $this->path = 'program-semester-fee';
        $this->access = 'program-semester-fee';

        $this->middleware('permission:'.$this->access.'-view|'.$this->access.'-create|'.$this->access.'-edit|'.$this->access.'-delete', ['only' => ['index','show']]);
        $this->middleware('permission:'.$this->access.'-create', ['only' => ['create','store']]);
        $this->middleware('permission:'.$this->access.'-edit', ['only' => ['edit','update']]);
        $this->middleware('permission:'.$this->access.'-delete', ['only' => ['destroy']]);
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $data['title'] = $this->title;
        $data['route'] = $this->route;
        $data['view'] = $this->view;
        $data['path'] = $this->path;
        $data['access'] = $this->access;

        // Get filter selections
        $data['selected_faculty'] = $request->faculty ?? '0';
        $data['selected_program'] = $request->program ?? '0';
        $data['selected_semester'] = $request->semester ?? '0';

        // Get faculties for filter
        $facultyQuery = Faculty::where('status', 1)->orderBy('title', 'asc');
        $data['faculties'] = StaffAssignmentService::filterFaculties($facultyQuery)->get();

        // Get programs for filter
        if ($data['selected_faculty'] !== '0') {
            $data['programs'] = Program::where('faculty_id', $data['selected_faculty'])
                ->where('status', 1)
                ->orderBy('title', 'asc')
                ->get();
        } else {
            $data['programs'] = collect();
        }

        // Get regular semesters (is_resit = 0) that have enrolled courses
        $semestersQuery = Semester::where('is_resit', 0)
            ->where('status', 1)
            ->whereHas('enrollSubjects', function($query) use ($data) {
                if ($data['selected_program'] !== '0') {
                    $query->where('program_id', $data['selected_program']);
                }
            })
            ->orderBy('id', 'asc');

        $data['semesters'] = $semestersQuery->get();

        // Get configured fees based on filters
        $feesQuery = ProgramSemesterFee::with(['program.faculty', 'semester', 'feesCategory', 'breakdowns']);

        if ($data['selected_faculty'] !== '0') {
            $feesQuery->whereHas('program', function($query) use ($data) {
                $query->where('faculty_id', $data['selected_faculty']);
            });
        }

        if ($data['selected_program'] !== '0') {
            $feesQuery->where('program_id', $data['selected_program']);
        }

        if ($data['selected_semester'] !== '0') {
            $feesQuery->where('semester_id', $data['selected_semester']);
        }

        $data['rows'] = $feesQuery->orderBy('program_id')
            ->orderBy('semester_id')
            ->orderBy('fees_category_id')
            ->get();

        return view($this->view.'.index', $data);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $data['title'] = $this->title;
        $data['route'] = $this->route;
        $data['view'] = $this->view;
        $data['path'] = $this->path;
        $data['access'] = $this->access;

        $facultyQuery = Faculty::where('status', 1)->orderBy('title', 'asc');
        $data['faculties'] = StaffAssignmentService::filterFaculties($facultyQuery)->get();

        return view($this->view.'.create', $data);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'program' => 'required|exists:programs,id',
            'semester_type' => 'required|integer',
            'fees_category' => 'required|array|min:1',
            'fees_category.*' => 'exists:fees_categories,id',
            'amount' => 'required|array',
            'amount.*' => 'required|numeric|min:0',
            'due_month' => 'nullable|array',
            'due_month.*' => 'nullable|integer|min:1|max:12',
            'due_day' => 'nullable|array',
            'due_day.*' => 'nullable|integer|min:1|max:31',
            'fine_amount' => 'nullable|array',
            'fine_amount.*' => 'nullable|numeric|min:0',
            'fine_type' => 'nullable|array',
            'fine_type.*' => 'nullable|in:fixed,percentage',
        ]);

        try {
            DB::beginTransaction();

            // Get all regular semesters of this type for the program
            $semesters = Semester::where('is_resit', 0)
                ->where('status', 1)
                ->where('semester_type', $request->semester_type)
                ->whereHas('enrollSubjects', function($query) use ($request) {
                    $query->where('program_id', $request->program);
                })
                ->get();

            if ($semesters->isEmpty()) {
                Flasher::addError('No semesters found for this type with enrolled courses.', 'Error');
                return redirect()->back()->withInput();
            }

            $created = 0;
            $updated = 0;
            $skipped = 0;
            $semestersProcessed = 0;

            // Get semester type for breakdown check
            $semesterType = $request->semester_type;

            // Process each semester of this type
            foreach ($semesters as $semester) {
                $semesterCreated = 0;
                $semesterUpdated = 0;

                foreach ($request->fees_category as $index => $categoryId) {
                    // Verify category is eligible
                    $category = FeesCategory::find($categoryId);
                    
                    if (!$category || $category->is_resit || (!$category->is_first_installment && !$category->is_second_installment)) {
                        $skipped++;
                        continue;
                    }

                    $amount = $request->amount[$index] ?? 0;
                    
                    // Handle optional fields - convert empty strings to null
                    $dueMonth = !empty($request->due_month[$index]) ? $request->due_month[$index] : null;
                    $dueDay = !empty($request->due_day[$index]) ? $request->due_day[$index] : null;
                    $fineAmount = !empty($request->fine_amount[$index]) ? $request->fine_amount[$index] : null;
                    $fineType = !empty($request->fine_type[$index]) ? $request->fine_type[$index] : null;

                    // Create or update
                    $fee = ProgramSemesterFee::updateOrCreate(
                        [
                            'program_id' => $request->program,
                            'semester_id' => $semester->id,
                            'fees_category_id' => $categoryId,
                        ],
                        [
                            'amount' => $amount,
                            'due_month' => $dueMonth,
                            'due_day' => $dueDay,
                            'fine_amount' => $fineAmount,
                            'fine_type' => $fineType,
                            'status' => 1,
                        ]
                    );

                    // Handle breakdowns for first installment in Type 1 semesters
                    if ($semesterType == 1 && $category->is_first_installment == 1) {
                        // Delete existing breakdowns
                        $fee->breakdowns()->delete();
                        
                        // Add new breakdowns if provided
                        if ($request->has("breakdown_titles_{$index}") && is_array($request->input("breakdown_titles_{$index}"))) {
                            $breakdownTitles = $request->input("breakdown_titles_{$index}");
                            $breakdownAmounts = $request->input("breakdown_amounts_{$index}");
                            
                            foreach ($breakdownTitles as $bIndex => $title) {
                                if (!empty($title) && !empty($breakdownAmounts[$bIndex])) {
                                    ProgramSemesterFeeBreakdown::create([
                                        'program_semester_fee_id' => $fee->id,
                                        'title' => $title,
                                        'amount' => $breakdownAmounts[$bIndex],
                                        'order' => $bIndex + 1,
                                    ]);
                                }
                            }
                        }
                    }

                    if ($fee->wasRecentlyCreated) {
                        $created++;
                        $semesterCreated++;
                    } else {
                        $updated++;
                        $semesterUpdated++;
                    }
                }

                if ($semesterCreated > 0 || $semesterUpdated > 0) {
                    $semestersProcessed++;
                }
            }

            DB::commit();

            $message = "Configuration saved for {$semestersProcessed} semester(s)! Created: {$created}, Updated: {$updated}";
            if ($skipped > 0) {
                $message .= ", Skipped (invalid category): {$skipped}";
            }

            Flasher::addSuccess($message, 'Success');

            return redirect()->route($this->route.'.index');

        } catch (\Exception $e) {
            DB::rollBack();
            Flasher::addError('Error saving configuration: ' . $e->getMessage(), 'Error');
            return redirect()->back()->withInput();
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(ProgramSemesterFee $programSemesterFee)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(ProgramSemesterFee $programSemesterFee)
    {
        $data['title'] = $this->title;
        $data['route'] = $this->route;
        $data['view'] = $this->view;
        $data['path'] = $this->path;
        $data['access'] = $this->access;

        $data['row'] = $programSemesterFee->load(['breakdowns', 'semester', 'feesCategory']);

        $facultyQuery = Faculty::where('status', 1)->orderBy('title', 'asc');
        $data['faculties'] = StaffAssignmentService::filterFaculties($facultyQuery)->get();

        $data['programs'] = Program::where('faculty_id', $programSemesterFee->program->faculty_id)
            ->where('status', 1)
            ->orderBy('title', 'asc')
            ->get();

        // Get regular semesters with enrolled courses
        $data['semesters'] = Semester::where('is_resit', 0)
            ->where('status', 1)
            ->whereHas('enrollSubjects', function($query) use ($programSemesterFee) {
                $query->where('program_id', $programSemesterFee->program_id);
            })
            ->orderBy('id', 'asc')
            ->get();

        // Get eligible fee categories
        $data['fee_categories'] = FeesCategory::where('status', 1)
            ->where('is_resit', 0)
            ->where(function($query) {
                $query->where('is_first_installment', 1)
                      ->orWhere('is_second_installment', 1);
            })
            ->orderBy('title', 'asc')
            ->get();

        return view($this->view.'.edit', $data);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, ProgramSemesterFee $programSemesterFee)
    {
        $request->validate([
            'amount' => 'required|numeric|min:0',
            'due_month' => 'nullable|integer|min:1|max:12',
            'due_day' => 'nullable|integer|min:1|max:31',
            'fine_amount' => 'nullable|numeric|min:0',
            'fine_type' => 'nullable|in:fixed,percentage',
            'status' => 'required|boolean',
            'breakdown_titles.*' => 'nullable|string|max:255',
            'breakdown_amounts.*' => 'nullable|numeric|min:0',
        ]);

        try {
            DB::beginTransaction();

            $programSemesterFee->amount = $request->amount;
            
            // Handle optional fields - convert empty strings to null
            $programSemesterFee->due_month = !empty($request->due_month) ? $request->due_month : null;
            $programSemesterFee->due_day = !empty($request->due_day) ? $request->due_day : null;
            $programSemesterFee->fine_amount = !empty($request->fine_amount) ? $request->fine_amount : null;
            $programSemesterFee->fine_type = !empty($request->fine_type) ? $request->fine_type : null;
            
            $programSemesterFee->status = $request->status;
            $programSemesterFee->save();

            // Handle breakdowns for first installment in Type 1 semesters
            if ($programSemesterFee->feesCategory->is_first_installment == 1 && 
                $programSemesterFee->semester->semester_type == 1) {
                
                // Delete existing breakdowns
                $programSemesterFee->breakdowns()->delete();
                
                // Add new breakdowns if provided
                if ($request->has('breakdown_titles') && is_array($request->breakdown_titles)) {
                    $breakdownTitles = $request->breakdown_titles;
                    $breakdownAmounts = $request->breakdown_amounts;
                    
                    // Validate total equals fee amount
                    $breakdownTotal = array_sum(array_filter($breakdownAmounts, 'is_numeric'));
                    if (abs($breakdownTotal - $request->amount) > 0.01 && count(array_filter($breakdownTitles)) > 0) {
                        throw new \Exception("Breakdown total ({$breakdownTotal}) must equal fee amount ({$request->amount})");
                    }
                    
                    foreach ($breakdownTitles as $index => $title) {
                        if (!empty($title) && !empty($breakdownAmounts[$index])) {
                            ProgramSemesterFeeBreakdown::create([
                                'program_semester_fee_id' => $programSemesterFee->id,
                                'title' => $title,
                                'amount' => $breakdownAmounts[$index],
                                'order' => $index + 1,
                            ]);
                        }
                    }
                }
            }

            DB::commit();

            Flasher::addSuccess('Fee configuration updated successfully', 'Success');

            return redirect()->route($this->route.'.index');

        } catch (\Exception $e) {
            DB::rollBack();
            Flasher::addError('Error updating configuration: ' . $e->getMessage(), 'Error');
            return redirect()->back()->withInput();
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(ProgramSemesterFee $programSemesterFee)
    {
        try {
            $programSemesterFee->delete();

            Flasher::addSuccess('Fee configuration deleted successfully', 'Success');

            return redirect()->route($this->route.'.index');

        } catch (\Exception $e) {
            Flasher::addError('Error deleting configuration: ' . $e->getMessage(), 'Error');
            return redirect()->back();
        }
    }

    /**
     * Get programs by faculty (AJAX)
     */
    public function getPrograms(Request $request)
    {
        $programs = Program::where('faculty_id', $request->faculty_id)
            ->where('status', 1)
            ->orderBy('title', 'asc')
            ->get(['id', 'title']);

        return response()->json($programs);
    }

    /**
     * Get semester types with enrolled courses for program (AJAX)
     */
    public function getSemesterTypes(Request $request)
    {
        $semesterTypes = Semester::where('is_resit', 0)
            ->where('status', 1)
            ->whereHas('enrollSubjects', function($query) use ($request) {
                $query->where('program_id', $request->program_id);
            })
            ->select('semester_type')
            ->selectRaw('COUNT(*) as count')
            ->selectRaw('MIN(title) as example')
            ->groupBy('semester_type')
            ->orderBy('semester_type', 'asc')
            ->get()
            ->map(function($item) {
                return [
                    'type' => $item->semester_type,
                    'count' => $item->count,
                    'example' => $item->example,
                ];
            });

        return response()->json($semesterTypes);
    }

    /**
     * Get semesters with enrolled courses for program (AJAX)
     */
    public function getSemesters(Request $request)
    {
        $query = Semester::where('is_resit', 0)
            ->where('status', 1)
            ->whereHas('enrollSubjects', function($query) use ($request) {
                $query->where('program_id', $request->program_id);
            });

        // Filter by semester type if provided
        if ($request->has('semester_type') && $request->semester_type !== '') {
            $query->where('semester_type', $request->semester_type);
        }

        $semesters = $query->orderBy('id', 'asc')
            ->get(['id', 'title', 'year', 'semester_type']);

        return response()->json($semesters);
    }

    /**
     * Get eligible fee categories (AJAX)
     */
    public function getFeeCategories(Request $request)
    {
        $categories = FeesCategory::where('status', 1)
            ->where('is_resit', 0)
            ->where(function($query) {
                $query->where('is_first_installment', 1)
                      ->orWhere('is_second_installment', 1);
            })
            ->orderBy('title', 'asc')
            ->get(['id', 'title', 'is_first_installment', 'is_second_installment']);

        return response()->json($categories);
    }
}
