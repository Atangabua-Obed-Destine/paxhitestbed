<?php

namespace Database\Seeders;

use App\Models\BudgetLine;
use Database\Seeders\Concerns\SeedsWithoutOverwriting;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Seeds the Income & Expenditure sheet structure.
 *
 * Transcribed from the diocesan budget sheet, including its own quirks — group
 * 430 really does carry children numbered 441-447, and 405-408 / 416-419 are
 * deliberately left free. Those gaps are reserved space, not mistakes to
 * "tidy up": renumbering here would break continuity with every printed sheet
 * the finance office already holds.
 *
 * Create-only: a line that already exists is left exactly as it is, including
 * its sort_order. The ordering is dragged into place by hand on the budget-line
 * screen, so rewriting it here would undo that work every time the seeder ran.
 * Lines an administrator adds later are left untouched.
 */
class BudgetLineSeeder extends Seeder
{
    use SeedsWithoutOverwriting;

    public function run(): void
    {
        $order = 0;
        $parentId = null;

        foreach ($this->lines() as $line) {
            [$section, $code, $name, $isHeader] = $line;
            $isLocal = $line[4] ?? false;
            // Some lines carry a figure but sit at group level with no parent
            // (520 Library, 530 Medical Laboratory). Without this they would be
            // swept into the preceding group and inflate its total.
            $isTopLevel = $line[5] ?? false;

            $order += 10;

            $row = $this->createIfAbsent(
                BudgetLine::class,
                ['code' => $code],
                [
                    'name' => $name,
                    'section' => $section,
                    'is_header' => $isHeader,
                    'is_local' => $isLocal,
                    // A header opens a group; the lines after it belong to it
                    // until the next header appears. Only ever applied to a
                    // line being created — an existing line's placement is
                    // whatever the finance office arranged.
                    'parent_id' => ($isHeader || $isTopLevel) ? null : $parentId,
                    'sort_order' => $order,
                    'status' => true,
                ],
                $code . ' ' . $name
            );

            if ($isHeader) {
                $parentId = $row->id;
            }
        }

        $this->linkTuitionToFaculties();

        $this->command?->info(sprintf(
            'Budget lines: %d created, %d already present, %d total.',
            $this->createdCount,
            $this->skippedCount,
            BudgetLine::count()
        ));
    }

    /**
     * Point each tuition line at the school it reports.
     *
     * Matched on a keyword rather than an id so the seeder survives a database
     * where the faculties were created in a different order. A school with no
     * matching line simply stays unlinked — the resolver then reports its
     * tuition as unallocated instead of silently adding it to another school.
     */
    protected function linkTuitionToFaculties(): void
    {
        $byKeyword = [
            '610' => ['management', 'business'],
            '611' => ['medical', 'biomedical', 'health'],
            '612' => ['computer', 'digital'],
            '613' => ['agricultur', 'food'],
        ];

        $faculties = DB::table('faculties')->get(['id', 'title']);

        foreach ($byKeyword as $code => $keywords) {
            $line = BudgetLine::where('code', $code)->first();
            if (!$line) {
                continue;
            }

            $match = $faculties->first(function ($faculty) use ($keywords) {
                $title = mb_strtolower($faculty->title);
                foreach ($keywords as $keyword) {
                    if (str_contains($title, $keyword)) {
                        return true;
                    }
                }
                return false;
            });

            // Only ever fills a blank. This is a keyword guess, and a line
            // that already names a faculty may have been pointed there by hand
            // at a school whose title contains none of these words — writing
            // over it, or nulling it when nothing matches, would silently
            // unlink that school's tuition.
            $this->fillIfBlank($line, 'faculty_id', $match->id ?? null);
        }
    }

