<?php

namespace Laravel\Prompts\DataTable\Modes;

use Laravel\Prompts\DataTablePrompt;
use Laravel\Prompts\Key;

class SelectMode implements DataTableMode
{
    public const NAME = 'select';

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
            Key::ESCAPE => $this->exitSelectMode($prompt),
            '/' => $this->switchToSearchMode($prompt),
            Key::BACKSPACE => $this->trimQueryAndAutoSelect($prompt),
            default => $this->appendQueryAndAutoSelect($prompt, $key),
        };
    }

    public function helpText(): string
    {
        return 'Type to narrow selectable columns | Auto-select when one match remains | Esc: exit select mode';
    }

    protected function exitSelectMode(DataTablePrompt $prompt): void
    {
        $prompt->clearSortQuery();
        $prompt->enterBrowseMode();
    }

    protected function switchToSearchMode(DataTablePrompt $prompt): void
    {
        $prompt->clearSortQuery();
        $prompt->enterSearchMode();
    }

    protected function trimQueryAndAutoSelect(DataTablePrompt $prompt): void
    {
        $query = $prompt->tableState->sortQuery;
        $prompt->trimSortQuery();

        if ($prompt->tableState->sortQuery !== $query) {
            $prompt->applySortFromQueryIfUniqueWhenQueryPresent();
        }
    }

    protected function appendQueryAndAutoSelect(DataTablePrompt $prompt, string $key): void
    {
        $query = $prompt->tableState->sortQuery;
        $prompt->appendSortQuery($key);

        if ($prompt->tableState->sortQuery !== $query) {
            $prompt->applySortFromQueryIfUniqueWhenQueryPresent();
        }
    }
}
