<?php

/**
 * Does the formatting an admin applies in the editor survive into the PDF the
 * applicant receives?
 *
 * The bug this suite exists to hold shut: the PDF template set the body font
 * with `* { font-family: ... }`. The universal selector applies a font
 * DIRECTLY to every element, including <strong> and <em>, and a direct rule
 * beats an inherited one — so bold text inside a span the author had set to
 * Georgia discarded the Georgia and reset to DejaVu Sans. The word came out
 * heavier and in a different typeface, which reads as a glitch rather than as
 * emphasis.
 *
 * Nothing here writes to the database.
 */

$base = 'C:/xampp/htdocs/paxhitestbed';
require $base . '/vendor/autoload.php';
$app = require $base . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Services\AcceptanceLetterService;
use App\Services\DocumentHtml;
use Dompdf\Dompdf;
use Illuminate\Support\Facades\DB;

$pass = 0;
$fail = 0;

function check(string $what, $expected, $actual): void
{
    global $pass, $fail;

    if ($expected === $actual) {
        $pass++;

        return;
    }

    $fail++;
    printf("  FAIL  %s\n          expected: %s\n          actual  : %s\n",
        $what, var_export($expected, true), var_export($actual, true));
}

/**
 * Which typefaces did dompdf actually embed?
 *
 * The PDF names each one in a /BaseFont entry. A subset prefix ("SUBAAC+") and
 * a weight/style suffix ("-Bold") are stripped, leaving the family — which is
 * the thing under test: regular, bold and italic of one choice must all come
 * from the SAME family, or the reader sees the typeface change mid-sentence.
 */
function familiesIn(string $body, string $css = 'body { font-family: DejaVu Sans, sans-serif; }'): array
{
    $dompdf = new Dompdf([
        'isHtml5ParserEnabled' => true,
        'fontDir' => storage_path('fonts'),
        'fontCache' => storage_path('fonts'),
    ]);
    $dompdf->loadHtml('<html><head><style>' . $css . '</style></head><body><div class="body">' . $body . '</div></body></html>');
    $dompdf->setPaper('a4', 'portrait');
    $dompdf->render();

    preg_match_all('/\/BaseFont\s*\/([A-Za-z0-9+,.-]+)/', $dompdf->output(), $matches);

    $families = [];

    foreach ($matches[1] as $font) {
        $font = preg_replace('/^[A-Z]{6}\+/', '', $font);   // subset prefix
        $font = preg_replace('/[-,].*$/', '', $font);       // weight / style
        $families[$font] = true;
    }

    ksort($families);

    return array_keys($families);
}

/** The template's own CSS, so the test renders through what the letter renders through. */
function templateCss(): string
{
    $view = file_get_contents('C:/xampp/htdocs/paxhitestbed/resources/views/admin/acceptance-letter/pdf.blade.php');

    preg_match('/<style>(.*?)<\/style>/s', $view, $match);

    return $match[1] ?? '';
}

echo "\n=== 1. one family per font choice, in the real template CSS ===\n";

$css = templateCss();

// Every font the editor offers, plus the ones letters already written use.
$fonts = [
    'Arial,helvetica,sans-serif',
    'Courier New,courier,monospace',
    'DejaVu Sans,sans-serif',
    'DejaVu Serif,serif',
    'Times New Roman,times,serif',
    "'Georgia',serif",            // 101 declarations in the live letter
    'Verdana,geneva',
    'Tahoma,arial,helvetica,sans-serif',
];

foreach ($fonts as $font) {
    $body = '<p><span style="font-family: ' . $font . ';">plain <strong>bold</strong> <em>italic</em> <strong><em>both</em></strong></span></p>';

    $families = familiesIn(DocumentHtml::mapPdfFonts($body), $css);

    check(sprintf('%-36s renders as exactly one family', $font), 1, count($families));
}

echo "\n=== 2. a sans choice stays sans, a serif choice stays serif ===\n";

$kind = function (string $font) use ($css) {
    $body = '<p><span style="font-family: ' . $font . ';">plain <strong>bold</strong></span></p>';

    return familiesIn(DocumentHtml::mapPdfFonts($body), $css)[0] ?? '(none)';
};

