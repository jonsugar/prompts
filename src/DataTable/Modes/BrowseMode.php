<?php

namespace Laravel\Prompts\DataTable\Modes;

use Laravel\Prompts\DataTablePrompt;

class BrowseMode implements DataTableMode
{
    public function name(): string
    {
        return 'browse';
    }

    public function acceptsTypedInput(): bool
    {
        return false;
    }

    public function handleKey(DataTablePrompt $prompt, string $key): void
    {
        $prompt->handleBrowseKey($key);
    }

    public function helpText(): string
    {
        return 'Enter: select | /: search | s: sort | h: help | Ctrl+C: cancel';
    }
}
