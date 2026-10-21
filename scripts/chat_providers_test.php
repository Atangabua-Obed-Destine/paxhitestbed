<?php

/**
 * Three model providers, tried in order.
 *
 * Why this exists: one provider's free tier answered HTTP 429 after roughly
 * two calls in a minute, and a client retrying itself cannot help when that
 * provider is the thing refusing. The chain asks the next one instead.
 *
 * The two things that have to be right, and are easy to get wrong:
 *
 *  - WHEN to fall through. A rate limit or an overloaded model is transient
 *    and the next provider may well answer. A rejected key or an unknown
 *    model is permanent: falling through would hide a configuration mistake
 *    behind a working fallback, and the administrator would never learn that
 *    the provider they chose has never once been used.
 *
 *  - The translation. Everything in the module speaks Gemini's shapes; Groq,
 *    OpenRouter, Mistral and Cerebras speak OpenAI's. Three details silently
 *    destroy tool calling if mistranslated: schema types must be lower-cased,
 *    every tool result must quote the id of the call it answers, and a
 *    parameterless tool still needs a properties object.
 *
 * No network call is made anywhere in here. The providers are fakes, and the
 * translation is checked by reading what the client would have sent.
 */

$base = 'C:/xampp/htdocs/paxhitestbed';
require $base . '/vendor/autoload.php';
$app = require_once $base . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\ChatSetting;
use App\Services\Chat\ChatProviderChain;
use App\Services\Chat\ChatProviderException;
use App\Services\Chat\Contracts\ChatModelClient;
use App\Services\Chat\GeminiChatClient;
use App\Services\Chat\OpenAiCompatibleClient;
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

/** A provider that does whatever the test needs, and records being asked. */
class FakeProvider implements ChatModelClient
{
    public array $calls = [];
    protected string $name;
    protected $behaviour;
    protected bool $configured;

    public function __construct(string $name, $behaviour, bool $configured = true)
    {
        $this->name = $name;
        $this->behaviour = $behaviour;
        $this->configured = $configured;
    }

    public function label(): string
    {
        return $this->name;
    }

    public function isConfigured(): bool
    {
        return $this->configured;
    }

    /** Records whether the chain asked for a forced tool choice. */
    public array $forced = [];

    public function generate(array $contents, array $declarations, string $systemInstruction, ChatSetting $settings, bool $forceTool = false): array
    {
        $this->calls[] = true;
        $this->forced[] = $forceTool;

        return ($this->behaviour)($this);
    }
}

/** A chain whose providers the test supplies. */
class TestChain extends ChatProviderChain
{
    public array $fakes = [];

    public function clients(ChatSetting $settings): array
    {
        return array_values(array_filter($this->fakes, fn ($f) => $f->isConfigured()));
    }
}

$settings = ChatSetting::current();

$answers = fn ($text) => fn () => ['text' => $text, 'functionCall' => null, 'thoughtSignature' => null];
$failsWith = fn (string $name, int $status) => function () use ($name, $status) {
    throw new ChatProviderException($name, "HTTP $status", ChatProviderException::transientStatus($status), $status);
};

// ---------------------------------------------------------------------------
section('A transient failure moves to the next provider');

$chain = new TestChain();
$first = new FakeProvider('First', $failsWith('First', 429));
$second = new FakeProvider('Second', $answers('answered by the second'));
$third = new FakeProvider('Third', $answers('should never be reached'));
$chain->fakes = [$first, $second, $third];

$result = $chain->generate([], [], 'system', $settings);

check('the rate-limited provider is tried', count($first->calls) === 1);
check('the next provider answers', $result['text'] === 'answered by the second');
check('and the third is left alone', count($third->calls) === 0);
check('the chain reports which one answered', $chain->usedProvider() === 'Second',
    (string) $chain->usedProvider());
check('and records why the first was passed over',
    count($chain->attempts()) === 1 && str_contains($chain->attempts()[0], 'First'));

