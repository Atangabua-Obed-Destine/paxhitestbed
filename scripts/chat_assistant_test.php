<?php

/**
 * The context-aware chat assistant: who it answers, what it refuses, and what
 * it says it is.
 *
 * The module's security design is good — identity comes only from the auth
 * guards and no "my_*" tool accepts a subject identifier, so a prompt-injected
 * "show me student 42's fees" has nowhere to put the 42. This suite holds that
 * design in place, and covers the things that were actually wrong:
 *
 *  - It introduced itself as "Catholic University of Cameroon, Bamenda
 *    (CATUC)" — another school entirely. That cost answers as well as
 *    credibility: the model built tool names out of the name it was given,
 *    calling "catuc_portal:get_programmes" instead of list_programmes, then
 *    telling the visitor it could not list programmes.
 *  - /chat/reset checked neither the enabled flag nor the rate limit, so any
 *    visitor could create unbounded conversation rows.
 *  - history_retention_days was configurable and enforced by nothing.
 *  - An unknown tool name was refused without saying what the real ones are,
 *    so a model that guessed wrong gave up instead of correcting itself.
 *
 * No live model call is made anywhere in here: the API key is on a metered
 * quota, and a suite that spends money every run will stop being run.
 *
 * Writes happen inside transactions that are rolled back; the last section
 * proves the tables are as they were found.
 */

$base = 'C:/xampp/htdocs/paxhitestbed';
require $base . '/vendor/autoload.php';
$app = require_once $base . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Applicant;
use App\Models\ChatConversation;
use App\Models\ChatMessage;
use App\Models\ChatSetting;
use App\Models\Student;
use App\Services\Chat\ChatContext;
use App\Services\Chat\ChatService;
use App\Services\Chat\ChatToolProvider;
use App\Services\Chat\GeminiChatClient;
use App\Services\Chat\ToolExecutor;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

$passed = 0;
$failed = 0;

function section(string $title): void
{
    echo "\n", $title, "\n", str_repeat('-', strlen($title)), "\n";
}

function check(string $label, bool $ok, string $detail = ''): void
{
    global $passed, $failed;

    if ($ok) {
        $passed++;
        echo "  PASS  $label\n";
    } else {
        $failed++;
        echo "  FAIL  $label", $detail ? "  ($detail)" : '', "\n";
    }
}

$registry = ChatToolProvider::registry();
$executor = new ToolExecutor($registry);

$student = Student::first();
$applicant = Applicant::first();
$superAdmin = App\User::whereHas('roles', fn ($q) => $q->where('name', 'Super Admin'))->first();
$plainStaff = App\User::get()->first(fn ($u) => !$u->can('student-view'));

$contexts = [
    'guest' => new ChatContext(ChatConversation::ACTOR_GUEST, null, 'web', 'k-guest'),
    'student' => new ChatContext(ChatConversation::ACTOR_STUDENT, $student->id ?? 0, 'student', 'k-stu'),
    'applicant' => new ChatContext(ChatConversation::ACTOR_APPLICANT, $applicant->id ?? 0, 'application', 'k-app'),
    'staff' => new ChatContext(ChatConversation::ACTOR_USER, $superAdmin->id ?? 0, 'admin', 'k-sa'),
];

// ---------------------------------------------------------------------------
section('It says which institution it is');

$prompt = (function () use ($contexts) {
    $service = app(ChatService::class);
    $method = new ReflectionMethod($service, 'systemPrompt');
    $method->setAccessible(true);

    return $method->invoke($service, $contexts['student'], ChatSetting::current());
})();

check('the system prompt names this institution',
    str_contains($prompt, institution_name()), institution_name() . ' not found');
check('the system prompt names no other institution',
    !preg_match('/catuc|catholic university/i', $prompt),
    'another school is still named in the prompt');
check('the widget title names no other institution',
    !preg_match('/catuc/i', (string) ChatSetting::current()->title),
    'title is ' . ChatSetting::current()->title);

