<?php

/**
 * What survives the trip from the editor to a sent document.
 *
 * Content written in TinyMCE is normalised before dompdf sees it, because Word
 * pastes carry fixed pixel widths that overflow the page and list indents that
 * make bullets overlap their own text. The strip was too broad twice over:
 *
 *   - it took the width off an <img> as well, so a logo resized in the editor
 *     came out of a sent letter at its natural size;
 *   - it took percentage widths off tables, so a letterhead laid out as a
 *     three-column table at width:100% collapsed to fit its text and its
 *     margin:auto then centred the remains.
 *
 * Both are about the same promise: what the editor shows is what the document
 * gets. So the checks are paired - each one names something that must survive
 * beside something that must still go, because a normaliser that keeps
 * everything is as wrong as one that keeps nothing.
 *
 * Nothing is written.
 *
 *   php scripts/document_html_test.php
 */

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Services\AcceptanceLetterService;
use App\Services\DocumentHtml;
use App\Services\LetterheadService;

$passed = 0;
$failed = 0;

function check(string $what, bool $ok, string $detail = ''): void
{
    global $passed, $failed;
    if ($ok) {
        $passed++;
        echo "  PASS  $what\n";
    } else {
        $failed++;
        echo "  FAIL  $what" . ($detail !== '' ? " — $detail" : '') . "\n";
    }
}

function section(string $title): void
{
    echo "\n$title\n";
}

$normalise = fn (string $html) => (string) DocumentHtml::normalize($html);

// ---------------------------------------------------------------------------
section('A size chosen in the editor is kept');
// ---------------------------------------------------------------------------
$out = $normalise('<p><img src="/uploads/editor/logo.png" width="180" height="90" alt=""></p>');

check('an image keeps the width it was given',
    str_contains($out, 'width="180"'),
    'a logo resized in the editor came out at its natural size — ' . $out);

check('and the height',
    str_contains($out, 'height="90"'),
    $out);

$out = $normalise('<p><img src="/uploads/editor/logo.png" style="width: 180px; height: 90px;"></p>');

check('an image sized through its style attribute keeps that too',
    str_contains($out, '180px') && str_contains($out, '90px'),
    $out);

// ---------------------------------------------------------------------------
section('A relative width is kept, a fixed one is not');
// ---------------------------------------------------------------------------
$out = $normalise('<table style="width: 100%"><tr><td style="width: 47%">a</td><td>b</td></tr></table>');

check('a table at 100% stays at 100%',
    str_contains($out, 'width: 100%'),
    'this is what made the letterhead collapse into the middle of the page — ' . $out);

check('and its column shares survive',
    str_contains($out, 'width: 47%'),
    $out);

$out = $normalise('<table width="451" style="width: 451px"><tr><td>a</td></tr></table>');

check('a fixed pixel width is still removed',
    !str_contains($out, '451'),
    'Word writes these and they overflow the page — ' . $out);

// Word also writes percentages above 100, which overflow just as surely.
$out = $normalise('<table width="109.167%"><tr><td>a</td></tr></table>');

check('a percentage over 100 is removed as well',
    !str_contains($out, '109'),
    $out);

// ---------------------------------------------------------------------------
section('Word\'s own junk still goes');
// ---------------------------------------------------------------------------
$out = $normalise('<p style="mso-pagination: none; text-indent: -18.0pt;">x<o:p></o:p></p>');

check('mso- properties are removed', !str_contains($out, 'mso-'), $out);
check('empty o:p tags are removed', !str_contains($out, 'o:p'), $out);
check('negative text-indent is removed',
    !str_contains($out, 'text-indent'),
    'it makes list bullets overlap their own text in dompdf — ' . $out);

// ---------------------------------------------------------------------------
section('The acceptance letter uses the same rule');
// ---------------------------------------------------------------------------
// It used to carry its own copy, and the copies drifted: one learned to set
// images aside and the other did not, so the same content behaved differently
// depending on which document it went into.
$letter = app(AcceptanceLetterService::class);
$sample = '<p><img src="/uploads/editor/logo.png" width="160" height="80"></p>';

check('a letter keeps an editor-chosen image size',
    str_contains((string) $letter->normalizeLetterHtml($sample), 'width="160"'),
    'the sent letter rendered it at full size');

check('and normalises identically to every other document',
    $letter->normalizeLetterHtml($sample) === DocumentHtml::normalize($sample),
    'two copies of one rule is how they drifted apart in the first place');

// ---------------------------------------------------------------------------
section('The letterhead comes out the way it is configured');
// ---------------------------------------------------------------------------
$rendered = (string) app(LetterheadService::class)->render(false);

if (trim($rendered) === '') {
    echo "  SKIP  no letterhead configured\n";
} else {
    preg_match('/<table[^>]*>/i', $rendered, $table);

    if (!$table) {
        echo "  SKIP  the configured letterhead is not laid out as a table\n";
    } else {
        check('its table keeps a full-width declaration',
            str_contains($table[0], 'width: 100%') || str_contains($table[0], 'width:100%'),
            'without it the table shrinks to its text and margin:auto centres it — ' . $table[0]);

        check('and its columns keep their shares',
            (bool) preg_match('/<td[^>]*width:\s*[0-9.]+%/i', $rendered),
            'the three-column layout depends on them');
    }
}

echo "\n----\npassed $passed, failed $failed\n";

exit($failed > 0 ? 1 : 0);
