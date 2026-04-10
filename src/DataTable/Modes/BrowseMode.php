<?php

namespace Laravel\Prompts\DataTable\Modes;

use Laravel\Prompts\Concerns\Colors;
use Laravel\Prompts\Key;
use Laravel\Prompts\DataTablePrompt;

class BrowseMode implements DataTableMode
{
    use Colors;

    public const NAME = 'browse';

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
            Key::UP, Key::UP_ARROW, Key::CTRL_P => $prompt->highlightPreviousRow(),
            Key::DOWN, Key::DOWN_ARROW, Key::CTRL_N => $prompt->highlightNextRow(),
            Key::PAGE_UP => $prompt->highlightPageUp(),
            Key::PAGE_DOWN => $prompt->highlightPageDown(),
            Key::oneOf([Key::HOME, Key::CTRL_A], $key) => $prompt->highlightFirstRow(),
            Key::oneOf([Key::END, Key::CTRL_E], $key) => $prompt->highlightLastRow(),
            Key::ENTER => $prompt->submitIfRowAvailable(),
            '/' => $prompt->enterSearchMode(),
            's' => $prompt->enterSortMode(),
            Key::CTRL_H => $prompt->toggleHelp(),
            default => null,
        };
    }

    public function helpText(): string
    {
        return '[Enter] ' . $this->bold($this->black('select')) . '  [/] search  [s] sort  [Ctrl+H]  help  [Ctrl+C] cancel';
    }

    public function helpToggleKey(): string
    {
        return 'Ctrl+H';
    }
}