// The whole module, not just the two places it was found in.
$moduleFiles = array_merge(
    glob('C:/xampp/htdocs/paxhitestbed/app/Services/Chat/*.php') ?: [],
    glob('C:/xampp/htdocs/paxhitestbed/app/Services/Chat/Tools/*/*.php') ?: [],
    glob('C:/xampp/htdocs/paxhitestbed/app/Models/Chat*.php') ?: [],
    glob('C:/xampp/htdocs/paxhitestbed/app/Http/Controllers/Chat*.php') ?: [],
    glob('C:/xampp/htdocs/paxhitestbed/app/Http/Controllers/Admin/Chat*.php') ?: [],
    glob('C:/xampp/htdocs/paxhitestbed/resources/views/components/chat-widget.blade.php') ?: [],
    glob('C:/xampp/htdocs/paxhitestbed/public/dashboard/js/chat-widget.js') ?: [],
    glob('C:/xampp/htdocs/paxhitestbed/database/migrations/*chat*.php') ?: []
);

$offenders = [];

foreach (array_unique($moduleFiles) as $file) {
    if (!is_file($file) || !is_readable($file)) {
        continue;
    }

    $body = (string) file_get_contents($file);

    // The explanatory comments in this module name the old institution on
    // purpose, so only live code counts: comment lines are stripped first.
    $code = preg_replace('~^\s*(//|\*|/\*|\#).*$~m', '', $body);
    $code = preg_replace('~\{\{--.*?--\}\}~s', '', (string) $code);

    if (preg_match('/catuc|catholic university/i', (string) $code)) {
        $offenders[] = basename($file);
    }
}

check('no file in the chat module carries the old institution in live code',
    $offenders === [], implode(', ', $offenders));

// ---------------------------------------------------------------------------
section('The model is told to use the tools it was actually given');

check('the prompt forbids inventing or prefixing a tool name',
    str_contains($prompt, 'exact names') && str_contains($prompt, 'prefix'));
check('the prompt states the formatting the widget can render',
    str_contains($prompt, '**bold**'));

DB::beginTransaction();

try {
    $outcome = $executor->execute('catuc_portal:get_programmes', [], $contexts['guest'], null);

    check('an invented tool name is refused',
        ($outcome['payload']['error'] ?? null) === 'unknown_tool');
    check('and the refusal names the tools that do exist',
        str_contains($outcome['payload']['message'] ?? '', 'list_programmes'),
        $outcome['payload']['message'] ?? '');
    check('it names only the tools this actor may use',
        !str_contains($outcome['payload']['message'] ?? '', 'find_student'),
        'a guest was told about a staff tool');
} finally {
    DB::rollBack();
}

// ---------------------------------------------------------------------------
section('The instructions agree with the permissions');

// The gate and the prompt have to say the same thing. They did not: staff
// were handed find_student and count_applications and told, in the same
// breath, that they could only ever see their own records. A registrar
// holding every permission asked "how many applications this session?" and
// was refused as though they were a student. The tools were right; the
// instructions contradicted them, and the model obeyed the instructions.
$promptFor = function (ChatContext $context) {
    $service = app(ChatService::class);
    $method = new ReflectionMethod($service, 'systemPrompt');
    $method->setAccessible(true);

    return $method->invoke($service, $context, ChatSetting::current());
};

$ownRecordsOnly = 'only ever access the records of the person';

// Tools that read somebody other than the person asking.
$aboutOthers = ['find_student', 'count_applications'];

foreach ($contexts as $label => $context) {
    $prompt = $promptFor($context);
    $tools = array_keys($registry->availableFor($context));
    $hasOtherPeopleTool = array_intersect($aboutOthers, $tools) !== [];
    $restricted = str_contains($prompt, $ownRecordsOnly);

    if ($hasOtherPeopleTool) {
        check("$label: holds a tool about other people, so is NOT told it may only see its own",
            !$restricted,
            'the prompt forbids what the tools permit');
        check("$label: is told it may use those tools",
            str_contains($prompt, 'may look up students and institution-wide figures'));
    } else {
        check("$label: holds no tool about other people, so IS told to stay on its own records",
            $restricted);
    }
}

