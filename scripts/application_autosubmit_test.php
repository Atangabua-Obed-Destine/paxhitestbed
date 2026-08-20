<?php
/**
 * Auto-submission on fee approval, and the payment gate in front of it.
 *
 * The rule being tested: an applicant may not pay until the application is
 * complete, and once the admission fee is settled the application submits
 * itself. Everything runs inside a transaction that is rolled back, so it is
 * safe against the live database.
 *
 *   php scripts/application_autosubmit_test.php
 */

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Application;
use App\Models\Fee;
use App\Services\ApplicationCompleteness;
use App\Services\ApplicationSubmissionService;
use App\Services\DegreeTypeFormConfig;
use Illuminate\Support\Facades\DB;

$passed = 0;
$failed = 0;

function check(string $label, bool $ok, string $detail = ''): void
{
    global $passed, $failed;
    if ($ok) {
        $passed++;
        echo "  PASS  $label\n";
    } else {
        $failed++;
        echo "  FAIL  $label" . ($detail !== '' ? "  — $detail" : '') . "\n";
    }
}

function section(string $title): void
{
    echo "\n== $title ==\n";
}

$service = app(ApplicationSubmissionService::class);

DB::beginTransaction();

try {
    /* =================================================================
     | 1. The definition of "complete" tracks update()'s required rules
     |=================================================================*/
    section('Completeness mirrors the submit rules');

    // Every field update() marks 'required' unconditionally must be one this
    // class reports on. Read the controller rather than trusting a copied list:
    // if someone adds a required field to update(), this test fails until the
    // completeness check learns about it too.
    $source = file_get_contents(__DIR__ . '/../app/Http/Controllers/Web/ApplicationController.php');
    $rulesBlock = null;
    if (preg_match('/\$rules = \[(.*?)\n        \];/s', $source, $m)) {
        $rulesBlock = $m[1];
    }
    check('found the rules block in update()', $rulesBlock !== null);

    $requiredInController = [];
    if ($rulesBlock !== null) {
        foreach (explode("\n", $rulesBlock) as $line) {
            if (preg_match("/'([a-z_]+)' => \['required'/", $line, $mm)) {
                $requiredInController[] = $mm[1];
            }
        }
    }
    // 'program' is stored as program_id; the checker reports it under that name.
    $known = [
        'program', 'first_name', 'last_name', 'gender', 'dob', 'nationality',
        'country', 'present_province', 'present_district', 'phone', 'email',
    ];
    sort($requiredInController);
    sort($known);
    check(
        'no unconditionally-required field has appeared without a matching check',
        $requiredInController === $known,
        'controller: ' . implode(',', array_diff($requiredInController, $known))
        . ' / checker: ' . implode(',', array_diff($known, $requiredInController))
    );

    /* =================================================================
     | 2. A real draft, made complete step by step
     |=================================================================*/
    section('Completeness against a real application');

    // Pick a draft that is genuinely unfinished rather than assuming the newest
    // one is. This used to take the newest draft and assert it was incomplete,
    // which stopped being true the moment somebody finished theirs — a test
    // failing on other people's data, not on a fault.
    $draft = Application::where('stage', 'draft')->orderByDesc('id')->get()
        ->first(fn ($a) => ApplicationCompleteness::missing($a) !== []);

    if (!$draft) {
        // Every draft happens to be complete: take the newest and empty one
        // required field, inside the transaction that is rolled back anyway.
        $draft = Application::where('stage', 'draft')->orderByDesc('id')->first();
        if ($draft) {
            $draft->phone = null;
            $draft->save();
        }
    }

    check('an incomplete draft is available to test with', $draft !== null);
    if (!$draft) {
        throw new RuntimeException('no draft to test with');
    }

    $before = ApplicationCompleteness::missing($draft);
    check(
        'the draft starts out incomplete',
        $before !== [],
        'application #' . $draft->id . ' reports nothing missing'
    );

    // Fill in whatever the checker says is outstanding, by column, so the test
    // does not assume which degree type this draft belongs to.
    $draft->first_name = $draft->first_name ?: 'Test';
    $draft->last_name = $draft->last_name ?: 'Applicant';
    $draft->gender = $draft->gender ?: 1;
    $draft->dob = $draft->dob ?: '2000-01-01';
    $draft->nationality = $draft->nationality ?: 'Cameroonian';
    $draft->country = $draft->country ?: 'Cameroon';
    $draft->present_province = $draft->present_province ?: 'North West';
    $draft->present_district = $draft->present_district ?: 'Ngoketunjia';
    $draft->phone = $draft->phone ?: '670000000';
    $draft->email = $draft->email ?: 'autosubmit-test@example.test';
    $draft->photo = $draft->photo ?: 'test-photo.jpg';
    $draft->declaration_name = $draft->declaration_name ?: 'Test Applicant';
    $draft->declaration_signed_date = $draft->declaration_signed_date ?: now()->toDateString();
    foreach (['birth_city', 'birth_division', 'birth_region', 'birth_country', 'academic_year'] as $col) {
        $draft->{$col} = $draft->{$col} ?: 'Test';
    }
    $draft->save();

    // The declaration tick, which now has to survive on a draft.
    $meta = is_array($draft->portal_meta) ? $draft->portal_meta : [];
    $draft->portal_meta = array_merge($meta, ['agreed_to_terms' => true]);
    $draft->save();
    check('a persisted tick is read back as agreement', ApplicationCompleteness::hasAgreedToTerms($draft->fresh()));

    $draft->portal_meta = array_merge($meta, ['agreed_to_terms' => false]);
    $draft->save();
    check('un-ticking is read back as disagreement', !ApplicationCompleteness::hasAgreedToTerms($draft->fresh()));
    $draft->portal_meta = array_merge($meta, ['agreed_to_terms' => true]);
    $draft->save();

    // Guardians, qualifications and documents, if this degree type asks for them.
    $degreeType = $draft->degreeType;
    if (DegreeTypeFormConfig::fieldEnabled($degreeType, 'application_guardians')
        && $draft->guardians()->count() === 0) {
        $draft->guardians()->create([
            'full_name' => 'Test Guardian',
            'type' => 'father',
            'phone_primary' => '670000001',
        ]);
    }
    if (DegreeTypeFormConfig::fieldEnabled($degreeType, 'application_academic_history')
        && $draft->academicHistories()->count() === 0) {
        $draft->academicHistories()->create([
            'institution_name' => 'Test School',
            'certificate_obtained' => 'GCE O Level',
        ]);
    }
    if (DegreeTypeFormConfig::fieldEnabled($degreeType, 'application_document_checklist')) {
        foreach (DegreeTypeFormConfig::documents($degreeType) as $key => $document) {
            if (!empty($document['required'])) {
                $draft->documents()->updateOrCreate(
                    ['document_type' => $key],
                    ['file_path' => 'test/' . $key . '.pdf', 'is_received' => true, 'is_optional' => false]
                );
            }
        }
    }
    if (!$draft->program_id) {
        $program = \App\Models\Program::where('degree_type_id', $draft->degree_type_id)->first()
            ?: \App\Models\Program::first();
        $draft->program_id = $program->id ?? null;
        $draft->save();
    }

    $draft = $draft->fresh();
    $after = ApplicationCompleteness::missing($draft);
    check('the draft is now complete', $after === [], 'still missing: '
        . implode(', ', array_column($after, 'label')));

    /* =================================================================
     | 3. The payment gate
     |=================================================================*/
    section('The payment gate');

    // Take one required item away again and confirm the gate closes.
    $savedPhoto = $draft->photo;
    $draft->photo = null;
    $draft->save();
    check('removing a required field closes the gate',
        ApplicationCompleteness::missingLabels($draft->fresh()) !== []);
    $draft->photo = $savedPhoto;
    $draft->save();
    check('restoring it opens the gate again',
        ApplicationCompleteness::missingLabels($draft->fresh()) === []);

    /* =================================================================
     | 4. Auto-submission
     |=================================================================*/
    section('Auto-submission on fee approval');

    $draft = $draft->fresh();
    $feeRequired = !empty(DegreeTypeFormConfig::settings($draft->degreeType)['fee_enabled']);
    $fee = $draft->admissionFee()->first();

    if ($feeRequired && $fee) {
        $fee->status = 0;
        $fee->saveQuietly();
        $draft = $draft->fresh();

        check('an unpaid fee blocks submission',
            in_array('admission fee is not settled', $service->blockers($draft), true),
            implode(' | ', $service->blockers($draft)));
        check('autoSubmit refuses while the fee is unpaid',
            $service->autoSubmit($draft, 'test') === false);
        check('and the application is still a draft', $draft->fresh()->stage === 'draft');

        // Settling the fee through a normal save is what the observer watches.
        $fee->status = 1;
        $fee->save();

        $reloaded = $draft->fresh();
        check('settling the fee submitted the application',
            $reloaded->stage === 'submitted', 'stage is ' . $reloaded->stage);
        check('status was set to 1', (int) $reloaded->status === 1);
        check('progress matches the submitted stage',
            (int) $reloaded->progress === Application::stageProgressMap()['submitted']);
        check('the timeline records it',
            $reloaded->statusUpdates()->where('stage', 'submitted')->exists());
        // apply_date is what the admissions register filters on. Submitting
        // without it produced an application that existed, was complete, was
        // paid for — and appeared nowhere in the admin list.
        check('the date of application is stamped',
            !empty($reloaded->apply_date),
            'apply_date is ' . var_export($reloaded->apply_date, true));
        check('it is stamped with today',
            optional($reloaded->apply_date)->format('Y-m-d') === now()->toDateString(),
            (string) optional($reloaded->apply_date)->format('Y-m-d'));

        $meta = is_array($reloaded->portal_meta) ? $reloaded->portal_meta : [];
        check('it is marked as automatic', !empty($meta['submitted_automatically']));
        check('no browser details were invented for it',
            !isset($meta['submitted_ip']) && !isset($meta['submitted_user_agent']));

        // Idempotence: saving the fee again must not submit a second time.
        $timelineCount = $reloaded->statusUpdates()->where('stage', 'submitted')->count();
        $fee->note = trim((string) $fee->note) . ' (touched)';
        $fee->status = 1;
        $fee->save();
        check('re-saving a settled fee does not submit twice',
            $reloaded->fresh()->statusUpdates()->where('stage', 'submitted')->count() === $timelineCount);
        check('autoSubmit on a submitted application is a no-op',
            $service->autoSubmit($reloaded->fresh(), 'test') === false);
    } else {
        echo "  SKIP  this degree type charges no admission fee\n";
    }

    /* =================================================================
     | 5. An ordinary student fee must not touch any application
     |=================================================================*/
    section('The admissions register can always find a submission');

    check('no submitted application is missing its date of application',
        Application::whereNull('apply_date')->where('stage', '!=', 'draft')->doesntExist(),
        Application::whereNull('apply_date')->where('stage', '!=', 'draft')->pluck('registration_no')->implode(', '));

    // Even so, the register must not hide a row over a missing date — that is
    // how this went unnoticed in the first place.
    $probe = Application::where('stage', '!=', 'draft')->orderByDesc('id')->first();
    if ($probe) {
        $keep = $probe->apply_date;
        $probe->apply_date = null;
        $probe->save();

        $found = Application::whereRaw('DATE(COALESCE(apply_date, created_at)) >= ?', [now()->subYears(5)->toDateString()])
            ->whereRaw('DATE(COALESCE(apply_date, created_at)) <= ?', [now()->toDateString()])
            ->whereKey($probe->id)
            ->exists();

        check('an application with no date still falls inside a date range', $found);

        $probe->apply_date = $keep;
        $probe->save();
    }

    section('Student fees are left alone');

    $studentFee = Fee::whereNotIn('id', Application::whereNotNull('admission_fee_id')->pluck('admission_fee_id'))
        ->first();
    if ($studentFee) {
        $submittedBefore = Application::where('stage', 'submitted')->count();
        $studentFee->status = 1;
        $studentFee->save();
        check('settling a student fee submits nothing',
            Application::where('stage', 'submitted')->count() === $submittedBefore);
    } else {
        echo "  SKIP  no non-admission fee to test with\n";
    }
} catch (\Throwable $e) {
    $failed++;
    echo "\n  ERROR  " . $e->getMessage() . "\n         " . $e->getFile() . ':' . $e->getLine() . "\n";
} finally {
    DB::rollBack();
    echo "\n  (all changes rolled back)\n";
}

echo "\n" . str_repeat('-', 60) . "\n";
echo sprintf("%d passed, %d failed\n", $passed, $failed);
exit($failed > 0 ? 1 : 0);
