<?php

namespace App\Services;

use App\Models\Application;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * How much demand there is for each programme, from the applications that have
 * come in — for the Admissions Board deciding which programmes to open.
 *
 * The question the board is answering is "if we open this programme, will there
 * be students in it?". So a programme is measured by its FIRST choices: those are
 * the people who would enrol. Second and third choices answer the follow-up
 * question — "if we do not open it, where do its applicants go?" — and that is
 * what the redirects section is for.
 *
 * Nothing here decides anything. Each programme gets a signal against a minimum
 * class size the admin chooses, and the report says plainly that these are
 * signals for the board.
 *
 * Both the PDF and the Excel workbook are built from the one array this returns,
 * so the two cannot disagree.
 */
class ApplicationDemandReport
{
    public const DEFAULT_MIN_CLASS = 10;

    public const STRONG = 'strong';
    public const REACHABLE = 'reachable';
    public const BELOW = 'below';
    public const NONE = 'none';

    public const SIGNALS = [self::STRONG, self::REACHABLE, self::BELOW, self::NONE];

    /**
     * @param Collection      $applications the applications in scope, with approvals, admissionFee.paymentReceipts, degreeType and session loaded
     * @param Collection|null $drafts       unfinished drafts under the same filters; null when drafts are themselves what is being reported on
     * @param Collection      $programmes   every programme, with faculty loaded
     * @param int             $minClass     first-choice applicants a programme needs to count as having enough
     * @param array           $filters      the filters in force, in words
     * @param int|null        $degreeTypeId when set, programmes of other degree types are left out unless someone chose them
     */
    public function build(
        Collection $applications,
        ?Collection $drafts,
        Collection $programmes,
        int $minClass,
        array $filters = [],
        ?int $degreeTypeId = null
    ): array {
        $applications = $applications->values();
        $total = $applications->count();
        $byId = $programmes->keyBy(fn ($p) => (int) $p->id);

        $code = fn ($id) => $id && $byId->has((int) $id)
            ? ($byId[(int) $id]->shortcode ?: 'P' . (int) $id)
            : null;
        $title = fn ($id) => $id && $byId->has((int) $id) ? $byId[(int) $id]->title : null;
        $choices = fn ($a) => [
            (int) $a->first_program_choice_id,
            (int) $a->second_program_choice_id,
            (int) $a->third_program_choice_id,
        ];

        // Where each application stands in the approval chain, worked out once.
        $summaries = $applications->mapWithKeys(fn ($a) => [$a->id => $a->approvalSummary()]);
        $group = function ($a) use ($summaries) {
            $state = $summaries[$a->id]['state'];

            return match (true) {
                in_array($state, ['approved', 'approved_before_flow'], true) => 'approved',
                $state === 'refused' => 'refused',
                $state === 'not_submitted' => 'not_submitted',
                default => 'in_progress',
            };
        };

        // The programmes to report on: every active one of the degree type in
        // question — a programme nobody chose is exactly what the board needs to
        // see — plus any other programme somebody did choose.
        $mentioned = $applications->flatMap($choices)->filter()->unique();

        $inScope = $programmes
            ->filter(function ($p) use ($mentioned, $degreeTypeId) {
                if ($mentioned->contains((int) $p->id)) {
                    return true;
                }
                if ((int) $p->status !== 1) {
                    return false;
                }

                return $degreeTypeId === null || (int) $p->degree_type_id === $degreeTypeId;
            })
            ->sortBy(fn ($p) => (optional($p->faculty)->title ?? "\u{FFFF}") . '|' . $p->title);

        /* -------- Programmes -------- */

        $rows = [];

        foreach ($inScope as $p) {
            $id = (int) $p->id;
            $first = $applications->filter(fn ($a) => (int) $a->first_program_choice_id === $id);
            $firstCount = $first->count();
            $secondCount = $applications->filter(fn ($a) => (int) $a->second_program_choice_id === $id)->count();
            $thirdCount = $applications->filter(fn ($a) => (int) $a->third_program_choice_id === $id)->count();

            $seconds = $first->pluck('second_program_choice_id')->filter()->map(fn ($v) => (int) $v)->countBy()->sortDesc();
            $topSecondId = $seconds->keys()->first();

            $row = [
                'id' => $id,
                'title' => $p->title,
                'code' => $code($id),
                'faculty_id' => (int) $p->faculty_id,
                'faculty' => optional($p->faculty)->title ?? __('No faculty'),
                'faculty_code' => optional($p->faculty)->shortcode,
                'first' => $firstCount,
                'second' => $secondCount,
                'third' => $thirdCount,
                'mentions' => $applications->filter(fn ($a) => in_array($id, $choices($a), true))->count(),
                'weighted' => 3 * $firstCount + 2 * $secondCount + $thirdCount,
                'share' => $total ? round($firstCount / $total * 100, 1) : 0.0,
                'approved' => $first->filter(fn ($a) => $group($a) === 'approved')->count(),
                'in_progress' => $first->filter(fn ($a) => $group($a) === 'in_progress')->count(),
                'refused' => $first->filter(fn ($a) => $group($a) === 'refused')->count(),
                'fee_paid' => $first->filter(fn ($a) => self::feePaid($a))->count(),
                'female' => $first->filter(fn ($a) => (int) $a->gender === 2)->count(),
                'male' => $first->filter(fn ($a) => (int) $a->gender === 1)->count(),
                'drafts' => $drafts === null
                    ? null
                    : $drafts->filter(fn ($d) => (int) $d->first_program_choice_id === $id)->count(),
                'top_second' => $topSecondId
                    ? ['title' => $title($topSecondId), 'code' => $code($topSecondId), 'count' => $seconds[$topSecondId]]
                    : null,
            ];

            $row['signal'] = self::signal($row, $minClass);
            $rows[$id] = $row;
        }

        $signalOf = collect($rows)->map(fn ($row) => $row['signal']);

        $ranked = collect($rows)
            ->sortBy([['first', 'desc'], ['weighted', 'desc'], ['title', 'asc']])
            ->values()
            ->all();

        /* -------- Faculties -------- */

        $facultyOf = fn ($id) => $id && $byId->has((int) $id) ? (int) $byId[(int) $id]->faculty_id : null;

        $faculties = collect($rows)
            ->groupBy('faculty_id')
            ->map(function ($programmesInFaculty, $facultyId) use ($applications, $total, $facultyOf, $choices) {
                $firstRow = $programmesInFaculty->first();
                $first = $programmesInFaculty->sum('first');

                return [
                    'id' => (int) $facultyId,
                    'title' => $firstRow['faculty'],
                    'code' => $firstRow['faculty_code'],
                    'programmes' => $programmesInFaculty->count(),
                    'programmes_with_first' => $programmesInFaculty->where('first', '>', 0)->count(),
                    'first' => $first,
                    // Applicants who named any programme of this faculty, each
                    // counted once however many of its programmes they chose.
                    'any' => $applications->filter(function ($a) use ($facultyId, $facultyOf, $choices) {
                        return in_array((int) $facultyId, array_map($facultyOf, array_filter($choices($a))), true);
                    })->count(),
                    'second' => $programmesInFaculty->sum('second'),
                    'third' => $programmesInFaculty->sum('third'),
                    'approved' => $programmesInFaculty->sum('approved'),
                    'in_progress' => $programmesInFaculty->sum('in_progress'),
                    'refused' => $programmesInFaculty->sum('refused'),
                    'fee_paid' => $programmesInFaculty->sum('fee_paid'),
                    'share' => $total ? round($first / $total * 100, 1) : 0.0,
                    'signals' => collect(self::SIGNALS)
                        ->mapWithKeys(fn ($s) => [$s => $programmesInFaculty->where('signal', $s)->count()])
                        ->all(),
                ];
            })
            ->sortBy([['first', 'desc'], ['title', 'asc']])
            ->values()
            ->all();

        /* -------- If a programme is not opened -------- */

        $redirects = [];

        foreach ($ranked as $row) {
            if ($row['signal'] === self::STRONG || $row['first'] === 0) {
                continue;
            }

            $firstChoosers = $applications
                ->filter(fn ($a) => (int) $a->first_program_choice_id === $row['id'])
                ->sortBy('registration_no');

            $fallbacks = [];
            $applicants = [];
            $stranded = 0;

            foreach ($firstChoosers as $a) {
                $second = (int) $a->second_program_choice_id ?: null;
                $third = (int) $a->third_program_choice_id ?: null;
                $secondSignal = $second ? ($signalOf[$second] ?? null) : null;
                $thirdSignal = $third ? ($signalOf[$third] ?? null) : null;
                $viable = $secondSignal === self::STRONG || $thirdSignal === self::STRONG;

                if (!$viable) {
                    $stranded++;
                }

                foreach (['second' => $second, 'third' => $third] as $rank => $pid) {
                    if (!$pid) {
                        continue;
                    }

                    $fallbacks[$pid] ??= [
                        'title' => $title($pid),
                        'code' => $code($pid),
                        'signal' => $signalOf[$pid] ?? null,
                        'second' => 0,
                        'third' => 0,
                    ];
                    $fallbacks[$pid][$rank]++;
                }

                $applicants[] = [
                    'registration_no' => $a->registration_no,
                    'name' => trim($a->first_name . ' ' . $a->last_name),
                    'second' => $title($second),
                    'second_code' => $code($second),
                    'second_signal' => $secondSignal,
                    'third' => $title($third),
                    'third_code' => $code($third),
                    'third_signal' => $thirdSignal,
                    'has_viable_fallback' => $viable,
                ];
            }

            $redirects[] = [
                'programme' => $row,
                'fallbacks' => collect($fallbacks)
                    ->sortByDesc(fn ($f) => $f['second'] * 2 + $f['third'])
                    ->values()
                    ->all(),
                'applicants' => $applicants,
                'stranded' => $stranded,
            ];
        }

        /* -------- First choice against second choice -------- */

        $matrixRows = collect($ranked)->where('first', '>', 0)->values();
        $matrixColumns = collect($ranked)
            ->filter(fn ($row) => $applications->contains(fn ($a) => (int) $a->second_program_choice_id === $row['id']))
            ->values();

        $cells = [];

        foreach ($matrixRows as $r) {
            foreach ($matrixColumns as $c) {
                $cells[$r['id']][$c['id']] = $applications->filter(
                    fn ($a) => (int) $a->first_program_choice_id === $r['id'] && (int) $a->second_program_choice_id === $c['id']
                )->count();
            }

            $cells[$r['id']]['none'] = $applications->filter(
                fn ($a) => (int) $a->first_program_choice_id === $r['id'] && !$a->second_program_choice_id
            )->count();
        }

        /* -------- By month -------- */

        $months = $applications
            ->groupBy(function ($a) {
                $date = $a->apply_date ?? $a->created_at;

                return $date ? Carbon::parse($date)->format('Y-m') : 'unknown';
            })
            ->map(fn ($inMonth, $key) => [
                'key' => $key,
                'month' => $key === 'unknown'
                    ? __('Unknown')
                    : Carbon::createFromFormat('Y-m-d', $key . '-01')->format('M Y'),
                'count' => $inMonth->count(),
            ])
            ->sortBy('key')
            ->values()
            ->all();

        /* -------- The register -------- */

        $register = $applications
            ->sort(function ($a, $b) use ($title) {
                return strcmp((string) $title($a->first_program_choice_id), (string) $title($b->first_program_choice_id))
                    ?: strcmp((string) $a->registration_no, (string) $b->registration_no);
            })
            ->map(function ($a) use ($title, $code, $summaries, $group) {
                $date = $a->apply_date ?? $a->created_at;

                return [
                    'registration_no' => $a->registration_no,
                    'name' => trim($a->first_name . ' ' . $a->last_name),
                    'gender' => self::genderLabel($a->gender),
                    'degree_type' => optional($a->degreeType)->title,
                    'intake' => optional($a->session)->title ?: ($a->academic_year ?: __('Not recorded')),
                    'first' => $title($a->first_program_choice_id),
                    'first_code' => $code($a->first_program_choice_id),
                    'second' => $title($a->second_program_choice_id),
                    'second_code' => $code($a->second_program_choice_id),
                    'third' => $title($a->third_program_choice_id),
                    'third_code' => $code($a->third_program_choice_id),
                    'stage' => $a->progress_label,
                    'approval' => $summaries[$a->id]['label'],
                    'approval_group' => $group($a),
                    'fee' => self::feeStatus($a),
                    'applied' => $date ? Carbon::parse($date)->format('d M Y') : null,
                    'phone' => $a->phone,
                    'email' => $a->email,
                ];
            })
            ->values()
            ->all();

        /* -------- Totals -------- */

        $totals = [
            'applications' => $total,
            'approved' => $applications->filter(fn ($a) => $group($a) === 'approved')->count(),
            'in_progress' => $applications->filter(fn ($a) => $group($a) === 'in_progress')->count(),
            'refused' => $applications->filter(fn ($a) => $group($a) === 'refused')->count(),
            'not_submitted' => $applications->filter(fn ($a) => $group($a) === 'not_submitted')->count(),
            'drafts' => $drafts?->count(),
            'female' => $applications->filter(fn ($a) => (int) $a->gender === 2)->count(),
            'male' => $applications->filter(fn ($a) => (int) $a->gender === 1)->count(),
            'gender_other' => $applications->filter(fn ($a) => !in_array((int) $a->gender, [1, 2], true))->count(),
            'fee_paid' => $applications->filter(fn ($a) => self::feePaid($a))->count(),
            'with_second' => $applications->filter(fn ($a) => (bool) $a->second_program_choice_id)->count(),
            'with_third' => $applications->filter(fn ($a) => (bool) $a->third_program_choice_id)->count(),
        ];

        $signals = collect(self::SIGNALS)
            ->mapWithKeys(fn ($s) => [$s => collect($ranked)->where('signal', $s)->values()->all()])
            ->all();

        return [
            'institution' => app(LetterheadService::class)->institutionName(),
            'generated_at' => now(),
            'min_class' => $minClass,
            'filters' => $filters,
            'totals' => $totals,
            'programme_counts' => [
                'offered' => count($rows),
                'with_first' => collect($rows)->where('first', '>', 0)->count(),
            ],
            'signals' => $signals,
            'signal_labels' => collect(self::SIGNALS)->mapWithKeys(fn ($s) => [$s => self::signalLabel($s)])->all(),
            'signal_explanations' => collect(self::SIGNALS)
                ->mapWithKeys(fn ($s) => [$s => self::signalExplanation($s, $minClass)])
                ->all(),
            'programmes' => $ranked,
            'faculties' => $faculties,
            'redirects' => $redirects,
            'matrix' => [
                'rows' => $matrixRows->all(),
                'columns' => $matrixColumns->all(),
                'cells' => $cells,
            ],
            'months' => $months,
            'register' => $register,
            'legend' => collect($ranked)
                ->sortBy('code')
                ->mapWithKeys(fn ($row) => [$row['code'] => $row['title']])
                ->all(),
            'drafts_counted' => $drafts !== null,
        ];
    }

