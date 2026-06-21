<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Flasher\Laravel\Facade\Flasher;
use Illuminate\Http\Request;
use App\Models\StudentLeave;
use App\Traits\FileUploader;
use Carbon\Carbon;

class StudentLeaveManagementController extends Controller
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
        $this->title = trans_choice('module_leave_manage', 1);
        $this->route = 'admin.student-leave-manage';
        $this->view = 'admin.student-leave-manage';
        $this->path = 'leave';
        $this->access = 'student-leave-manage';


        $this->middleware('permission:'.$this->access.'-view|'.$this->access.'-edit|'.$this->access.'-delete', ['only' => ['index','show']]);
        $this->middleware('permission:'.$this->access.'-edit', ['only' => ['update', 'status']]);
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


        if(!empty($request->student_id) || $request->student_id != null){
            $data['selected_student_id'] = $student_id = $request->student_id;
        }
        else{
            $data['selected_student_id'] = $student_id = null;
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

        // Search Filter
        $rows = StudentLeave::whereDate('apply_date', '>=', $start_date)
                            ->whereDate('apply_date', '<=', $end_date);

        if(!empty($request->student_id)){
            $rows->with('student.studentEnrolls')->whereHas('student', function ($query) use ($student_id){
                $query->where('student_id', 'LIKE', '%'.$student_id.'%');
                $query->orWhereHas('studentEnrolls', function ($q) use ($student_id) {
                    $q->where('matricule', 'LIKE', '%'.$student_id.'%');
                });
            });
        }
        else {
            $rows->with('student.studentEnrolls');
        }

        if(!empty($request->status) || $request->status != null){
            $rows->where('status', $status);
        }

        $rows = $rows->get();

        // Array Sorting
        $data['rows'] = $rows->sortByDesc(function($row){
            return $row->student->studentEnrolls->sortByDesc('id')->first()->matricule ?? $row->student->student_id;
        })->all();


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
        //
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
            'from_date' => 'required|date',
            'to_date' => 'required|date|after_or_equal:from_date',
            'status' => 'required',
        ]);


        //Update Data
        $leave = StudentLeave::findOrFail($id);
        $leave->review_by = Auth::guard('web')->user()->id;
        $leave->from_date = $request->from_date;
        $leave->to_date = $request->to_date;
        $leave->note = $request->note;
        $leave->status = $request->status;
        $leave->save();


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
        $leave = StudentLeave::findOrFail($id);
        // Delete Attach
        $this->deleteMedia($this->path, $leave);

        // Delete data
        $leave->delete();

        Flasher::addSuccess(__('msg_deleted_successfully'), __('msg_success'));

        return redirect()->back();
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function status(Request $request, $id)
    {
        // Field Validation
        $request->validate([
            'status' => 'required',
        ]);

        //Status Update
        $leave = StudentLeave::findOrFail($id);
        $leave->status = $request->status;
        $leave->review_by = Auth::guard('web')->user()->id;
        $leave->save();


        if($request->status == 1) {
            Flasher::addSuccess(__('msg_approve_successfully'), __('msg_success'));
        }
        else {
            Flasher::addSuccess(__('msg_reject_successfully'), __('msg_success'));
        }

        return redirect()->back();
    }
}
