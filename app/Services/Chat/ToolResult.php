<?php

namespace App\Services\Chat;

/**
 * What a tool hands back: the data the model may quote, plus an optional deep
 * link so the answer can point at the real screen.
 */
class ToolResult
{
    public array $data;
    public ?array $link;
    public ?string $note;

    public function __construct(array $data, ?array $link = null, ?string $note = null)
    {
        $this->data = $data;
        $this->link = $link;
        $this->note = $note;
    }

    /**
     * @param array $data      Payload passed back to the model.
     * @param array|null $link ['url' => ..., 'label' => ...]
     */
    public static function make(array $data, ?array $link = null, ?string $note = null): self
    {
        return new self($data, $link, $note);
    }

    /**
     * A tool that found nothing — distinct from a tool that failed.
     *
     * Still accepts a link: "you have no outstanding fees" is a perfectly good
     * answer that should keep pointing at the fee statement.
     */
    public static function empty(string $note, ?array $link = null): self
    {
        return new self([], $link, $note);
    }

    public function toArray(): array
    {
        return array_filter([
            'data' => $this->data,
            'link' => $this->link,
            'note' => $this->note,
        ], fn ($value) => $value !== null && $value !== []);
    }
}