    /**
     * A programme's standing against the minimum class size.
     *
     * "Reachable" counts second choices at face value — as though every
     * applicant who chose it second would come — so it is a ceiling, and the
     * report says so.
     */
    public static function signal(array $row, int $minClass): string
    {
        if ($row['mentions'] === 0) {
            return self::NONE;
        }
        if ($row['first'] >= $minClass) {
            return self::STRONG;
        }
        if ($row['first'] + $row['second'] >= $minClass) {
            return self::REACHABLE;
        }

        return self::BELOW;
    }

    public static function signalLabel(?string $signal): string
    {
        return match ($signal) {
            self::STRONG => __('Enough applicants'),
            self::REACHABLE => __('Reachable with 2nd choices'),
            self::BELOW => __('Below the minimum'),
            self::NONE => __('No applicants'),
            default => '',
        };
    }

    public static function signalExplanation(string $signal, int $minClass): string
    {
        return match ($signal) {
            self::STRONG => __('At least :min applicants chose it first.', ['min' => $minClass]),
            self::REACHABLE => __('Fewer than :min chose it first, but :min or more counting those who chose it second.', ['min' => $minClass]),
            self::BELOW => __('Fewer than :min, even counting second choices.', ['min' => $minClass]),
            self::NONE => __('Nobody chose it, first, second or third.'),
            default => '',
        };
    }

    public static function genderLabel($gender): string
    {
        return match ((int) $gender) {
            1 => __('gender_male'),
            2 => __('gender_female'),
            3 => __('gender_other'),
            default => __('Not recorded'),
        };
    }

    public static function feePaid(Application $application): bool
    {
        return $application->admissionFee && (int) $application->admissionFee->status === 1;
    }

    /** The admission fee in the same words the applications list uses. */
    public static function feeStatus(Application $application): string
    {
        $enabled = in_array(strtolower((string) env('ADMISSION_FEE_ENABLED', 'true')), ['true', '1', 'yes', 'on'], true);

        if (!$enabled) {
            return __('Not required');
        }

        $fee = $application->admissionFee;

        if (!$fee) {
            return __('No fee');
        }
        if ((int) $fee->status === 1) {
            return __('Paid');
        }
        if ($fee->paymentReceipts && $fee->paymentReceipts->where('verification_status', 'pending')->count() > 0) {
            return __('Pending verification');
        }
        if ($fee->remaining_balance > 0) {
            return __('Unpaid');
        }

        return __('N/A');
    }
}
