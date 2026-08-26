<?php
/**
 * The institution's payment instructions must be impossible to miss.
 *
 * They are configured per degree type under Academic > Degree Type > Form
 * Configuration, and used to render into a bare unstyled <div> wedged between
 * the "what to upload" notice and the form fields. An applicant on that tab has
 * exactly one question first - which account do I pay into - and everything
 * below the fold is unusable until it is answered, yet that was the least
 * visible text on the page.
 *
 * What has to hold:
 *   - the configured text reaches the payment step, formatted, not raw;
 *   - it appears BEFORE the form fields that depend on having paid;
 *   - the amount and the reference to quote are shown as figures;
 *   - plain text typed into a textarea becomes paragraphs, lists stay lists;
 *   - markup an admin pastes deliberately still renders;
 *   - text an admin did not intend as markup is escaped.
 *
 * Usage: php scripts/payment_instructions_test.php
 */

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Application;
use App\Services\DegreeTypeFormConfig;
use Illuminate\Support\Facades\Auth;

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
        echo "  FAIL  $label" . ($detail !== '' ? "\n          $detail" : '') . "\n";
    }
}

echo "\n== The formatter ==\n";

check(
    'a single typed sentence becomes a paragraph',
    admin_rich_text('Pay at the bursary.') === '<p>Pay at the bursary.</p>',
    admin_rich_text('Pay at the bursary.')
);

$numbered = admin_rich_text("1. Go to any UBA branch\n2. Pay into 0123456789\n3. Keep the receipt");
check('numbered lines become an ordered list', strpos($numbered, '<ol>') === 0 && substr_count($numbered, '<li>') === 3, $numbered);
check('the numbering itself is not repeated in the text', strpos($numbered, '<li>1.') === false, $numbered);

$bulleted = admin_rich_text("- Pay into UBA 0123456789\n- Quote your application number");
check('dashed lines become a bullet list', strpos($bulleted, '<ul>') === 0 && substr_count($bulleted, '<li>') === 2, $bulleted);

check(
    'markup an admin pasted deliberately is left alone',
    admin_rich_text('<p>Pay at <strong>the bursary</strong>.</p>') === '<p>Pay at <strong>the bursary</strong>.</p>'
);

// The escape hatch above is for trusted admin markup. Anything that does not
// look like markup must not be able to smuggle a tag through.
$hostile = admin_rich_text("Pay here\nthen <script>alert(1)</script>");
check('text that is not markup is escaped', strpos($hostile, '<script>') === false, $hostile);

check('empty configuration yields nothing', admin_rich_text('') === '' && admin_rich_text(null) === '');

$blanks = admin_rich_text("Line one\n\n\nLine two");
check('blank lines do not produce empty paragraphs', strpos($blanks, '<p></p>') === false, $blanks);

echo "\n== On the payment step ==\n";

$application = Application::whereNotNull('applicant_id')->whereNotNull('degree_type_id')->orderByDesc('id')->first();
if (!$application) {
    fwrite(STDERR, "no application with a degree type to render\n");
    exit(2);
}

Auth::guard('applicant')->loginUsingId($application->applicant_id);

$kernel  = app(Illuminate\Contracts\Http\Kernel::class);
$request = Illuminate\Http\Request::create('/application/' . $application->id . '/edit', 'GET');
$request->setLaravelSession(app('session.store'));
$response = $kernel->handle($request);

check('the wizard renders', $response->getStatusCode() === 200, 'status ' . $response->getStatusCode());
$html = $response->getContent();

check('the How to pay panel is present', strpos($html, 'pay-guide-head') !== false);
check('the amount is shown as a figure', strpos($html, 'Amount to pay') !== false);
check('the receipt reminder is present', strpos($html, 'Keep your receipt') !== false);

if (!empty($application->registration_no)) {
    check(
        'the reference to quote is the application number',
        strpos($html, 'Quote this reference') !== false
            && strpos($html, (string) $application->registration_no) !== false
    );
}

$settings = DegreeTypeFormConfig::settings($application->degreeType);
$configured = trim((string) $settings['fee_instructions']);

if ($configured !== '') {
    // The words the administrator actually typed must reach the page.
    $firstWords = implode(' ', array_slice(preg_split('/\s+/', strip_tags($configured)), 0, 4));
    check(
        'the configured instructions reach the applicant',
        stripos($html, e($firstWords)) !== false || stripos($html, $firstWords) !== false,
        'looked for: ' . $firstWords
    );
    check(
        'they are formatted, not dumped as a bare string',
        strpos($html, 'pay-guide-body') !== false
    );

    // Position is the whole point of the change: instructions before the
    // fields, not after them.
    $guidePos = strpos($html, 'pay-guide-head');
    $formPos  = strpos($html, 'pay_payment_reference');
    check(
        'they appear before the fields that depend on having paid',
        $guidePos !== false && $formPos !== false && $guidePos < $formPos,
        "guide at $guidePos, form at $formPos"
    );
} else {
    echo "  ..  no instructions configured for this degree type\n";
    check('a fallback tells the applicant who to ask', strpos($html, 'Contact the Admissions Office') !== false);
}

echo "\n$passed passed, $failed failed\n";
exit($failed === 0 ? 0 : 1);
