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
section('A table set to no borders renders with no borders');
// ---------------------------------------------------------------------------
// The document stylesheet gives tables a default border, written against th and
// td. An editor records "no borders" on the <table> element only, so the cells
// had nothing to override that default with and the letterhead came out with a
// box round the logo and a line down the middle of the address block.
$marks = fn (string $html) => str_contains(
    (string) DocumentHtml::normalize($html),
    DocumentHtml::NO_BORDER_CLASS
);

$cell = '<tr><td>x</td></tr></table>';

// What the editor on this system actually writes.
check('the spelling the editor writes is recognised',
    $marks('<table style="border-collapse: collapse; width: 98.75%; border-style: none;">' . $cell),
    'border-style: none on the table, nothing on its cells');

foreach ([
    'border-style with no space' => '<table style="border-style:none">',
    'the border shorthand, none' => '<table style="border: none;">',
    'the border shorthand, zero' => '<table style="border:0">',
    'border-style: hidden' => '<table style="border-style: hidden;">',
    'the border="0" attribute' => '<table border="0">',
    'border=0 unquoted' => '<table border=0>',
    'upper case' => '<TABLE STYLE="BORDER-STYLE: NONE">',
] as $what => $open) {
    check('it recognises ' . $what, $marks($open . $cell), $open);
}

// And the other half of the rule: a table that wants a border keeps one.
foreach ([
    'a table asking for a border' => '<table style="border: 1px solid #555;">',
    'a plain table with no styling' => '<table>',
    'border="1"' => '<table border="1">',
] as $what => $open) {
    check('it leaves ' . $what . ' alone', !$marks($open . $cell),
        'the default border exists for exactly this case - ' . $open);
}

check('a table marked twice is marked once',
    substr_count((string) DocumentHtml::normalize(
        (string) DocumentHtml::normalize('<table style="border: none;">' . $cell)
    ), DocumentHtml::NO_BORDER_CLASS) === 1,
    'normalise runs on paths that may already have run');

check('an existing class is kept',
    str_contains((string) DocumentHtml::normalize(
        '<table class="fee-breakdown" style="border: none;">' . $cell
    ), 'fee-breakdown'),
    'the fee breakdown table carries a class the stylesheet needs');

// The stylesheet has to honour the mark, or none of the above matters.
$letterCss = (string) file_get_contents(
    __DIR__ . '/../resources/views/admin/acceptance-letter/pdf.blade.php'
);

check('the letter stylesheet turns the border off for marked tables',
    str_contains($letterCss, 'table.' . DocumentHtml::NO_BORDER_CLASS),
    'the class is only worth setting if something reads it');

check('and it turns it off for their cells, not just the table',
    str_contains($letterCss, 'table.' . DocumentHtml::NO_BORDER_CLASS . ' td')
    || str_contains($letterCss, 'table.' . DocumentHtml::NO_BORDER_CLASS . ' th'),
    'the cells are what was drawing the box in the first place');

// ---------------------------------------------------------------------------
section('The letter starts where the page margin says, not twice as far down');
// ---------------------------------------------------------------------------
// dompdf's built-in stylesheet applies @page { margin: 1.2cm } whenever nothing
// says otherwise, and the template's own .sheet padding used to land inside it.
// The two stacked, so every letter began about 2.3cm down the page with nothing
// in this project having asked for it. Measured on the rendered PDF rather than
// matched in the CSS, because the number on the page is the thing that matters.
$firstLineFromTop = function (string $body): ?float {
    $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('admin.acceptance-letter.pdf', [
        'body' => $body,
        'student' => null,
        'setting' => null,
    ]);
    $pdf->setPaper('a4', 'portrait');
    $pdf->setOption('isHtml5ParserEnabled', true);

    preg_match_all('/stream\r?\n(.*?)\r?\nendstream/s', $pdf->output(), $streams);

    foreach ($streams[1] as $stream) {
        $content = @gzuncompress($stream);
        if ($content === false) {
            $content = @gzinflate($stream);
        }

        if ($content === false || !str_contains($content, 'BT ')) {
            continue;
        }

        preg_match_all('/BT ([\d.]+) ([\d.]+) Td/', $content, $text, PREG_SET_ORDER);

        $highest = 0.0;
        foreach ($text as $one) {
            $highest = max($highest, (float) $one[2]);
        }

        // A4 is 841.89pt tall and PDF y counts up from the bottom.
        return $highest > 0 ? 841.89 - $highest : null;
    }

    return null;
};

$top = $firstLineFromTop('<p>First line.</p>');

if ($top === null) {
    echo "  SKIP  could not read a text position out of the rendered PDF
";
} else {
    // 1.2cm is 34pt; the baseline sits a line's ascent below the top of the box.
    check('the first line sits about one page margin down, not two',
        $top < 60,
        sprintf('first line is %.1fpt (%.2f in) from the top - a second margin is '
            . 'stacking inside the page margin', $top, $top / 72));

    check('and it is not jammed against the edge of the page',
        $top > 20,
        sprintf('first line is %.1fpt from the top', $top));
}

// Comments stripped first: the note explaining this rule quotes the very
// declaration it is looking for, so a plain search matched the prose and the
// check passed with the rule deleted.
$letterDeclarations = (string) preg_replace('#/\*.*?\*/#s', '', $letterCss);

check('the letter stylesheet states its page margin',
    (bool) preg_match('/@page\s*\{[^}]*margin/i', $letterDeclarations),
    'left unstated, dompdf applies its own 1.2cm and nothing in this project shows where it came from');

// ---------------------------------------------------------------------------
section('The preview shows what is actually sent');
// ---------------------------------------------------------------------------
// The sent letter resolves the editor's relative image paths; the preview did
// not, so the one screen an admin checks their letter on printed
// "Image not found" where the applicant's copy carried the logo.
$service = (string) file_get_contents(__DIR__ . '/../app/Services/AcceptanceLetterService.php');
$previewAt = strpos($service, 'acceptance-letter-preview');
$previewAt = $previewAt === false ? strpos($service, 'function previewPdf') : $previewAt;

check('the preview resolves image paths the way the sent letter does',
    substr_count($service, 'DocumentHtml::prepare') >= 1
    || substr_count($service, 'DocumentHtml::resolveImages') >= 2,
    'dompdf cannot resolve the editor\'s relative paths and drops the image silently');

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
