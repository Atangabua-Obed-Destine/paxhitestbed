<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Flasher\Laravel\Facade\Flasher;
use Spatie\Permission\Models\Role;
use App\Models\StaffAttendance;
use App\Models\WorkShiftType;
use Illuminate\Http\Request;
use App\Models\Designation;
use App\Models\Department;
use App\Models\Setting;
use Carbon\Carbon;
use App\User;

class StaffAttendanceController extends Controller
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
        $this->title = trans_choice('module_staff_daily_attendance', 1);
        $this->route = 'admin.staff-daily-attendance';
        $this->view = 'admin.staff-daily-attendance';
        $this->path = 'staff-daily-attendance';
        $this->access = 'staff-daily-attendance';


        $this->middleware('permission:'.$this->access.'-action', ['only' => ['index','store','scanner','scan']]);
        $this->middleware('permission:'.$this->access.'-report', ['only' => ['report']]);
    }

    /**
     * Display the scanner interface.
     *
     * @return \Illuminate\Http\Response
     */
    public function scanner()
    {
        $data['title'] = 'Attendance Scanner';
        $data['route'] = $this->route;
        $data['view'] = $this->view;
        $data['path'] = $this->path;
        $data['access'] = $this->access;

        // Statistics
        $date = date("Y-m-d");
        $data['today_date'] = date("l, d F Y");
        
        $data['total_staff'] = User::where('status', '1')->where('salary_type', '1')->count();
        
        $attendances = StaffAttendance::where('date', $date)
                        ->whereHas('user', function($q) {
                            $q->where('salary_type', '1');
                        })
                        ->get();
        $data['present_count'] = $attendances->where('attendance', 1)->count();
        $data['clocked_in_now'] = $attendances->whereNotNull('start_time')->whereNull('end_time')->count();

        return view($this->view.'.scanner', $data);
    }

    /**
     * Process the scanned QR code.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function scan(Request $request)
    {
        $request->validate([
            'staff_id' => 'required',
        ]);

        $staff_id = $request->staff_id;
        
        // Try to find by Staff ID String first, then by Database ID
        $user = User::where('status', '1')
                    ->where(function($query) use ($staff_id) {
                        $query->where('staff_id', $staff_id)
                              ->orWhere('id', $staff_id);
                    })
                    ->first();

        if (!$user) {
            return response()->json([
                'status' => 'error',
                'message' => 'Staff not found or inactive.'
            ], 404);
        }

        // Check Salary Type (1 = Fixed, 2 = Hourly)
        if ($user->salary_type != '1') {
             return response()->json([
                'status' => 'error',
                'message' => 'Daily attendance is only for Fixed Salary staff.'
            ], 403);
        }

        $date = date("Y-m-d");
        $time = date("H:i:s");
        
        // Check for existing attendance today
        $attendance = StaffAttendance::where('user_id', $user->id)
                                    ->where('date', $date)
                                    ->first();

        if (!$attendance) {
            // Clock In
            StaffAttendance::create([
                'user_id' => $user->id,
                'date' => $date,
                'start_time' => $time,
                'attendance' => 1, // Present
                'created_by' => Auth::guard('web')->user()->id ?? 1, // Fallback if kiosk user not logged in (should be protected though)
            ]);

            // Recalculate stats
            $attendances = StaffAttendance::where('date', $date)
                            ->whereHas('user', function($q) {
                                $q->where('salary_type', '1');
                            })
                            ->get();
            $present_count = $attendances->where('attendance', 1)->count();
            $clocked_in_now = $attendances->whereNotNull('start_time')->whereNull('end_time')->count();

            return response()->json([
                'status' => 'success',
                'type' => 'in',
                'user' => $user->first_name . ' ' . $user->last_name,
                'time' => date("h:i A", strtotime($time)),
                'message' => 'Clocked In Successfully',
                'stats' => [
                    'present' => $present_count,
                    'active' => $clocked_in_now
                ]
            ]);
        } else {
            // If record exists but start_time is null (e.g. created by bulk update), treat as Clock In
            if ($attendance->start_time == null) {
                $attendance->start_time = $time;
                $attendance->attendance = 1; // Ensure marked as Present
                $attendance->save();

                // Recalculate stats
                $attendances = StaffAttendance::where('date', $date)
                                ->whereHas('user', function($q) {
                                    $q->where('salary_type', '1');
                                })
                                ->get();
                $present_count = $attendances->where('attendance', 1)->count();
                $clocked_in_now = $attendances->whereNotNull('start_time')->whereNull('end_time')->count();

                return response()->json([
                    'status' => 'success',
                    'type' => 'in',
                    'user' => $user->first_name . ' ' . $user->last_name,
                    'time' => date("h:i A", strtotime($time)),
                    'message' => 'Clocked In Successfully',
                    'stats' => [
                        'present' => $present_count,
                        'active' => $clocked_in_now
                    ]
                ]);
            }

            // Check if already clocked out
            if ($attendance->end_time != null) {
                 return response()->json([
                    'status' => 'warning',
                    'message' => 'Already clocked out for today.'
                ]);
            }

            // Prevent double scan (e.g. within 1 minute)
            $startTime = Carbon::parse($attendance->start_time);
            $now = Carbon::parse($time);
            if ($now->diffInMinutes($startTime) < 1) {
                 return response()->json([
                    'status' => 'warning',
                    'message' => 'Duplicate scan detected. Please wait.'
                ]);
            }

            // Clock Out
            $attendance->end_time = $time;
            $attendance->save();

            // Calculate duration
            $duration = $startTime->diff($now)->format('%H:%I');

            // Recalculate stats
            $attendances = StaffAttendance::where('date', $date)
                            ->whereHas('user', function($q) {
                                $q->where('salary_type', '1');
                            })
                            ->get();
            $present_count = $attendances->where('attendance', 1)->count();
            $clocked_in_now = $attendances->whereNotNull('start_time')->whereNull('end_time')->count();

            return response()->json([
                'status' => 'success',
                'type' => 'out',
                'user' => $user->first_name . ' ' . $user->last_name,
                'time' => date("h:i A", strtotime($time)),
                'duration' => $duration,
                'message' => 'Clocked Out Successfully',
                'stats' => [
                    'present' => $present_count,
                    'active' => $clocked_in_now
                ]
            ]);
        }
    }

    /**
     * Display the current user's attendance history.
     *
     * @return \Illuminate\Http\Response
     */
    public function myAttendance()
    {
        $data['title'] = 'My Attendance';
        $data['route'] = $this->route;
        $data['view'] = $this->view;
        $data['path'] = $this->path;
        
        $user_id = Auth::guard('web')->user()->id;
        $data['attendances'] = StaffAttendance::where('user_id', $user_id)
                                            ->orderBy('date', 'desc')
                                            ->paginate(20);
                                            
        $data['min_hours'] = Setting::first()->staff_attendance_min_hours ?? 8;

        return view($this->view.'.my_attendance', $data);
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


        if(!empty($request->role) || $request->role != null){
            $data['selected_role'] = $role = $request->role;
        }
        else{
            $data['selected_role'] = '0';
        }

        if(!empty($request->department) || $request->department != null){
            $data['selected_department'] = $department = $request->department;
        }
        else{
            $data['selected_department'] = '0';
        }

        if(!empty($request->designation) || $request->designation != null){
            $data['selected_designation'] = $designation = $request->designation;
        }
        else{
            $data['selected_designation'] = '0';
        }

        if(!empty($request->shift) || $request->shift != null){
            $data['selected_shift'] = $shift = $request->shift;
        }
        else{
            $data['selected_shift'] = '0';
        }

        if(!empty($request->date) || $request->date != null){
            $data['selected_date'] = $date = $request->date;
        }
        else{
            $data['selected_date'] = date("Y-m-d", strtotime(Carbon::today()));
        }


        $data['roles'] = Role::orderBy('name', 'asc')->get();
        $data['departments'] = Department::where('status', '1')->orderBy('title', 'asc')->get();
        $data['designations'] = Designation::where('status', '1')->orderBy('title', 'asc')->get();
        $data['work_shifts'] = WorkShiftType::where('status', '1')->orderBy('title', 'asc')->get();


        // Filter Users
        if(!empty($request->role) || !empty($request->department) || !empty($request->designation) || !empty($request->shift) || !empty($request->date)){

            $users = User::where('salary_type', '1');

            if(!empty($request->role)){
                $users->with('roles')->whereHas('roles', function ($query) use ($role){
                    $query->where('role_id', $role);
                });
            }
            if(!empty($request->department)){
                $users->where('department_id', $department);
            }
            if(!empty($request->designation)){
                $users->where('designation_id', $designation);
            }
            if(!empty($request->shift)){
                $users->where('work_shift', $shift);
            }

            $data['rows'] = $users->where('status', '1')->orderBy('staff_id', 'asc')->get();
        }


        // Attendances
        if(!empty($request->role) || !empty($request->department) || !empty($request->designation) || !empty($request->shift) || !empty($request->date)){

            $attendances = StaffAttendance::where('date', $date);

            if(!empty($request->role)){
                $attendances->with('user.roles')->whereHas('user.roles', function ($query) use ($role){
                    $query->where('role_id', $role);
                });
            }
            if(!empty($request->department)){
                $attendances->with('user')->whereHas('user', function ($query) use ($department){
                    $query->where('department_id', $department);
                });
            }
            if(!empty($request->designation)){
                $attendances->with('user')->whereHas('user', function ($query) use ($designation){
                    $query->where('designation_id', $designation);
                });
            }
            if(!empty($request->shift)){
                $attendances->with('user')->whereHas('user', function ($query) use ($shift){
                    $query->where('work_shift', $shift);
                });
            }

            $data['attendances'] = $attendances->orderBy('id', 'desc')->get();
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
            'users' => 'required',
            'date' => 'required|date|before_or_equal:today',
            'attendances' => 'required',
        ]);

        $date = date("Y-m-d H:i:s", strtotime($request->date));
        $attendances = explode(",",$request->attendances);

        // Insert Data
        foreach($request->users as $key =>$user){
            // Insert Or Update Data
            $staffAttendance = StaffAttendance::updateOrCreate(
            [
                'user_id' => $request->users[$key],
                'date' => $date
            ],[
                'user_id' => $request->users[$key],
                'date' => $date,
                'attendance' => $attendances[$key],
                'note' => $request->notes[$key],
                'created_by' => Auth::guard('web')->user()->id,
            ]);
        }


        Flasher::addSuccess(__('msg_updated_successfully'), __('msg_success'));

        return redirect()->back();
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function report(Request $request)
    {
        //
        $data['title'] = trans_choice('module_staff_daily_report', 1);
        $data['route'] = $this->route;
        $data['view'] = $this->view;
        $data['path'] = $this->path;
        $data['access'] = $this->access;


        if(!empty($request->role) || $request->role != null){
            $data['selected_role'] = $role = $request->role;
        }
        else{
            $data['selected_role'] = '0';
        }

        if(!empty($request->department) || $request->department != null){
            $data['selected_department'] = $department = $request->department;
        }
        else{
            $data['selected_department'] = '0';
        }

        if(!empty($request->designation) || $request->designation != null){
            $data['selected_designation'] = $designation = $request->designation;
        }
        else{
            $data['selected_designation'] = '0';
        }

        if(!empty($request->shift) || $request->shift != null){
            $data['selected_shift'] = $shift = $request->shift;
        }
        else{
            $data['selected_shift'] = '0';
        }

        if(!empty($request->month) || $request->month != null){
            $data['selected_month'] = $month = $request->month;
        }
        else{
            $data['selected_month'] = date("m", strtotime(Carbon::today()));
        }

        if(!empty($request->year) || $request->year != null){
            $data['selected_year'] = $year = $request->year;
        }
        else{
            $data['selected_year'] = date("Y", strtotime(Carbon::today()));
        }


        $data['roles'] = Role::orderBy('name', 'asc')->get();
        $data['departments'] = Department::where('status', '1')->orderBy('title', 'asc')->get();
        $data['designations'] = Designation::where('status', '1')->orderBy('title', 'asc')->get();
        $data['work_shifts'] = WorkShiftType::where('status', '1')->orderBy('title', 'asc')->get();


        // Filter Users
        if(!empty($request->role) || !empty($request->department) || !empty($request->designation) || !empty($request->shift) || !empty($request->month) || !empty($request->year)){

            $users = User::where('salary_type', '1');

            if(!empty($request->role)){
                $users->with('roles')->whereHas('roles', function ($query) use ($role){
                    $query->where('role_id', $role);
                });
            }
            if(!empty($request->department)){
                $users->where('department_id', $department);
            }
            if(!empty($request->designation)){
                $users->where('designation_id', $designation);
            }
            if(!empty($request->shift)){
                $users->where('work_shift', $shift);
            }

            $data['rows'] = $users->where('status', '1')->orderBy('staff_id', 'asc')->get();
        }


        // Attendances
        if(!empty($request->role) || !empty($request->department) || !empty($request->designation) || !empty($request->shift) || !empty($request->month) || !empty($request->year)){

            if(!empty($request->month) && !empty($request->year)){

                $attendances = StaffAttendance::whereYear('date', $year)->whereMonth('date', $month);
            }

            if(!empty($request->role)){
                $attendances->with('user.roles')->whereHas('user.roles', function ($query) use ($role){
                    $query->where('role_id', $role);
                });
            }
            if(!empty($request->department)){
                $attendances->with('user')->whereHas('user', function ($query) use ($department){
                    $query->where('department_id', $department);
                });
            }
            if(!empty($request->designation)){
                $attendances->with('user')->whereHas('user', function ($query) use ($designation){
                    $query->where('designation_id', $designation);
                });
            }
            if(!empty($request->shift)){
                $attendances->with('user')->whereHas('user', function ($query) use ($shift){
                    $query->where('work_shift', $shift);
                });
            }

            $data['attendances'] = $attendances->orderBy('id', 'desc')->get();
        }

        return view($this->view.'.report', $data);
    }
}