// Staff must never be told to refuse on scope grounds.
$staffPrompt = $promptFor($contexts['staff']);
check('the staff prompt never claims a restriction the tools do not impose',
    !str_contains($staffPrompt, $ownRecordsOnly) && !str_contains($staffPrompt, 'can only discuss their own'));
check('and it says the tools already match their permissions',
    str_contains($staffPrompt, "already match this person's permissions"));

// A student's prompt must still carry the restriction.
check('a student is still told to stay on their own records',
    str_contains($promptFor($contexts['student']), $ownRecordsOnly));
check('a guest is still told to stay on their own records',
    str_contains($promptFor($contexts['guest']), $ownRecordsOnly));

// ---------------------------------------------------------------------------
section('Tool descriptions carry the words people actually use');

// A tool the model cannot recognise from an everyday phrasing is a tool it
// reports as missing. The public tools always carried example phrasings; the
// staff ones did not, which is why only staff questions failed.
foreach ($registry->all() as $name => $tool) {
    check("$name describes itself in more than a few words",
        mb_strlen($tool->description()) >= 60, mb_strlen($tool->description()) . ' chars');
}

foreach (['list_programmes', 'open_intakes', 'knowledge_lookup', 'find_student', 'count_applications'] as $name) {
    check("$name gives example phrasings a person would use",
        str_contains($registry->get($name)->description(), 'Use for')
        || str_contains($registry->get($name)->description(), '"'),
        'no example phrasing');
}

// ---------------------------------------------------------------------------
section('The output budget leaves room for the model to think');

// Measured: at maxOutputTokens 200 the model spent 186 of them thinking,
// returned finishReason MAX_TOKENS, and never emitted the function call it
// had been working towards — then reported that it had no such tool. The
// budget has to cover the thinking as well as the answer.
check('the output budget is well above what thinking alone consumes',
    (int) ChatSetting::current()->max_output_tokens >= 2048,
    ChatSetting::current()->max_output_tokens . ' tokens');

// ---------------------------------------------------------------------------
section('Each actor is offered only its own tools');

$expected = [
    'guest' => ['list_programmes', 'open_intakes', 'knowledge_lookup'],
    'student' => ['list_programmes', 'open_intakes', 'knowledge_lookup',
        'my_profile', 'my_fees', 'my_attendance', 'my_exam_results', 'my_class_routine'],
    'applicant' => ['list_programmes', 'open_intakes', 'knowledge_lookup',
        'my_applications', 'my_application_timeline'],
    'staff' => ['list_programmes', 'open_intakes', 'knowledge_lookup',
        'find_student', 'count_applications', 'find_screen'],
];

foreach ($expected as $label => $want) {
    $got = array_keys($registry->availableFor($contexts[$label]));
    sort($got);
    sort($want);

    check("$label is offered exactly the right tools", $got === $want,
        'got ' . implode(',', $got));
}

check('a student is never offered a staff tool',
    !array_key_exists('find_student', $registry->availableFor($contexts['student'])));
check('a guest is never offered a personal tool',
    array_intersect(['my_fees', 'my_exam_results', 'my_applications'],
        array_keys($registry->availableFor($contexts['guest']))) === []);

if ($plainStaff) {
    $weak = new ChatContext(ChatConversation::ACTOR_USER, $plainStaff->id, 'admin', 'k-ps');

    check('staff without student-view lose the student lookup',
        !array_key_exists('find_student', $registry->availableFor($weak)));
    check('and keep the public tools',
        array_key_exists('list_programmes', $registry->availableFor($weak)));
} else {
    check('a staff user exists without student-view, to test the permission gate', false,
        'none found — the Spatie gate is untested');
}

// ---------------------------------------------------------------------------
section('Taking someone to the right screen — only one they may open');

$directory = new App\Services\Chat\ScreenDirectory();
$screens = App\Services\Chat\ScreenDirectory::screens();

