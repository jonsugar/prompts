<?php

namespace Laravel\Prompts\DataTable\Modes;

use Laravel\Prompts\DataTablePrompt;

interface DataTableMode
{
    /**
     * Unique mode identifier.
     */
    public function name(): string;

    /**
     * Determine if typed input should be tracked in this mode.
     */
    public function acceptsTypedInput(): bool;

    /**
     * Handle a key press for this mode.
     */
    public function handleKey(DataTablePrompt $prompt, string $key): void;

    /**
     * Help text shown when help is toggled.
     */
    public function helpText(): string;

    /**
     * Key label used to show help for this mode.
     */
    public function helpToggleKey(): string;
}
