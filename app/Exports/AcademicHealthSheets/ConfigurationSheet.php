<?php

namespace App\Exports\AcademicHealthSheets;

/**
 * Whether each stage of the academic setup is complete.
 *
 * The stages build on each other: courses cannot be offered without a
 * programme, and a programme cannot exist without a faculty. Presented in that
 * order so a gap shows where the chain actually breaks.
 */
class ConfigurationSheet extends HealthSheet
{
    public function title(): string
    {
        return 'Configuration';
    }

    public function array(): array
    {
        $rows = $this->masthead('Setup completeness, stage by stage');

        $rows[] = ['CONFIGURATION PIPELINE'];
        $this->sectionRows[] = count($rows);
        $rows[] = ['#', 'Stage', 'Configured', 'Status', 'Required'];
        $this->headerRows[] = count($rows);

        foreach ($this->data['pipeline'] as $i => $step) {
            $optional = $step['optional'] ?? false;
            $ok = $step['ok'] ?? false;
            $empty = ($step['count'] ?? 0) == 0;

            if ($optional) {
                $status = $empty ? 'Not used' : 'In use';
                $verdict = 'ok';
            } elseif ($ok) {
                $status = 'Complete';
                $verdict = 'ok';
            } else {
                $status = 'Nothing configured';
                $verdict = 'bad';
            }

            $rows[] = [
                $i + 1,
                $step['label'],
                $step['count'],
                $status,
                $optional ? 'Optional' : 'Required',
            ];
            $this->verdictRows[count($rows)] = $verdict;
        }

        $rows[] = [''];
        $rows[] = ['SESSIONS AND SEMESTERS'];
        $this->sectionRows[] = count($rows);
        $rows[] = ['Item', 'Value', 'Notes'];
        $this->headerRows[] = count($rows);

        $current = $this->data['current_session'];
        $rows[] = [
            'Current session',
            $current->title ?? 'NONE SET',
            $current ? 'Everything dated is measured against this.' : 'Nothing can be dated until one is set.',
        ];
        $this->verdictRows[count($rows)] = $current ? 'ok' : 'bad';

        $rows[] = [
            'Active semesters',
            $this->data['active_semesters']->count(),
            $this->data['active_semesters']->pluck('title')->implode(', '),
        ];
        $rows[] = [
            'Resit semesters',
            $this->data['resit_semesters']->count(),
            'Used for resit sittings only.',
        ];
        $rows[] = [
            'Sessions on record',
            $this->data['all_sessions']->count(),
            'Including closed years.',
        ];

        return $rows;
    }
}
