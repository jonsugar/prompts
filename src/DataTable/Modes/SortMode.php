<?php

namespace Laravel\Prompts\DataTable\Modes;

use Laravel\Prompts\DataTablePrompt;

class SortMode implements DataTableMode
{
    public function name(): string
    {
        return 'sort';
    }

    public function acceptsTypedInput(): bool
    {
        return false;
    }

    public function handleKey(DataTablePrompt $prompt, string $key): void
    {
        $prompt->handleSortKey($key);
    }

    public function helpText(): string
    {
        return 'Press a header shortcut to sort | Same column toggles direction | Enter/Esc: exit sort mode';
    }
}
