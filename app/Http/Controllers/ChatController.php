<?php

namespace App\Http\Controllers;

use App\Models\ChatConversation;
use App\Models\ChatMessage;
use App\Models\ChatSetting;
use App\Services\Chat\ChatContext;
use App\Services\Chat\ChatService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

/**
 * The single chat endpoint for all four surfaces.
 *
 * There is no per-portal controller on purpose: identity is resolved once, in
 * ChatContext, from the auth guards. Adding a surface cannot accidentally skip
 * a scoping rule.
 */
class ChatController extends Controller
{
    protected ChatService $chat;

    public function __construct(ChatService $chat)
    {
        $this->chat = $chat;
    }

    public function message(Request $request)
    {
        $validated = $request->validate([
            'message' => ['required', 'string', 'max:1000'],
        ]);

        $settings = ChatSetting::current();
        $context = ChatContext::fromRequest($request);

        if (!$settings->enabledFor($context->surface())) {
            return response()->json([
                'success' => false,
                'message' => __('The assistant is not available here.'),
            ], 403);
        }

        // Per-actor throttle, configurable by an administrator.
        $key = 'chat:' . $context->actorType() . ':' . ($context->actorId() ?? $context->sessionKey());
        if (RateLimiter::tooManyAttempts($key, $settings->rate_limit_per_minute)) {
            return response()->json([
                'success' => false,
                'message' => __('You are sending messages too quickly. Please wait a moment.'),
            ], 429);
        }
        RateLimiter::hit($key, 60);

        $result = $this->chat->ask($context, $validated['message']);

        return response()->json([
            'success' => true,
            'reply' => $result['reply'],
            'link' => $result['link'],
            'degraded' => $result['degraded'],
        ]);
    }

    public function history(Request $request)
    {
        $settings = ChatSetting::current();
        $context = ChatContext::fromRequest($request);

        if (!$settings->enabledFor($context->surface())) {
            return response()->json(['success' => false, 'messages' => []], 403);
        }

        $conversation = $this->chat->conversationFor($context);

        // reorder() clears the relation's ascending orderBy, which would
        // otherwise beat latest('id') and return the oldest rows, reversed.
        $messages = $conversation->messages()
            ->reorder('id', 'desc')
            ->whereIn('role', [ChatMessage::ROLE_USER, ChatMessage::ROLE_ASSISTANT])
            ->limit(20)
            ->get()
            ->reverse()
            ->map(fn ($m) => ['role' => $m->role, 'content' => $m->content])
            ->values();

        return response()->json([
            'success' => true,
            'greeting' => $settings->greetingFor($context->surface()),
            'messages' => $messages,
        ]);
    }

    /**
     * Start a fresh thread. The previous conversation is left untouched so the
     * audit trail survives — conversationFor() simply picks up the newest row.
     */
    public function reset(Request $request)
    {
        $context = ChatContext::fromRequest($request);

        ChatConversation::create([
            'actor_type' => $context->actorType(),
            'actor_id' => $context->actorId(),
            'session_key' => $context->sessionKey(),
            'surface' => $context->surface(),
            'last_message_at' => now(),
        ]);

        return response()->json(['success' => true]);
    }
}
