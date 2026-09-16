<?php

/**
 * A pristine application to test against — one with no approvals, which is not
 * a draft and has not been converted into a student.
 *
 * The admission suites used to take whatever happened to be lying on the
 * database. As real applications were approved and converted through the
 * screens there was eventually nothing pristine left, and the suites quietly
 * skipped: application_approval_test fell from 116 checks to 40 and
 * application_course_registration_test from 15 to 4, both still reporting
 * "passed". A suite that stops testing must not look the same as one that
 * passes, so they now make their own fixture.
 *
 * Callers often ask for one before opening their transaction, so a fixture can
 * outlive a rollback. Every one is recorded and removed by removeFixtures(),
 * which each suite calls before it reports.
 */

use App\Models\Application;
use App\Models\Student;
use Illuminate\Support\Facades\DB;

$GLOBALS['createdApplicationFixtures'] = $GLOBALS['createdApplicationFixtures'] ?? [];

if (!function_exists('pendingApplication')) {
    /** An application with no approvals on it yet. */
    function pendingApplication(): ?Application
    {
        $existing = Application::whereDoesntHave('approvals')
            ->where('stage', '!=', 'draft')
            ->whereNotNull('registration_no')
            ->orderBy('id', 'desc')
            ->first();

        if ($existing) {
            return $existing;
        }

        // Shaped like a real submitted application, borrowing the programme and
        // degree type from one so every enrolment lookup resolves.
        $template = Application::where('stage', '!=', 'draft')->orderBy('id', 'desc')->first();

        if (!$template) {
            return null;
        }

        $fixture = new Application;
        $fixture->registration_no = '99' . random_int(100000, 999999);
        $fixture->first_name = 'Fixture';
        $fixture->last_name = 'Application';
        $fixture->email = 'fixture.' . uniqid() . '@example.test';
        $fixture->phone = '000000000';
        $fixture->gender = 1;
        $fixture->dob = '2000-01-01';
        $fixture->program_id = $template->program_id;
        $fixture->degree_type_id = $template->degree_type_id;
        $fixture->session_id = $template->session_id;
        $fixture->first_program_choice_id = $template->program_id;
        $fixture->apply_date = now()->toDateString();
        $fixture->stage = 'submitted';
        $fixture->status = 1;
        $fixture->progress = 10;
        $fixture->save();

        $GLOBALS['createdApplicationFixtures'][] = $fixture->id;

        return $fixture;
    }
}

if (!function_exists('pendingUnconvertedApplication')) {
    /** The same, and guaranteed to have no student record behind it. */
    function pendingUnconvertedApplication(): ?Application
    {
        $application = pendingApplication();

        if (!$application) {
            return null;
        }

        return Student::where('registration_no', $application->registration_no)->exists()
            ? null
            : $application;
    }
}

if (!function_exists('removeFixtures')) {
    /** Remove everything the suite made for itself, so the next run starts level. */
    function removeFixtures(): void
    {
        $ids = $GLOBALS['createdApplicationFixtures'] ?? [];

        if (!$ids) {
            return;
        }

        DB::table('application_approvals')->whereIn('application_id', $ids)->delete();
        DB::table('application_status_updates')->whereIn('application_id', $ids)->delete();
        DB::table('applications')->whereIn('id', $ids)->delete();

        $GLOBALS['createdApplicationFixtures'] = [];
    }
}

if (!function_exists('fixturesWereRemoved')) {
    /** Nothing named like a fixture is left on the database. */
    function fixturesWereRemoved(): bool
    {
        return !Application::where('first_name', 'Fixture')->where('last_name', 'Application')->exists();
    }
}
