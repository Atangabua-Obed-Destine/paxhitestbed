<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\TimetableBreak;
use Flasher\Laravel\Facade\Flasher;
use Illuminate\Http\Request;

/**
 * Routine > Timetable breaks: the blocks of the week that are not classes
 * (the midday break and Holy Mass, Friday's club activities), shown on every
 * class timetable, the printed timetables and the students' own week.
 */
class TimetableBreakController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:class-routine-view|class-routine-create|class-routine-print', ['only' => ['index']]);
        $this->middleware('permission:class-routine-create', ['only' => ['store', 'destroy']]);
    }

    public function index()
    {
        return view('admin.timetable-break.index', [
            'title' => __('Timetable breaks'),
            'rows' => TimetableBreak::orderBy('start_time')->get(),
            'days' => [3 => 'Monday', 4 => 'Tuesday', 5 => 'Wednesday', 6 => 'Thursday', 7 => 'Friday', 1 => 'Saturday', 2 => 'Sunday'],
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'title' => 'required|string|max:191',
            'start_time' => 'required|date_format:H:i',
            'end_time' => 'required|date_format:H:i|after:start_time',
            'days' => 'nullable|array',
            'days.*' => 'integer|between:1,7',
        ]);

        TimetableBreak::create([
            'title' => $request->title,
            'start_time' => $request->start_time,
            'end_time' => $request->end_time,
            'days' => array_values(array_map('intval', (array) $request->days)) ?: null,
            'status' => 1,
        ]);
        Flasher::addSuccess(__('Break saved. It now shows on every timetable.'), __('msg_success'));

        return redirect()->route('admin.timetable-break.index');
    }

    public function destroy(TimetableBreak $timetableBreak)
    {
        $timetableBreak->delete();
        Flasher::addSuccess(__('Break removed.'), __('msg_success'));

        return redirect()->route('admin.timetable-break.index');
    }
}
