<?php

namespace App\Services\Chat;

use App\Models\ChatConversation;
use App\Models\ChatKnowledgeEntry;
use App\Models\ChatMessage;
use App\Models\ChatSetting;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Orchestrates one question: build the prompt, let the model pick tools, run
 * them under the security invariants, and compose the answer.
 */
class ChatService
{
    protected ChatProviderChain $providers;
    protected ToolRegistry $registry;
    protected ToolExecutor $executor;

    public function __construct(?ChatProviderChain $providers = null, ?ToolRegistry $registry = null)
    {
        $this->providers = $providers ?: new ChatProviderChain();
        $this->registry = $registry ?: ChatToolProvider::registry();
        $this->executor = new ToolExecutor($this->registry);
    }

    /**
     * @return array{reply: string, link: ?array, conversation_id: int, degraded: bool}
     */
    public function ask(ChatContext $context, string $question): array
    {
        $settings = ChatSetting::current();
        $conversation = $this->conversationFor($context);

        ChatMessage::create([
            'conversation_id' => $conversation->id,
            'role' => ChatMessage::ROLE_USER,
            'content' => $question,
            'acting_user_id' => $context->actingUserId(),
        ]);

        $declarations = $this->registry->declarationsFor($context);
        $contents = $this->historyFor($conversation, $question);

        $link = null;
        $degraded = false;

        try {
            $reply = $this->runLoop($context, $conversation, $settings, $contents, $declarations, $link);
        } catch (\Throwable $e) {
            Log::error('Chat request failed', [
                'actor_type' => $context->actorType(),
                'actor_id' => $context->actorId(),
                'error' => $e->getMessage(),
            ]);

            $degraded = true;
            $reply = __('The assistant is temporarily unavailable. Please try again shortly, or contact the office for help.');

            ChatMessage::create([
                'conversation_id' => $conversation->id,
                'role' => ChatMessage::ROLE_ASSISTANT,
                'content' => $reply,
                'provider' => $this->providers->usedProvider(),
                'error' => Str::limit($e->getMessage(), 500),
                'acting_user_id' => $context->actingUserId(),
            ]);
        }

        $conversation->update([
            'last_message_at' => now(),
            'title' => $conversation->title ?: Str::limit($question, 60),
        ]);

        return [
            'reply' => $reply,
            'link' => $link,
            'conversation_id' => $conversation->id,
            'degraded' => $degraded,
        ];
    }

    /**
     * The tool loop. Bounded by max_tool_calls so a confused model cannot spend
     * unbounded time or money.
     */
    protected function runLoop(
        ChatContext $context,
        ChatConversation $conversation,
        ChatSetting $settings,
        array $contents,
        array $declarations,
        &$link
    ): string {
        $system = $this->systemPrompt($context, $settings);
        $rounds = max(1, (int) $settings->max_tool_calls);

        // Set after the model asks for a tool that does not exist. Measured,
        // not theorised: asked "how many applications this session?", a
        // registrar holding every permission got a model that invented
        // get_current_academic_session, was told the real names, invented
        // list_academic_sessions, ran out of rounds and reported that it had
        // no such tool — while holding count_applications the whole time.
        // The next round is then constrained to the real names.
        $forceTool = false;

        for ($i = 0; $i < $rounds; $i++) {
            $result = $this->providers->generate($contents, $declarations, $system, $settings, $forceTool);
            $forceTool = false;

            if (empty($result['functionCall'])) {
                $reply = $result['text'] ?: __('I could not find an answer to that.');

                ChatMessage::create([
                    'conversation_id' => $conversation->id,
                    'role' => ChatMessage::ROLE_ASSISTANT,
                    'content' => $reply,
                    'provider' => $this->providers->usedProvider(),
                    'acting_user_id' => $context->actingUserId(),
                ]);

                return $reply;
            }

            $call = $result['functionCall'];

            // The executor re-checks permission; nothing here trusts the model.
            $outcome = $this->executor->execute(
                $call['name'],
                is_array($call['args']) ? $call['args'] : [],
                $context,
                $conversation->id
            );

            if (!empty($outcome['payload']['link'])) {
                $link = $outcome['payload']['link'];
            }

            // An invented name means the model wants a tool but guessed. Hold
            // it to the real list rather than let it guess again.
            if (($outcome['payload']['error'] ?? null) === 'unknown_tool' && !empty($declarations)) {
                $forceTool = true;
            }

            // Cast to objects: an empty PHP array encodes as a JSON list, but
            // the API expects an object here and rejects the whole request with
            // "Proto field is not repeating, cannot start list".
            $modelPart = [
                'functionCall' => [
                    'name' => $call['name'],
                    'args' => (object) (is_array($call['args']) ? $call['args'] : []),
                ],
            ];

            if (!empty($result['thoughtSignature'])) {
                $modelPart['thoughtSignature'] = $result['thoughtSignature'];
            }

            $contents[] = ['role' => 'model', 'parts' => [$modelPart]];
            $contents[] = ['role' => 'user', 'parts' => [[
                'functionResponse' => [
                    'name' => $call['name'],
                    'response' => (object) $outcome['payload'],
                ],
            ]]];
        }

        // Ran out of rounds: ask for a final answer with no tools offered.
        $final = $this->providers->generate($contents, [], $system, $settings);
        $reply = $final['text'] ?: __('I could not complete that request.');

        ChatMessage::create([
            'conversation_id' => $conversation->id,
            'role' => ChatMessage::ROLE_ASSISTANT,
            'content' => $reply,
            'provider' => $this->providers->usedProvider(),
            'acting_user_id' => $context->actingUserId(),
        ]);

        return $reply;
    }

