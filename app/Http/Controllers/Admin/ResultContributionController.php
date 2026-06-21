<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Flasher\Laravel\Facade\Flasher;
use App\Models\ResultContribution;
use App\Models\ExamTypeContribution;
use App\Models\Exam;
use App\Models\Session;
use Illuminate\Http\Request;
use App\Models\ExamType;
use App\Models\Subject;
use App\Models\Faculty;
use App\Models\Program;
use App\Services\StaffAssignmentService;

class ResultContributionController extends Controller
{
    protected $title, $route, $view, $path, $access;
    
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        // Module Data
        $this->title = trans_choice('module_result_contribution', 1);
        $this->route = 'admin.result-contribution';
        $this->view = 'admin.result-contribution';
        $this->access = 'result-contribution';


        $this->middleware('permission:'.$this->access.'-view');
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
        $data['access'] = $this->access;

        // Filter setup
        $data['selected_faculty'] = $faculty = $request->faculty ?? '0';
        $data['selected_program'] = $program = $request->program ?? '0';
        $data['selected_subject'] = $subject = $request->subject ?? '0';

        // Search Filter
        $facultyQuery = Faculty::where('status', '1')->orderBy('title', 'asc');
        $data['faculties'] = StaffAssignmentService::filterFaculties($facultyQuery)->get();
        $data['programs'] = collect();
        $data['subjects'] = collect();

        if($faculty !== '0'){
            $data['programs'] = Program::where('faculty_id', $faculty)->where('status', '1')->orderBy('title', 'asc')->get();
        }

        if($program !== '0'){
            $subjects = Subject::where('status', '1');
            $subjects->with('programs')->whereHas('programs', function ($query) use ($program){
                $query->where('program_id', $program);
            });
            $data['subjects'] = $subjects->orderBy('code', 'asc')->get();

            // Build configuration status for all subjects in this program
            $configStatus = [];
            foreach ($data['subjects'] as $subj) {
                $isConfigured = \App\Services\ResultContributionService::isConfigured($subj->id);
                $configStatus[] = [
                    'id' => $subj->id,
                    'code' => $subj->code,
                    'title' => $subj->title,
                    'configured' => $isConfigured,
                ];
            }
            $data['subject_config_status'] = $configStatus;
            $data['unconfigured_count'] = collect($configStatus)->where('configured', false)->count();
            $data['configured_count'] = collect($configStatus)->where('configured', true)->count();
        }

        // Get data if subject is selected
        if($subject !== '0'){
            $data['row'] = ResultContribution::where('subject_id', $subject)->where('status', '1')->first();
            $data['exams'] = ExamType::orderBy('id', 'asc')->get();
            
            // Get exam type contributions for this subject
            $data['exam_contributions'] = ExamTypeContribution::where('subject_id', $subject)->get()->keyBy('exam_type_id');
        }

        return view($this->view.'.index', $data);
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
            'subject' => 'required',
            'attendances' => 'required|numeric',
            'assignments' => 'required|numeric',
            'activities' => 'required|numeric',
            'contributions' => 'required',
        ]);


        // Check Contribution Total
        $exam_contributions = 0;
        foreach($request->contributions as $contribution){
            if(!is_numeric($contribution)){
                Flasher::addError(__('msg_your_contribution_is_not_correct'), __('msg_error'));

                return redirect()->back()->withInput();
            }
            else {
                $exam_contributions = $exam_contributions + $contribution;
            }
        }
        if( ($exam_contributions + $request->attendances + $request->assignments + $request->activities) != 100 ) {

            Flasher::addError(__('msg_your_contribution_is_not_correct'), __('msg_error'));

            return redirect()->back()->withInput();
        }



        $id = $request->id;
        $subject_id = $request->subject;

        // -1 means no data row found
        if($id == -1){
            // Insert Data
            $contribution = new ResultContribution;
            $contribution->subject_id = $subject_id;
            $contribution->attendances = $request->attendances;
            $contribution->assignments = $request->assignments;
            $contribution->activities = $request->activities;
            $contribution->save();
        }
        else{
            // Update Data
            $contribution = ResultContribution::find($id);
            $contribution->subject_id = $subject_id;
            $contribution->attendances = $request->attendances;
            $contribution->assignments = $request->assignments;
            $contribution->activities = $request->activities;
            $contribution->save();
        }


        // Update/Create Exam Type Contributions for this subject
        foreach($request->exams as $key => $exam){
            ExamTypeContribution::updateOrCreate(
                [
                    'subject_id' => $subject_id,
                    'exam_type_id' => $exam
                ],
                [
                    'contribution' => $request->contributions[$key]
                ]
            );
        }

        // Backfill contribution weights on existing exam records for the current session.
        // The exams table stores a denormalized copy of the contribution value. When mark
        // distribution is (re)configured, existing exam records must be updated so the new
        // weights are reflected everywhere in the system (results preview, publishing, etc.)
        $currentSession = Session::where('current', 1)->where('status', 1)->first();
        if ($currentSession) {
            // Build a map of exam_type_id => new contribution value
            $contributionMap = [];
            foreach ($request->exams as $key => $examTypeId) {
                $contributionMap[$examTypeId] = (float)$request->contributions[$key];
            }

            // Update all exam records for this subject in the current session
            foreach ($contributionMap as $examTypeId => $newContribution) {
                Exam::where('subject_id', $subject_id)
                    ->where('exam_type_id', $examTypeId)
                    ->whereHas('studentEnroll', function ($q) use ($currentSession) {
                        $q->where('session_id', $currentSession->id);
                    })
                    ->update(['contribution' => $newContribution]);
            }

            // Re-sync subject_markings with updated contribution weights
            \App\Services\ExamMarksSyncService::syncAllForSubject((int)$subject_id, $currentSession->id);
        }


        Flasher::addSuccess(__('msg_updated_successfully'), __('msg_success'));

        return redirect()->back();
    }
}
