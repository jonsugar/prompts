<?php

namespace Laravel\Prompts\DataTable\Modes;

use Laravel\Prompts\DataTablePrompt;
use Laravel\Prompts\Key;

class SortColumnMode implements DataTableMode
{
    public const NAME = 'sort-column';

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
            't' => $prompt->toggleSortDirection(),
            Key::ESCAPE => $prompt->exitSortColumnMode(),
            Key::CTRL_H => $prompt->toggleHelp(),
            default => null,
        };
    }

    public function helpText(): string
    {
        return 't: toggle sort direction | Esc: back to sort columns | Ctrl+H: help';
    }

    public function helpToggleKey(): string
    {
        return 'Ctrl+H';
    }
}
