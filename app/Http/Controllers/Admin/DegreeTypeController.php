<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Flasher\Laravel\Facade\Flasher;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use App\Models\DegreeType;
use App\Models\DegreeTypeFieldSetting;
use App\Models\DegreeTypeDocument;
use App\Models\DegreeTypeQualification;
use App\Models\DegreeTypeApplicationSetting;
use App\Models\Field;

class DegreeTypeController extends Controller
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
        $this->title = trans_choice('module_degree_type', 1);
        $this->route = 'admin.degree-type';
        $this->view = 'admin.degree-type';
        $this->path = 'degree-type';
        $this->access = 'degree-type';

        $this->middleware('permission:'.$this->access.'-view|'.$this->access.'-create|'.$this->access.'-edit|'.$this->access.'-delete', ['only' => ['index','show','downloadBlankForm','previewAcceptanceLetter']]);
        $this->middleware('permission:'.$this->access.'-create', ['only' => ['create','store']]);
        $this->middleware('permission:'.$this->access.'-edit', ['only' => ['edit','update','formConfig','saveFormConfig']]);
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

        $data['rows'] = DegreeType::orderBy('sort_order', 'asc')->orderBy('title', 'asc')->get();

        return view($this->view.'.index', $data);
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
            'title' => 'required|max:100|unique:degree_types,title',
            'shortcode' => 'required|max:20|unique:degree_types,shortcode',
            'level' => 'required|max:50',
            'duration_years' => 'nullable|integer|min:1|max:10',
            'min_credits' => 'nullable|integer|min:0|max:500',
            'sort_order' => 'nullable|integer|min:0',
        ]);

        // Insert Data
        $degreeType = new DegreeType;
        $degreeType->title = $request->title;
        $degreeType->shortcode = strtoupper($request->shortcode);
        $degreeType->code_append_to_student_matricule = $request->code_append_to_student_matricule ? strtoupper($request->code_append_to_student_matricule) : null;
        $degreeType->level = $request->level;
        $degreeType->duration_years = $request->duration_years;
        $degreeType->min_credits = $request->min_credits;
        $degreeType->slug = Str::slug($request->title, '-');
        $degreeType->description = $request->description;
        $degreeType->requirements = $request->requirements;
        $degreeType->sort_order = $request->sort_order ?? 0;
        $degreeType->status = $request->status;
        $degreeType->is_hnd = $request->has('is_hnd') ? 1 : 0;
        $degreeType->is_postgraduate = $request->has('is_postgraduate') ? 1 : 0;
        $degreeType->save();

        Flasher::addSuccess(__('msg_created_successfully'), __('msg_success'));

        return redirect()->back();
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show(DegreeType $degreeType)
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
    public function update(Request $request, DegreeType $degreeType)
    {
        // Field Validation
        $request->validate([
            'title' => 'required|max:100|unique:degree_types,title,'.$degreeType->id,
            'shortcode' => 'required|max:20|unique:degree_types,shortcode,'.$degreeType->id,
            'level' => 'required|max:50',
            'duration_years' => 'nullable|integer|min:1|max:10',
            'min_credits' => 'nullable|integer|min:0|max:500',
            'sort_order' => 'nullable|integer|min:0',
        ]);

        // Update Data
        $degreeType->title = $request->title;
        $degreeType->shortcode = strtoupper($request->shortcode);
        $degreeType->code_append_to_student_matricule = $request->code_append_to_student_matricule ? strtoupper($request->code_append_to_student_matricule) : null;
        $degreeType->level = $request->level;
        $degreeType->duration_years = $request->duration_years;
        $degreeType->min_credits = $request->min_credits;
        $degreeType->slug = Str::slug($request->title, '-');
        $degreeType->description = $request->description;
        $degreeType->requirements = $request->requirements;
        $degreeType->sort_order = $request->sort_order ?? 0;
        $degreeType->status = $request->status;
        $degreeType->is_hnd = $request->has('is_hnd') ? 1 : 0;
        $degreeType->is_postgraduate = $request->has('is_postgraduate') ? 1 : 0;
        $degreeType->save();

        Flasher::addSuccess(__('msg_updated_successfully'), __('msg_success'));

        return redirect()->back();
    }

    /**
     * Show the per-degree-type application FORM CONFIGURATION (which sections/
     * fields are enabled, the document checklist, the intro/requirements text and
     * the admission fee). This is what makes each degree type's online application
     * form different.
     */
    public function formConfig(DegreeType $degreeType)
    {
        $data['title'] = $this->title . ' — ' . __('Form Configuration');
        $data['route'] = $this->route;
        $data['view'] = $this->view;
        $data['access'] = $this->access;

        $data['degreeType'] = $degreeType;
        // Application-form field slugs only (they all start with application_).
        $data['fields'] = Field::where('slug', 'like', 'application_%')->orderBy('slug')->get();
        $data['fieldMap'] = $degreeType->fieldSettings()->pluck('status', 'slug')->toArray();
        $data['documents'] = $degreeType->applicationDocuments()->orderBy('sort_order')->get();
        $data['qualifications'] = $degreeType->applicationQualifications()->orderBy('sort_order')->get();
        $data['settings'] = $degreeType->applicationSetting;

        return view($this->view.'.form-config', $data);
    }

    /**
     * Persist the per-degree-type form configuration.
     */
    public function saveFormConfig(Request $request, DegreeType $degreeType)
    {
        // 1) Field/section toggles. Every slug rendered is listed in all_slugs[];
        //    a checked box appears in fields[<slug>].
        $checked = (array) $request->input('fields', []);
        foreach ((array) $request->input('all_slugs', []) as $slug) {
            DegreeTypeFieldSetting::updateOrCreate(
                ['degree_type_id' => $degreeType->id, 'slug' => $slug],
                ['status' => isset($checked[$slug]) ? 1 : 0]
            );
        }

        // 2a) Qualification cards. Saved before documents so a document can be
        //     attached to a card created in the same request.
        $qualDeleteIds = (array) $request->input('qual_delete', []);
        foreach ((array) $request->input('qual', []) as $qualId => $row) {
            $qual = DegreeTypeQualification::where('degree_type_id', $degreeType->id)->find($qualId);
            if (!$qual) {
                continue;
            }
            if (in_array($qualId, $qualDeleteIds)) {
                // Release the documents first, otherwise they would point at a
                // card that no longer exists and vanish from the form entirely.
                DegreeTypeDocument::where('degree_type_id', $degreeType->id)
                    ->where('qualification_group', $qual->qual_key)
                    ->update(['qualification_group' => null]);
                $qual->delete();
                continue;
            }
            $qual->label = $row['label'] ?? $qual->label;
            $qual->description = $row['description'] ?? null;
            $qual->required = !empty($row['required']);
            $qual->status = !empty($row['status']);
            $qual->sort_order = (int) ($row['sort_order'] ?? $qual->sort_order);
            $qual->save();
        }

        // 2b) New qualification cards.
        $newQualLabels = (array) $request->input('newqual_label', []);
        $newQualKeys = (array) $request->input('newqual_key', []);
        $newQualRequired = (array) $request->input('newqual_required', []);
        foreach ($newQualLabels as $i => $label) {
            if (!filled($label)) {
                continue;
            }
            $key = filled($newQualKeys[$i] ?? null) ? Str::slug($newQualKeys[$i], '_') : Str::slug($label, '_');
            DegreeTypeQualification::updateOrCreate(
                ['degree_type_id' => $degreeType->id, 'qual_key' => $key],
                [
                    'label' => $label,
                    'required' => !empty($newQualRequired[$i]),
                    'status' => 1,
                    'sort_order' => 100 + $i,
                ]
            );
        }

        // 3) Existing documents (update / delete).
        $deleteIds = (array) $request->input('doc_delete', []);
        foreach ((array) $request->input('doc', []) as $docId => $row) {
            $doc = DegreeTypeDocument::where('degree_type_id', $degreeType->id)->find($docId);
            if (!$doc) {
                continue;
            }
            if (in_array($docId, $deleteIds)) {
                $doc->delete();
                continue;
            }
            $doc->label = $row['label'] ?? $doc->label;
            $doc->description = $row['description'] ?? null;
            // Blank means "collect it on the Documents step".
            $doc->qualification_group = filled($row['qualification_group'] ?? null)
                ? $row['qualification_group']
                : null;
            $doc->required = !empty($row['required']);
            $doc->status = !empty($row['status']);
            $doc->sort_order = (int) ($row['sort_order'] ?? $doc->sort_order);
            $doc->save();
        }

        // 3) New documents.
        $newLabels = (array) $request->input('newdoc_label', []);
        $newKeys = (array) $request->input('newdoc_key', []);
        $newRequired = (array) $request->input('newdoc_required', []);
        foreach ($newLabels as $i => $label) {
            if (!filled($label)) {
                continue;
            }
            $key = filled($newKeys[$i] ?? null) ? Str::slug($newKeys[$i], '_') : Str::slug($label, '_');
            DegreeTypeDocument::updateOrCreate(
                ['degree_type_id' => $degreeType->id, 'doc_key' => $key],
                [
                    'label' => $label,
                    'required' => !empty($newRequired[$i]),
                    'status' => 1,
                    'sort_order' => 100 + $i,
                ]
            );
        }

        // 4) Settings (intro / requirements / fee).
        DegreeTypeApplicationSetting::updateOrCreate(
            ['degree_type_id' => $degreeType->id],
            [
                'intro_html' => $request->input('intro_html'),
                'requirements_html' => $request->input('requirements_html'),
                'fee_enabled' => $request->boolean('fee_enabled'),
                'fee_amount' => $request->input('fee_amount'),
                'fee_due_days' => $request->input('fee_due_days'),
                'fee_instructions' => $request->input('fee_instructions'),
                'acceptance_letter_enabled' => $request->boolean('acceptance_letter_enabled'),
                'acceptance_letter_html' => $request->input('acceptance_letter_html'),
            ]
        );

        Flasher::addSuccess(__('msg_updated_successfully'), __('msg_success'));
        return redirect()->back();
    }

    /**
     * Stream a sample PDF of this degree type's acceptance letter (admin preview).
     */
    public function previewAcceptanceLetter(DegreeType $degreeType, \App\Services\AcceptanceLetterService $service)
    {
        $degreeType->load('applicationSetting');
        return $service->previewPdf($degreeType)->stream('acceptance-letter-preview.pdf');
    }

    /**
     * Download a blank, printable application form PDF for this degree type.
     */
    public function downloadBlankForm(DegreeType $degreeType, \App\Services\BlankApplicationFormService $service)
    {
        $filename = str_replace(' ', '_', $degreeType->title) . '_Application_Form.pdf';
        return $service->pdf($degreeType)->download($filename);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy(DegreeType $degreeType)
    {
        // Check if degree type has programs
        if($degreeType->programs()->count() > 0)
        {
            Flasher::addError(__('msg_cannot_delete_has_programs'), __('msg_error'));
            return redirect()->back();
        }

        // Delete Data
        $degreeType->delete();

        Flasher::addSuccess(__('msg_deleted_successfully'), __('msg_success'));

        return redirect()->back();
    }
}
