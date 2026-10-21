<?php

namespace App\Services\Chat;

use App\Models\ChatSetting;
use App\Services\Chat\Contracts\ChatModelClient;
use Illuminate\Support\Facades\Http;

/**
 * Any provider that speaks the OpenAI chat-completions API.
 *
 * One class covers Groq, OpenRouter, Mistral, Cerebras and OpenAI itself:
 * they differ only in base URL, key and model name. Groq's own documentation
 * states that every model it hosts supports tool use, which is the one
 * capability this assistant cannot do without.
 *
 * The module speaks Gemini's shapes everywhere else, so the translation lives
 * here, on the way in and on the way out. Three details that are easy to get
 * wrong and silently lose tool calling:
 *
 *  - JSON Schema types. Gemini declares "OBJECT" and "STRING"; the OpenAI
 *    schema wants them lower case. Sent as-is, some providers reject the
 *    whole tools block and the model then answers as though it had no tools.
 *  - Tool call identifiers. Gemini pairs a functionCall with a
 *    functionResponse positionally and carries no id. OpenAI requires every
 *    tool message to quote the tool_call_id it answers, so ids are minted
 *    here and matched by order.
 *  - A parameterless tool. Gemini accepts an empty properties object; the
 *    OpenAI schema needs properties present, so it is always emitted.
 */
class OpenAiCompatibleClient implements ChatModelClient
{
    /** Upstream states worth trying on another provider. */
    protected const RETRYABLE = [429, 500, 502, 503, 504];

    protected const ATTEMPTS = 2;

    protected string $key;
    protected string $baseUrl;
    protected string $model;
    protected string $label;

    public function __construct(string $label, string $key, string $baseUrl, string $model)
    {
        $this->label = $label;
        $this->key = $key;
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->model = $model;
    }

    public function label(): string
    {
        return $this->label;
    }

    public function isConfigured(): bool
    {
        return $this->key !== '' && $this->model !== '';
    }

    public function generate(array $contents, array $declarations, string $systemInstruction, ChatSetting $settings, bool $forceTool = false): array
    {
        if (!$this->isConfigured()) {
            throw new ChatProviderException($this->label, 'No API key or model configured.', false);
        }

        $payload = [
            'model' => $this->model,
            'messages' => $this->messages($contents, $systemInstruction),
            'temperature' => (float) $settings->temperature,
            'max_tokens' => (int) $settings->max_output_tokens,
        ];

        if (!empty($declarations)) {
            $payload['tools'] = $this->tools($declarations);
            // "required" is this API's way of saying the same thing Gemini's
            // ANY mode says: pick one of the tools you were actually given.
            $payload['tool_choice'] = $forceTool ? 'required' : 'auto';
        }

        $response = null;

        for ($attempt = 1; $attempt <= self::ATTEMPTS; $attempt++) {
            $response = Http::timeout(45)
                ->withHeaders([
                    'Authorization' => 'Bearer ' . $this->key,
                    'Content-Type' => 'application/json',
                ])
                ->post($this->baseUrl . '/chat/completions', $payload);

            if (!$response->failed() || !in_array($response->status(), self::RETRYABLE, true)) {
                break;
            }

            if ($attempt < self::ATTEMPTS) {
                usleep(500 * 1000);
            }
        }

        if ($response->failed()) {
            $reason = $response->json('error.message') ?? $response->body();

            throw new ChatProviderException(
                $this->label,
                $this->label . ' request failed (HTTP ' . $response->status() . '): ' . $reason,
                ChatProviderException::transientStatus($response->status()),
                $response->status()
            );
        }

        return $this->readReply($response->json('choices.0.message') ?? []);
    }

    /* ------------------------------------------------------------ inbound */

    /** Gemini contents -> OpenAI messages. */
    protected function messages(array $contents, string $systemInstruction): array
    {
        $messages = [['role' => 'system', 'content' => $systemInstruction]];

        // Tool responses have to quote the id of the call they answer, so the
        // ids minted for each functionCall are remembered in order and
        // consumed by the functionResponse that follows.
        $pending = [];
        $counter = 0;

        foreach ($contents as $entry) {
            $role = ($entry['role'] ?? 'user') === 'model' ? 'assistant' : 'user';

            foreach ($entry['parts'] ?? [] as $part) {
                if (isset($part['functionCall'])) {
                    $id = 'call_' . (++$counter);
                    $pending[] = $id;

                    $messages[] = [
                        'role' => 'assistant',
                        'content' => null,
                        'tool_calls' => [[
                            'id' => $id,
                            'type' => 'function',
                            'function' => [
                                'name' => $part['functionCall']['name'] ?? '',
                                'arguments' => json_encode((object) ((array) ($part['functionCall']['args'] ?? []))),
                            ],
                        ]],
                    ];

                    continue;
                }

                if (isset($part['functionResponse'])) {
                    $messages[] = [
                        'role' => 'tool',
                        'tool_call_id' => array_shift($pending) ?: 'call_0',
                        'name' => $part['functionResponse']['name'] ?? '',
                        'content' => json_encode((object) ((array) ($part['functionResponse']['response'] ?? []))),
                    ];

                    continue;
                }

                if (isset($part['text']) && $part['text'] !== '') {
                    $messages[] = ['role' => $role, 'content' => $part['text']];
                }
            }
        }

        return $messages;
    }

    /** Gemini functionDeclarations -> OpenAI tools. */
    protected function tools(array $declarations): array
    {
        $tools = [];

        foreach ($declarations as $declaration) {
            $parameters = $this->lowerTypes($declaration['parameters'] ?? []);

            // The OpenAI schema expects an object with properties, even when
            // the tool takes none.
            if (!isset($parameters['type'])) {
                $parameters['type'] = 'object';
            }

            if (!isset($parameters['properties'])) {
                $parameters['properties'] = (object) [];
            }

            $tools[] = [
                'type' => 'function',
                'function' => [
                    'name' => $declaration['name'],
                    'description' => $declaration['description'] ?? '',
                    'parameters' => $parameters,
                ],
            ];
        }

        return $tools;
    }

    /** "OBJECT" -> "object", through the whole schema. */
    protected function lowerTypes($node)
    {
        if (!is_array($node)) {
            return $node;
        }

        $out = [];

        foreach ($node as $key => $value) {
            $out[$key] = ($key === 'type' && is_string($value))
                ? strtolower($value)
                : $this->lowerTypes($value);
        }

        return $out;
    }

    /* ----------------------------------------------------------- outbound */

    /** OpenAI message -> the shape the rest of the module expects. */
    protected function readReply(array $message): array
    {
        $functionCall = null;
        $call = $message['tool_calls'][0] ?? null;

        if ($call) {
            $arguments = $call['function']['arguments'] ?? '{}';

            // Arguments arrive as a JSON string. A model that emits something
            // that is not valid JSON must not take the whole request down —
            // the tool simply receives no arguments and says what it needs.
            $decoded = is_string($arguments) ? json_decode($arguments, true) : $arguments;

            $functionCall = [
                'name' => $call['function']['name'] ?? '',
                'args' => is_array($decoded) ? $decoded : [],
            ];
        }

        $text = $message['content'] ?? null;

        return [
            'text' => is_string($text) && trim($text) !== '' ? trim($text) : null,
            'functionCall' => $functionCall,
            // Gemini-only; nothing to echo back for these providers.
            'thoughtSignature' => null,
        ];
    }
}
