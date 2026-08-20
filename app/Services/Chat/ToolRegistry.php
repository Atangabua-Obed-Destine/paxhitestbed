<?php

namespace App\Services\Chat;

use App\Services\Chat\Contracts\ChatTool;

/**
 * The fixed catalogue of things the assistant can do.
 *
 * There is no dynamic registration from user input and no SQL generation: if a
 * capability is not listed here, it does not exist. Filtering happens once, up
 * front, so a tool the actor may not use is never even described to the model.
 */
class ToolRegistry
{
    /** @var array<string, ChatTool> */
    protected array $tools = [];

    public function register(ChatTool $tool): void
    {
        $this->tools[$tool->name()] = $tool;
    }

    public function registerMany(array $tools): void
    {
        foreach ($tools as $tool) {
            $this->register($tool);
        }
    }

    public function get(string $name): ?ChatTool
    {
        return $this->tools[$name] ?? null;
    }

    /** @return array<string, ChatTool> */
    public function all(): array
    {
        return $this->tools;
    }

    /**
     * Tools this actor is permitted to use — deny by default.
     *
     * @return array<string, ChatTool>
     */
    public function availableFor(ChatContext $context): array
    {
        return array_filter(
            $this->tools,
            fn (ChatTool $tool) => $this->isAllowed($tool, $context)
        );
    }

    /**
     * The single authority on whether a tool may run. Used both to build the
     * declaration list and to re-check at execution time, so a model that
     * invents a tool name or replays an old one still cannot get through.
     */
    public function isAllowed(ChatTool $tool, ChatContext $context): bool
    {
        if (!in_array($context->actorType(), $tool->allowedFor(), true)) {
            return false;
        }

        $permission = $tool->permission();
        if ($permission !== null && !$context->can($permission)) {
            return false;
        }

        return true;
    }

    /** Gemini functionDeclarations for the tools this actor may use. */
    public function declarationsFor(ChatContext $context): array
    {
        $declarations = [];

        foreach ($this->availableFor($context) as $tool) {
            $declarations[] = [
                'name' => $tool->name(),
                'description' => $tool->description(),
                'parameters' => $tool->parameters(),
            ];
        }

        return $declarations;
    }
}
