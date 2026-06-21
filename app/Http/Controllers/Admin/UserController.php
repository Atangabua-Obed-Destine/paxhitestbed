<?php

namespace App\Http\Controllers\Admin;

use Illuminate\Support\Facades\Crypt;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Flasher\Laravel\Facade\Flasher;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use App\Models\WorkShiftType;
use Illuminate\Http\Request;
use App\Imports\UsersImport;
use App\Traits\FileUploader;
use App\Models\Designation;
use App\Models\MailSetting;
use App\Mail\SendPassword;
use App\Models\Department;
use App\Models\District;
use App\Models\Province;
use App\Models\Document;
use App\Models\Program;
use App\User;

class UserController extends Controller
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
        $this->title     = trans_choice('module_staff', 1);
        $this->route     = 'admin.user';
        $this->view      = 'admin.user';
        $this->path      = 'user';
        $this->access    = 'user';


        $this->middleware('permission:'.$this->access.'-view|'.$this->access.'-create|'.$this->access.'-edit|'.$this->access.'-delete', ['only' => ['index','show','status','sendPassword']]);
        $this->middleware('permission:'.$this->access.'-create', ['only' => ['create','store']]);
        $this->middleware('permission:'.$this->access.'-edit', ['only' => ['edit','update','status']]);
        $this->middleware('permission:'.$this->access.'-delete', ['only' => ['destroy']]);
        $this->middleware('permission:'.$this->access.'-password-print', ['only' => ['printPassword']]);
        $this->middleware('permission:'.$this->access.'-password-change', ['only' => ['passwordChange']]);
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
        $data['title']     = $this->title;
        $data['route']     = $this->route;
        $data['view']      = $this->view;
        $data['path']      = $this->path;
        $data['access']    = $this->access;


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

        if(!empty($request->contract_type) || $request->contract_type != null){
            $data['selected_contract'] = $contract_type = $request->contract_type;
        }
        else{
            $data['selected_contract'] = '0';
        }


        if(isset($request->role) || isset($request->department) || isset($request->designation) || isset($request->shift) || isset($request->contract_type)){
            // Filter Users
            $users = User::where('id', '!=', null)
                ->whereDoesntHave('roles', function($query) {
                    $query->whereIn('name', ['Super Admin', 'Admin']);
                });

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
            if(!empty($request->contract_type)){
                $users->where('contract_type', $contract_type);
            }

            $data['rows'] = $users->orderBy('staff_id', 'asc')->get();
        }
        else {
            // Default: Show all users except Super Admin and Admin
            $data['rows'] = User::whereDoesntHave('roles', function($query) {
                $query->whereIn('name', ['Super Admin', 'Admin']);
            })->orderBy('staff_id', 'asc')->get();
        }


        $data['departments'] = Department::where('status', '1')
                        ->orderBy('title', 'asc')->get();
        $data['designations'] = Designation::where('status', '1')
                        ->orderBy('title', 'asc')->get();
        $data['roles'] = Role::whereNotIn('name', ['Super Admin', 'Admin'])->orderBy('name', 'asc')->get();
        $data['work_shifts'] = WorkShiftType::where('status', '1')
                        ->orderBy('title', 'asc')->get();

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
        $data['title']     = $this->title;
        $data['route']     = $this->route;
        $data['view']      = $this->view;

        $data['roles'] = Role::whereNotIn('name', ['Super Admin', 'Admin'])->orderBy('name', 'asc')->get();
        $data['departments'] = Department::where('status', '1')
                        ->orderBy('title', 'asc')->get();
        $data['designations'] = Designation::where('status', '1')
                        ->orderBy('title', 'asc')->get();
        $data['programs'] = Program::where('status', '1')
                        ->orderBy('title', 'asc')->get();
        $data['provinces'] = Province::where('status', '1')
                        ->orderBy('title', 'asc')->get();
        $data['work_shifts'] = WorkShiftType::where('status', '1')
                        ->orderBy('title', 'asc')->get();

        return view($this->view.'.create', $data);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    /**
     * Generate Staff ID
     * Format: PAX00001, PAX00002, etc.
     */
    public function generateStaffId(Request $request)
    {
        try {
            $staffId = User::generateStaffId();

            return response()->json([
                'success' => true,
                'staff_id' => $staffId,
                'message' => 'Staff ID generated successfully'
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
        $this->validate($request, [
            'staff_id' => 'nullable|unique:users,staff_id',
            'first_name' => 'required',
            'last_name' => 'required',
            'email' => 'required|email|unique:users,email',
            'department' => 'required',
            'designation' => 'required',
            'gender' => 'required',
            'dob' => 'required|date',
            'joining_date' => 'nullable|date',
            'ending_date' => 'nullable|date|after_or_equal:joining_date',
            'phone' => 'required',
            'basic_salary' => 'required|numeric',
            'contract_type' => 'required',
            'salary_type' => 'required',
            'roles' => 'required',
            'photo' => 'nullable|image',
            'signature' => 'nullable|image',
            'resume' => 'nullable|file|mimes:jpg,jpeg,png,pdf,doc,docx,zip,rar,csv,xls,xlsx,ppt,pptx|max:20480',
            'joining_letter' => 'nullable|file|mimes:jpg,jpeg,png,pdf,doc,docx,zip,rar,csv,xls,xlsx,ppt,pptx|max:20480',
        ]);

        // Auto-generate staff_id if empty (as a fallback)
        $staffId = $request->staff_id;
        if (empty($staffId)) {
            try {
                $staffId = User::generateStaffId();
            } catch (\Exception $e) {
                DB::rollBack();
                Flasher::addError('Error generating staff ID: ' . $e->getMessage());
                return redirect()->back()->withInput();
            }
        }

        // Random Password
        $password = str_random(8);

        // Insert Data
        try{
            DB::beginTransaction();

            $user = new User;
            $user->staff_id = $staffId;
            $user->department_id = $request->department;
            $user->designation_id = $request->designation;
            $user->first_name = $request->first_name;
            $user->last_name = $request->last_name;
            $user->father_name = $request->father_name;
            $user->mother_name = $request->mother_name;

            $user->email = $request->email;
            $user->password = Hash::make($password);
            $user->password_text = Crypt::encryptString($password);
            $user->gender = $request->gender;
            $user->dob = $request->dob;
            $user->joining_date = $request->joining_date;
            $user->ending_date = $request->ending_date;
            $user->phone = $request->phone;
            $user->emergency_phone = $request->emergency_phone;

            $user->religion = $request->religion;
            $user->caste = $request->caste;
            $user->mother_tongue = $request->mother_tongue;
            $user->marital_status = $request->marital_status;
            $user->blood_group = $request->blood_group;
            $user->nationality = $request->nationality;
            $user->national_id = $request->national_id;
            $user->passport_no = $request->passport_no;

            $user->country = $request->country;
            $user->present_province = $request->present_province;
            $user->present_district = $request->present_district;
            $user->present_village = $request->present_village;
            $user->present_address = $request->present_address;
            $user->permanent_province = $request->permanent_province;
            $user->permanent_district = $request->permanent_district;
            $user->permanent_village = $request->permanent_village;
            $user->permanent_address = $request->permanent_address;

            $user->education_level = $request->education_level;
            $user->graduation_academy = $request->graduation_academy;
            $user->year_of_graduation = $request->year_of_graduation;
            $user->graduation_field = $request->graduation_field;
            $user->experience = $request->experience;
            $user->note = $request->note;

            $user->basic_salary = $request->basic_salary;
            $user->contract_type = $request->contract_type;
            $user->work_shift = $request->work_shift;
            $user->salary_type = $request->salary_type;
            $user->epf_no = $request->epf_no;

            $user->bank_account_name = $request->bank_account_name;
            $user->bank_account_no = $request->bank_account_no;
            $user->bank_name = $request->bank_name;
            $user->ifsc_code = $request->ifsc_code;
            $user->bank_brach = $request->bank_brach;
            $user->tin_no = $request->tin_no;

            $user->photo = $this->uploadImage($request, 'photo', $this->path, 300, 300);
            $user->signature = $this->uploadImage($request, 'signature', $this->path, 300, 100);
            $user->resume = $this->uploadMedia($request, 'resume', $this->path);
            $user->joining_letter = $this->uploadMedia($request, 'joining_letter', $this->path);
            $user->status = '1';
            $user->created_by = Auth::guard('web')->user()->id;
            $user->save();

            // Save Bank Accounts
            if(is_array($request->accounts) && count($request->accounts) > 0) {
                $defaultAccountIndex = $request->default_account ?? 0;
                
                foreach($request->accounts as $index => $accountData) {
                    if(!empty($accountData['bank_name']) && !empty($accountData['account_number'])) {
                        \App\Models\StaffBankAccount::create([
                            'user_id' => $user->id,
                            'bank_name' => $accountData['bank_name'],
                            'account_name' => $accountData['account_name'],
                            'account_number' => $accountData['account_number'],
                            'branch' => $accountData['branch'] ?? null,
                            'ifsc_code' => $accountData['ifsc_code'] ?? null,
                            'is_default' => ($index == $defaultAccountIndex) ? 1 : 0,
                            'status' => 1
                        ]);
                    }
                }
            }


            // User Documents
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
                $document->users()->attach($user->id);

                }
            }}


            // Assign Role
            $user->roles()->attach($request->roles);

            // Attach Programs
            $user->programs()->attach($request->programs);

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
    public function show($id)
    {
        //
        $data['title']     = $this->title;
        $data['route']     = $this->route;
        $data['view']      = $this->view;
        $data['path']      = $this->path;

        $data['row'] = User::findOrFail($id);

        $data['documents'] = Document::whereHas('users', function ($query) use ($id) {
                            $query->where('id', $id);
                        })->get();

        return view($this->view.'.show', $data);
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
        $data['title']     = $this->title;
        $data['route']     = $this->route;
        $data['view']      = $this->view;
        $data['path']      = $this->path;

        $data['row'] = $user = User::find($id);
        $data['documents'] = Document::whereHas('users', function ($query) use ($id) {
                            $query->where('id', $id);
                            $query->where('status', '1');
                        })->get();

        $data['roles'] = Role::whereNotIn('name', ['Super Admin', 'Admin'])->orderBy('name', 'asc')->get();
        $data['departments'] = Department::where('status', '1')
                        ->orderBy('title', 'asc')->get();
        $data['designations'] = Designation::where('status', '1')
                        ->orderBy('title', 'asc')->get();
        $data['programs'] = Program::where('status', '1')
                        ->orderBy('title', 'asc')->get();
        $data['userRoles'] = $user->roles->all();
        $data['provinces'] = Province::where('status', '1')
                        ->orderBy('title', 'asc')->get();
        $data['work_shifts'] = WorkShiftType::where('status', '1')
                        ->orderBy('title', 'asc')->get();
        $data['present_districts'] = District::where('status', '1')
                        ->where('province_id', $user->present_province)
                        ->orderBy('title', 'asc')->get();
        $data['permanent_districts'] = District::where('status', '1')
                        ->where('province_id', $user->permanent_province)
                        ->orderBy('title', 'asc')->get();

        return view($this->view.'.edit', $data);
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
            'staff_id' => 'required|unique:users,staff_id,'.$id,
            'first_name' => 'required',
            'last_name' => 'required',
            'email' => 'required|email|unique:users,email,'.$id,
            'department' => 'required',
            'designation' => 'required',
            'gender' => 'required',
            'dob' => 'required|date',
            'joining_date' => 'nullable|date',
            'ending_date' => 'nullable|date|after_or_equal:joining_date',
            'phone' => 'required',
            'basic_salary' => 'required|numeric',
            'contract_type' => 'required',
            'salary_type' => 'required',
            'roles' => 'required',
            'photo' => 'nullable|image',
            'signature' => 'nullable|image',
            'resume' => 'nullable|file|mimes:jpg,jpeg,png,pdf,doc,docx,zip,rar,csv,xls,xlsx,ppt,pptx|max:20480',
            'joining_letter' => 'nullable|file|mimes:jpg,jpeg,png,pdf,doc,docx,zip,rar,csv,xls,xlsx,ppt,pptx|max:20480',
        ]);


        // Update Data
        try{
            DB::beginTransaction();

            $user = User::find($id);
            $user->staff_id = $request->staff_id;
            $user->department_id = $request->department;
            $user->designation_id = $request->designation;
            $user->first_name = $request->first_name;
            $user->last_name = $request->last_name;
            $user->father_name = $request->father_name;
            $user->mother_name = $request->mother_name;

            $user->email = $request->email;
            $user->gender = $request->gender;
            $user->dob = $request->dob;
            $user->joining_date = $request->joining_date;
            $user->ending_date = $request->ending_date;
            $user->phone = $request->phone;
            $user->emergency_phone = $request->emergency_phone;

            $user->religion = $request->religion;
            $user->caste = $request->caste;
            $user->mother_tongue = $request->mother_tongue;
            $user->marital_status = $request->marital_status;
            $user->blood_group = $request->blood_group;
            $user->nationality = $request->nationality;
            $user->national_id = $request->national_id;
            $user->passport_no = $request->passport_no;

            $user->country = $request->country;
            $user->present_province = $request->present_province;
            $user->present_district = $request->present_district;
            $user->present_village = $request->present_village;
            $user->present_address = $request->present_address;
            $user->permanent_province = $request->permanent_province;
            $user->permanent_district = $request->permanent_district;
            $user->permanent_village = $request->permanent_village;
            $user->permanent_address = $request->permanent_address;

            $user->education_level = $request->education_level;
            $user->graduation_academy = $request->graduation_academy;
            $user->year_of_graduation = $request->year_of_graduation;
            $user->graduation_field = $request->graduation_field;
            $user->experience = $request->experience;
            $user->note = $request->note;

            $user->basic_salary = $request->basic_salary;
            $user->contract_type = $request->contract_type;
            $user->work_shift = $request->work_shift;
            $user->salary_type = $request->salary_type;
            $user->epf_no = $request->epf_no;

            $user->bank_account_name = $request->bank_account_name;
            $user->bank_account_no = $request->bank_account_no;
            $user->bank_name = $request->bank_name;
            $user->ifsc_code = $request->ifsc_code;
            $user->bank_brach = $request->bank_brach;
            $user->tin_no = $request->tin_no;

            $user->photo = $this->updateImage($request, 'photo', $this->path, 300, 300, $user, 'photo');
            $user->signature = $this->updateImage($request, 'signature', $this->path, 300, 100, $user, 'signature');
            $user->resume = $this->updateMultiMedia($request, 'resume', $this->path, $user, 'resume');
            $user->joining_letter = $this->updateMultiMedia($request, 'joining_letter', $this->path, $user, 'joining_letter');
            $user->updated_by = Auth::guard('web')->user()->id;
            $user->save();

            // Handle Deleted Bank Accounts
            if($request->has('deleted_accounts') && is_array($request->deleted_accounts)) {
                foreach($request->deleted_accounts as $deletedId) {
                    $account = \App\Models\StaffBankAccount::where('id', $deletedId)
                                                           ->where('user_id', $user->id)
                                                           ->first();
                    if($account) {
                        $account->delete();
                    }
                }
            }

            // Update/Create Bank Accounts
            if(is_array($request->accounts) && count($request->accounts) > 0) {
                $defaultAccountIndex = $request->default_account ?? 0;
                
                // First, set all existing accounts to not default
                \App\Models\StaffBankAccount::where('user_id', $user->id)->update(['is_default' => 0]);
                
                foreach($request->accounts as $index => $accountData) {
                    if(!empty($accountData['bank_name']) && !empty($accountData['account_number'])) {
                        $accountInfo = [
                            'user_id' => $user->id,
                            'bank_name' => $accountData['bank_name'],
                            'account_name' => $accountData['account_name'],
                            'account_number' => $accountData['account_number'],
                            'branch' => $accountData['branch'] ?? null,
                            'ifsc_code' => $accountData['ifsc_code'] ?? null,
                            'is_default' => ($index == $defaultAccountIndex) ? 1 : 0,
                            'status' => 1
                        ];
                        
                        if(isset($accountData['id']) && !empty($accountData['id'])) {
                            // Update existing account
                            \App\Models\StaffBankAccount::where('id', $accountData['id'])
                                                        ->where('user_id', $user->id)
                                                        ->update($accountInfo);
                        } else {
                            // Create new account
                            \App\Models\StaffBankAccount::create($accountInfo);
                        }
                    }
                }
            }


            // User Documents
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
                $document->users()->sync($user->id);

                }
            }}


            // Assign Role
            $user->roles()->sync($request->roles);

            // Attach Update
            $user->programs()->sync($request->programs);

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
    public function destroy($id)
    {
        DB::beginTransaction();
        // Delete
        $user = User::find($id);
        $this->deleteMultiMedia($this->path, $user, 'photo');
        $this->deleteMultiMedia($this->path, $user, 'signature');
        $this->deleteMultiMedia($this->path, $user, 'resume');
        $this->deleteMultiMedia($this->path, $user, 'joining_letter');

        // Detach
        $user->roles()->detach();
        $user->documents()->detach();
        $user->programs()->detach();
        $user->contents()->detach();
        $user->notices()->detach();
        $user->member()->delete();
        $user->notes()->delete();

        $user->delete();
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
        $user = User::where('id', $id)->firstOrFail();

        if($user->status == 1){
            $user->login = 0;
            $user->status = 0;
            $user->save();
        }
        else {
            $user->login = 1;
            $user->status = 1;
            $user->save();
        }

        Flasher::addSuccess(__('msg_updated_successfully'), __('msg_success'));

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
        $user = User::where('id', $id)->firstOrFail();

        $mail = MailSetting::where('status', '1')->first();

        if(isset($mail->sender_email) && isset($mail->sender_name)){

            $sendTo = $user->email;
            $receiver = $user->first_name.' '.$user->last_name;

            // Passing data to email template
            $data['name'] = $user->first_name.' '.$user->last_name;
            $data['staff_id'] = $user->staff_id;
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

        $data['row'] = User::where('id', $id)->firstOrFail();

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
            'staff_id' => 'required',
            'password' => 'required|confirmed|min:8',
        ]);

        // Update Data
        $user = User::findOrFail($request->staff_id);
        $user->password = Hash::make($request->password);
        $user->password_text = Crypt::encryptString($request->password);
        $user->save();


        Flasher::addSuccess(__('msg_updated_successfully'), __('msg_success'));

        return redirect()->back();
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
        $data['departments'] = Department::where('status', '1')
                        ->orderBy('title', 'asc')->get();
        $data['designations'] = Designation::where('status', '1')
                        ->orderBy('title', 'asc')->get();

        return view($this->view.'.import', $data);
    }

    /**
    * @return \Illuminate\Support\Collection
    */
    public function importStore(Request $request)
    {
        // Field Validation
        $request->validate([
            'department' => 'required',
            'designation' => 'required',
            'import' => 'required|file|mimes:xlsx',
        ]);


        // Passing Data
        $data['department'] = $request->department;
        $data['designation'] = $request->designation;

        Excel::import(new UsersImport($data), $request->file('import'));


        Flasher::addSuccess(__('msg_updated_successfully'), __('msg_success'));

        return redirect()->back();
    }
}