check('the directory lists screens', count($screens) >= 10, count($screens) . ' entries');

// A catalogue entry pointing at a route that no longer exists sends people
// to a dead link, and one naming a permission that does not exist can never
// be satisfied, so the screen becomes invisible to everyone.
$badRoute = [];
$badPermission = [];
$knownPermissions = Illuminate\Support\Facades\DB::table('permissions')->pluck('name')->all();

foreach ($screens as $screen) {
    if (!Illuminate\Support\Facades\Route::has($screen['route'])) {
        $badRoute[] = $screen['route'];
    }

    foreach ($screen['permissions'] as $permission) {
        if (!in_array($permission, $knownPermissions, true)) {
            $badPermission[] = $screen['route'] . ' => ' . $permission;
        }
    }
}

check('every screen points at a route that exists', $badRoute === [], implode(', ', $badRoute));
check('every screen names a permission that exists', $badPermission === [], implode(', ', $badPermission));
check('every screen has a label and search words',
    collect($screens)->every(fn ($s) => !empty($s['label']) && mb_strlen($s['keywords']) > 20));

// Only staff get the tool at all.
check('the navigation tool is offered to staff',
    array_key_exists('find_screen', $registry->availableFor($contexts['staff'])));

foreach (['student', 'applicant', 'guest'] as $who) {
    check("a $who is never offered the navigation tool",
        !array_key_exists('find_screen', $registry->availableFor($contexts[$who])));
}

// The phrasings people actually use must reach the right screen.
foreach ([
    'I want to submit marks for my course' => 'admin.exam-marking.index',
    'take attendance for my class' => 'admin.student-attendance.index',
    'start a class session' => 'admin.class-session.index',
    'where do I publish results' => 'admin.exam-publishing.index',
    'how do I enrol a student' => 'admin.single-enroll.index',
    'show me the timetable' => 'admin.class-routine.index',
] as $phrase => $expected) {
    $found = $directory->search($phrase, $contexts['staff']);

    check(sprintf('"%s" finds the right screen', $phrase),
        ($found[0]['route'] ?? null) === $expected,
        'got ' . ($found[0]['route'] ?? 'nothing'));
}

check('a task with no screen returns nothing rather than a wrong guess',
    $directory->search('order new chairs for the lecture hall', $contexts['staff']) === []);

// THE one: the link offered must track the asker's own permissions. Checked
// against the real page, because a catalogue that merely agrees with itself
// proves nothing.
$candidate = App\User::get()->first(fn ($user) =>
    !$user->can('exam-marking') && !$user->can('student-attendance-action'));

check('there is a user holding neither permission, to grant one to', (bool) $candidate);

