<?php

namespace App\Http\Controllers\Admin\Web;

use App\Http\Controllers\Controller;
use Flasher\Laravel\Facade\Flasher;
use Illuminate\Http\Request;
use App\Models\HistoryTimeline;

class HistoryTimelineController extends Controller
{
    protected $title, $route, $view, $access;

    public function __construct()
    {
        $this->title = trans_choice('module_history_timeline', 1);
        $this->route = 'admin.history-timeline';
        $this->view = 'admin.history-timeline';
        $this->access = 'history-timeline';

        $this->middleware('permission:'.$this->access.'-view|'.$this->access.'-create|'.$this->access.'-edit|'.$this->access.'-delete', ['only' => ['index']]);
        $this->middleware('permission:'.$this->access.'-create', ['only' => ['store']]);
        $this->middleware('permission:'.$this->access.'-edit', ['only' => ['update']]);
        $this->middleware('permission:'.$this->access.'-delete', ['only' => ['destroy']]);
    }

    public function index()
    {
        $data['title'] = $this->title;
        $data['route'] = $this->route;
        $data['view'] = $this->view;
        $data['access'] = $this->access;
        $data['rows'] = HistoryTimeline::orderBy('sort_order', 'asc')->get();

        return view($this->view.'.index', $data);
    }

    public function store(Request $request)
    {
        $request->validate([
            'year' => 'required|max:50',
            'title' => 'required|max:191',
            'description' => 'required',
        ]);

        $timeline = new HistoryTimeline;
        $timeline->year = $request->year;
        $timeline->title = $request->title;
        $timeline->description = $request->description;
        $timeline->sort_order = $request->sort_order ?? 0;
        $timeline->save();

        Flasher::addSuccess(__('msg_created_successfully'), __('msg_success'));
        return redirect()->back();
    }

    public function update(Request $request, HistoryTimeline $historyTimeline)
    {
        $request->validate([
            'year' => 'required|max:50',
            'title' => 'required|max:191',
            'description' => 'required',
        ]);

        $historyTimeline->year = $request->year;
        $historyTimeline->title = $request->title;
        $historyTimeline->description = $request->description;
        $historyTimeline->sort_order = $request->sort_order ?? 0;
        $historyTimeline->status = $request->status;
        $historyTimeline->save();

        Flasher::addSuccess(__('msg_updated_successfully'), __('msg_success'));
        return redirect()->back();
    }

    public function destroy(HistoryTimeline $historyTimeline)
    {
        $historyTimeline->delete();

        Flasher::addSuccess(__('msg_deleted_successfully'), __('msg_success'));
        return redirect()->back();
    }
}
