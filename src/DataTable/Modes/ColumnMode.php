<?php

namespace Laravel\Prompts\DataTable\Modes;

use Laravel\Prompts\DataTablePrompt;
use Laravel\Prompts\Key;

class ColumnMode implements DataTableMode
{
    public const NAME = 'column';

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
            's' => $prompt->enterSortedMode(),
            Key::ESCAPE => $prompt->exitColumnMode(),
            default => null,
        };
    }

    public function helpText(): string
    {
        return 's: enter sort mode | Esc: back to select';
    }
}
