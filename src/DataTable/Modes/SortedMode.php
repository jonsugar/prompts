<?php

namespace Laravel\Prompts\DataTable\Modes;

use Laravel\Prompts\DataTablePrompt;
use Laravel\Prompts\Key;

class SortedMode implements DataTableMode
{
    public const NAME = 'sorted';

    public function name(): string
    {
        return self::NAME;
    }

    public function acceptsTypedInput(): bool
    {
        return false;
    }

    public function handleKey(DataTablePrompt $prompt, string $key): void
    {
        match ($key) {
            's' => $prompt->toggleSortDirection(),
            Key::ESCAPE => $prompt->exitSortedMode(),
            default => null,
        };
    }

    public function helpText(): string
    {
        return 's: toggle sort direction | Esc: back to column';
    }
}