check('Georgia  (serif) prints in a serif face', 'Times', $kind("'Georgia',serif"));
check('Verdana  (sans)  prints in a sans face', 'Helvetica', $kind('Verdana,geneva'));
check('Tahoma   (sans)  prints in a sans face', 'Helvetica', $kind('Tahoma,arial,helvetica,sans-serif'));
check('Arial    (sans)  prints in a sans face', 'Helvetica', $kind('Arial,helvetica,sans-serif'));
check('Times New Roman  prints in a serif face', 'Times', $kind('Times New Roman,times,serif'));
check('DejaVu Serif     prints as DejaVu Serif', 'DejaVuSerif', $kind('DejaVu Serif,serif'));

echo "\n=== 3. text with no font set still gets the document default ===\n";

$families = familiesIn('<p>plain <strong>bold</strong> <em>italic</em></p>', $css);
check('untouched text uses one family', 1, count($families));
check('and that family is DejaVu Sans', 'DejaVuSans', $families[0] ?? '(none)');

echo "\n=== 4. the universal selector is gone from the template ===\n";

$view = file_get_contents($base . '/resources/views/admin/acceptance-letter/pdf.blade.php');

// Comments quote the old rule by way of explanation, so strip them first —
// otherwise this check passes or fails on the prose, not on the CSS.
$withoutComments = preg_replace('!/\*.*?\*/!s', '', $view);

check('no `* { font-family }` rule survives', 0,
    preg_match('/\*\s*\{[^}]*font-family/i', $withoutComments));
check('the body does set a font-family', 1,
    preg_match('/\bbody\s*\{[^}]*font-family/i', $withoutComments));

echo "\n=== 5. mapPdfFonts only rewrites what dompdf cannot serve ===\n";

check('a serviceable family is left exactly as written',
    'font-family: helvetica, sans-serif',
    DocumentHtml::mapPdfFonts('font-family: helvetica, sans-serif'));

check('Times New Roman resolves to the face dompdf has',
    'font-family: times, serif',
    DocumentHtml::mapPdfFonts('font-family: Times New Roman, times, serif'));

check('Georgia becomes a serif dompdf has',
    'font-family: times, serif',
    DocumentHtml::mapPdfFonts("font-family: 'Georgia', serif"));

check('Verdana becomes a sans dompdf has',
    'font-family: helvetica',
    DocumentHtml::mapPdfFonts('font-family: Verdana, geneva'));

// dompdf renders a bare "arial" or "courier new" in Times-Roman, so a mapped
// list must never be left with one as its only entry.
check('bare Arial is replaced, not kept',
    'font-family: helvetica',
    DocumentHtml::mapPdfFonts('font-family: Arial'));

check('bare Courier New is replaced, not kept',
    'font-family: courier',
    DocumentHtml::mapPdfFonts('font-family: "Courier New"'));

check('a list of nothing serviceable loses its declaration',
    'color: red; ; font-size: 12px',
    DocumentHtml::mapPdfFonts('color: red; font-family: Brandon Grotesque, Foo Sans; font-size: 12px'));

check('the text itself is never touched',
    '<p>Georgia is a state and a font.</p>',
    DocumentHtml::mapPdfFonts('<p>Georgia is a state and a font.</p>'));

check('null in, null out', null, DocumentHtml::mapPdfFonts(null));

echo "\n=== 6. a placeholder split by editor markup still resolves ===\n";

$tokens = ['[name]' => 'ATANGANA Marie', '[program]' => 'Nursing'];

$cases = [
    'intact'                      => ['[name]', 'ATANGANA Marie'],
    'wholly bold'                 => ['<strong>[name]</strong>', '<strong>ATANGANA Marie</strong>'],
    'opening tag inside'          => ['[na<strong>me]</strong>', '<strong>ATANGANA Marie</strong>'],
    'closing tag inside'          => ['<strong>[na</strong>me]', '<strong>ATANGANA Marie</strong>'],
    'span with a font inside'     => ['[na<span style="font-family: Georgia">me]</span>', '<span style="font-family: Georgia">ATANGANA Marie</span>'],
    'bold and italic, both split' => ['[na<strong><em>me]</em></strong>', '<strong><em>ATANGANA Marie</em></strong>'],
    'two tokens, one split'       => ['[program] [na<strong>me]</strong>', 'Nursing <strong>ATANGANA Marie</strong>'],
];

$letters = app(AcceptanceLetterService::class);

