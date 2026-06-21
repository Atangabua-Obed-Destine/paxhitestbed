<?php

namespace App\Http\Controllers\Admin\Web;

use App\Http\Controllers\Controller;
use Flasher\Laravel\Facade\Flasher;
use Illuminate\Http\Request;
use App\Traits\FileUploader;
use App\Models\LeadershipTeam;

class LeadershipTeamController extends Controller
{
    use FileUploader;

    protected $title, $route, $view, $path, $access;

    public function __construct()
    {
        $this->title = trans_choice('module_leadership_team', 1);
        $this->route = 'admin.leadership-team';
        $this->view = 'admin.leadership-team';
        $this->path = 'leadership-team';
        $this->access = 'leadership-team';

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

        $data['rows'] = LeadershipTeam::orderBy('sort_order', 'asc')->get();

        return view($this->view.'.index', $data);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|max:191',
            'designation' => 'required|max:191',
            'photo' => 'nullable|file|mimes:jpg,jpeg,png|max:2048',
        ]);

        $leader = new LeadershipTeam;
        $leader->name = $request->name;
        $leader->designation = $request->designation;
        $leader->bio = $request->bio;
        $leader->email = $request->email;
        $leader->phone = $request->phone;
        $leader->sort_order = $request->sort_order ?? 0;
        $leader->photo = $this->uploadMedia($request, 'photo', $this->path);
        $leader->save();

        Flasher::addSuccess(__('msg_created_successfully'), __('msg_success'));

        return redirect()->back();
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, LeadershipTeam $leadershipTeam)
    {
        $request->validate([
            'name' => 'required|max:191',
            'designation' => 'required|max:191',
            'photo' => 'nullable|file|mimes:jpg,jpeg,png|max:2048',
        ]);

        $leadershipTeam->name = $request->name;
        $leadershipTeam->designation = $request->designation;
        $leadershipTeam->bio = $request->bio;
        $leadershipTeam->email = $request->email;
        $leadershipTeam->phone = $request->phone;
        $leadershipTeam->sort_order = $request->sort_order ?? 0;
        $leadershipTeam->status = $request->status;
        $leadershipTeam->photo = $this->updateMedia($request, 'photo', $this->path, $leadershipTeam);
        $leadershipTeam->save();

        Flasher::addSuccess(__('msg_updated_successfully'), __('msg_success'));

        return redirect()->back();
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(LeadershipTeam $leadershipTeam)
    {
        $this->deleteMedia($this->path, $leadershipTeam);
        $leadershipTeam->delete();

        Flasher::addSuccess(__('msg_deleted_successfully'), __('msg_success'));

        return redirect()->back();
    }
}