foreach ([429, 500, 502, 503, 504, 408] as $status) {
    $chain = new TestChain();
    $a = new FakeProvider('A', $failsWith('A', $status));
    $b = new FakeProvider('B', $answers('ok'));
    $chain->fakes = [$a, $b];

    $text = $chain->generate([], [], 's', $settings)['text'];
    check("HTTP $status falls through", $text === 'ok');
}

// ---------------------------------------------------------------------------
section('A permanent failure stops, so a misconfiguration cannot hide');

foreach ([400 => 'a malformed request', 401 => 'a rejected key', 403 => 'a forbidden key', 404 => 'an unknown model'] as $status => $what) {
    $chain = new TestChain();
    $a = new FakeProvider('A', $failsWith('A', $status));
    $b = new FakeProvider('B', $answers('must not be used'));
    $chain->fakes = [$a, $b];

    $threw = false;

    try {
        $chain->generate([], [], 's', $settings);
    } catch (ChatProviderException $e) {
        $threw = !$e->isTransient();
    }

    check("$what (HTTP $status) stops the chain", $threw);
    check("and the next provider is not asked", count($b->calls) === 0);
}

// ---------------------------------------------------------------------------
section('Providers without a key are skipped, not failed');

$chain = new TestChain();
$none = new FakeProvider('Unkeyed', $answers('never'), false);
$keyed = new FakeProvider('Keyed', $answers('from the keyed one'));
$chain->fakes = [$none, $keyed];

check('an unkeyed provider is never called',
    $chain->generate([], [], 's', $settings)['text'] === 'from the keyed one'
    && count($none->calls) === 0);

$chain = new TestChain();
$chain->fakes = [new FakeProvider('Unkeyed', $answers('never'), false)];
$message = '';

try {
    $chain->generate([], [], 's', $settings);
} catch (ChatProviderException $e) {
    $message = $e->getMessage();
}

check('with nothing configured the error says so plainly',
    str_contains($message, 'No chat provider is configured'), $message);

// Every provider failing transiently is still an error, naming them all.
$chain = new TestChain();
$chain->fakes = [
    new FakeProvider('A', $failsWith('A', 503)),
    new FakeProvider('B', $failsWith('B', 429)),
];
$message = '';

try {
    $chain->generate([], [], 's', $settings);
} catch (ChatProviderException $e) {
    $message = $e->getMessage();
}

check('when all of them fail, the error names each one',
    str_contains($message, 'A') && str_contains($message, 'B')
    && str_contains($message, 'Every configured provider failed'), $message);

// ---------------------------------------------------------------------------
section('Gemini shapes translate to the OpenAI API');

$client = new OpenAiCompatibleClient('Test', 'k', 'https://example.test/v1', 'test-model');

$messagesOf = function (array $contents, string $system = 'SYSTEM') use ($client) {
    $m = new ReflectionMethod($client, 'messages');
    $m->setAccessible(true);

    return $m->invoke($client, $contents, $system);
};

$toolsOf = function (array $declarations) use ($client) {
    $m = new ReflectionMethod($client, 'tools');
    $m->setAccessible(true);

    return $m->invoke($client, $declarations);
};

$messages = $messagesOf([
    ['role' => 'user', 'parts' => [['text' => 'What do I owe?']]],
    ['role' => 'model', 'parts' => [['functionCall' => ['name' => 'my_fees', 'args' => ['only_unpaid' => true]]]]],
    ['role' => 'user', 'parts' => [['functionResponse' => ['name' => 'my_fees', 'response' => ['balance' => 100000]]]]],
]);

check('the system instruction leads', ($messages[0]['role'] ?? '') === 'system'
    && ($messages[0]['content'] ?? '') === 'SYSTEM');
check('a user turn stays a user message', ($messages[1]['role'] ?? '') === 'user');
check('a model turn becomes an assistant message', ($messages[2]['role'] ?? '') === 'assistant');
check('a function call becomes tool_calls',
    isset($messages[2]['tool_calls'][0]['function']['name'])
    && $messages[2]['tool_calls'][0]['function']['name'] === 'my_fees');
