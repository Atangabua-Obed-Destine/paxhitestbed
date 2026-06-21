<?php

namespace App\Http\Controllers\Admin\Web;

use App\Http\Controllers\Controller;
use Flasher\Laravel\Facade\Flasher;
use Illuminate\Http\Request;
use App\Traits\FileUploader;
use App\Models\Project;
use App\Models\Faculty;

class ProjectController extends Controller
{
    use FileUploader;

    protected $title, $route, $view, $path, $access;

    public function __construct()
    {
        $this->title = trans_choice('module_project', 1);
        $this->route = 'admin.project';
        $this->view = 'admin.project';
        $this->path = 'project';
        $this->access = 'project';

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
        $data['title'] = $this->title;
        $data['route'] = $this->route;
        $data['view'] = $this->view;
        $data['path'] = $this->path;
        $data['access'] = $this->access;

        $data['faculties'] = Faculty::where('status', '1')->orderBy('title', 'asc')->get();
        $data['rows'] = Project::orderBy('id', 'desc')->get();

        return view($this->view.'.index', $data);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'title' => 'required|max:191',
            'description' => 'required',
            'faculty_id' => 'nullable|integer',
            'theme' => 'nullable|max:191',
            'status' => 'required|in:ongoing,completed',
            'attach' => 'nullable|file|mimes:jpg,jpeg,png|max:2048',
        ]);

        $project = new Project;
        $project->faculty_id = $request->faculty_id;
        $project->title = $request->title;
        $project->description = $request->description;
        $project->lead_researcher = $request->lead_researcher;
        $project->theme = $request->theme;
        $project->status = $request->status;
        $project->start_date = $request->start_date;
        $project->end_date = $request->end_date;
        $project->featured = $request->featured ? '1' : '0';
        $project->attach = $this->uploadMedia($request, 'attach', $this->path);
        $project->save();

        Flasher::addSuccess(__('msg_created_successfully'), __('msg_success'));

        return redirect()->back();
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Project $project)
    {
        $request->validate([
            'title' => 'required|max:191',
            'description' => 'required',
            'faculty_id' => 'nullable|integer',
            'theme' => 'nullable|max:191',
            'status' => 'required|in:ongoing,completed',
            'attach' => 'nullable|file|mimes:jpg,jpeg,png|max:2048',
        ]);

        $project->faculty_id = $request->faculty_id;
        $project->title = $request->title;
        $project->description = $request->description;
        $project->lead_researcher = $request->lead_researcher;
        $project->theme = $request->theme;
        $project->status = $request->status;
        $project->start_date = $request->start_date;
        $project->end_date = $request->end_date;
        $project->featured = $request->featured ? '1' : '0';
        $project->is_active = $request->is_active;
        $project->attach = $this->updateMedia($request, 'attach', $this->path, $project);
        $project->save();

        Flasher::addSuccess(__('msg_updated_successfully'), __('msg_success'));

        return redirect()->back();
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Project $project)
    {
        $this->deleteMedia($this->path, $project);
        $project->delete();

        Flasher::addSuccess(__('msg_deleted_successfully'), __('msg_success'));

        return redirect()->back();
    }
}
