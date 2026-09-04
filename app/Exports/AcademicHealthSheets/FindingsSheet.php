<?php

namespace App\Exports\AcademicHealthSheets;

/**
 * Everything currently wrong, and what to do about each.
 *
 * Errors before warnings, because the difference matters: an error stops
 * something working, a warning is incomplete but survivable. Each row carries
 * the consequence in plain words, so the reader is not left to infer why a line
 * item is on the list.
 */
class FindingsSheet extends HealthSheet
{
    public function title(): string
    {
        return 'Action Required';
    }

    public function array(): array
    {
        $errors = $this->data['diagnostic_errors'];
        $warnings = $this->data['warnings'];
        $healthy = $this->data['healthy'];

        $rows = $this->masthead('What needs attention');

        $rows[] = ['MUST BE CORRECTED (' . count($errors) . ')'];
        $this->sectionRows[] = count($rows);

        if ($errors === []) {
            $rows[] = ['Nothing. No error was found.'];
            $this->verdictRows[count($rows)] = 'ok';
        } else {
            $rows[] = ['#', 'Area', 'Finding', 'What it means', 'Where to fix it'];
            $this->headerRows[] = count($rows);

            foreach ($errors as $i => $finding) {
                $rows[] = [
                    $i + 1,
                    $finding['category'],
                    $finding['title'],
                    $finding['message'],
                    $finding['action_text'] ?? '',
                ];
                $this->verdictRows[count($rows)] = 'bad';
            }
        }

        $rows[] = [''];
        $rows[] = ['NEEDS ATTENTION (' . count($warnings) . ')'];
        $this->sectionRows[] = count($rows);

        if ($warnings === []) {
            $rows[] = ['Nothing outstanding.'];
            $this->verdictRows[count($rows)] = 'ok';
        } else {
            $rows[] = ['#', 'Area', 'Finding', 'What it means', 'Where to fix it'];
            $this->headerRows[] = count($rows);

            foreach ($warnings as $i => $finding) {
                $rows[] = [
                    $i + 1,
                    $finding['category'],
                    $finding['title'],
                    $finding['message'],
                    $finding['action_text'] ?? '',
                ];
                $this->verdictRows[count($rows)] = 'warn';
            }
        }

        // The passing checks are listed too. A report that shows only failures
        // cannot be used to confirm anything was checked at all, which is the
        // question the school is actually asking.
        $rows[] = [''];
        $rows[] = ['CHECKED AND CORRECT (' . count($healthy) . ')'];
        $this->sectionRows[] = count($rows);
        $rows[] = ['#', 'Area', 'Check', 'Result'];
        $this->headerRows[] = count($rows);

        foreach ($healthy as $i => $finding) {
            $rows[] = [$i + 1, $finding['category'], $finding['title'], $finding['message']];
            $this->verdictRows[count($rows)] = 'ok';
        }

        return $rows;
    }
}