    /**
     * [section, code, name, is_header, is_local, is_top_level]
     */
    protected function lines(): array
    {
        return [
            // ---- INCOME -------------------------------------------------
            ['income', '600', 'Registration Fees', false],
            ['income', '610', 'Tuition Fees Business school', false],
            ['income', '611', 'Tuition Fees Health school', false],
            // The diocesan form assumes two schools. This institution collects
            // tuition from four, and half of it (13.7M) had nowhere to go.
            ['income', '612', 'Tuition Fees Computer Engineering', false, true],
            ['income', '613', 'Tuition Fees Agriculture', false, true],
            // 745,000 across 151 resit payments, with no line on the form.
            // Kept separate rather than netted against examination costs, which
            // would hide both figures.
            ['income', '614', 'Resit / Examination Fees', false, true],
            ['income', '620', 'OTHER INCOME', true],
            ['income', '621', 'Donations / Grants', false],
            ['income', '622', 'Benefactors', false],
            // 30M — 92% of all recorded income — currently sits in a category
            // called "Capital contribution". Given its own line so it is
            // visible and can be moved wholesale if Finance confirms it is a
            // capital injection rather than income.
            ['income', '623', 'Diocesan subvention / Capital contribution', false, true],
            ['income', '624', 'Sundry income', false, true],
            ['income', '625', 'Chaplaincy collections', false, true],

            // ---- EXPENDITURE --------------------------------------------
            ['expenditure', '400', 'ADMINISTRATION', true],
            ['expenditure', '401', 'Travelling', false],
            ['expenditure', '402', 'Meetings and Seminars', false],
            ['expenditure', '403', 'Telephone / Postage', false],
            ['expenditure', '404', 'Stationery', false],
            ['expenditure', '409', 'Staff Meetings', false],

            ['expenditure', '410', 'PEDAGOGY', true],
            ['expenditure', '411', 'Didactic material', false],
            ['expenditure', '412', 'Examinations', false],
            ['expenditure', '413', 'Supervision of Internship Report', false],
            ['expenditure', '414', "Expenses on Students' Defences", false],
            ['expenditure', '415', 'Seminars / Conferences', false],

            ['expenditure', '420', 'ELECTRICITY AND WATER', true],
            ['expenditure', '421', 'Electricity Bills', false],
            ['expenditure', '422', 'Electricity repairs', false],
            ['expenditure', '423', 'Generator Fuel', false],
            ['expenditure', '424', 'Water Bills', false],
            ['expenditure', '425', 'Mineral Water', false],
            ['expenditure', '426', 'Plumbing repairs', false],

            // The sheet numbers this group 430 but its children 441-447.
            ['expenditure', '430', 'UNIVERSITY ORDINARY', true],
            ['expenditure', '441', 'Internet', false],
            ['expenditure', '442', 'Salaries (Administration)', false],
            ['expenditure', '443', 'Salaries (Lecturers)', false],
            // 29.3M of salaries is recorded in one category with no admin /
            // lecturer split. Inventing that split would misstate both lines,
            // so history lands here honestly until the category is subdivided.
            // 444 was free inside this group, so no renumbering was needed.
            ['expenditure', '444', 'Salaries (not yet split admin / lecturer)', false, true],
            ['expenditure', '445', 'CNPS / NSIF', false],
            ['expenditure', '446', 'Duty Post Allowances', false],
            ['expenditure', '447', 'Recruitment / Interviews', false],

            ['expenditure', '450', 'SANITATION', true],
            ['expenditure', '451', 'Compound care (out door)', false],
            ['expenditure', '452', 'Cleaning (Buildings)', false],
            ['expenditure', '453', 'Hygiene', false],
            ['expenditure', '454', 'BEPHA scheme for Students', false],

            ['expenditure', '460', 'TRANSPORT', true],
            ['expenditure', '461', 'Fuel', false],
            ['expenditure', '462', 'Vehicle Running expenses', false],
            ['expenditure', '463', 'Travelling', false],

            ['expenditure', '470', 'OTHER EXPENSES', true],
            ['expenditure', '471', 'Matriculation / Convocation ceremonies', false],
            ['expenditure', '472', 'Internship fees for nursing students', false],
            ['expenditure', '473', 'Practicals (Health students)', false],
            ['expenditure', '474', 'Staff visitation of students on internship', false],
            ['expenditure', '475', 'Visitations / Receptions', false],
            ['expenditure', '476', 'Other Celebrations', false],
            ['expenditure', '477', 'Staff welfare', false],
            ['expenditure', '478', "Students' Association / welfare", false],
            ['expenditure', '479', 'Campaign for students / Adverts', false],

            // Local extension: this institution runs income-generating
            // projects (poultry, farm, MIDEVIV) worth ~3.6M a year that the
            // diocesan template has nowhere to put.
            ['expenditure', '480', 'INCOME-GENERATING PROJECTS', true, true],
            ['expenditure', '481', 'Poultry', false, true],
            ['expenditure', '482', 'School Farm / Garden', false, true],
            ['expenditure', '483', 'MIDEVIV', false, true],
            ['expenditure', '489', 'Other projects', false, true],

            ['expenditure', '500', 'DEPRECIATION', true],
            ['expenditure', '501', 'Furniture / Equipment', false],
            ['expenditure', '502', 'Building', false],
            ['expenditure', '503', 'Vehicle depreciation', false],

            ['expenditure', '510', 'REPAIRS AND MAINTENANCE', true],
            ['expenditure', '511', 'Buildings', false],
            ['expenditure', '512', 'Furniture / Equipment', false],

            // Both stand alone on the sheet — group level, carrying their own
            // figure, with no children. Marked top-level so they are not
            // swallowed into 510 and counted twice.
            ['expenditure', '520', 'LIBRARY / Mentorship', false, false, true],
            ['expenditure', '530', 'MEDICAL Laboratory', false, false, true],

            // Local extensions in their own 59x band, so they are never
            // mistaken for part of a diocesan numbered group.
            ['expenditure', '590', 'Financial expenses / Bank charges', false, true, true],
            ['expenditure', '591', 'Security and guarding', false, true, true],
            // 4.16M is recorded as "Cash to ..." with no classification. That is
            // an advance awaiting receipts, not a category of spending, and the
            // line is named so the state is obvious on the face of the sheet.
            ['expenditure', '592', 'Unclassified expenses (incl. cash advances pending retirement)', false, true, true],

            // ---- CAPITAL ------------------------------------------------
            ['capital', '540', 'CAPITAL EXPENSES / PURCHASES', true],
            ['capital', '541', 'Sports', false],
            ['capital', '542', 'Furniture / Equipment', false],
        ];
    }
}
