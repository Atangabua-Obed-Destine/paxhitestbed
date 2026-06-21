<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\IdCardSetting;
use App\Models\Department;
use App\Models\Designation;
use App\User;
use Illuminate\Http\Request;
use App\Traits\FileUploader;

class StaffIdCardController extends Controller
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
        $this->title = trans_choice('module_staff_id_card', 1);
        $this->route = 'admin.staff-id-card';
        $this->view = 'admin.staff-id-card';
        $this->path = 'staff';
        $this->access = 'staff';

        $this->middleware('permission:'.$this->access.'-view')->except('verify');
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

        // Filter parameters
        if(!empty($request->department) || $request->department != null){
            $data['selected_department'] = $department = $request->department;
        }
        else{
            $data['selected_department'] = $department = '0';
        }

        if(!empty($request->designation) || $request->designation != null){
            $data['selected_designation'] = $designation = $request->designation;
        }
        else{
            $data['selected_designation'] = $designation = '0';
        }

        if(!empty($request->staff_id) || $request->staff_id != null){
            $data['selected_staff_id'] = $staff_id = $request->staff_id;
        }
        else{
            $data['selected_staff_id'] = null;
        }

        // Search Filter Data
        $data['departments'] = Department::where('status', '1')->orderBy('title', 'asc')->get();
        $data['designations'] = Designation::where('status', '1')->orderBy('title', 'asc')->get();

        if(isset($request->department) || isset($request->designation) || isset($request->staff_id)){
            // Staff Filter
            $staffs = User::where('status', '1')
                ->whereDoesntHave('roles', function($query) {
                    $query->where('name', 'Super Admin');
                });
            
            if($department != 0){
                $staffs->where('department_id', $department);
            }
            if($designation != 0){
                $staffs->where('designation_id', $designation);
            }
            if(!empty($request->staff_id)){
                $staffs->where('staff_id', 'LIKE', '%'.$staff_id.'%');
            }
            
            $rows = $staffs->orderBy('staff_id', 'asc')->get();

            if(count($rows) > 0){
                $data['rows'] = $rows;
                $data['print'] = IdCardSetting::where('slug', 'staff-id-card')->where('status', '1')->first();
            }
        }

        return view($this->view.'.index', $data);
    }

    /**
     * Display the specified resource for print.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function print($id)
    {
        //
        $data['title'] = $this->title;
        $data['route'] = $this->route;
        $data['view'] = $this->view;
        $data['path'] = $this->path;

        $data['print'] = IdCardSetting::where('slug', 'staff-id-card')->where('status', '1')->firstOrFail();
        $data['row'] = User::where('status', '1')->findOrFail($id);

        return view($this->view.'.print', $data);
    }

    /**
     * Display the specified resources for multi print.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function multiPrint(Request $request)
    {
        //
        $data['title'] = $this->title;
        $data['route'] = $this->route;
        $data['view'] = $this->view;
        $data['path'] = $this->path;

        $data['print'] = IdCardSetting::where('slug', 'staff-id-card')->where('status', '1')->firstOrFail();
        
        $staffs = explode(',', $request->staffs);
        $data['rows'] = User::where('status', '1')->whereIn('id', $staffs)->orderBy('staff_id', 'asc')->get();

        return view($this->view.'.print', $data);
    }

    /**
     * Download single Staff ID card as image
     *
     * @param int $id Staff ID
     * @return \Illuminate\Http\Response
     */
    public function download($id)
    {
        $data['title'] = $this->title;
        $data['route'] = $this->route;
        $data['view'] = $this->view;
        $data['path'] = $this->path;

        // Get staff
        $staff = User::with(['department', 'designation'])
            ->where('status', '1')
            ->where('id', $id)
            ->first();
        
        if (!$staff) {
            return redirect()->back()->with('error', __('Staff not found'));
        }

        $data['row'] = $staff;
        $data['print'] = IdCardSetting::where('slug', 'staff-id-card')->first();
        $data['download_mode'] = true;

        return view($this->view.'.download', $data);
    }

    /**
     * Download multiple Staff ID cards as ZIP file (returns data for client-side generation)
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function downloadZip(Request $request)
    {
        $staffIds = $request->input('staffs', '');
        
        if (empty($staffIds)) {
            return response()->json([
                'success' => false,
                'message' => __('No staff selected')
            ], 400);
        }

        $ids = explode(',', $staffIds);
        
        // Get staff members
        $staffMembers = User::with(['department', 'designation'])
            ->where('status', '1')
            ->whereIn('id', $ids)
            ->get();

        if ($staffMembers->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => __('No valid staff found')
            ], 404);
        }

        $print = IdCardSetting::where('slug', 'staff-id-card')->first();

        // Return data for client-side ZIP generation
        $staffData = [];
        foreach ($staffMembers as $staff) {
            $staffData[] = [
                'id' => $staff->id,
                'staff_id' => $staff->staff_id,
                'first_name' => $staff->first_name,
                'last_name' => $staff->last_name,
                'designation' => $staff->designation->title ?? 'N/A',
                'validity' => $staff->id_card_validity,
                'photo' => $staff->photo && file_exists(public_path('uploads/user/'.$staff->photo)) 
                    ? asset('uploads/user/'.$staff->photo) 
                    : asset('dashboard/images/user.jpg'),
                'qr_url' => url('verify-staff/'.$staff->id),
            ];
        }

        return response()->json([
            'success' => true,
            'staffs' => $staffData,
            'template_url' => asset('uploads/templates/staffid.jpg')
        ]);
    }

    /**
     * Update staff photo via AJAX
     *
     * @param Request $request
     * @param int $id Staff ID
     * @return \Illuminate\Http\JsonResponse
     */
    public function updatePhoto(Request $request, $id)
    {
        try {
            $staff = User::findOrFail($id);
            
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
            $staff->photo = $this->updateImage($request, 'photo', 'user', 300, 300, $staff, 'photo');
            $staff->save();
            
            return response()->json([
                'success' => true,
                'message' => __('alert_update'),
                'photo_url' => asset('uploads/user/' . $staff->photo)
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Public verification page for staff (no authentication required)
     *
     * @param  string  $staff_identifier
     * @return \Illuminate\Http\Response
     */
    public function verify($staff_identifier)
    {
        //
        $data['title'] = __('verify_staff_id');
        
        // Find staff by ID or staff_id
        $staff = User::where('id', $staff_identifier)
            ->orWhere('staff_id', $staff_identifier)
            ->with(['department', 'designation'])
            ->first();

        if (!$staff) {
            $data['staff_found'] = false;
            $data['staff_identifier'] = $staff_identifier;
        } else {
            $data['staff_found'] = true;
            $data['row'] = $staff;
            $data['print'] = IdCardSetting::where('slug', 'staff-id-card')->first();
        }

        return view('verify-staff', $data);
    }
}
