<?php

namespace App\Console\Commands;

use App\Models\Session;
use App\Models\StudentEnroll;
use App\Models\StudentExamCode;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Read the commission's pre-registration code list and record the codes.
 *
 *   php artisan exam-codes:import "COMMISSION NATIONALE HND CODES.docx"
 *   php artisan exam-codes:import "…docx" --session=2 --level=1 --apply
 *
 * Without --apply it only reports what it would do. Names it cannot match to a
 * student are listed so they can be typed in on Admission → HND Exam Codes.
 *
 * A .docx is a zip holding word/document.xml, so the file is read with PHP's
 * own zip and XML support — no extra library.
 */
class ImportExamCodes extends Command
{
    protected $signature = 'exam-codes:import
                            {file : The commission\'s .docx file}
                            {--session= : Academic year (session id); defaults to the newest active one}
                            {--level=1 : 1 or 2}
                            {--apply : Record the codes; without this nothing is written}';

    protected $description = 'Import HND national exam codes from the commission\'s Word list.';

    public function handle(): int
    {
        $path = $this->argument('file');

        if (!is_file($path)) {
            $this->error("File not found: {$path}");

            return self::FAILURE;
        }

        $level = (int) $this->option('level');

        if (!in_array($level, [1, 2], true)) {
            $this->error('Level must be 1 or 2.');

            return self::FAILURE;
        }

        $session = $this->option('session')
            ? Session::find($this->option('session'))
            : (Session::where('status', '1')->orderBy('id', 'desc')->first() ?: Session::orderBy('id', 'desc')->first());

        if (!$session) {
            $this->error('No academic year to import into. Pass --session=<id>.');

            return self::FAILURE;
        }

        $entries = $this->readList($path);

        if ($entries === []) {
            $this->error('No codes found in that file. Is it the commission\'s list?');

            return self::FAILURE;
        }

        $this->info(sprintf('%d codes in the file — importing into %s, Level %d.', count($entries), $session->title, $level));

        // Students enrolled that year at that level, indexed by name.
        $enrollments = StudentEnroll::with('student')
            ->where('session_id', $session->id)
            ->whereHas('semester', fn ($query) => $query->where('year', $level))
            ->whereHas('student', fn ($query) => $query->where('status', '!=', 0))
            ->orderBy('id', 'desc')
            ->get()
            ->unique('student_id');

        $matched = [];
        $unmatched = [];

        foreach ($entries as [$code, $name]) {
            $enroll = $this->matchStudent($name, $enrollments);

            $enroll
                ? $matched[] = [$code, $name, $enroll]
                : $unmatched[] = [$code, $name];
        }

        $this->table(['Code', 'Name in the file', 'Student'], array_map(
            fn ($row) => [$row[0], $row[1], optional($row[2]->student)->student_id . ' — ' . trim(optional($row[2]->student)->first_name . ' ' . optional($row[2]->student)->last_name)],
            array_slice($matched, 0, 10)
        ));

        if (count($matched) > 10) {
            $this->line(sprintf('   … and %d more matched.', count($matched) - 10));
        }

        if ($unmatched !== []) {
            $this->warn(sprintf('%d name(s) could not be matched to a student — type these in on the screen:', count($unmatched)));

            foreach ($unmatched as [$code, $name]) {
                $this->line("   {$code}  {$name}");
            }
        }

        if (!$this->option('apply')) {
            $this->line('');
            $this->line('Nothing was written. Re-run with --apply to record these codes.');

            return self::SUCCESS;
        }

        $saved = $skipped = 0;
        $problems = [];

        DB::transaction(function () use ($matched, $session, $level, &$saved, &$skipped, &$problems) {
            foreach ($matched as [$code, $name, $enroll]) {
                $existing = StudentExamCode::forSitting($session->id, $level)->where('student_id', $enroll->student_id)->first();

                if ($existing && $existing->code === $code) {
                    $skipped++;

                    continue;
                }

                $owner = StudentExamCode::where('code', $code)
                    ->when($existing, fn ($query) => $query->where('id', '!=', $existing->id))
                    ->first();

                if ($owner) {
                    $problems[] = "{$code} ({$name}) already belongs to another student";

                    continue;
                }

                StudentExamCode::updateOrCreate(
                    ['student_id' => $enroll->student_id, 'session_id' => $session->id, 'level' => $level],
                    ['code' => $code, 'program_id' => $enroll->program_id]
                );

                $saved++;
            }
        });

        $this->info(sprintf('%d code(s) recorded, %d already correct.', $saved, $skipped));

        foreach ($problems as $problem) {
            $this->warn('   ' . $problem);
        }

        return self::SUCCESS;
    }

    /**
     * Pull [code, name] pairs out of the .docx tables.
     *
     * @return array<int, array{0:string,1:string}>
     */
    protected function readList(string $path): array
    {
        $zip = new \ZipArchive();

        if ($zip->open($path) !== true) {
            return [];
        }

        $xml = $zip->getFromName('word/document.xml');
        $zip->close();

        if (!$xml) {
            return [];
        }

        $entries = [];

        preg_match_all('/<w:tr[ >].*?<\/w:tr>/s', $xml, $rows);

        foreach ($rows[0] as $row) {
            $cells = array_map(function ($cell) {
                preg_match_all('/<w:t(?: [^>]*)?>(.*?)<\/w:t>/s', $cell, $text);

                return trim(html_entity_decode(implode('', $text[1]), ENT_QUOTES | ENT_XML1, 'UTF-8'));
            }, array_slice(preg_split('/<\/w:tc>/', $row), 0, -1));

            $code = '';
            $name = '';

            foreach ($cells as $cell) {
                $candidate = StudentExamCode::normalise($cell);

                if (preg_match('/^HND[0-9A-F]{6,14}$/', $candidate)) {
                    $code = $candidate;
                } elseif (!is_numeric($cell) && strlen($cell) > 3) {
                    $name = $cell;
                }
            }

            if ($code !== '' && $name !== '') {
                $entries[] = [$code, preg_replace('/\s+/', ' ', $name)];
            }
        }

        return $entries;
    }

    /**
     * Match a name from the file to an enrolled student.
     *
     * The commission's spelling is not the school's. In this year's list alone:
     * "FUANGO RANIBELBENWI" for "FUANGO RANIBEL BENWI" (a missing space),
     * "NYING DILAND GONOH" for "GONAH" (a letter), "FOMEGHANG TCHINDA R." and
     * "NCHIDENG SYNTHIA N" (an initial for a full name). So matching runs in
     * order of certainty and stops at the first rule that finds exactly one
     * student — anything ambiguous is left for a person to decide.
     */
    protected function matchStudent(string $name, $enrollments)
    {
        $letters = fn ($value) => preg_replace('/[^A-Z]/', '', strtoupper($value));
        $words = fn ($value) => array_values(array_filter(preg_split('/\s+/', preg_replace('/[^A-Z ]/', ' ', strtoupper($value)))));

        $fileWords = $words($name);
        $fileLetters = $letters($name);

        if ($fileWords === []) {
            return null;
        }

        $sameName = $spaced = $close = $initials = [];

        foreach ($enrollments as $enroll) {
            $their = $words(trim(optional($enroll->student)->first_name . ' ' . optional($enroll->student)->last_name));

            if ($their === []) {
                continue;
            }

            $theirLetters = $letters(implode('', $their));
            $mine = $fileWords;
            sort($mine);
            $sorted = $their;
            sort($sorted);

            if ($mine === $sorted) {
                return $enroll;
            }

            // Same letters in the same order: only the spacing differs.
            if ($fileLetters === $theirLetters) {
                $spaced[] = $enroll;

                continue;
            }

            // A letter or two out, on a name long enough for that to be safe.
            if (strlen($fileLetters) >= 10 && levenshtein($fileLetters, $theirLetters) <= 2) {
                $close[] = $enroll;

                continue;
            }

            // Every word matches, counting a lone letter as an initial.
            if ($this->wordsAgree($fileWords, $their)) {
                $initials[] = $enroll;
            }
        }

        foreach ([$spaced, $close, $initials] as $candidates) {
            if (count($candidates) === 1) {
                return $candidates[0];
            }
        }

        return null;
    }

    /**
     * Do two names agree word by word, allowing an initial to stand for a name
     * and a single letter to differ in a long word?
     */
    protected function wordsAgree(array $fileWords, array $studentWords): bool
    {
        // The file sometimes carries a name the school does not hold, and the
        // school sometimes holds one the file leaves out, so the shorter name
        // is the one that must match in full.
        if (count($studentWords) < count($fileWords)) {
            [$fileWords, $studentWords] = [$studentWords, $fileWords];
        }

        $pool = $studentWords;
        $strong = 0;

        foreach ($fileWords as $word) {
            $found = null;

            foreach ($pool as $index => $candidate) {
                $isInitial = strlen($word) === 1 || strlen($candidate) === 1;

                if ($isInitial) {
                    if ($word[0] === $candidate[0]) {
                        $found = $index;
                        break;
                    }

                    continue;
                }

                $slack = strlen($word) >= 8 ? 2 : (strlen($word) >= 5 ? 1 : 0);

                if ($word === $candidate || ($slack && levenshtein($word, $candidate) <= $slack)) {
                    $found = $index;
                    $strong++;
                    break;
                }
            }

            if ($found === null) {
                return false;
            }

            unset($pool[$index]);
            $pool = array_values($pool);
        }

        // Two real names in common, not just matching initials.
        return $strong >= 2;
    }
}