    /**
     * Instructions describing who is asking and the hard boundaries. Note this
     * is defence in depth only — the real enforcement is in ToolExecutor.
     */
    protected function systemPrompt(ChatContext $context, ChatSetting $settings): string
    {
        // The institution's own name, never a hardcoded one. This used to say
        // "Catholic University of Cameroon, Bamenda (CATUC)", which told every
        // visitor they were talking to a different school — and cost answers
        // as well as credibility: the model built tool names out of it, asking
        // for "catuc_portal:get_programmes" instead of the list_programmes
        // tool it had actually been given, then reported that it could not
        // list programmes at all.
        $institution = institution_name();
        $code = institution_code();

        // The scope rule depends on WHO is asking, and getting this wrong is
        // not cosmetic. Staff were being handed find_student and
        // count_applications and then told, in the same breath, that they
        // could only ever see their own records — so the assistant refused
        // "how many applications this session?" from a registrar holding
        // every permission. The tools were gated correctly; the instructions
        // contradicted the gate, and the model obeyed the instructions.
        $scopeRules = $context->isStaff()
            ? [
                '- The tools you have been given already match this person\'s permissions: their roles were checked before the tools were offered. Use them freely for what they cover.',
                '- You may look up students and institution-wide figures where a tool exists for it. Report exactly what the tool returns.',
                '- If you have no tool for something, say so plainly and name the office to ask. Never guess a figure, and never claim a restriction you have not been given.',
            ]
            : [
                '- You can only ever access the records of the person you are speaking with. If asked about anyone else, explain that you can only discuss their own information.',
            ];

        $lines = array_merge([
            'You are the assistant for ' . $institution
                . ($code && $code !== $institution ? ' (' . $code . ')' : '') . '.',
            'You are speaking with ' . $context->describeActor() . '.',
            'Their name is ' . $context->displayName() . '.',
            '',
            'Rules:',
            '- Answer only from the results of the tools available to you, or from general facts about the institution stated below.',
            '- Never invent figures, dates, balances or statuses. If a tool returns nothing, say so plainly.',
        ], $scopeRules, [
            '- Be concise and warm. Use short paragraphs or a small list. Amounts are in FCFA unless stated otherwise.',
            '- If the question is outside what you can look up, say so and suggest contacting the relevant office.',
            // Named explicitly, because a model that invents a tool name burns
            // a round and then tells the user the capability does not exist.
            '- Use only the tools listed in this request, by their exact names. Never prefix or namespace a tool name.',
            '- You may format with **bold**, bullet lists starting "- ", and numbered lists. Do not use tables or headings.',
        ]);

        if ($context->currentPath()) {
            $lines[] = '- The user is currently on the page: /' . ltrim($context->currentPath(), '/');
        }

        $knowledge = ChatKnowledgeEntry::forSurface($context->surface())
            ->orderBy('sort_order')
            ->limit(40)
            ->get(['question', 'answer']);

        if ($knowledge->isNotEmpty()) {
            $lines[] = '';
            $lines[] = 'Reference information published by the university:';
            foreach ($knowledge as $entry) {
                $lines[] = '- Q: ' . $entry->question . ' A: ' . $entry->answer;
            }
        }

        if ($settings->system_prompt) {
            $lines[] = '';
            $lines[] = 'Additional instructions from the university:';
            $lines[] = $settings->system_prompt;
        }

        return implode("\n", $lines);
    }

    /** Recent turns, oldest first, in Gemini's content format. */
    protected function historyFor(ChatConversation $conversation, string $question): array
    {
        // reorder() is essential: the messages() relation carries orderBy('id')
        // ascending, which would win over latest('id') and silently hand back
        // the OLDEST rows — the model then answers a question from earlier in
        // the thread instead of the one just asked.
        $recent = $conversation->messages()
            ->reorder('id', 'desc')
            ->whereIn('role', [ChatMessage::ROLE_USER, ChatMessage::ROLE_ASSISTANT])
            ->limit(10)
            ->get()
            ->reverse()
            ->values();

        $contents = [];
        foreach ($recent as $message) {
            if (!$message->content) {
                continue;
            }
            $contents[] = [
                'role' => $message->role === ChatMessage::ROLE_ASSISTANT ? 'model' : 'user',
                'parts' => [['text' => $message->content]],
            ];
        }

        // The question was persisted above, so it is already the last entry.
        if (empty($contents)) {
            $contents[] = ['role' => 'user', 'parts' => [['text' => $question]]];
        }

        return $contents;
    }

    /** The actor's open thread for this surface, created on first use. */
    public function conversationFor(ChatContext $context): ChatConversation
    {
        $query = ChatConversation::where('surface', $context->surface())
            ->where('actor_type', $context->actorType());

        if ($context->isGuest()) {
            $query->where('session_key', $context->sessionKey());
        } else {
            $query->where('actor_id', $context->actorId());
        }

        return $query->latest('id')->first() ?? ChatConversation::create([
            'actor_type' => $context->actorType(),
            'actor_id' => $context->actorId(),
            'session_key' => $context->sessionKey(),
            'surface' => $context->surface(),
            'last_message_at' => now(),
        ]);
    }
}
