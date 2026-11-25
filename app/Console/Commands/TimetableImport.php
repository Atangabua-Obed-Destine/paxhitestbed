<?php

namespace App\Console\Commands;

use App\Models\ClassRoom;
use App\Models\ClassRoutine;
use App\Models\Program;
use App\Models\Section;
use App\Models\Semester;
use App\Models\Session;
use App\Models\Subject;
use App\Services\Academic\ClassTimetable;
use App\Services\Academic\CourseGroups;
use App\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Import a class timetable from a CSV, one row per class:
 *
 *   semester,programmes,day,start,end,course,lecturer,room,source
 *   FIRST SEMESTER - Year 1,HND - ACC;HND - MKT,Tuesday,08:00,10:00,ENG1101H,PAX00057,SMBS HALL,(any note)
 *
 * - programmes: shortcodes (or titles) separated by ";" — one row for a class
 *   several programmes sit together;
 * - course: a course code; each programme gets the course it takes: the code
 *   itself, or, when the code is in a course group, the group's course for
 *   that programme (AGE118H for AGE, MGT1101H for ACC);
 * - lecturer: staff ID; room: room title.
 *
 * Shows what it would do and writes nothing unless --apply. A class is left
 * out (and listed) when its programme does not take the course in that
 * semester, when a name cannot be found, or when that programme already has a
 * class at that day and time. Running it twice adds nothing the second time.
 */
class TimetableImport extends Command
{
    protected $signature = 'timetable:import
                            {file : The CSV to read}
                            {--session= : Session title or id (default: the current session)}
                            {--section=All : Section title or id}
                            {--apply : Save the classes (without it, only show what would happen)}';

    protected $description = 'Import a class timetable from a CSV (preview unless --apply)';

    private const DAYS = ['saturday' => 1, 'sunday' => 2, 'monday' => 3, 'tuesday' => 4, 'wednesday' => 5, 'thursday' => 6, 'friday' => 7];

    public function handle(): int
    {
        $file = $this->argument('file');
        if (!is_readable($file)) {
            $this->error("Cannot read $file");

            return 1;
        }

        $session = $this->option('session')
            ? Session::where('id', $this->option('session'))->orWhere('title', $this->option('session'))->first()
            : Session::where('current', 1)->first();
        $section = Section::where('id', $this->option('section'))->orWhere('title', $this->option('section'))->first();
        if (!$session || !$section) {
            $this->error('Session or section not found.');

            return 1;
        }

        [$header, $lines] = $this->read($file);
        foreach (['semester', 'programmes', 'day', 'start', 'end', 'course', 'lecturer', 'room'] as $col) {
            if (!in_array($col, $header, true)) {
                $this->error("The CSV has no \"$col\" column.");

                return 1;
            }
        }

        $teacherRole = DB::table('roles')->where('slug', 'teacher')->value('id');
        $plan = [];      // classes to create
        $left = [];      // [where, why]
        $already = 0;

        foreach ($lines as $n => $row) {
            $line = $n + 2;
            $semester = Semester::where('title', trim($row['semester']))->orWhere('id', trim($row['semester']))->first();
            $day = self::DAYS[strtolower(trim($row['day']))] ?? null;
            $start = $this->time($row['start']);
            $end = $this->time($row['end']);
            $course = Subject::where('code', trim($row['course']))->where('status', 1)->first();
            $lecturer = User::where('staff_id', trim($row['lecturer']))->where('status', 1)->first();
            $room = ClassRoom::where('title', trim($row['room']))->first();
            $where = "line $line ({$row['course']}, {$row['day']} {$row['start']})";

            $missing = array_filter([
                !$semester ? 'semester "' . $row['semester'] . '"' : null,
                !$day ? 'day "' . $row['day'] . '"' : null,
                (!$start || !$end || $end <= $start) ? 'time ' . $row['start'] . '-' . $row['end'] : null,
                !$course ? 'course "' . $row['course'] . '"' : null,
                !$lecturer ? 'lecturer "' . $row['lecturer'] . '"' : null,
                !$room ? 'room "' . $row['room'] . '"' : null,
            ]);
            if ($missing) {
                $left[] = [$where, 'not found: ' . implode(', ', $missing)];
                continue;
            }
            if ($teacherRole && !DB::table('model_has_roles')->where('model_id', $lecturer->id)->where('role_id', $teacherRole)->exists()) {
                $left[] = [$where, "{$lecturer->first_name} {$lecturer->last_name} does not have the Lecturer role"];
                continue;
            }

            foreach (array_filter(array_map('trim', explode(';', $row['programmes']))) as $code) {
                $programme = Program::where('shortcode', $code)->orWhere('title', $code)->first();
                if (!$programme) {
                    $left[] = [$where, "programme \"$code\" not found"];
                    continue;
                }
                $own = $this->courseFor($course, $programme, $semester, $section);
                if (!$own) {
                    $left[] = [$where, "{$programme->shortcode} does not take {$course->code}"
                        . ($course->course_group_id ? ' or any course of its group' : '') . " in {$semester->title}"];
                    continue;
                }
                $taken = ClassRoutine::where('session_id', $session->id)->where('program_id', $programme->id)
                    ->where('semester_id', $semester->id)->where('section_id', $section->id)
                    ->where('day', $day)->where('start_time', $start . ':00')->first();
                $key = "{$programme->id}|{$semester->id}|$day|$start";
                if ($taken && (int) $taken->subject_id === (int) $own->id) {
                    $already++;
                    continue;
                }
                if ($taken || isset($plan[$key])) {
                    $other = $taken ? optional($taken->subject)->code : $plan[$key]['course']->code;
                    $left[] = [$where, "{$programme->shortcode} already has $other at that time"];
                    continue;
                }
                $plan[$key] = [
                    'programme' => $programme, 'semester' => $semester, 'course' => $own, 'day' => $day,
                    'start' => $start, 'end' => $end, 'lecturer' => $lecturer, 'room' => $room, 'line' => $line,
                ];
            }
        }

        // Write inside a transaction so the session's own clash check can judge
        // the new classes beside everything already there; undone unless --apply.
        $clashes = [];
        DB::beginTransaction();
        try {
            $ids = [];
            foreach ($plan as $p) {
                $ids[] = ClassRoutine::create([
                    'teacher_id' => $p['lecturer']->id, 'subject_id' => $p['course']->id, 'room_id' => $p['room']->id,
                    'session_id' => $session->id, 'program_id' => $p['programme']->id, 'semester_id' => $p['semester']->id,
                    'section_id' => $section->id, 'start_time' => $p['start'] . ':00', 'end_time' => $p['end'] . ':00',
                    'day' => $p['day'], 'status' => 1,
                ])->id;
            }
            CourseGroups::forget();
            $scope = ClassRoutine::with(['subject', 'room', 'program', 'teacher'])->where('session_id', $session->id)->where('status', 1)->get();
            foreach ((new ClassTimetable())->conflicts($scope) as $id => $found) {
                if (in_array($id, $ids, true)) {
                    foreach ($found as $f) {
                        if (in_array($f['type'], ['room', 'lecturer', 'clash'], true)) {
                            $r = $scope->firstWhere('id', $id);
                            $clashes[] = [($r->program->shortcode ?? '') . ' ' . ($r->subject->code ?? '') . ' ' . $this->dayName($r->day) . ' ' . substr($r->start_time, 0, 5), $f['note']];
                        }
                    }
                }
            }

            $this->report($session, $section, $plan, $left, $already, $clashes);

            if ($this->option('apply')) {
                DB::commit();
                $this->info(count($plan) . ' classes saved.');
            } else {
                DB::rollBack();
                $this->line('Nothing written. Run again with --apply to save the classes.');
            }
        } catch (\Throwable $e) {
            DB::rollBack();
            $this->error('Nothing saved: ' . $e->getMessage());

            return 1;
        }

        return 0;
    }

