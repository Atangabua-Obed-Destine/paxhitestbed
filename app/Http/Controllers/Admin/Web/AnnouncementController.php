<?php

namespace App\Http\Controllers\Admin\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Web\Announcement;
use App\Models\Language;

class AnnouncementController extends Controller
{
    protected $title, $route, $view, $access;

    public function __construct()
    {
        // Module Data
        $this->title   = 'Announcements';
        $this->route   = 'admin.announcement';
        $this->view    = 'admin.web.announcement';
        $this->access  = 'announcement';

        $this->middleware('permission:'.$this->access.'-view|'.$this->access.'-create|'.$this->access.'-edit|'.$this->access.'-delete', ['only' => ['index','show']]);
        $this->middleware('permission:'.$this->access.'-create', ['only' => ['create','store']]);
        $this->middleware('permission:'.$this->access.'-edit', ['only' => ['edit','update']]);
        $this->middleware('permission:'.$this->access.'-delete', ['only' => ['destroy']]);
    }

    public function index()
    {
        $data['title']  = $this->title;
        $data['route']  = $this->route;
        $data['view']   = $this->view;
        $data['access'] = $this->access;

        $data['rows'] = Announcement::with('language')
                        ->orderByDesc('created_at')
                        ->get();

        return view($this->view.'.index', $data);
    }

    public function create()
    {
        $data['title']  = $this->title;
        $data['route']  = $this->route;
        $data['view']   = $this->view;
        $data['access'] = $this->access;
        $data['languages'] = Language::all();

        return view($this->view.'.create', $data);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'language_id' => 'nullable|exists:languages,id',
            'message' => 'required|string',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'status' => 'required|in:0,1',
        ]);

        Announcement::create($data);

        return redirect()->route($this->route.'.index')->with('success','Announcement created successfully');
    }

    public function edit(Announcement $announcement)
    {
        $data['title']  = $this->title;
        $data['route']  = $this->route;
        $data['view']   = $this->view;
        $data['access'] = $this->access;
        $data['row'] = $announcement;
        $data['languages'] = Language::all();

        return view($this->view.'.edit', $data);
    }

    public function update(Request $request, Announcement $announcement)
    {
        $data = $request->validate([
            'language_id' => 'nullable|exists:languages,id',
            'message' => 'required|string',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'status' => 'required|in:0,1',
        ]);

        $announcement->update($data);

        return redirect()->route($this->route.'.index')->with('success','Announcement updated successfully');
    }

    public function destroy(Announcement $announcement)
    {
        $announcement->delete();
        return redirect()->route($this->route.'.index')->with('success','Announcement deleted');
    }
}
