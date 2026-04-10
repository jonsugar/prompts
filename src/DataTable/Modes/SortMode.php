<?php

namespace Laravel\Prompts\DataTable\Modes;

use Laravel\Prompts\Key;
use Laravel\Prompts\DataTablePrompt;

class SortMode implements DataTableMode
{
    public const NAME = 'sort';

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
            Key::ENTER, Key::ESCAPE, 's' => $prompt->enterBrowseMode(),
            '/' => $prompt->enterSearchMode(),
            Key::CTRL_H => $prompt->toggleHelp(),
            default => $prompt->applySortShortcut($key) ? $prompt->enterBrowseMode() : null,
        };
    }

    public function helpText(): string
    {
        return 'Press a header shortcut to sort | Same column toggles direction | Enter/Esc: exit sort mode';
    }

    public function helpToggleKey(): string
    {
        return 'Ctrl+H';
    }
}
