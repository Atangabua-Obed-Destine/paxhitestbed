<?php

namespace App\Http\Controllers\Admin;

use Illuminate\Support\Facades\Notification;
use App\Notifications\NoticeNotification;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Flasher\Laravel\Facade\Flasher;
use Illuminate\Support\Facades\DB;
use App\Models\NoticeCategory;
use Illuminate\Http\Request;
use App\Traits\FileUploader;
use App\Models\Faculty;
use App\Services\StaffAssignmentService;
use App\Models\Student;
use App\Models\Notice;
use Carbon\Carbon;

class NoticeController extends Controller
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
        $this->title = trans_choice('module_notice', 1);
        $this->route = 'admin.notice';
        $this->view = 'admin.notice';
        $this->path = 'notice';
        $this->access = 'notice';


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
    public function index(Request $request)
    {
        //
        $data['title'] = $this->title;
        $data['route'] = $this->route;
        $data['view'] = $this->view;
        $data['path'] = $this->path;
        $data['access'] = $this->access;


        if(!empty($request->title) || $request->title != null){
            $data['selected_title'] = $title = $request->title;
        }
        else{
            $data['selected_title'] = $title = null;
        }

        if(!empty($request->category) || $request->category != null){
            $data['selected_category'] = $category = $request->category;
        }
        else{
            $data['selected_category'] = $category = '0';
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
        $data['categories'] = NoticeCategory::where('status', '1')
                            ->orderBy('title', 'asc')->get();

        $rows = Notice::whereDate('date', '>=', $start_date)
                    ->whereDate('date', '<=', $end_date);
                    if(!empty($request->title) || $request->title != null){
                        $rows->where('title', 'LIKE', '%'.$title.'%');
                    }
                    if(!empty($request->category) || $request->category != null){
                        $rows->where('category_id', $category);
                    }
        $data['rows'] = $rows->orderBy('id', 'desc')->get();


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
        $data['title'] = $this->title;
        $data['route'] = $this->route;
        $data['view'] = $this->view;

        $data['faculties'] = Faculty::where('status', '1')
                            ->orderBy('title', 'asc')->get();
        $data['categories'] = NoticeCategory::where('status', '1')
                            ->orderBy('title', 'asc')->get();

        // Auto-generate next notice number
        $lastNotice = Notice::orderBy('notice_no', 'desc')->first();
        $data['next_notice_no'] = $lastNotice ? ($lastNotice->notice_no + 1) : 1;

        return view($this->view.'.create', $data);
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
            'faculty' => 'required',
            'program' => 'required',
            'session' => 'required',
            'semester' => 'required',
            'section' => 'required',
            'category' => 'required|integer',
            'notice_no' => 'required|numeric|unique:notices,notice_no',
            'title' => 'required|max:191',
            'date' => 'required|date|after_or_equal:today',
            'attach' => 'nullable|file|mimes:jpg,jpeg,png,pdf,doc,docx,zip,rar,csv,xls,xlsx,ppt,pptx|max:20480',
        ]);


        try{
            DB::beginTransaction();
            // Insert Data
            $notice = new Notice;
            $notice->faculty_id = $request->faculty;
            $notice->program_id = $request->program;
            $notice->session_id = $request->session;
            $notice->semester_id = $request->semester;
            $notice->section_id = $request->section;
            $notice->category_id = $request->category;
            $notice->notice_no = $request->notice_no;
            $notice->title = $request->title;
            $notice->description = $request->description;
            $notice->date = $request->date;
            $notice->attach = $this->uploadMedia($request, 'attach', $this->path);
            $notice->created_by = Auth::guard('web')->user()->id;
            $notice->save();


            // Set Value
            $faculty = $request->faculty;
            $program = $request->program;
            $session = $request->session;
            $semester = $request->semester;
            $section = $request->section;

            // Student Filter
            $students = Student::where('status', '1');
            if($faculty != 0){
                $students->with('program')->whereHas('program', function ($query) use ($faculty){
                    $query->where('faculty_id', $faculty);
                });
            }
            $students->with('currentEnroll')->whereHas('currentEnroll', function ($query) use ($program, $session, $semester, $section){
                if($program != 0){
                $query->where('program_id', $program);
                }
                if($session != 0){
                $query->where('session_id', $session);
                }
                if($semester != 0){
                $query->where('semester_id', $semester);
                }
                if($section != 0){
                $query->where('section_id', $section);
                }
                $query->where('status', '1');
            });
            $all_students = $students->orderBy('matricule', 'desc')->get();

            // Attach Data
            $notice->students()->attach($all_students);


            // Notification Data
            $data = [
                'id' => $notice->id,
                'title' => $notice->title,
                'type' => 'notice'
            ];

            $today_date = Carbon::parse(Carbon::today())->format('Y-m-d');

            if($request->date == $today_date){

                Notification::send($all_students, new NoticeNotification($data));
            }
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
    public function show(Notice $notice)
    {
        //
        $data['title'] = $this->title;
        $data['route'] = $this->route;
        $data['view'] = $this->view;
        $data['path'] = $this->path;

        $data['row'] = $notice;

        return view($this->view.'.show', $data);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit(Notice $notice)
    {
        //
        $data['title'] = $this->title;
        $data['route'] = $this->route;
        $data['view'] = $this->view;
        $data['path'] = $this->path;

        $data['row'] = $notice;

        $data['categories'] = NoticeCategory::where('status', '1')
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
    public function update(Request $request, Notice $notice)
    {
        // Field Validation
        $request->validate([
            'category' => 'required|integer',
            'notice_no' => 'required|numeric|unique:notices,notice_no,'.$notice->id,
            'date' => 'required|date',
            'attach' => 'nullable|file|mimes:jpg,jpeg,png,pdf,doc,docx,zip,rar,csv,xls,xlsx,ppt,pptx|max:20480',
        ]);


        // Update Data
        $notice->category_id = $request->category;
        $notice->notice_no = $request->notice_no;
        // $notice->title = $request->title;
        $notice->description = $request->description;
        $notice->date = $request->date;
        $notice->attach = $this->updateMedia($request, 'attach', $this->path, $notice);
        $notice->status = $request->status;
        $notice->updated_by = Auth::guard('web')->user()->id;
        $notice->save();


        Flasher::addSuccess(__('msg_updated_successfully'), __('msg_success'));

        return redirect()->back();
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy(Notice $notice)
    {
        DB::beginTransaction();
        // Delete Attach File
        $this->deleteMedia($this->path, $notice);

        // Delete Attach
        $notice->students()->detach();
        $notice->users()->detach();

        // Delete Notification
        DB::table('notifications')->where('type', 'App\Notifications\NoticeNotification')->where('data->id', $notice->id)->delete();

        //Delete Data
        $notice->delete();
        DB::commit();

        Flasher::addSuccess(__('msg_deleted_successfully'), __('msg_success'));

        return redirect()->back();
    }
}
