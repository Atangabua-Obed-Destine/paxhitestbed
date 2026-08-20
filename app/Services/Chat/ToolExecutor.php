<?php

namespace App\Services\Chat;

use App\Models\ChatMessage;
use Illuminate\Support\Facades\Log;

/**
 * Runs a tool the model asked for, and is the place the security invariants are
 * actually enforced.
 *
 * The permission check lives here rather than inside each tool on purpose: a
 * new tool that forgets to check is still safe, because it never gets called.
 */
class ToolExecutor
{
    protected ToolRegistry $registry;

    public function __construct(ToolRegistry $registry)
    {
        $this->registry = $registry;
    }

    /**
     * @return array{status:string, payload:array}
     */
    public function execute(string $name, array $args, ChatContext $context, ?int $conversationId = null): array
    {
        $startedAt = microtime(true);

        $tool = $this->registry->get($name);

        // Unknown tool: the model invented a name, or one was removed.
        if (!$tool) {
            return $this->record($conversationId, $name, $args, ChatMessage::STATUS_DENIED, [
                'error' => 'unknown_tool',
                'message' => 'That capability does not exist.',
            ], $context, $startedAt);
        }

        // Re-check authorisation at call time, not just when building the list.
        if (!$this->registry->isAllowed($tool, $context)) {
            Log::warning('Chat tool denied', [
                'tool' => $name,
                'actor_type' => $context->actorType(),
                'actor_id' => $context->actorId(),
            ]);

            return $this->record($conversationId, $name, $args, ChatMessage::STATUS_DENIED, [
                'error' => 'not_permitted',
                'message' => 'You are not permitted to access that information.',
            ], $context, $startedAt);
        }

        try {
            $result = $tool->handle($context, $args);

            return $this->record(
                $conversationId,
                $name,
                $args,
                ChatMessage::STATUS_OK,
                $result->toArray(),
                $context,
                $startedAt
            );
        } catch (\Throwable $e) {
            report($e);

            return $this->record($conversationId, $name, $args, ChatMessage::STATUS_ERROR, [
                'error' => 'tool_failed',
                'message' => 'That information could not be retrieved right now.',
            ], $context, $startedAt, $e->getMessage());
        }
    }

    /**
     * Persist the audit row and return the payload handed back to the model.
     */
    protected function record(
        ?int $conversationId,
        string $name,
        array $args,
        string $status,
        array $payload,
        ChatContext $context,
        float $startedAt,
        ?string $error = null
    ): array {
        if ($conversationId) {
            ChatMessage::create([
                'conversation_id' => $conversationId,
                'role' => ChatMessage::ROLE_TOOL,
                'content' => null,
                'tool_name' => $name,
                'tool_args' => $args,
                'tool_result' => $payload,
                'tool_status' => $status,
                'acting_user_id' => $context->actingUserId(),
                'latency_ms' => (int) round((microtime(true) - $startedAt) * 1000),
                'error' => $error,
            ]);
        }

        return ['status' => $status, 'payload' => $payload];
    }
}
