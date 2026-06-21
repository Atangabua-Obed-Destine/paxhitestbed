<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Flasher\Laravel\Facade\Flasher;
use Illuminate\Http\Request;
use App\Traits\FileUploader;
use App\Models\LeaveType;
use App\Models\Leave;
use Carbon\Carbon;
use App\User;

class LeaveManagementController extends Controller
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
        $this->route = 'admin.leave-manage';
        $this->view = 'admin.leave-manage';
        $this->path = 'staff-leave';
        $this->access = 'staff-leave-manage';


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


        if(!empty($request->user) || $request->user != null){
            $data['selected_user'] = $user = $request->user;
        }
        else{
            $data['selected_user'] = $user = '0';
        }

        if(!empty($request->pay_type) || $request->pay_type != null){
            $data['selected_pay_type'] = $pay_type = $request->pay_type;
        }
        else{
            $data['selected_pay_type'] = $pay_type = '0';
        }

        if(!empty($request->type) || $request->type != null){
            $data['selected_type'] = $type = $request->type;
        }
        else{
            $data['selected_type'] = $type = '0';
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
        $data['users'] = User::where('status', '1')
                            ->orderBy('staff_id', 'asc')->get();
        $data['types'] = LeaveType::where('status', '1')
                            ->orderBy('title', 'asc')->get();

        $rows = Leave::whereDate('apply_date', '>=', $start_date)
                    ->whereDate('apply_date', '<=', $end_date);
                    if(!empty($request->user) || $request->user != null){
                        $rows->where('user_id', $user);
                    }
                    if(!empty($request->pay_type) || $request->pay_type != null){
                        $rows->where('pay_type', $pay_type);
                    }
                    if(!empty($request->type) || $request->type != null){
                        $rows->where('type_id', $type);
                    }
        $data['rows'] = $rows->orderBy('id', 'desc')->get();

        return view($this->view.'.index', $data);
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
            'pay_type' => 'required',
            'status' => 'required',
        ]);


        // Update Data
        $leave = Leave::findOrFail($id);

        // Balance enforcement: block approval that would exceed the type's annual limit.
        if ($request->status == 1) {
            $exceeded = $this->exceedsLeaveLimit($leave, $request->from_date, $request->to_date);
            if ($exceeded !== false) {
                Flasher::addError($exceeded, __('msg_error'));
                return redirect()->back()->withInput();
            }
        }

        $leave->review_by = Auth::guard('web')->user()->id;
        $leave->from_date = $request->from_date;
        $leave->to_date = $request->to_date;
        $leave->note = $request->note;
        $leave->pay_type = $request->pay_type;
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
    public function destroy(Request $request, $id)
    {
        //
        $leave = Leave::findOrFail($id);
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

        // Status Update
        $leave = Leave::findOrFail($id);

        // Balance enforcement: block approval that would exceed the type's annual limit.
        if ($request->status == 1) {
            $exceeded = $this->exceedsLeaveLimit($leave, $leave->from_date, $leave->to_date);
            if ($exceeded !== false) {
                Flasher::addError($exceeded, __('msg_error'));
                return redirect()->back();
            }
        }

        $leave->review_by = Auth::guard('web')->user()->id;
        $leave->status = $request->status;
        $leave->save();


        if($request->status == 1)
        {
            Flasher::addSuccess(__('msg_approve_successfully'), __('msg_success'));
        }
        else{
            Flasher::addSuccess(__('msg_reject_successfully'), __('msg_success'));
        }

        return redirect()->back();
    }

    /**
     * Check whether approving the given leave for the given dates would push the
     * staff member over the leave type's annual limit (counting other already-approved
     * leaves of that type in the same calendar year, excluding this leave).
     *
     * @return string|false  an error message if the limit would be exceeded, otherwise false
     */
    private function exceedsLeaveLimit(Leave $leave, $fromDate, $toDate)
    {
        $type = $leave->leaveType;
        if (!$type || $type->limit <= 0) {
            return false; // unlimited
        }

        $year = (int) date('Y', strtotime($fromDate));
        $requestedDays = (int) ((strtotime($toDate) - strtotime($fromDate)) / 86400) + 1;
        $used = Leave::usedDaysForType($leave->user_id, $type->id, $year, [1], $leave->id);

        if (($used + $requestedDays) > $type->limit) {
            $remaining = max(0, $type->limit - $used);
            return __('Approving this exceeds the :type limit: :remaining of :limit day(s) left for :year (this request is :requested).', [
                'type' => $type->title,
                'remaining' => $remaining,
                'limit' => $type->limit,
                'year' => $year,
                'requested' => $requestedDays,
            ]);
        }

        return false;
    }
}
