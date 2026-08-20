<?php

namespace App\Services\Chat;

use App\Models\ChatSetting;
use Illuminate\Support\Facades\Http;

/**
 * Multi-turn Gemini client with function calling.
 *
 * Deliberately separate from App\Services\GeminiService, which is single-turn,
 * text-only and pinned to the v1 endpoint for the e-library. Reliable function
 * calling needs v1beta plus systemInstruction, so distorting that class would
 * have risked a working feature. Both read the same API key.
 */
class GeminiChatClient
{
    protected string $apiKey;
    protected string $baseUrl = 'https://generativelanguage.googleapis.com/v1beta';

    public function __construct()
    {
        $this->apiKey = (string) config('services.gemini.api_key');
    }

    public function isConfigured(): bool
    {
        return $this->apiKey !== '';
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
    public function generate(array $contents, array $declarations, string $systemInstruction, ChatSetting $settings): array
    {
        if (!$this->isConfigured()) {
            throw new \RuntimeException('The assistant is not configured: no Gemini API key is set.');
        }

        $payload = [
            'systemInstruction' => ['parts' => [['text' => $systemInstruction]]],
            'contents' => $contents,
            'generationConfig' => [
                'temperature' => $settings->temperature,
                'maxOutputTokens' => $settings->max_output_tokens,
            ],
        ];

        if (!empty($declarations)) {
            $payload['tools'] = [['functionDeclarations' => array_values($declarations)]];
        }

        $url = "{$this->baseUrl}/models/{$settings->model}:generateContent?key={$this->apiKey}";

        $response = Http::timeout(45)->post($url, $payload);

        if ($response->failed()) {
            // Surface the provider's own reason; the caller turns this into a
            // user-facing message and logs the detail.
            $reason = $response->json('error.message') ?? $response->body();
            throw new \RuntimeException('Gemini request failed (HTTP ' . $response->status() . '): ' . $reason);
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
