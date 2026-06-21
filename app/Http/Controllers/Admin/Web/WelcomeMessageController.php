<?php

namespace App\Http\Controllers\Admin\Web;

use App\Http\Controllers\Controller;
use Flasher\Laravel\Facade\Flasher;
use Illuminate\Http\Request;
use App\Traits\FileUploader;
use App\Models\Web\WelcomeMessage;
use App\Models\Language;

class WelcomeMessageController extends Controller
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
        $this->title    = trans_choice('module_welcome_message', 1);
        $this->route    = 'admin.welcome-message';
        $this->view     = 'admin.web.welcome-message';
        $this->path     = 'welcome-message';
        $this->access   = 'welcome-message';


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
        $data['title']  = $this->title;
        $data['route']  = $this->route;
        $data['view']   = $this->view;
        $data['path']   = $this->path;
        $data['access'] = $this->access;

        $data['rows'] = WelcomeMessage::orderBy('sort_order', 'asc')->get();

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
        $data['title']  = $this->title;
        $data['route']  = $this->route;
        $data['view']   = $this->view;

        $data['languages'] = Language::where('status', '1')->orderBy('name', 'asc')->get();

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
            'language_id' => 'required',
            'title' => 'required|max:191',
            'message' => 'required',
            'designation' => 'nullable|max:191',
            'image' => 'nullable|image',
            'sort_order' => 'required|integer',
            'status' => 'required',
        ]);


        // Insert Data
        $welcomeMessage = new WelcomeMessage;
        $welcomeMessage->language_id = $request->language_id;
        $welcomeMessage->title = $request->title;
        $welcomeMessage->message = $request->message;
        $welcomeMessage->designation = $request->designation;
        $welcomeMessage->image = $this->uploadImage($request, 'image', $this->path, 400, 400);
        $welcomeMessage->sort_order = $request->sort_order;
        $welcomeMessage->status = $request->status;
        $welcomeMessage->save();


        Flasher::addSuccess(__('msg_added_successfully'), __('msg_success'));

        return redirect()->route($this->route.'.index');
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
        $data['title']  = $this->title;
        $data['route']  = $this->route;
        $data['view']   = $this->view;
        $data['path']   = $this->path;

        $data['languages'] = Language::where('status', '1')->orderBy('name', 'asc')->get();
        $data['row'] = WelcomeMessage::findOrFail($id);

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
            'language_id' => 'required',
            'title' => 'required|max:191',
            'message' => 'required',
            'designation' => 'nullable|max:191',
            'image' => 'nullable|image',
            'sort_order' => 'required|integer',
            'status' => 'required',
        ]);


        // Update Data
        $welcomeMessage = WelcomeMessage::findOrFail($id);
        $welcomeMessage->language_id = $request->language_id;
        $welcomeMessage->title = $request->title;
        $welcomeMessage->message = $request->message;
        $welcomeMessage->designation = $request->designation;
        $welcomeMessage->image = $this->updateImage($request, 'image', $this->path, 400, 400, $welcomeMessage, 'image');
        $welcomeMessage->sort_order = $request->sort_order;
        $welcomeMessage->status = $request->status;
        $welcomeMessage->save();


        Flasher::addSuccess(__('msg_updated_successfully'), __('msg_success'));

        return redirect()->route($this->route.'.index');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        // Delete Data
        $welcomeMessage = WelcomeMessage::findOrFail($id);

        $this->deleteImage($this->path, $welcomeMessage, 'image');
        
        $welcomeMessage->delete();

        Flasher::addSuccess(__('msg_deleted_successfully'), __('msg_success'));

        return redirect()->route($this->route.'.index');
    }
}
