<?php

namespace App\Services\Chat;

use App\Models\ChatSetting;
use App\Services\Chat\Contracts\ChatModelClient;
use Illuminate\Support\Facades\Log;

/**
 * Up to three providers, tried in order until one answers.
 *
 * Why this exists: a single provider's free tier returned HTTP 429 after
 * roughly two calls in a minute, and retrying the same provider — which is
 * all the client could do on its own — does not help when that provider is
 * the thing refusing. A second and third provider does.
 *
 * Only a TRANSIENT failure moves to the next provider. A rejected key or an
 * unknown model is permanent: trying the chain would make the person wait
 * three times as long for the same failure, and would hide a configuration
 * mistake behind a working fallback. Those stop the chain and are reported.
 *
 * A provider with no key is skipped silently, so an installation that
 * configures one provider behaves exactly as it did before.
 */
class ChatProviderChain
{
    /** Where each provider lives, and which config key holds its secret. */
    public const CATALOGUE = [
        'gemini' => [
            'label' => 'Google Gemini',
            'kind' => 'gemini',
            'config' => 'services.gemini.api_key',
            'default_model' => 'gemini-2.5-flash-lite',
            'docs' => 'https://aistudio.google.com/apikey',
        ],
        'groq' => [
            'label' => 'Groq',
            'kind' => 'openai',
            'config' => 'services.groq.api_key',
            'base_url' => 'https://api.groq.com/openai/v1',
            'default_model' => 'openai/gpt-oss-120b',
            'docs' => 'https://console.groq.com/keys',
        ],
        'openrouter' => [
            'label' => 'OpenRouter',
            'kind' => 'openai',
            'config' => 'services.openrouter.api_key',
            'base_url' => 'https://openrouter.ai/api/v1',
            'default_model' => 'meta-llama/llama-3.3-70b-instruct',
            'docs' => 'https://openrouter.ai/keys',
        ],
        'mistral' => [
            'label' => 'Mistral',
            'kind' => 'openai',
            'config' => 'services.mistral.api_key',
            'base_url' => 'https://api.mistral.ai/v1',
            'default_model' => 'mistral-small-latest',
            'docs' => 'https://console.mistral.ai/api-keys',
        ],
        'cerebras' => [
            'label' => 'Cerebras',
            'kind' => 'openai',
            'config' => 'services.cerebras.api_key',
            'base_url' => 'https://api.cerebras.ai/v1',
            'default_model' => 'llama-3.3-70b',
            'docs' => 'https://cloud.cerebras.ai',
        ],
    ];

    /** The provider that answered the last call, for the audit trail. */
    protected ?string $usedProvider = null;

    /** @var array<int, string> Why each earlier provider was passed over. */
    protected array $attempts = [];

    /**
     * The configured chain, in order, skipping anything without a key.
     *
     * @return array<int, ChatModelClient>
     */
    public function clients(ChatSetting $settings): array
    {
        $clients = [];

        foreach ($this->slots($settings) as [$provider, $model]) {
            $client = static::make($provider, $model);

            if ($client && $client->isConfigured()) {
                $clients[] = $client;
            }
        }

        return $clients;
    }

    /**
     * The three slots as [provider, model] pairs, primary first.
     *
     * @return array<int, array{0: ?string, 1: ?string}>
     */
    public function slots(ChatSetting $settings): array
    {
        return array_values(array_filter([
            [$settings->provider, $settings->model],
            [$settings->provider_2, $settings->model_2],
            [$settings->provider_3, $settings->model_3],
        ], fn ($slot) => !empty($slot[0])));
    }

    /** Build one client, or null for a provider we do not know. */
    public static function make(?string $provider, ?string $model): ?ChatModelClient
    {
        $spec = static::CATALOGUE[$provider] ?? null;

        if (!$spec) {
            return null;
        }

        $key = (string) config($spec['config']);
        $model = $model ?: $spec['default_model'];

        if ($spec['kind'] === 'gemini') {
            return new GeminiChatClient($key, $model);
        }

        return new OpenAiCompatibleClient($spec['label'], $key, $spec['base_url'], $model);
    }

    /**
     * Ask each provider in turn until one answers.
     *
     * @throws ChatProviderException when every provider has been tried.
     */
    public function generate(array $contents, array $declarations, string $systemInstruction, ChatSetting $settings, bool $forceTool = false): array
    {
        $clients = $this->clients($settings);

        $this->usedProvider = null;
        $this->attempts = [];

        if ($clients === []) {
            throw new ChatProviderException('none',
                'No chat provider is configured. Add an API key for at least one provider.', false);
        }

        $last = null;

        foreach ($clients as $index => $client) {
            try {
                $result = $client->generate($contents, $declarations, $systemInstruction, $settings, $forceTool);
                $this->usedProvider = $client->label();

                if ($index > 0) {
                    Log::info('Chat answered by a fallback provider', [
                        'provider' => $client->label(),
                        'skipped' => $this->attempts,
                    ]);
                }

                return $result;
            } catch (ChatProviderException $e) {
                $last = $e;
                $this->attempts[] = $e->provider() . ': ' . $e->getMessage();

                // A permanent failure is a configuration problem. Falling
                // through would hide it and answer from somewhere the
                // administrator did not choose.
                if (!$e->isTransient()) {
                    throw $e;
                }

                Log::warning('Chat provider unavailable, trying the next', [
                    'provider' => $e->provider(),
                    'status' => $e->status(),
                    'message' => $e->getMessage(),
                ]);
            }
        }

        throw new ChatProviderException(
            'chain',
            'Every configured provider failed. ' . implode(' | ', $this->attempts),
            true,
            $last ? $last->status() : null
        );
    }

    /** Which provider answered, for the audit row. */
    public function usedProvider(): ?string
    {
        return $this->usedProvider;
    }

    /** @return array<int, string> */
    public function attempts(): array
    {
        return $this->attempts;
    }
}
