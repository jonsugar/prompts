<?php

namespace Laravel\Prompts;

use Closure;
use Illuminate\Support\Collection;
use Laravel\Prompts\DataTable\Modes\BrowseMode;
use Laravel\Prompts\DataTable\Modes\SearchMode;
use Laravel\Prompts\DataTable\Modes\SortMode;
use Laravel\Prompts\DataTable\TableState;

class DataTablePrompt extends Prompt
{
    use Concerns\Scrolling;
    use Concerns\TypedValue;

    /**
     * The table headers.
     *
     * @var array<int, string|array<int, string>>
     */
    public array $headers;

    /**
     * The table rows.
     *
     * @var array<int|string, array<int, string>>
     */
    public array $rows;

    /**
     * Datatable UI and sorting state.
     */
    public TableState $tableState;

    /**
     * The cached filtered rows.
     *
     * @var array<int|string, array<int, string>>|null
     */
    protected ?array $filteredCache = null;

    /**
     * The previous cache key (query + sort state).
     */
    protected string $previousCacheKey = '';

    /**
     * Create a new DataTable instance.
     *
     * @param array<int, string|array<int, string>>|Collection<int, string|array<int, string>> $headers
     * @param array<int|string, array<int, string>>|Collection<int|string, array<int, string>>|null $rows
     * @param array<int|string, string|bool|array{
     *     type?: string,
     *     enabled?: bool,
     *     pattern?: string|array<int, string>,
     *     date_pattern?: string|array<int, string>,
     *     format?: string|array<int, string>,
     *     formats?: array<int, string>,
     *     date_formats?: array<int, string>
     * }>|null $sort
     *
     * @phpstan-param ($rows is null ? list<list<string>>|Collection<int, list<string>> : list<string|list<string>>|Collection<int, string|list<string>>) $headers
     */
    public function __construct(
        array|Collection $headers = [],
        array|Collection|null $rows = null,
        public int $scroll = 10,
        public string $label = '',
        public string $hint = '',
        public bool|string $required = false,
        public mixed $validate = null,
        public ?Closure $transform = null,
        public ?Closure $filter = null,
        public ?array $sort = null,
    ) {
        if ($rows === null) {
            $rows = $headers;
            $headers = [];
        }

        $this->headers = $headers instanceof Collection ? $headers->all() : $headers;
        $this->rows = $rows instanceof Collection ? $rows->all() : $rows;
        $this->tableState = new TableState($this->headers, $this->rows, $sort);

        $this->initializeScrolling(0);

        $this->trackTypedValue(
            submit: false,
            ignore: fn (string $key) => ! $this->tableState->mode()->acceptsTypedInput()
                || ($this->isSearchMode() && $key === Key::CTRL_H),
        );

        $this->on('key', fn (string $key) => $this->tableState->mode()->handleKey($this, $key));
    }

    /**
     * Handle key presses in browse mode.
     */
    public function handleBrowseKey(string $key): void
    {
        $total = count($this->filteredRows());

        match ($key) {
            Key::UP, Key::UP_ARROW, Key::CTRL_P => $this->highlightPrevious($total),
            Key::DOWN, Key::DOWN_ARROW, Key::CTRL_N => $this->highlightNext($total),
            Key::PAGE_UP => $this->highlight(max(0, $this->highlighted - $this->scroll)),
            Key::PAGE_DOWN => $this->highlight(min($total - 1, $this->highlighted + $this->scroll)),
            Key::oneOf([Key::HOME, Key::CTRL_A], $key) => $this->highlight(0),
            Key::oneOf([Key::END, Key::CTRL_E], $key) => $this->highlight(max(0, $total - 1)),
            Key::ENTER => $total > 0 ? $this->submit() : null,
            '/' => $this->enterSearchMode(),
            's' => $this->enterSortMode(),
            'h' => $this->tableState->toggleHelp(),
            default => null,
        };
    }

    /**
     * Handle key presses in search mode.
     */
    public function handleSearchKey(string $key): void
    {
        match ($key) {
            Key::ENTER => $this->exitSearchMode(),
            Key::ESCAPE => $this->cancelSearchMode(),
            Key::CTRL_H => $this->tableState->toggleHelp(),
            default => $this->refreshSearchResults(),
        };
    }

    /**
     * Handle key presses in sort mode.
     */
    public function handleSortKey(string $key): void
    {
        match ($key) {
            Key::ENTER, Key::ESCAPE, 's' => $this->enterBrowseMode(),
            '/' => $this->enterSearchMode(),
            'h' => $this->tableState->toggleHelp(),
            default => $this->applySortShortcut($key) ? $this->enterBrowseMode() : null,
        };
    }

    /**
     * Enter search mode.
     */
    public function enterSearchMode(): void
    {
        $this->tableState->setMode(new SearchMode);
        $this->cursorPosition = mb_strlen($this->typedValue);
    }

