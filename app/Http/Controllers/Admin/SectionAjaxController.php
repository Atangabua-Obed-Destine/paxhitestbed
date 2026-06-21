<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Section;
use App\Models\Program;
use Illuminate\Http\Request;

class SectionAjaxController extends Controller
{
    /**
     * Get section edit form data via AJAX
     */
    public function getEditData($id)
    {
        $section = Section::with(['semesterPrograms'])->findOrFail($id);
        
        $programs = Program::where('status', '1')
                    ->with(['semesters' => function($query) {
                        $query->where('status', 1)->orderBy('title', 'asc');
                    }])
                    ->orderBy('title', 'asc')->get();
        
        // Create a lookup array for faster checking
        $selectedCombos = $section->semesterPrograms->map(function($sp) {
            return $sp->program_id . '_' . $sp->semester_id;
        })->toArray();
        
        return response()->json([
            'section' => $section,
            'programs' => $programs,
            'selectedCombos' => $selectedCombos
        ]);
    }
}
