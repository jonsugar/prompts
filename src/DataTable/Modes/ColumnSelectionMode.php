<?php

namespace Laravel\Prompts\DataTable\Modes;

use Laravel\Prompts\DataTablePrompt;
use Laravel\Prompts\Key;

class ColumnSelectionMode implements DataTableMode
{
    public const NAME = 'column-selection';

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
            Key::oneOf([Key::LEFT, Key::LEFT_ARROW], $key) => $prompt->selectPreviousSortableColumn(),
            Key::oneOf([Key::RIGHT, Key::RIGHT_ARROW], $key) => $prompt->selectNextSortableColumn(),
            '/' => $prompt->enterSearchMode(),
            's' => $prompt->sortSelectedColumn(),
            Key::ESCAPE => $prompt->enterBrowseMode(),
            default => null,
        };
    }

    public function helpText(): string
    {
        return '[←/→] column  [s] sort/toggle  [/] search  [Esc] normal';
    }
}