    /**
     * Exit search mode, keeping the filtered results.
     */
    public function exitSearchMode(): void
    {
        $this->tableState->setMode(new BrowseMode);
        $this->highlighted = 0;
        $this->firstVisible = 0;
    }

    /**
     * Cancel search, clearing the query and showing all rows.
     */
    public function cancelSearchMode(): void
    {
        $this->tableState->setMode(new BrowseMode);
        $this->typedValue = '';
        $this->cursorPosition = 0;
        $this->invalidateFilteredRows();
        $this->highlighted = 0;
        $this->firstVisible = 0;
    }

    /**
     * Handle typing in search mode.
     */
    public function refreshSearchResults(): void
    {
        $this->invalidateFilteredRows();
        $this->highlighted = 0;
        $this->firstVisible = 0;
    }

    /**
     * Enter sort mode.
     */
    public function enterSortMode(): void
    {
        if (! $this->tableState->hasSortableColumns()) {
            return;
        }

        $this->tableState->setMode(new SortMode);
    }

    /**
     * Return to browse mode.
     */
    public function enterBrowseMode(): void
    {
        $this->tableState->setMode(new BrowseMode);
    }

    /**
     * Apply sorting by mode shortcut.
     */
    public function applySortShortcut(string $key): bool
    {
        $applied = $this->tableState->applySortShortcut($key);

        if (! $applied) {
            return false;
        }

        $this->invalidateFilteredRows();
        $this->highlighted = 0;
        $this->firstVisible = 0;

        return true;
    }

    /**
     * Determine whether search mode is active.
     */
    public function isSearchMode(): bool
    {
        return $this->tableState->mode() instanceof SearchMode;
    }

    /**
     * Determine whether sort mode is active.
     */
    public function isSortMode(): bool
    {
        return $this->tableState->mode() instanceof SortMode;
    }

    /**
     * Determine whether help text is visible.
     */
    public function isHelpVisible(): bool
    {
        return $this->tableState->helpVisible;
    }

    /**
     * Help text for the current mode.
     */
    public function helpText(): string
    {
        return $this->tableState->mode()->helpText().' | '.$this->tableState->sortSummary();
    }

    /**
     * Header titles for display.
     *
     * @return array<int, string>
     */
    public function displayHeaders(): array
    {
        return $this->tableState->displayHeaders();
    }

    /**
     * Number of available columns.
     */
    public function columnCount(): int
    {
        return count($this->tableState->columns);
    }

    /**
     * Invalidate filtered rows cache.
     */
    protected function invalidateFilteredRows(): void
    {
        $this->filteredCache = null;
        $this->previousCacheKey = '';
    }

    /**
     * Get the filtered rows based on the current search query.
     *
     * @return array<int|string, array<int, string>>
     */
    public function filteredRows(): array
    {
        $cacheKey = $this->typedValue.'|'.$this->tableState->sortCacheKey();

        if ($this->filteredCache !== null && $this->previousCacheKey === $cacheKey) {
            return $this->filteredCache;
        }

        $this->previousCacheKey = $cacheKey;

        if ($this->typedValue === '') {
            return $this->filteredCache = $this->tableState->applySorting($this->rows);
        }

        if ($this->filter !== null) {
            return $this->filteredCache = $this->tableState->applySorting(array_filter(
                $this->rows,
                fn (array $row) => ($this->filter)($row, $this->typedValue),
            ));
        }

        return $this->filteredCache = $this->tableState->applySorting(array_filter(
            $this->rows,
            fn (array $row) => str_contains(
                mb_strtolower(implode(' ', $row)),
                mb_strtolower($this->typedValue),
            ),
        ));
    }

    /**
     * The currently visible rows.
     *
     * @return array<int|string, array<int, string>>
     */
    public function visible(): array
    {
        return array_slice($this->filteredRows(), $this->firstVisible, $this->scroll, preserve_keys: true);
    }

    /**
     * Get the current search query.
     */
    public function searchValue(): string
    {
        return $this->typedValue;
    }

    /**
     * Get the search query with a virtual cursor.
     */
    public function searchWithCursor(int $maxWidth): string
    {
        if ($this->typedValue === '') {
            return $this->dim($this->addCursor('', 0, $maxWidth));
        }

        return $this->addCursor($this->typedValue, $this->cursorPosition, $maxWidth);
    }

    /**
     * Get the value of the prompt.
     */
    public function value(): mixed
    {
        if ($this->highlighted === null) {
            return null;
        }

        $filtered = $this->filteredRows();
        $keys = array_keys($filtered);

        if (! isset($keys[$this->highlighted])) {
            return null;
        }

        return $keys[$this->highlighted];
    }

    /**
     * Get the selected row for display purposes.
     *
     * @return array<int, string>|null
     */
    public function selectedRow(): ?array
    {
        if ($this->highlighted === null) {
            return null;
        }

        $filtered = $this->filteredRows();
        $keys = array_keys($filtered);

        if (! isset($keys[$this->highlighted])) {
            return null;
        }

        return $filtered[$keys[$this->highlighted]];
    }
}