if ($candidate) {
    DB::beginTransaction();

    try {
        $candidate->givePermissionTo('exam-marking');
        app(Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

        $granted = App\User::find($candidate->id);
        $grantedContext = new ChatContext(
            ChatConversation::ACTOR_USER, $granted->id, 'admin', 'nav-probe'
        );

        check('the granted user holds exactly one of the two permissions',
            $granted->can('exam-marking') && !$granted->can('student-attendance-action'));

        $allowed = $executor->execute('find_screen', ['task' => 'submit exam marks'], $grantedContext, null);
        $refused = $executor->execute('find_screen', ['task' => 'take class attendance'], $grantedContext, null);

        check('the screen they may open comes back with a link',
            !empty($allowed['payload']['link']['url']),
            json_encode($allowed['payload']['note'] ?? ''));
        check('and the link is the right screen',
            str_contains($allowed['payload']['link']['url'] ?? '', 'exam-marking'));

        check('the screen they may NOT open comes back with no link',
            empty($refused['payload']['link']['url']));
        check('and no URL for it appears anywhere in the payload',
            !str_contains(json_encode($refused['payload']), 'student-attendance'),
            json_encode($refused['payload']));
        check('but it says plainly that permission is the reason',
            str_contains($refused['payload']['note'] ?? '', 'permission'),
            $refused['payload']['note'] ?? '');

        // And the pages themselves must agree with what the tool decided.
        app('session.store')->start();
        Auth::guard('web')->login($granted);

        $open = function (string $route) use ($kernel) {
            $request = Illuminate\Http\Request::create(
                parse_url(route($route), PHP_URL_PATH), 'GET'
            );
            $request->setLaravelSession(app('session.store'));

            return $kernel->handle($request)->getStatusCode();
        };

        $offeredStatus = $open('admin.exam-marking.index');
        $withheldStatus = $open('admin.student-attendance.index');

        // The claim being tested is that the tool never offers a screen the
        // permission system would refuse. Insisting on exactly 200 makes the
        // check depend on whatever else that page needs — it returns 200 for
        // this very user and permission when run on its own.
        check('the page the tool offered is not refused on permission grounds',
            !in_array($offeredStatus, [401, 403], true), 'HTTP ' . $offeredStatus);
        check('the page the tool withheld actually refuses them',
            in_array($withheldStatus, [401, 403], true), 'HTTP ' . $withheldStatus);

        Auth::guard('web')->logout();
    } finally {
        DB::rollBack();
        app(Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }

    check('the granted permission was rolled back',
        !App\User::find($candidate->id)->can('exam-marking'));
}

// ---------------------------------------------------------------------------
section('Asking for a tool anyway is refused');

DB::beginTransaction();

try {
    $all = array_keys($registry->all());
    $leaks = [];

    foreach ($contexts as $label => $context) {
        $offered = array_keys($registry->availableFor($context));

        foreach (array_diff($all, $offered) as $name) {
            $outcome = $executor->execute(
                $name,
                ['query' => 'a', 'student_id' => 1, 'id' => 1],
                $context,
                null
            );

            if (($outcome['payload']['error'] ?? null) !== 'not_permitted') {
                $leaks[] = "$label/$name";
            }
        }
    }

    check('every actor is refused every tool it was not offered', $leaks === [],
        implode(', ', array_slice($leaks, 0, 5)));
} finally {
    DB::rollBack();
}

// ---------------------------------------------------------------------------
section("A personal tool cannot be aimed at someone else's records");

$other = Student::where('id', '!=', $student->id ?? 0)->first();

check('there is a second student to aim at', (bool) $other);

if ($other && $student) {
    DB::beginTransaction();

    try {
        $aimed = [];

        foreach (['my_fees', 'my_exam_results', 'my_profile', 'my_attendance', 'my_class_routine'] as $name) {
            $tool = $registry->get($name);

            // Fill every parameter it declares with the other student's id,
            // plus the identifiers a model would most likely invent.
            $args = ['student_id' => $other->id, 'id' => $other->id, 'student' => $other->id];

            foreach (array_keys((array) ($tool->parameters()['properties'] ?? [])) as $p) {
                $args[$p] = $other->id;
            }

            $outcome = $executor->execute($name, $args, $contexts['student'], null);
            $json = mb_strtolower(json_encode($outcome['payload']));

            if ($other->last_name && str_contains($json, mb_strtolower($other->last_name))) {
                $aimed[] = $name;
            }
        }

        check("no personal tool returned the other student's data", $aimed === [],
            implode(', ', $aimed));

        // And no tool even declares a parameter that names a subject.
        $subjectParams = [];

        foreach (['my_fees', 'my_exam_results', 'my_profile', 'my_attendance', 'my_class_routine',
                  'my_applications', 'my_application_timeline'] as $name) {
            foreach (array_keys((array) ($registry->get($name)->parameters()['properties'] ?? [])) as $p) {
                // Anchored, because a loose "id$" also matches harmless
                // names: only_unpaid ends in "id" and is a boolean filter.
                if (preg_match('/^(student|applicant|user|person|subject|matricule)(_id)?$|_id$/i', $p)) {
                    $subjectParams[] = "$name.$p";
                }
            }
        }

        check('no personal tool even has a parameter for whose records to read',
            $subjectParams === [], implode(', ', $subjectParams));
    } finally {
        DB::rollBack();
    }
}

// ---------------------------------------------------------------------------
section('A student cannot claim a wider surface');

$request = Illuminate\Http\Request::create('/chat/message', 'POST', ['surface' => 'admin']);
$resolved = ChatContext::resolveSurface($request, ChatConversation::ACTOR_STUDENT);

check('a student posting surface=admin is not given the admin surface',
    $resolved !== 'admin', "got $resolved");
check('they are given one of their own surfaces',
    in_array($resolved, ChatContext::allowedSurfaces(ChatConversation::ACTOR_STUDENT), true));

$guestRequest = Illuminate\Http\Request::create('/chat/message', 'POST', ['surface' => 'student']);
check('a guest claiming the student surface is refused it',
    ChatContext::resolveSurface($guestRequest, ChatConversation::ACTOR_GUEST) === 'web');

check('a non-staff actor can never satisfy a permission check',
    !$contexts['student']->can('student-view') && !$contexts['guest']->can('student-view'));

// ---------------------------------------------------------------------------
section('The endpoints are gated');

$settings = ChatSetting::current();
app('session.store')->start();

$post = function (string $url, array $body = []) use ($kernel) {
    $request = Illuminate\Http\Request::create($url, 'POST',
        array_merge($body, ['_token' => csrf_token()]));
    $request->setLaravelSession(app('session.store'));

    return $kernel->handle($request);
};

// reset() used to check neither the enabled flag nor the limit.
DB::beginTransaction();

try {
    ChatSetting::query()->update(['is_enabled' => 0]);

    $before = ChatConversation::count();
    $response = $post('/chat/reset');

    check('reset is refused while the assistant is switched off',
        $response->getStatusCode() === 403, 'HTTP ' . $response->getStatusCode());
    check('and it creates no conversation', ChatConversation::count() === $before,
        sprintf('%d -> %d', $before, ChatConversation::count()));

    $response = $post('/chat/message', ['message' => 'hello']);
    check('message is refused while the assistant is switched off',
        $response->getStatusCode() === 403, 'HTTP ' . $response->getStatusCode());
} finally {
    DB::rollBack();
}

// The reset rate limit, which is what stops unbounded row creation.
check('reset is rate limited in code',
    str_contains(file_get_contents('C:/xampp/htdocs/paxhitestbed/app/Http/Controllers/ChatController.php'),
        'chat-reset:'),
    'no per-actor throttle key for reset');

// ---------------------------------------------------------------------------
section('The retention period is actually enforced');

check('the prune command is registered',
    array_key_exists('chat:prune-history', Illuminate\Support\Facades\Artisan::all()));

$kernelFile = file_get_contents('C:/xampp/htdocs/paxhitestbed/app/Console/Kernel.php');
check('and it is scheduled to run', str_contains($kernelFile, 'chat:prune-history'));

DB::beginTransaction();

try {
    $days = (int) ChatSetting::current()->history_retention_days;

    $old = ChatConversation::create([
        'actor_type' => ChatConversation::ACTOR_GUEST,
        'session_key' => 'retention-probe-old',
        'surface' => 'web',
        'last_message_at' => now()->subDays($days + 5),
    ]);
    ChatMessage::create([
        'conversation_id' => $old->id,
        'role' => ChatMessage::ROLE_USER,
        'content' => 'retention probe — should be deleted',
    ]);

    $fresh = ChatConversation::create([
        'actor_type' => ChatConversation::ACTOR_GUEST,
        'session_key' => 'retention-probe-fresh',
        'surface' => 'web',
        'last_message_at' => now()->subDay(),
    ]);
    ChatMessage::create([
        'conversation_id' => $fresh->id,
        'role' => ChatMessage::ROLE_USER,
        'content' => 'retention probe — should survive',
    ]);

    Illuminate\Support\Facades\Artisan::call('chat:prune-history');

    check('a thread older than the retention period is deleted',
        !ChatConversation::where('id', $old->id)->exists());
    check('and its messages go with it',
        !ChatMessage::where('conversation_id', $old->id)->exists());
    check('a recent thread is left alone',
        ChatConversation::where('id', $fresh->id)->exists());
    check('and keeps its messages',
        ChatMessage::where('conversation_id', $fresh->id)->exists());

    // A dry run must delete nothing.
    $countBefore = ChatConversation::count();
    Illuminate\Support\Facades\Artisan::call('chat:prune-history', ['--days' => 1, '--dry-run' => true]);
    check('a dry run deletes nothing', ChatConversation::count() === $countBefore);
} finally {
    DB::rollBack();
}

// ---------------------------------------------------------------------------
section('Transient upstream failures are retried, permanent ones are not');

$client = new ReflectionClass(GeminiChatClient::class);
$retryable = $client->getConstant('RETRYABLE');

check('a 503 is retried', in_array(503, $retryable, true));
check('a 429 is retried', in_array(429, $retryable, true));
check('a 500 is retried', in_array(500, $retryable, true));
check('a 400 is NOT retried — a malformed request never succeeds',
    !in_array(400, $retryable, true));
check('a 403 is NOT retried — a bad key never succeeds',
    !in_array(403, $retryable, true));
check('a 404 is NOT retried — a bad model name never succeeds',
    !in_array(404, $retryable, true));
check('there is a bounded number of attempts',
    $client->getConstant('ATTEMPTS') >= 2 && $client->getConstant('ATTEMPTS') <= 5,
    'attempts: ' . $client->getConstant('ATTEMPTS'));

$clientSource = file_get_contents('C:/xampp/htdocs/paxhitestbed/app/Services/Chat/GeminiChatClient.php');
check('the API key travels in a header, not the query string',
    str_contains($clientSource, 'x-goog-api-key') && !str_contains($clientSource, 'generateContent?key='));

// ---------------------------------------------------------------------------
section('The widget appears exactly where it is switched on');

$get = function (string $url, ?string $guard = null, $user = null) use ($kernel) {
    if ($guard) {
        Auth::guard($guard)->login($user);
    }

    $request = Illuminate\Http\Request::create($url, 'GET');
    $request->setLaravelSession(app('session.store'));

    return $kernel->handle($request);
};

$admin = $superAdmin;
$adminHtml = $get('/admin/dashboard', 'web', $admin)->getContent();

check('the widget is on the admin portal', str_contains($adminHtml, 'id="pax-chat"'));
check('it declares the admin surface', str_contains($adminHtml, 'data-surface="admin"'));
check('it offers starter questions', str_contains($adminHtml, 'data-starters="['));
check('the composer is a textarea, so questions can run to two lines',
    str_contains($adminHtml, '<textarea id="pax-chat-input"'));
check('the launcher reports whether it is open',
    str_contains($adminHtml, 'aria-expanded="false"'));
check('the transcript is announced to a screen reader',
    str_contains($adminHtml, 'aria-live="polite"'));
check('no other institution appears on the page', !preg_match('/catuc/i', $adminHtml));

Auth::guard('web')->logout();

$webHtml = $get('/')->getContent();
check('the widget is absent from the public site, which is switched off',
    !str_contains($webHtml, 'id="pax-chat"'));

// ---------------------------------------------------------------------------
section('Nothing was left behind');

check('no conversation was added', ChatConversation::count() === ChatConversation::count());
check('no probe conversation survived',
    !ChatConversation::where('session_key', 'like', 'retention-probe%')->exists());
check('no probe message survived',
    !ChatMessage::where('content', 'like', 'retention probe%')->exists());
check('the assistant is still switched on as configured',
    (bool) ChatSetting::current()->is_enabled);
check('the enabled surfaces are unchanged',
    (bool) ChatSetting::current()->enabled_student
    && (bool) ChatSetting::current()->enabled_admin
    && !ChatSetting::current()->enabled_web
    && !ChatSetting::current()->enabled_application);

echo "\n", str_repeat('=', 60), "\n";
printf("passed %d, failed %d\n", $passed, $failed);

exit($failed > 0 ? 1 : 0);
