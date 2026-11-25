<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CourseGroup;
use App\Models\Subject;
use App\Services\Academic\CourseGroups;
use Flasher\Laravel\Facade\Flasher;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Academic > Course groups: link courses that are one course taught to
 * several programmes under each programme's own code. Each course keeps its
 * code, credits, marks and transcript; the group makes joint classes, class
 * sessions, exam timetables and the shared mark sheet treat them as one.
 */
class CourseGroupController extends Controller
{
    protected $title, $route, $view, $path, $access;

    public function __construct()
    {
        $this->title = __('Course groups');
        $this->route = 'admin.course-group';
        $this->view = 'admin.course-group';
        $this->path = 'course-group';
        $this->access = 'course-group';

        $this->middleware('permission:' . $this->access . '-view|' . $this->access . '-create|' . $this->access . '-edit|' . $this->access . '-delete', ['only' => ['index']]);
        $this->middleware('permission:' . $this->access . '-create', ['only' => ['create', 'store']]);
        $this->middleware('permission:' . $this->access . '-edit', ['only' => ['edit', 'update']]);
        $this->middleware('permission:' . $this->access . '-delete', ['only' => ['destroy']]);
    }

    private function base(): array
    {
        return ['title' => $this->title, 'route' => $this->route, 'view' => $this->view, 'path' => $this->path, 'access' => $this->access];
    }

    public function index()
    {
        $data = $this->base();
        $data['rows'] = CourseGroup::with('subjects')->orderBy('title')->get();
        $data['programmes'] = CourseGroups::programmesOf($data['rows']->flatMap->subjects->pluck('id')->all());
        $data['programNames'] = DB::table('programs')->pluck('shortcode', 'id')->map(fn ($s) => trim(str_replace(['HND - ', 'HND- '], '', (string) $s)));
        $data['suggestions'] = $this->suggestions();

        return view($this->view . '.index', $data);
    }

    public function create(Request $request)
    {
        $data = $this->base();
        $data['row'] = new CourseGroup(['status' => 1]);
        $data['selected'] = array_map('intval', (array) $request->get('subjects', []));
        if (!$data['row']->title && $data['selected']) {
            $data['row']->title = Subject::whereIn('id', $data['selected'])->value('title');
        }
        $data += $this->formData();

        return view($this->view . '.form', $data);
    }

    public function store(Request $request)
    {
        $subjects = $this->validated($request);
        $problems = CourseGroups::check($subjects);
        if ($problems) {
            return back()->withInput()->withErrors(['subjects' => $problems]);
        }

        DB::transaction(function () use ($request, $subjects) {
            $group = CourseGroup::create([
                'title' => $request->title, 'note' => $request->note, 'status' => 1, 'created_by' => auth()->id(),
            ]);
            CourseGroups::setMembers($group, $subjects);
        });

        Flasher::addSuccess(__('Course group saved.'), __('msg_success'));

        return redirect()->route($this->route . '.index');
    }

    public function edit(CourseGroup $courseGroup)
    {
        $data = $this->base();
        $data['row'] = $courseGroup;
        $data['selected'] = $courseGroup->subjects->pluck('id')->map(fn ($id) => (int) $id)->all();
        $data += $this->formData();

        return view($this->view . '.form', $data);
    }

    public function update(Request $request, CourseGroup $courseGroup)
    {
        $subjects = $this->validated($request);
        $problems = CourseGroups::check($subjects, $courseGroup->id);
        if ($problems) {
            return back()->withInput()->withErrors(['subjects' => $problems]);
        }

        DB::transaction(function () use ($request, $subjects, $courseGroup) {
            $courseGroup->update(['title' => $request->title, 'note' => $request->note, 'updated_by' => auth()->id()]);
            CourseGroups::setMembers($courseGroup, $subjects);
        });

        Flasher::addSuccess(__('Course group saved.'), __('msg_success'));

        return redirect()->route($this->route . '.index');
    }

    /** Deleting a group only unlinks its courses: no course, class or mark is touched. */
    public function destroy(CourseGroup $courseGroup)
    {
        DB::transaction(function () use ($courseGroup) {
            Subject::where('course_group_id', $courseGroup->id)->update(['course_group_id' => null]);
            $courseGroup->delete();
        });
        CourseGroups::forget();

        Flasher::addSuccess(__('Course group removed. Its courses are separate courses again.'), __('msg_success'));

        return redirect()->route($this->route . '.index');
    }

    /** @return int[] */
    private function validated(Request $request): array
    {
        $request->validate([
            'title' => 'required|string|max:191',
            'note' => 'nullable|string|max:2000',
            'subjects' => 'required|array|min:2',
            'subjects.*' => 'integer|exists:subjects,id',
        ], [
            'subjects.min' => __('A course group needs at least two courses.'),
            'subjects.required' => __('A course group needs at least two courses.'),
        ]);

        return array_values(array_unique(array_map('intval', $request->subjects)));
    }

    private function formData(): array
    {
        $subjects = Subject::where('status', 1)->with('courseGroup')->orderBy('code')->get();
        $programmes = CourseGroups::programmesOf($subjects->pluck('id')->all());
        $names = DB::table('programs')->pluck('shortcode', 'id')->map(fn ($s) => trim(str_replace(['HND - ', 'HND- '], '', (string) $s)));

        return [
            'subjects' => $subjects,
            'subjectProgrammes' => collect($programmes)->map(fn ($ps) => collect($ps)->map(fn ($p) => $names[$p] ?? $p)->sort()->implode(', ')),
        ];
    }

    /**
     * Courses not in any group whose titles are the same, which may be one
     * course under several codes. Only a suggestion: a Year 1 and a Year 2
     * course can share a name, so nothing is grouped without a person.
     */
    private function suggestions()
    {
        $norm = function ($t) {
            $t = strtolower(str_replace('&', ' and ', (string) $t));
            $t = preg_replace('/[^a-z0-9 ]/', ' ', $t);
            $map = ['iii' => '3', 'ii' => '2', 'i' => '1', 'iv' => '4'];
            $w = array_filter(array_map(fn ($x) => $map[$x] ?? rtrim($x, 's'), preg_split('/\s+/', trim($t))), fn ($x) => $x !== '' && !in_array($x, ['and', 'of', 'the', 'for', 'in', 'a', 'an'], true));

            return implode(' ', $w);
        };

        $free = Subject::where('status', 1)->whereNull('course_group_id')->orderBy('code')->get();
        $programmes = CourseGroups::programmesOf($free->pluck('id')->all());

        // Same title and same level (the first digit of the code: ENG1201 is
        // Year 1, ENG2101 Year 2).
        return $free->groupBy(fn ($s) => $norm($s->title) . '|' . (preg_match('/\d/', (string) $s->code, $m) ? $m[0] : ''))
            ->filter(fn ($g) => $g->count() > 1)
            ->map(function ($g) use ($programmes) {
                // Leave out pairs one programme takes both of: two courses to it.
                $seen = [];
                $clash = false;
                foreach ($g as $s) {
                    foreach ($programmes[$s->id] ?? [] as $p) {
                        $clash = $clash || isset($seen[$p]);
                        $seen[$p] = true;
                    }
                }

                return $clash ? null : $g;
            })
            ->filter()
            ->values();
    }
}
