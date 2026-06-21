<?php

namespace App\Http\Controllers\Admin\Web;

use App\Http\Controllers\Controller;
use Flasher\Laravel\Facade\Flasher;
use Illuminate\Http\Request;
use App\Traits\FileUploader;
use App\Models\CampusLifeSection;
use App\Models\Language;

class CampusLifeSectionController extends Controller
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
        $this->title    = 'Campus Life';
        $this->route    = 'admin.campus-life';
        $this->view     = 'admin.web.campus-life';
        $this->path     = 'campus-life';
        $this->access   = 'campus-life';

        $this->middleware('permission:'.$this->access.'-view|'.$this->access.'-create|'.$this->access.'-edit|'.$this->access.'-delete', ['only' => ['index','show']]);
        $this->middleware('permission:'.$this->access.'-create', ['only' => ['create','store']]);
        $this->middleware('permission:'.$this->access.'-edit', ['only' => ['edit','update']]);
        $this->middleware('permission:'.$this->access.'-delete', ['only' => ['destroy']]);
    }

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $data['title']  = $this->title;
        $data['route']  = $this->route;
        $data['view']   = $this->view;
        $data['path']   = $this->path;
        $data['access'] = $this->access;

        $data['rows'] = CampusLifeSection::orderBy('sort_order', 'asc')->get();

        return view($this->view.'.index', $data);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $data['title']  = $this->title;
        $data['route']  = $this->route;
        $data['view']   = $this->view;

        $data['languages'] = Language::where('status', '1')->orderBy('name', 'asc')->get();

        return view($this->view.'.create', $data);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        // Field Validation
        $request->validate([
            'language_id' => 'required',
            'section_type' => 'required',
            'title' => 'required|max:191',
            'description' => 'nullable',
            'image' => 'nullable|image',
            'icon' => 'nullable|max:191',
            'sort_order' => 'required|integer',
            'status' => 'required',
        ]);

        // Insert Data
        $section = new CampusLifeSection;
        $section->language_id = $request->language_id;
        $section->section_type = $request->section_type;
        $section->title = $request->title;
        $section->description = $request->description;
        $section->icon = $request->icon;
        $section->sort_order = $request->sort_order;
        $section->status = $request->status;

        // Handle image upload
        if ($request->hasFile('image')) {
            $section->image = $this->uploadImage($request, 'image', $this->path, 800, 600);
        }

        $section->save();

        Flasher::addSuccess('Data has been added successfully.');

        return redirect()->route($this->route.'.index');
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit($id)
    {
        $data['title']  = $this->title;
        $data['route']  = $this->route;
        $data['view']   = $this->view;
        $data['path']   = $this->path;

        $data['row'] = CampusLifeSection::findOrFail($id);
        $data['languages'] = Language::where('status', '1')->orderBy('name', 'asc')->get();

        return view($this->view.'.edit', $data);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        // Field Validation
        $request->validate([
            'language_id' => 'required',
            'section_type' => 'required',
            'title' => 'required|max:191',
            'description' => 'nullable',
            'image' => 'nullable|image',
            'icon' => 'nullable|max:191',
            'sort_order' => 'required|integer',
            'status' => 'required',
        ]);

        // Update Data
        $section = CampusLifeSection::findOrFail($id);
        $section->language_id = $request->language_id;
        $section->section_type = $request->section_type;
        $section->title = $request->title;
        $section->description = $request->description;
        $section->icon = $request->icon;
        $section->sort_order = $request->sort_order;
        $section->status = $request->status;

        // Handle image upload
        if ($request->hasFile('image')) {
            $section->image = $this->updateMedia($request, 'image', $this->path, $section->image, 800, 600);
        }

        $section->save();

        Flasher::addSuccess('Data has been updated successfully.');

        return redirect()->route($this->route.'.index');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        // Delete Data
        $section = CampusLifeSection::findOrFail($id);
        
        // Delete image if exists
        if ($section->image) {
            $this->deleteMedia($this->path.'/'.$section->image);
        }
        
        $section->delete();

        Flasher::addSuccess('Data has been deleted successfully.');

        return back();
    }
}