check('its arguments are a JSON string, as the API requires',
    is_string($messages[2]['tool_calls'][0]['function']['arguments'] ?? null));
check('a function response becomes a tool message', ($messages[3]['role'] ?? '') === 'tool');

// THE one: an unmatched id makes the provider reject the whole request.
check('the tool result quotes the id of the call it answers',
    ($messages[3]['tool_call_id'] ?? null) === ($messages[2]['tool_calls'][0]['id'] ?? 'x'),
    sprintf('call %s vs result %s',
        $messages[2]['tool_calls'][0]['id'] ?? '-', $messages[3]['tool_call_id'] ?? '-'));

// Two rounds must not reuse one id.
$twoRounds = $messagesOf([
    ['role' => 'user', 'parts' => [['text' => 'q']]],
    ['role' => 'model', 'parts' => [['functionCall' => ['name' => 'a', 'args' => []]]]],
    ['role' => 'user', 'parts' => [['functionResponse' => ['name' => 'a', 'response' => []]]]],
    ['role' => 'model', 'parts' => [['functionCall' => ['name' => 'b', 'args' => []]]]],
    ['role' => 'user', 'parts' => [['functionResponse' => ['name' => 'b', 'response' => []]]]],
]);

$ids = [];

foreach ($twoRounds as $m) {
    if (isset($m['tool_calls'][0]['id'])) { $ids[] = $m['tool_calls'][0]['id']; }
}

check('each round gets its own call id', count($ids) === 2 && $ids[0] !== $ids[1],
    implode(',', $ids));

$pairs = [];

foreach ($twoRounds as $m) {
    if (($m['role'] ?? '') === 'tool') { $pairs[] = $m['tool_call_id']; }
}

check('and each result answers its own call, in order', $pairs === $ids,
    implode(',', $pairs) . ' vs ' . implode(',', $ids));

// ---------------------------------------------------------------------------
section('Tool declarations survive the crossing');

$tools = $toolsOf([
    [
        'name' => 'my_fees',
        'description' => 'Fees owed.',
        'parameters' => [
            'type' => 'OBJECT',
            'properties' => ['only_unpaid' => ['type' => 'BOOLEAN', 'description' => 'd']],
            'required' => ['only_unpaid'],
        ],
    ],
    ['name' => 'no_args', 'description' => 'Takes nothing.', 'parameters' => ['type' => 'OBJECT', 'properties' => (object) []]],
]);

check('each tool is wrapped as a function', ($tools[0]['type'] ?? '') === 'function');
check('the name survives', ($tools[0]['function']['name'] ?? '') === 'my_fees');
check('the description survives', ($tools[0]['function']['description'] ?? '') === 'Fees owed.');

// Uppercase types are what Gemini wants and what the registry emits; some
// OpenAI-compatible providers reject them and then answer as though no tools
// had been offered at all.
$json = json_encode($tools);

check('schema types are lower-cased', str_contains($json, '"type":"object"')
    && str_contains($json, '"type":"boolean"'), $json);
check('no uppercase type survives', !preg_match('/"type":"[A-Z]/', $json));
check('required fields survive', ($tools[0]['function']['parameters']['required'] ?? []) === ['only_unpaid']);
check('a parameterless tool still declares properties',
    isset($tools[1]['function']['parameters']['properties']));
check('and still declares an object type',
    ($tools[1]['function']['parameters']['type'] ?? '') === 'object');

// ---------------------------------------------------------------------------
section('The reply translates back');

$readReply = function (array $message) use ($client) {
    $m = new ReflectionMethod($client, 'readReply');
    $m->setAccessible(true);

    return $m->invoke($client, $message);
};

$plain = $readReply(['content' => '  Your balance is 100,000 FCFA.  ']);
check('plain text comes back trimmed', $plain['text'] === 'Your balance is 100,000 FCFA.');
check('with no function call', $plain['functionCall'] === null);

$called = $readReply(['content' => null, 'tool_calls' => [[
    'id' => 'call_1', 'type' => 'function',
    'function' => ['name' => 'my_fees', 'arguments' => '{"only_unpaid":true}'],
]]]);

