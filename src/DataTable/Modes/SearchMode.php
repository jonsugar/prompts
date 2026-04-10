<?php

namespace Laravel\Prompts\DataTable\Modes;

use Laravel\Prompts\DataTablePrompt;

class SearchMode implements DataTableMode
{
    public function name(): string
    {
        return 'search';
    }

    public function acceptsTypedInput(): bool
    {
        return true;
    }

    public function handleKey(DataTablePrompt $prompt, string $key): void
    {
        $prompt->handleSearchKey($key);
    }

    public function helpText(): string
    {
        return 'Type to filter | Enter: keep filter | Esc: clear filter | Ctrl+H: help';
    }
}
