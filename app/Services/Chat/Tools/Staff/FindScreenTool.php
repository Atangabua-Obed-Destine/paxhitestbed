<?php

namespace App\Services\Chat\Tools\Staff;

use App\Models\ChatConversation;
use App\Services\Chat\ChatContext;
use App\Services\Chat\Contracts\ChatTool;
use App\Services\Chat\ScreenDirectory;
use App\Services\Chat\ToolResult;

/**
 * "Where do I submit marks for my course?" — and a link straight to it.
 *
 * The system has over five hundred administrative pages. Somebody who needs
 * one of them knows the task, not the menu path, and asking a colleague is
 * how most people currently find out.
 *
 * It offers only screens this person may actually open: every candidate is
 * checked against their own Spatie permissions through ChatContext::can(),
 * which returns false for anyone who is not staff, so a student can never
 * reach this tool at all.
 *
 * Where a screen matches but the person lacks the permission, it says so
 * rather than pretending nothing exists — they already described the task, so
 * naming it tells them nothing they did not type, and "ask an administrator"
 * is far more use than "I could not find it". The URL is withheld.
 *
 * This grants nothing: the screen enforces its own permission when opened.
 * The worst a wrong entry can do is point somebody at a page that refuses
 * them.
 */
class FindScreenTool implements ChatTool
{
    public function name(): string
    {
        return 'find_screen';
    }

    public function description(): string
    {
        return 'Find the screen in this system for a task the user wants to carry out, and return '
            . 'a link to open it. Only screens the user is permitted to open are returned. '
            . 'Use for "where do I submit marks", "how do I take attendance", "I want to start a '
            . 'class session", "take me to the timetable", "where can I publish results", '
            . '"how do I enrol a student", "where is the page for ...", "how do I do ...".';
    }

    public function parameters(): array
    {
        return [
            'type' => 'OBJECT',
            'properties' => [
                'task' => [
                    'type' => 'STRING',
                    'description' => 'What the user wants to do, in their own words, '
                        . 'e.g. "submit exam marks" or "take class attendance".',
                ],
            ],
            'required' => ['task'],
        ];
    }

    public function allowedFor(): array
    {
        return [ChatConversation::ACTOR_USER];
    }

    /**
     * No single permission gates the tool: it is the individual screens that
     * are gated. Requiring one here would stop a lecturer who holds only
     * exam-marking from being shown the one screen they do have.
     */
    public function permission(): ?string
    {
        return null;
    }

    public function handle(ChatContext $context, array $args): ToolResult
    {
        $task = trim((string) ($args['task'] ?? ''));

        if (mb_strlen($task) < 2) {
            return ToolResult::empty('Please say what you would like to do.');
        }

        $matches = (new ScreenDirectory())->search($task, $context);

        if ($matches === []) {
            return ToolResult::empty(
                'No screen in this system matches that. Say it a different way, or ask the office '
                . 'that owns the task.'
            );
        }

        $allowed = array_values(array_filter($matches, fn ($m) => $m['permitted']));
        $refused = array_values(array_filter($matches, fn ($m) => !$m['permitted']));

        if ($allowed === []) {
            // Named, but not linked. They described the task themselves, so
            // this reveals nothing — and it is the answer they need.
            return ToolResult::empty(sprintf(
                'The screen for that is "%s", but this account does not have permission to open it. '
                . 'An administrator can grant it.',
                $refused[0]['label']
            ));
        }

        $best = $allowed[0];

        $data = [
            'best_match' => [
                'screen' => $best['label'],
                'what_it_is' => $best['description'],
                'url' => $best['url'],
            ],
            'other_matches' => array_map(fn ($m) => [
                'screen' => $m['label'],
                'url' => $m['url'],
            ], array_slice($allowed, 1)),
        ];

        if ($refused !== []) {
            $data['matched_but_not_permitted'] = array_map(fn ($m) => $m['label'], $refused);
        }

        // The link becomes a button in the widget, so the answer is one tap
        // rather than a path to follow.
        return ToolResult::make($data, [
            'url' => $best['url'],
            'label' => 'Open ' . $best['label'],
        ]);
    }
}