check('a tool call comes back as a functionCall', ($called['functionCall']['name'] ?? '') === 'my_fees');
check('its arguments are decoded to an array',
    ($called['functionCall']['args']['only_unpaid'] ?? null) === true);

$broken = $readReply(['content' => null, 'tool_calls' => [[
    'function' => ['name' => 'my_fees', 'arguments' => 'not json at all'],
]]]);

check('arguments that are not valid JSON do not take the request down',
    ($broken['functionCall']['args'] ?? null) === []);

$empty = $readReply(['content' => '   ']);
check('whitespace-only content reads as no text', $empty['text'] === null);

// ---------------------------------------------------------------------------
section('The catalogue and the configured chain');

foreach (ChatProviderChain::CATALOGUE as $key => $spec) {
    check("$key names a label, a config key and a default model",
        !empty($spec['label']) && !empty($spec['config']) && !empty($spec['default_model']));

    if ($spec['kind'] === 'openai') {
        check("$key has a base URL over https",
            str_starts_with($spec['base_url'] ?? '', 'https://'));
    }
}

check('an unknown provider name builds nothing',
    ChatProviderChain::make('not-a-provider', 'x') === null);
check('gemini builds the Gemini client',
    ChatProviderChain::make('gemini', 'gemini-2.5-flash-lite') instanceof GeminiChatClient);
check('groq builds an OpenAI-compatible client',
    ChatProviderChain::make('groq', null) instanceof OpenAiCompatibleClient);
check('a provider with no key reports itself unconfigured',
    !ChatProviderChain::make('mistral', null)->isConfigured()
    || (string) config('services.mistral.api_key') !== '');

$real = new ChatProviderChain();
$slots = $real->slots(ChatSetting::current());

check('the installation has a primary provider', count($slots) >= 1);
check('every configured slot names a known provider',
    collect($slots)->every(fn ($s) => isset(ChatProviderChain::CATALOGUE[$s[0]])),
    json_encode($slots));
check('at least one provider in the chain is usable',
    count($real->clients(ChatSetting::current())) >= 1);

// ---------------------------------------------------------------------------
section('Keys stay out of the database and off the screen');

$columns = Illuminate\Support\Facades\Schema::getColumnListing('chat_settings');
// Anchored on credential names. A loose /token/ also matches
// max_output_tokens, which is a size, not a secret.
$keyish = array_filter($columns, fn ($c) => preg_match(
    '/(^|_)(api_?key|secret|password|credential)(_|$)|(access|auth|bearer|api)_token/i', $c
));

check('no chat_settings column holds a secret', $keyish === [], implode(', ', $keyish));

$stored = DB::table('chat_settings')->first();
$blob = json_encode($stored);

check('no setting value looks like an API key',
    !preg_match('/AIza[0-9A-Za-z_\-]{20,}|sk-[0-9A-Za-z]{20,}|gsk_[0-9A-Za-z]{20,}/', $blob));

check('the chat settings model cannot mass-assign a key',
    !in_array('api_key', (new ChatSetting())->getFillable(), true));

// ---------------------------------------------------------------------------
section('Which provider answered is recorded');

check('chat_messages can record the provider',
    Illuminate\Support\Facades\Schema::hasColumn('chat_messages', 'provider'));
check('and the model allows it to be written',
    in_array('provider', (new App\Models\ChatMessage())->getFillable(), true));

$serviceSource = file_get_contents($base . '/app/Services/Chat/ChatService.php');
check('every assistant message records who answered',
    substr_count($serviceSource, "'provider' => \$this->providers->usedProvider()") >= 3,
    substr_count($serviceSource, "'provider' => \$this->providers->usedProvider()") . ' of 3');
check('the service asks the chain, not one client',
    !str_contains($serviceSource, '$this->client->generate('));

echo "\n", str_repeat('=', 60), "\n";
printf("passed %d, failed %d\n", $passed, $failed);

exit($failed > 0 ? 1 : 0);