foreach ($cases as $what => [$input, $expected]) {
    check(sprintf('%-28s', $what), $expected, $letters->substitute($input, $tokens));
}

echo "\n=== 7. and the formatting now wraps the value, not half a token ===\n";

$rendered = $letters->substitute('<p>Dear [na<strong>me]</strong>,</p>', $tokens);

check('the value sits inside the <strong>', 1,
    preg_match('/<strong>ATANGANA Marie<\/strong>/', $rendered));
check('no bracket is left behind', 0, preg_match('/[\[\]]/', $rendered));

echo "\n=== 8. prose in brackets is not a placeholder ===\n";

check('bracketed prose is left alone',
    '[see <em>note</em> below]',
    $letters->substitute('[see <em>note</em> below]', $tokens));

check('an unknown token is left alone',
    '[not<strong>_a_token]</strong>',
    $letters->substitute('[not<strong>_a_token]</strong>', $tokens));

check('a known token name that is not in this map is left alone',
    '[fa<strong>culty]</strong>',
    $letters->substitute('[fa<strong>culty]</strong>', $tokens));

echo "\n=== 9. the live letter for degree type 1 ===\n";

$stored = DB::table('degree_type_application_settings')
    ->where('degree_type_id', 1)
    ->value('acceptance_letter_html');

if (!filled($stored)) {
    echo "  (no letter configured — skipped)\n";
} else {
    $prepared = DocumentHtml::prepare($stored, true);

    check('no family dompdf lacks a file for survives', 0,
        preg_match('/font-family\s*:\s*[^;"}]*(georgia|verdana|tahoma|palatino|geneva|arial|courier new)/i', $prepared));

    // The letter legitimately mixes faces - it uses Georgia for the body and
    // Arial for the headings - so "one family in the whole letter" is the
    // wrong invariant. What matters is that each family the author CHOSE
    // prints as one family, bold and italic included. That is checked here
    // against the letter's own declarations rather than a list I picked.
    preg_match_all('/font-family\s*:\s*([^;"}]+)/i', $prepared, $declared);

    $distinct = array_values(array_unique(array_map('trim', $declared[1])));

    printf("  %d font-family declarations, %d distinct: %s
",
        count($declared[1]), count($distinct), implode(' | ', $distinct));

    foreach ($distinct as $font) {
        $sample = '<p><span style="font-family: ' . $font . ';">plain <strong>bold</strong> <em>italic</em></span></p>';

        check(sprintf('its own %-24s prints as one family', '"' . $font . '"'),
            1, count(familiesIn($sample, $css)));
    }

    printf("  families the whole letter embeds: %s
", implode(', ', familiesIn($prepared, $css)));
}

echo "\n=== 10. teeth: break each rule, prove a named check fails ===\n";

$template = $base . '/resources/views/admin/acceptance-letter/pdf.blade.php';
$before = file_get_contents($template);
$hashBefore = md5($before);

// Put the universal selector back and confirm check 1 notices.
file_put_contents($template, str_replace(
    'body { font-family: DejaVu Sans, sans-serif; }',
    '* { font-family: DejaVu Sans, sans-serif; }',
    $before));

$brokenCss = templateCss();
$body = '<p><span style="font-family: times new roman, times, serif;">plain <strong>bold</strong></span></p>';
$brokenFamilies = familiesIn($body, $brokenCss);

check('with `*` restored, bold leaves the family (check 1 would fail)',
    true, count($brokenFamilies) > 1);
printf("          (it rendered as: %s)\n", implode(', ', $brokenFamilies));

file_put_contents($template, $before);

check('template restored byte for byte', $hashBefore, md5(file_get_contents($template)));

// And the placeholder repair: without it, a split token goes out raw.
check('without the repair, a split token survives into the letter',
    '[na<strong>me]</strong>',
    strtr('[na<strong>me]</strong>', $tokens));

echo "\n=== nothing was left behind ===\n";

printf("  degree_type_application_settings : %d rows\n", DB::table('degree_type_application_settings')->count());
printf("  letter html for degree type 1    : %d bytes\n", strlen((string) $stored));
printf("  pdf.blade.php                    : %s\n",
    md5_file($template) === $hashBefore ? 'unchanged' : '*** CHANGED');

printf("\n%d passed, %d failed\n\n", $pass, $fail);

exit($fail === 0 ? 0 : 1);
