<?php

namespace Laravel\Prompts\DataTable\Modes;

use Laravel\Prompts\Key;
use Laravel\Prompts\DataTablePrompt;

class SearchMode implements DataTableMode
{
    public const NAME = 'search';

    public function name(): string
    {
        return self::NAME;
    }

    public function acceptsTypedInput(): bool
    {
        return true;
    }

    public function handleKey(DataTablePrompt $prompt, string $key): void
    {
        match ($key) {
            Key::ENTER => $prompt->exitSearchMode(),
            Key::ESCAPE => $prompt->cancelSearchMode(),
            Key::CTRL_H => $prompt->toggleHelp(),
            default => $prompt->refreshSearchResults(),
        };
    }

    public function helpText(): string
    {
        return 'Type to filter | Enter: keep filter | Esc: clear filter | Ctrl+H: help';
    }

    public function helpToggleKey(): string
    {
        return 'Ctrl+H';
    }
}
