<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Flasher\Laravel\Facade\Flasher;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use App\Models\Faculty;
use App\Models\Sector;
use App\Services\StaffAssignmentService;

class FacultyController extends Controller
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
        $this->title = trans_choice('module_faculty', 1);
        $this->route = 'admin.faculty';
        $this->view = 'admin.faculty';
        $this->path = 'faculty';
        $this->access = 'faculty';


        $this->middleware('permission:'.$this->access.'-view|'.$this->access.'-create|'.$this->access.'-edit|'.$this->access.'-delete', ['only' => ['index','show']]);
        $this->middleware('permission:'.$this->access.'-create', ['only' => ['create','store']]);
        $this->middleware('permission:'.$this->access.'-edit', ['only' => ['edit','update']]);
        $this->middleware('permission:'.$this->access.'-delete', ['only' => ['destroy']]);
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        //
        $data['title'] = $this->title;
        $data['route'] = $this->route;
        $data['view'] = $this->view;
        $data['path'] = $this->path;
        $data['access'] = $this->access;

        // Apply staff assignment filter
        $query = Faculty::orderBy('title', 'asc');
        $query = StaffAssignmentService::filterFaculties($query);
        $data['rows'] = $query->get();
        $data['sectors'] = Sector::where('status', '1')->orderBy('title', 'asc')->get();

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
        // Field Validation
        $request->validate([
            'title' => 'required|max:191|unique:faculties,title',
            'featured_image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'banner_image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'dean_photo' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'email' => 'nullable|email|max:191',
            'phone' => 'nullable|max:191',
            'website' => 'nullable|url|max:191',
        ]);

        // Insert Data
        $faculty = new Faculty;
        $faculty->sector_id = $request->sector_id ?: null;
        $faculty->title = $request->title;
        $faculty->slug = Str::slug($request->title, '-');
        $faculty->shortcode = $request->shortcode;
        $faculty->matric_code = $request->matric_code;
        
        // CMS Fields
        $faculty->excerpt = $request->excerpt;
        $faculty->description = $request->description;
        $faculty->dean_name = $request->dean_name;
        $faculty->email = $request->email;
        $faculty->phone = $request->phone;
        $faculty->website = $request->website;
        
        // Handle Featured Image Upload
        if($request->hasFile('featured_image')){
            $file = $request->file('featured_image');
            $filename = time().'_featured_'.$file->getClientOriginalName();
            $file->move(public_path('uploads/faculties'), $filename);
            $faculty->featured_image = $filename;
        }
        
        // Handle Banner Image Upload
        if($request->hasFile('banner_image')){
            $file = $request->file('banner_image');
            $filename = time().'_banner_'.$file->getClientOriginalName();
            $file->move(public_path('uploads/faculties'), $filename);
            $faculty->banner_image = $filename;
        }
        
        // Handle Dean Photo Upload
        if($request->hasFile('dean_photo')){
            $file = $request->file('dean_photo');
            $filename = time().'_dean_'.$file->getClientOriginalName();
            $file->move(public_path('uploads/faculties/deans'), $filename);
            $faculty->dean_photo = $filename;
        }
        
        $faculty->save();


        Flasher::addSuccess(__('msg_created_successfully'), __('msg_success'));

        return redirect()->back();
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show(Faculty $faculty)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit(Faculty $faculty)
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
    public function update(Request $request, Faculty $faculty)
    {
        // Field Validation
        $request->validate([
            'title' => 'required|max:191|unique:faculties,title,'.$faculty->id,
            'featured_image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'banner_image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'dean_photo' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'email' => 'nullable|email|max:191',
            'phone' => 'nullable|max:191',
            'website' => 'nullable|url|max:191',
        ]);

        // Update Data
        $faculty->sector_id = $request->sector_id ?: null;
        $faculty->title = $request->title;
        $faculty->slug = Str::slug($request->title, '-');
        $faculty->shortcode = $request->shortcode;
        $faculty->matric_code = $request->matric_code;
        $faculty->status = $request->status;
        
        // CMS Fields
        $faculty->excerpt = $request->excerpt;
        $faculty->description = $request->description;
        $faculty->dean_name = $request->dean_name;
        $faculty->email = $request->email;
        $faculty->phone = $request->phone;
        $faculty->website = $request->website;
        
        // Handle Featured Image Upload
        if($request->hasFile('featured_image')){
            // Delete old image if exists
            if($faculty->featured_image && file_exists(public_path('uploads/faculties/'.$faculty->featured_image))){
                unlink(public_path('uploads/faculties/'.$faculty->featured_image));
            }
            
            $file = $request->file('featured_image');
            $filename = time().'_featured_'.$file->getClientOriginalName();
            $file->move(public_path('uploads/faculties'), $filename);
            $faculty->featured_image = $filename;
        }
        
        // Handle Banner Image Upload
        if($request->hasFile('banner_image')){
            // Delete old image if exists
            if($faculty->banner_image && file_exists(public_path('uploads/faculties/'.$faculty->banner_image))){
                unlink(public_path('uploads/faculties/'.$faculty->banner_image));
            }
            
            $file = $request->file('banner_image');
            $filename = time().'_banner_'.$file->getClientOriginalName();
            $file->move(public_path('uploads/faculties'), $filename);
            $faculty->banner_image = $filename;
        }
        
        // Handle Dean Photo Upload
        if($request->hasFile('dean_photo')){
            // Delete old image if exists
            if($faculty->dean_photo && file_exists(public_path('uploads/faculties/deans/'.$faculty->dean_photo))){
                unlink(public_path('uploads/faculties/deans/'.$faculty->dean_photo));
            }
            
            $file = $request->file('dean_photo');
            $filename = time().'_dean_'.$file->getClientOriginalName();
            $file->move(public_path('uploads/faculties/deans'), $filename);
            $faculty->dean_photo = $filename;
        }
        
        $faculty->save();


        Flasher::addSuccess(__('msg_updated_successfully'), __('msg_success'));

        return redirect()->back();
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy(Faculty $faculty)
    {
        // Delete Data
        $faculty->delete();

        Flasher::addSuccess(__('msg_deleted_successfully'), __('msg_success'));

        return redirect()->back();
    }
}
