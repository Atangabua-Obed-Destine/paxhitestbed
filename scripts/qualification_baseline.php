<?php
/**
 * Data-safety snapshot for the qualification-card migration.
 *
 * Run before migrating to write a baseline, then again after to compare. The
 * point is to prove the 291 existing checklist files and the 97 academic
 * history rows survive untouched — the migration adds columns, it must never
 * move or drop evidence an applicant already submitted.
 *
 *   php scripts/qualification_baseline.php save
 *   php scripts/qualification_baseline.php compare
 */
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

$mode = $argv[1] ?? 'compare';
$file = __DIR__ . '/../storage/app/qualification-baseline.json';

$snapshot = [
    'documents_by_type' => DB::table('application_documents')
        ->selectRaw('document_type, count(*) total, sum(case when file_path is not null and file_path <> "" then 1 else 0 end) with_file')
        ->groupBy('document_type')->orderBy('document_type')
        ->get()->keyBy('document_type')
        ->map(fn ($r) => ['total' => (int) $r->total, 'with_file' => (int) $r->with_file])
        ->toArray(),

    'academic_history_rows' => (int) DB::table('application_academic_histories')->count(),

    // Hash the payload so a silent value change is caught, not just a count.
    'academic_history_digest' => md5((string) DB::table('application_academic_histories')
        ->orderBy('id')
        ->get(['id', 'application_id', 'institution_name', 'city', 'country',
               'instruction_language', 'date_from', 'date_to', 'certificate_obtained',
               'certificate_file', 'display_order'])
        ->toJson()),

    'legacy_columns' => DB::table('applications')
        ->selectRaw('sum(school_certificate is not null and school_certificate <> "") school_cert,
                     sum(collage_certificate is not null and collage_certificate <> "") collage_cert,
                     sum(school_transcript is not null and school_transcript <> "") school_trans,
                     sum(collage_transcript is not null and collage_transcript <> "") collage_trans')
        ->first(),
];

// Which recorded files are readable on disk. NOTE: on this dev database most
// are already absent (the DB was restored without public/uploads), so the test
// is not "nothing is missing" but "nothing NEWLY went missing".
$missing = [];
$checked = 0;
foreach (DB::table('application_documents')->whereNotNull('file_path')->where('file_path', '<>', '')->get(['document_type', 'file_path']) as $doc) {
    $checked++;
    if (!is_file(public_path('uploads/student/' . $doc->file_path))) {
        $missing[] = $doc->document_type . ' → ' . $doc->file_path;
    }
}
sort($missing);
$snapshot['missing_files'] = $missing;

if ($mode === 'save') {
    file_put_contents($file, json_encode($snapshot, JSON_PRETTY_PRINT));
    echo "Baseline written to storage/app/qualification-baseline.json\n\n";
}

printf("Checklist files: %d recorded, %d present, %d absent from disk (pre-existing on dev)\n",
    $checked, $checked - count($missing), count($missing));

echo "\nDocuments by type:\n";
foreach ($snapshot['documents_by_type'] as $type => $counts) {
    printf("  %-24s total=%-4d with_file=%d\n", $type, $counts['total'], $counts['with_file']);
}
printf("\nAcademic history rows: %d (digest %s)\n", $snapshot['academic_history_rows'], substr($snapshot['academic_history_digest'], 0, 12));
printf("Legacy columns: school_cert=%d collage_cert=%d school_trans=%d collage_trans=%d\n",
    $snapshot['legacy_columns']->school_cert, $snapshot['legacy_columns']->collage_cert,
    $snapshot['legacy_columns']->school_trans, $snapshot['legacy_columns']->collage_trans);

if ($mode === 'compare') {
    if (!is_file($file)) {
        echo "\nNo baseline saved yet — run with 'save' first.\n";
        exit(1);
    }

    $base = json_decode(file_get_contents($file), true);
    $now = json_decode(json_encode($snapshot), true);
    $problems = [];

    foreach ($base['documents_by_type'] as $type => $counts) {
        $after = $now['documents_by_type'][$type] ?? null;
        if (!$after) {
            $problems[] = "document type '$type' disappeared";
        } elseif ($after['with_file'] < $counts['with_file']) {
            $problems[] = "'$type' lost files: {$counts['with_file']} → {$after['with_file']}";
        }
    }

    if ($base['academic_history_rows'] !== $now['academic_history_rows']) {
        $problems[] = "academic history row count changed: {$base['academic_history_rows']} → {$now['academic_history_rows']}";
    }
    if ($base['academic_history_digest'] !== $now['academic_history_digest']) {
        $problems[] = 'academic history existing column values changed';
    }
    foreach ((array) $base['legacy_columns'] as $col => $val) {
        if ((int) $val !== (int) $now['legacy_columns'][$col]) {
            $problems[] = "applications.$col count changed: $val → {$now['legacy_columns'][$col]}";
        }
    }
    // Only newly-absent files matter; the pre-existing gap is dev data drift.
    $newlyMissing = array_diff($missing, $base['missing_files'] ?? []);
    foreach (array_slice($newlyMissing, 0, 10) as $m) {
        $problems[] = "file newly missing from disk: $m";
    }

    echo "\n";
    foreach ($problems as $p) {
        echo "FAIL  $p\n";
    }
    echo $problems ? "\n" . count($problems) . " problem(s)\n" : "OK — no evidence lost\n";
    exit($problems ? 1 : 0);
}