    /**
     * The course this programme takes for the class: the code itself when it
     * is in the programme's curriculum for the semester, otherwise its course
     * group's course for the programme. Null when the programme takes neither.
     */
    private function courseFor(Subject $course, Program $programme, Semester $semester, Section $section): ?Subject
    {
        $curriculum = DB::table('enroll_subject_subject as es')->join('enroll_subjects as e', 'e.id', 'es.enroll_subject_id')
            ->where('e.program_id', $programme->id)->where('e.semester_id', $semester->id)
            ->where(fn ($q) => $q->where('e.section_id', $section->id)->orWhereNull('e.section_id'))
            ->pluck('es.subject_id')->map(fn ($id) => (int) $id)->all();

        // A programme with no curriculum filed for the semester falls back on
        // the courses linked to the programme itself.
        if (!$curriculum) {
            $curriculum = DB::table('program_subject')->where('program_id', $programme->id)->pluck('subject_id')->map(fn ($id) => (int) $id)->all();
        }
        if (in_array((int) $course->id, $curriculum, true)) {
            return $course;
        }
        foreach (CourseGroups::memberIds($course->id) as $id) {
            if (in_array($id, $curriculum, true)) {
                return Subject::find($id);
            }
        }

        return null;
    }

    private function report($session, $section, array $plan, array $left, int $already, array $clashes): void
    {
        $this->line("Timetable for {$session->title}, section {$section->title}");
        $by = collect($plan)->groupBy(fn ($p) => $p['semester']->title . ' | ' . $p['programme']->shortcode);
        $this->table(['Semester | programme', 'Classes', 'Courses'], $by->map(fn ($g, $k) => [
            $k, $g->count(), $g->pluck('course.code')->unique()->sort()->implode(', '),
        ])->values()->all());
        $this->line(count($plan) . ' classes to add, ' . $already . ' already there, ' . count($left) . ' left out.');
        if ($left) {
            $this->warn('Left out:');
            foreach ($left as [$where, $why]) {
                $this->line("  $where: $why");
            }
        }
        if ($clashes) {
            $this->warn('Clashes the timetable check finds with these classes:');
            foreach (collect($clashes)->unique(fn ($c) => $c[0] . $c[1]) as [$what, $note]) {
                $this->line("  $what: $note");
            }
        } else {
            $this->info('No room, lecturer or group clashes.');
        }
    }

    private function read(string $file): array
    {
        $h = fopen($file, 'r');
        $header = array_map(fn ($c) => strtolower(trim(preg_replace('/^\xEF\xBB\xBF/', '', $c))), fgetcsv($h) ?: []);
        $rows = [];
        while (($r = fgetcsv($h)) !== false) {
            if (count(array_filter($r, fn ($v) => trim((string) $v) !== '')) === 0) {
                continue;
            }
            $rows[] = array_combine($header, array_pad(array_slice($r, 0, count($header)), count($header), ''));
        }
        fclose($h);

        return [$header, $rows];
    }

    private function time($v): ?string
    {
        return preg_match('/^\s*(\d{1,2}):(\d{2})/', (string) $v, $m) ? sprintf('%02d:%02d', $m[1], $m[2]) : null;
    }

    private function dayName($n): string
    {
        return ucfirst((string) array_search((int) $n, self::DAYS, true));
    }
}
