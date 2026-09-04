<?php

namespace App\Exports\AcademicHealthSheets;

/**
 * Whether the semester can be closed.
 *
 * Configuration being complete does not mean results are. A mark left in draft
 * is invisible on every transcript, and an enrolment with no mark at all cannot
 * be graded — both hold a session open regardless of how well the school is set
 * up.
 */
class ResultsSheet extends HealthSheet
{
    public function title(): string
    {
        return 'Results Readiness';
    }

    public function array(): array
    {
        $r = $this->data['results_readiness'];

        $rows = $this->masthead('Completeness of marks for the current session');

        $rows[] = ['POSITION'];
        $this->sectionRows[] = count($rows);
        $rows[] = ['Measure', 'Count', 'What it means'];
        $this->headerRows[] = count($rows);

        $rows[] = [
            'Marks published',
            $r['published_marks'],
            'Visible to students and on transcripts.',
        ];
        $this->verdictRows[count($rows)] = 'ok';

        $rows[] = [
            'Marks still in draft',
            $r['draft_marks'],
            'Entered but not published, so they appear on no transcript or result sheet.',
        ];
        $this->verdictRows[count($rows)] = $r['draft_marks'] > 0 ? 'warn' : 'ok';

        $rows[] = [
            'Enrolments with no marks',
            $r['enrolments_without_marks'],
            'No mark recorded at all. These must be entered or the enrolments withdrawn.',
        ];
        $this->verdictRows[count($rows)] = $r['enrolments_without_marks'] > 0 ? 'bad' : 'ok';

        $rows[] = ['Total enrolments', $r['total_enrolments'], ''];
        $rows[] = ['Published', $r['published_percent'] . '%', 'Share of entered marks that are published.'];

        $rows[] = [''];
        $rows[] = ['VERDICT'];
        $this->sectionRows[] = count($rows);

        if ($r['enrolments_without_marks'] > 0) {
            $rows[] = ['The semester cannot be closed. ' . $r['enrolments_without_marks']
                . ' enrolment(s) have no marks recorded.'];
            $this->verdictRows[count($rows)] = 'bad';
        } elseif ($r['draft_marks'] > 0) {
            $rows[] = ['Marks are complete but ' . $r['draft_marks']
                . ' are unpublished, so students cannot yet see them.'];
            $this->verdictRows[count($rows)] = 'warn';
        } else {
            $rows[] = ['Every enrolment has marks and all are published. Nothing outstanding.'];
            $this->verdictRows[count($rows)] = 'ok';
        }

        return $rows;
    }
}
