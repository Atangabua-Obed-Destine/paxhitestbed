<?php

namespace App\Services\Chat\Contracts;

use App\Models\ChatSetting;

/**
 * One language-model provider the assistant can ask.
 *
 * The shapes crossing this boundary are Gemini's, because that is what the
 * rest of the module already speaks and translating once inside a provider is
 * cheaper than translating everywhere else. A provider that speaks something
 * different converts on the way in and on the way out.
 */
interface ChatModelClient
{
    /** A short name for logs, the audit trail and the settings screen. */
    public function label(): string;

    /** False when no API key is configured, so the chain can skip it. */
    public function isConfigured(): bool;

    /**
     * One exchange with the model.
     *
     * @param  array  $contents      Conversation turns in Gemini's format.
     * @param  array  $declarations  functionDeclarations for the allowed tools.
     * @param  bool   $forceTool     Require a call from the declared tools.
     * @return array{text: ?string, functionCall: ?array, thoughtSignature: ?string}
     *
     * @throws \App\Services\Chat\ChatProviderException
     */
    public function generate(array $contents, array $declarations, string $systemInstruction, ChatSetting $settings, bool $forceTool = false): array;
}
