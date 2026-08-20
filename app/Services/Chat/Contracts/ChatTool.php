<?php

namespace App\Services\Chat\Contracts;

use App\Services\Chat\ChatContext;
use App\Services\Chat\ToolResult;

/**
 * A single read-only capability the assistant may invoke.
 *
 * Implementations MUST derive the subject of every query from the supplied
 * ChatContext, never from $args. Arguments are for narrowing a result the actor
 * is already entitled to see (a date range, a semester), never for choosing
 * WHOSE data is read.
 */
interface ChatTool
{
    /** Stable identifier exposed to the model. */
    public function name(): string;

    /** Plain-language description; this is how the model decides to call it. */
    public function description(): string;

    /**
     * JSON-schema-ish parameter definition in Gemini's format.
     * Return an empty properties object when the tool takes no arguments.
     */
    public function parameters(): array;

    /**
     * Which actor types may use this tool: guest, applicant, student, user.
     */
    public function allowedFor(): array;

    /**
     * Spatie permission additionally required, or null when none applies.
     * Only consulted for staff actors.
     */
    public function permission(): ?string;

    /** Execute the query. Must be side-effect free. */
    public function handle(ChatContext $context, array $args): ToolResult;
}
