<?php

namespace App\Http\Controllers\Admin;

use App\Models\ProgramSemesterSection;
use App\Http\Controllers\Controller;
use Flasher\Laravel\Facade\Flasher;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use App\Models\Section;
use App\Models\Program;

class SectionController extends Controller
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
        $this->title = trans_choice('module_section', 1);
        $this->route = 'admin.section';
        $this->view = 'admin.section';
        $this->path = 'section';
        $this->access = 'section';


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

        $data['programs'] = Program::where('status', '1')
                            ->with(['semesters' => function($query) {
                                $query->where('status', 1)->orderBy('title', 'asc');
                            }])
                            ->orderBy('title', 'asc')->get();
        
        // Eager load relationships to avoid N+1 query problem
        $data['rows'] = Section::withCount('semesterPrograms')
                            ->with(['semesterPrograms' => function($query) {
                                $query->with(['program', 'semester']);
                            }])
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
            'title' => 'required|max:191|unique:sections,title',
            'programs' => 'required',
            'semesters' => 'required',
            'items' => 'required',
        ]);

        try{
            // Insert Data
            DB::beginTransaction();
            $section = new Section;
            $section->title = $request->title;
            $section->seat = null; // Unlimited capacity
            $section->save();


            // Insert Or Update Data
            foreach($request->items as $item){

                $programSemesterSection = ProgramSemesterSection::updateOrCreate(
                [
                    'program_id' => $request->programs[$item - 1],
                    'semester_id' => $request->semesters[$item - 1],
                    'section_id' => $section->id
                ],[
                    'program_id' => $request->programs[$item - 1],
                    'semester_id' => $request->semesters[$item - 1],
                    'section_id' => $section->id
                ]);
            }
            DB::commit();

            Flasher::addSuccess(__('msg_created_successfully'), __('msg_success'));

            return redirect()->back();
        }
        catch(\Exception $e){
            DB::rollBack();
            
            Flasher::addError(__('msg_created_error') . ': ' . $e->getMessage(), __('msg_error'));

            return redirect()->back();
        }
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show(Section $section)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit(Section $section)
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
    public function update(Request $request, Section $section)
    {
        // Field Validation
        $request->validate([
            'title' => 'required|max:191|unique:sections,title,'.$section->id,
            'program_semesters_json' => 'required|string',
        ]);

        try{
            // Decode JSON data
            $programSemesters = json_decode($request->program_semesters_json, true);
            
            // Log for debugging
            \Log::info('Section Update - Received data:', [
                'json_length' => strlen($request->program_semesters_json),
                'decoded_count' => is_array($programSemesters) ? count($programSemesters) : 0,
            ]);
            
            if (!is_array($programSemesters) || empty($programSemesters)) {
                throw new \Exception('Invalid program_semesters data');
            }

            // Update Data
            DB::beginTransaction();
            $section->title = $request->title;
            $section->seat = null; // Unlimited capacity
            $section->status = $request->status ?? 1;
            $section->save();

            // Delete existing associations
            $section->programSemesters()->delete();

            // Insert new associations in batches for better performance
            $insertData = [];
            foreach($programSemesters as $programSemester){
                // Split the value "program_id_semester_id"
                list($programId, $semesterId) = explode('_', $programSemester);

                $insertData[] = [
                    'program_id' => $programId,
                    'semester_id' => $semesterId,
                    'section_id' => $section->id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }

            // Batch insert for better performance
            if (!empty($insertData)) {
                // Insert in chunks of 500 to avoid query size limits
                foreach(array_chunk($insertData, 500) as $chunk) {
                    ProgramSemesterSection::insert($chunk);
                }
            }
            DB::commit();

            Flasher::addSuccess(__('msg_updated_successfully'), __('msg_success'));

            return redirect()->back();
        }
        catch(\Exception $e){
            DB::rollBack();

            Flasher::addError(__('msg_updated_error') . ': ' . $e->getMessage(), __('msg_error'));

            return redirect()->back();
        }
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy(Section $section)
    {
        // Delete Data
        $section->delete();

        Flasher::addSuccess(__('msg_deleted_successfully'), __('msg_success'));

        return redirect()->back();
    }
}
