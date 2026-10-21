<?php

namespace App\Services\Chat;

use App\Models\ChatSetting;
use App\Services\Chat\Contracts\ChatModelClient;
use Illuminate\Support\Facades\Http;

/**
 * Multi-turn Gemini client with function calling.
 *
 * Deliberately separate from App\Services\GeminiService, which is single-turn,
 * text-only and pinned to the v1 endpoint for the e-library. Reliable function
 * calling needs v1beta plus systemInstruction, so distorting that class would
 * have risked a working feature. Both read the same API key.
 */
class GeminiChatClient implements ChatModelClient
{
    protected string $apiKey;
    protected string $model;
    protected string $baseUrl = 'https://generativelanguage.googleapis.com/v1beta';

    /**
     * Key and model are injected rather than read from config, so the
     * provider chain can place Gemini in any slot with any model without
     * this class knowing where it sits.
     */
    public function __construct(?string $apiKey = null, ?string $model = null)
    {
        $this->apiKey = $apiKey !== null ? $apiKey : (string) config('services.gemini.api_key');
        $this->model = $model ?: (string) config('services.gemini.model', 'gemini-2.5-flash-lite');
    }

    public function label(): string
    {
        return 'Google Gemini';
    }

    /** Upstream states that are worth trying again rather than giving up on. */
    protected const RETRYABLE = [429, 500, 502, 503, 504];

    /** How many attempts in total, and the pause before each retry. */
    protected const ATTEMPTS = 3;
    protected const BACKOFF_MS = [400, 1200];

    public function isConfigured(): bool
    {
        return $this->apiKey !== '';
    }

    /**
     * Replace any unsigned function call in the history with plain text.
     *
     * Gemini 3.x signs its reasoning and refuses a later turn that replays a
     * functionCall part without that signature: "Function call is missing a
     * thought_signature in functionCall parts." A call the model invented, or
     * one that reached us from a provider that does not sign at all, has no
     * signature to echo — and replaying it takes down the whole request,
     * turning a recoverable mistake into "the assistant is unavailable".
     *
     * The exchange is kept, as text, so the model still sees what it asked
     * for and what came back. Only the part shape changes.
     */
    protected function signable(array $contents): array
    {
        $out = [];
        $droppedCall = false;

        foreach ($contents as $entry) {
            $parts = [];

            foreach ($entry['parts'] ?? [] as $part) {
                if (isset($part['functionCall']) && empty($part['thoughtSignature'])) {
                    $droppedCall = true;
                    $parts[] = ['text' => 'I tried to use a tool called "'
                        . ($part['functionCall']['name'] ?? '?') . '".'];

                    continue;
                }

                // The response to a call we just turned into text has to
                // become text as well, or it answers a call that is no
                // longer there.
                if (isset($part['functionResponse']) && $droppedCall) {
                    $droppedCall = false;
                    $parts[] = ['text' => 'Result: '
                        . json_encode($part['functionResponse']['response'] ?? [])];

                    continue;
                }

                $parts[] = $part;
            }

            if ($parts !== []) {
                $out[] = ['role' => $entry['role'] ?? 'user', 'parts' => $parts];
            }
        }

        return $out;
    }

    /**
     * POST with a bounded retry on transient upstream failures.
     *
     * Measured need: in a sample of four live calls, three came back
     * "503 — this model is currently experiencing high demand". With no retry
     * each of those is a user staring at "the assistant is temporarily
     * unavailable", which for a flagship feature reads as broken rather than
     * busy. A permanent failure — a bad key, a bad model name, a malformed
     * request — is NOT retried: it would never succeed and the user would
     * just wait longer for the same answer.
     */
    protected function send(string $url, array $payload)
    {
        $response = null;

        for ($attempt = 1; $attempt <= self::ATTEMPTS; $attempt++) {
            $response = Http::timeout(45)
                ->withHeaders(['x-goog-api-key' => $this->apiKey])
                ->post($url, $payload);

            if (!$response->failed() || !in_array($response->status(), self::RETRYABLE, true)) {
                return $response;
            }

            if ($attempt < self::ATTEMPTS) {
                usleep((self::BACKOFF_MS[$attempt - 1] ?? 1200) * 1000);
            }
        }

        return $response;
    }

    /**
     * One exchange with the model.
     *
     * @param  array $contents      Conversation turns in Gemini's format.
     * @param  array $declarations  functionDeclarations for the tools this actor may use.
     * @return array{text: ?string, functionCall: ?array}
     *
     * @throws \RuntimeException on transport or API failure.
     */
    public function generate(array $contents, array $declarations, string $systemInstruction, ChatSetting $settings, bool $forceTool = false): array
    {
        if (!$this->isConfigured()) {
            throw new ChatProviderException($this->label(), 'No Gemini API key is set.', false);
        }

        $payload = [
            'systemInstruction' => ['parts' => [['text' => $systemInstruction]]],
            'contents' => $this->signable($contents),
            'generationConfig' => [
                'temperature' => $settings->temperature,
                'maxOutputTokens' => $settings->max_output_tokens,
            ],
        ];

        if (!empty($declarations)) {
            $payload['tools'] = [['functionDeclarations' => array_values($declarations)]];

            // Used only to correct a model that has just asked for a tool
            // that does not exist, and never on the first round, where a
            // plain answer is often the right one.
            //
            // Measured caveat: on gemini-3.5-flash this is a no-op — the same
            // question returned text with mode ANY exactly as it did with
            // mode auto. It is kept because the OpenAI-compatible providers
            // honour their equivalent (tool_choice: required), and because a
            // later Gemini model may. It is NOT the reason tool calls go
            // missing: that was the output budget, see max_output_tokens.
            if ($forceTool) {
                $payload['toolConfig'] = [
                    'functionCallingConfig' => [
                        'mode' => 'ANY',
                        'allowedFunctionNames' => array_values(array_map(
                            fn ($d) => $d['name'],
                            $declarations
                        )),
                    ],
                ];
            }
        }

        // The key travels in a header rather than the query string, so it does
        // not end up in access logs, proxy logs or an exception message that
        // quotes the URL.
        // The model comes from this client's own slot, not from the primary
        // setting: slot two may well be a different Gemini model.
        $url = "{$this->baseUrl}/models/{$this->model}:generateContent";

        $response = $this->send($url, $payload);

        if ($response->failed()) {
            // Surface the provider's own reason; the caller turns this into a
            // user-facing message and logs the detail.
            $reason = $response->json('error.message') ?? $response->body();
            throw new ChatProviderException(
                $this->label(),
                'Gemini request failed (HTTP ' . $response->status() . '): ' . $reason,
                ChatProviderException::transientStatus($response->status()),
                $response->status()
            );
        }

        $parts = $response->json('candidates.0.content.parts') ?? [];

        $text = null;
        $functionCall = null;
        $thoughtSignature = null;

        foreach ($parts as $part) {
            if (isset($part['functionCall'])) {
                $functionCall = [
                    'name' => $part['functionCall']['name'] ?? '',
                    'args' => $part['functionCall']['args'] ?? [],
                ];
                // Gemini 3.x signs its reasoning and rejects the follow-up turn
                // unless the signature is echoed back with the same part.
                $thoughtSignature = $part['thoughtSignature'] ?? null;
            }
            if (isset($part['text'])) {
                $text = trim(($text ?? '') . $part['text']);
            }
        }

        return [
            'text' => $text,
            'functionCall' => $functionCall,
            'thoughtSignature' => $thoughtSignature,
        ];
    }
}
