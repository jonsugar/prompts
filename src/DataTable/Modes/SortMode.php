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
            Key::ENTER => $prompt->applySortFromQueryIfUnique(),
            Key::ESCAPE => $this->exitSortMode($prompt),
            '/' => $this->switchToSearchMode($prompt),
            Key::BACKSPACE => $prompt->trimSortQuery(),
            Key::CTRL_H => $prompt->toggleHelp(),
            default => $prompt->appendSortQuery($key),
        };
    }

    public function helpText(): string
    {
        return 'Type to narrow sortable columns | Enter: choose column when one match remains | Esc: exit sort mode | Ctrl+H: help';
    }

    public function helpToggleKey(): string
    {
        return 'Ctrl+H';
    }

    protected function exitSortMode(DataTablePrompt $prompt): void
    {
        $prompt->clearSortQuery();
        $prompt->enterBrowseMode();
    }

    protected function switchToSearchMode(DataTablePrompt $prompt): void
    {
        $prompt->clearSortQuery();
        $prompt->enterSearchMode();
    }
}
