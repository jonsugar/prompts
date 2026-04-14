<?php

namespace Laravel\Prompts;

use Closure;
use Illuminate\Support\Collection;
use Laravel\Prompts\DataTable\Modes\BrowseMode;
use Laravel\Prompts\DataTable\Modes\ColumnMode;
use Laravel\Prompts\DataTable\Modes\DataTableMode;
use Laravel\Prompts\DataTable\Modes\SearchMode;
use Laravel\Prompts\DataTable\Modes\SelectMode;
use Laravel\Prompts\DataTable\Modes\SortedMode;
use Laravel\Prompts\DataTable\TableState;

/**
 * @phpstan-type DataTableCell scalar|\Stringable|null|array{raw?: mixed, display?: mixed}
 * @phpstan-type DataTableRow array<int|string, DataTableCell>
 */
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
     * @var array<int|string, DataTableRow>
     */
    public array $rows;

    /**
     * Datatable UI and sorting state.
     */
    public TableState $tableState;

    /**
     * The cached filtered rows.
     *
     * @var array<int|string, DataTableRow>|null
     */
    protected ?array $filteredCache = null;

    /**
     * The previous cache key (query + sort state).
     */
    protected string $previousCacheKey = '';

    /**
     * Cached rows after applying display formatters.
     *
     * @var array<int|string, array<int, string>>|null
     */
    protected ?array $displayRowsCache = null;

    /**
     * Cached filtered rows after applying display formatters.
     *
     * @var array<int|string, array<int, string>>|null
     */
    protected ?array $displayFilteredCache = null;

    /**
     * The previous display cache key (query + sort state).
     */
    protected string $previousDisplayCacheKey = '';

    /**
     * Create a new DataTable instance.
     *
     * @param array<int, string|array<int, string>>|Collection<int, string|array<int, string>> $headers
     * @param array<int|string, DataTableRow>|Collection<int|string, DataTableRow>|null $rows
     * @param array<int|string, string|bool|array{
     *     type?: string,
     *     enabled?: bool,
     *     pattern?: string|array<int, string>,
     *     date_pattern?: string|array<int, string>,
     *     format?: string|array<int, string>,
     *     formats?: array<int, string>,
     *     date_formats?: array<int, string>,
     *     display?: string|Closure(string, array<int|string, string>): string|array{
     *         type?: string,
     *         pattern?: string,
     *         template?: string
     *     },
     *     template?: string
     * }>|null $sort
     *
     * @phpstan-param ($rows is null ? list<DataTableRow>|Collection<int, DataTableRow> : list<string|list<string>>|Collection<int, string|list<string>>) $headers
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
            ignore: fn (string $key) => ! $this->tableState->mode()->acceptsTypedInput(),
        );

        $this->on('key', fn (string $key) => $this->tableState->mode()->handleKey($this, $key));
    }

    /**
     * Handle key presses in browse mode.
     */
    public function handleBrowseKey(string $key): void
    {
        (new BrowseMode)->handleKey($this, $key);
    }

    /**
     * Handle key presses in search mode.
     */
    public function handleSearchKey(string $key): void
    {
        (new SearchMode)->handleKey($this, $key);
    }

    /**
     * Handle key presses in select mode.
     */
    public function handleSelectKey(string $key): void
    {
        (new SelectMode)->handleKey($this, $key);
    }

    /**
     * Handle key presses in column mode.
     */
    public function handleColumnKey(string $key): void
    {
        (new ColumnMode)->handleKey($this, $key);
    }

    /**
     * Handle key presses in sorted mode.
     */
    public function handleSortedKey(string $key): void
    {
        (new SortedMode)->handleKey($this, $key);
    }

    /**
     * Handle key presses in sort mode.
     *
     * @deprecated Use handleSelectKey().
     */
    public function handleSortKey(string $key): void
    {
        $this->handleSelectKey($key);
    }

    /**
     * Handle key presses in sort column mode.
     *
     * @deprecated Use handleColumnKey().
     */
    public function handleSortColumnKey(string $key): void
    {
        $this->handleColumnKey($key);
    }

    /**
     * Move the selection to the previous row.
     */
    public function highlightPreviousRow(): void
    {
        $this->highlightPrevious(count($this->filteredRows()));
    }

    /**
     * Move the selection to the next row.
     */
    public function highlightNextRow(): void
    {
        $this->highlightNext(count($this->filteredRows()));
    }

    /**
     * Move the selection one page up.
     */
    public function highlightPageUp(): void
    {
        $this->highlight(max(0, $this->highlighted - $this->scroll));
    }

    /**
     * Move the selection one page down.
     */
    public function highlightPageDown(): void
    {
        $this->highlight(min(count($this->filteredRows()) - 1, $this->highlighted + $this->scroll));
    }

    /**
     * Move the selection to the first row.
     */
    public function highlightFirstRow(): void
    {
        $this->highlight(0);
    }

    /**
     * Move the selection to the last row.
     */
    public function highlightLastRow(): void
    {
        $this->highlight(max(0, count($this->filteredRows()) - 1));
    }

    /**
     * Submit the highlighted row when rows are available.
     */
    public function submitIfRowAvailable(): void
    {
        if (count($this->filteredRows()) > 0) {
            $this->submit();
        }
    }

    /**
     * Transition to another datatable interaction mode.
     */
    public function transitionTo(DataTableMode $mode): void
    {
        $this->tableState->setMode($mode);
    }

    /**
     * Enter search mode.
     */
    public function enterSearchMode(): void
    {
        $this->transitionTo(new SearchMode);
        $this->cursorPosition = mb_strlen($this->typedValue);
    }

    /**
     * Exit search mode, keeping the filtered results.
     */
    public function exitSearchMode(): void
    {
        $this->enterBrowseMode();
        $this->highlighted = 0;
        $this->firstVisible = 0;
    }

    /**
     * Cancel search, clearing the query and showing all rows.
     */
    public function cancelSearchMode(): void
    {
        $this->enterBrowseMode();
        $this->typedValue = '';
        $this->cursorPosition = 0;
        $this->refreshSearchResults();
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
     * Enter select mode.
     */
    public function enterSelectMode(): void
    {
        if (! $this->tableState->hasSortableColumns()) {
            return;
        }

        $this->tableState->clearSortQuery();
        $this->transitionTo(new SelectMode);
    }

    /**
     * Enter column mode.
     */
    public function enterColumnMode(): void
    {
        $this->transitionTo(new ColumnMode);
    }

    /**
     * Enter sorted mode.
     */
    public function enterSortedMode(): void
    {
        if ($this->tableState->selectedColumnIndex === null) {
            return;
        }

        $sortActivated = $this->tableState->activateSortForSelectedColumn();

        if ($sortActivated) {
            $this->invalidateFilteredRows();
            $this->highlighted = 0;
            $this->firstVisible = 0;
        }

        $this->transitionTo(new SortedMode);
    }

    /**
     * Enter sort mode.
     *
     * @deprecated Use enterSelectMode().
     */
    public function enterSortMode(): void
    {
        $this->enterSelectMode();
    }

    /**
     * Enter sort column mode.
     *
     * @deprecated Use enterColumnMode().
     */
    public function enterSortColumnMode(): void
    {
        $this->enterColumnMode();
    }

    /**
     * Return to browse mode.
     */
    public function enterBrowseMode(): void
    {
        $this->transitionTo(new BrowseMode);
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
     * Append printable characters to the sort mode query.
     */
    public function appendSortQuery(string $key): void
    {
        foreach (mb_str_split($key) as $character) {
            if (mb_ord($character) >= 32) {
                $this->tableState->appendSortQuery($character);
            }
        }
    }

    /**
     * Remove one character from the sort mode query.
     */
    public function trimSortQuery(): void
    {
        $this->tableState->trimSortQuery();
    }

    /**
     * Reset the sort mode query.
     */
    public function clearSortQuery(): void
    {
        $this->tableState->clearSortQuery();
    }

    /**
     * Leave column mode and return to select mode.
     */
    public function exitColumnMode(): void
    {
        $this->enterSelectMode();
    }

    /**
     * Leave sorted mode and return to column mode.
     */
    public function exitSortedMode(): void
    {
        $this->enterColumnMode();
    }

    /**
     * Leave sort column mode and return to select mode.
     *
     * @deprecated Use exitColumnMode().
     */
    public function exitSortColumnMode(): void
    {
        $this->exitColumnMode();
    }

    /**
     * Toggle the active sort direction.
     */
    public function toggleSortDirection(): void
    {
        $toggled = $this->tableState->toggleSortDirection();

        if (! $toggled) {
            return;
        }

        $this->invalidateFilteredRows();
        $this->highlighted = 0;
        $this->firstVisible = 0;
    }

    /**
     * Apply sorting when the type-ahead query matches exactly one sortable column.
     */
    public function applySortFromQueryIfUnique(): bool
    {
        $matches = $this->tableState->matchingSortableColumnIndexes();

        if (count($matches) !== 1) {
            return false;
        }

        $selectionChanged = $this->tableState->selectColumn($matches[0]);
        $this->clearSortQuery();

        if ($selectionChanged) {
            $this->invalidateFilteredRows();
            $this->highlighted = 0;
            $this->firstVisible = 0;
        }

        $this->enterColumnMode();

        return true;
    }

    /**
     * Apply sorting from query only when a non-empty query is present.
     */
    public function applySortFromQueryIfUniqueWhenQueryPresent(): bool
    {
        if ($this->tableState->sortQuery === '') {
            return false;
        }

        return $this->applySortFromQueryIfUnique();
    }

    /**
     * Determine whether search mode is active.
     */
    public function isSearchMode(): bool
    {
        return $this->tableState->mode()->name() === SearchMode::NAME;
    }

    /**
     * Determine whether select mode is active.
     */
    public function isSelectMode(): bool
    {
        return $this->tableState->mode()->name() === SelectMode::NAME;
    }

    /**
     * Determine whether column mode is active.
     */
    public function isColumnMode(): bool
    {
        return $this->tableState->mode()->name() === ColumnMode::NAME;
    }

    /**
     * Determine whether sorted mode is active.
     */
    public function isSortedMode(): bool
    {
        return $this->tableState->mode()->name() === SortedMode::NAME;
    }

    /**
     * Determine whether any select/column/sorted mode is active.
     */
    public function isColumnSelectionMode(): bool
    {
        return $this->isSelectMode() || $this->isColumnMode() || $this->isSortedMode();
    }

    /**
     * Determine whether sort mode is active.
     *
     * @deprecated Use isSelectMode().
     */
    public function isSortMode(): bool
    {
        return $this->isSelectMode();
    }

    /**
     * Determine whether sort column mode is active.
     *
     * @deprecated Use isColumnMode().
     */
    public function isSortColumnMode(): bool
    {
        return $this->isColumnMode();
    }

    /**
     * Help text for the current mode.
     */
    public function helpText(): string
    {
        return $this->tableState->mode()->helpText().' | '.$this->tableState->sortSummary();
    }

    /**
     * The user-facing label for the current mode.
     */
    public function modeLabel(): string
    {
        return match ($this->tableState->mode()->name()) {
            BrowseMode::NAME => 'NORMAL',
            SearchMode::NAME => 'SEARCH',
            SelectMode::NAME => 'SELECT',
            ColumnMode::NAME => 'COLUMN',
            SortedMode::NAME => 'SORTED',
            default => strtoupper($this->tableState->mode()->name()),
        };
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
        $this->displayFilteredCache = null;
        $this->previousDisplayCacheKey = '';
    }

    /**
     * Get the filtered rows based on the current search query.
     *
     * @return array<int|string, DataTableRow>
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
                mb_strtolower(implode(' ', $this->tableState->normalizeRowForRawValues($row))),
                mb_strtolower($this->typedValue),
            ),
        ));
    }

    /**
     * All rows with configured display formatting applied.
     *
     * @return array<int|string, array<int, string>>
     */
    public function displayRows(): array
    {
        if ($this->displayRowsCache !== null) {
            return $this->displayRowsCache;
        }

        return $this->displayRowsCache = $this->tableState->formatRowsForDisplay($this->rows);
    }

    /**
     * Filtered rows with configured display formatting applied.
     *
     * @return array<int|string, array<int, string>>
     */
    public function displayFilteredRows(): array
    {
        $cacheKey = $this->typedValue.'|'.$this->tableState->sortCacheKey();

        if ($this->displayFilteredCache !== null && $this->previousDisplayCacheKey === $cacheKey) {
            return $this->displayFilteredCache;
        }

        $this->previousDisplayCacheKey = $cacheKey;

        return $this->displayFilteredCache = $this->tableState->formatRowsForDisplay($this->filteredRows());
    }

    /**
     * The currently visible rows.
     *
     * @return array<int|string, DataTableRow>
     */
    public function visible(): array
    {
        return array_slice($this->filteredRows(), $this->firstVisible, $this->scroll, preserve_keys: true);
    }

    /**
     * The currently visible rows with display formatting applied.
     *
     * @return array<int|string, array<int, string>>
     */
    public function displayVisible(): array
    {
        return array_slice($this->displayFilteredRows(), $this->firstVisible, $this->scroll, preserve_keys: true);
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
     * @return array<int|string, mixed>|null
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

    /**
     * Get the selected display row.
     *
     * @return array<int, string>|null
     */
    public function displaySelectedRow(): ?array
    {
        if ($this->highlighted === null) {
            return null;
        }

        $filtered = $this->displayFilteredRows();
        $keys = array_keys($filtered);

        if (! isset($keys[$this->highlighted])) {
            return null;
        }

        return $filtered[$keys[$this->highlighted]];
    }
}
