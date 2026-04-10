<?php

namespace Laravel\Prompts\DataTable;

use DateTimeImmutable;
use Laravel\Prompts\DataTable\Modes\BrowseMode;
use Laravel\Prompts\DataTable\Modes\DataTableMode;

class TableState
{
    /**
     * @var array<int, ColumnDefinition>
     */
    public array $columns;

    /**
     * The active sort selection.
     */
    public ?SortSelection $sortSelection = null;

    /**
     * Indicates whether help text should be shown.
     */
    public bool $helpVisible = false;

    /**
     * @var DataTableMode
     */
    protected DataTableMode $mode;

    /**
     * @param array<int, string|array<int, string>> $headers
     * @param array<int|string, array<int, string>> $rows
     * @param array<int|string, string|bool|array<string, mixed>>|null $sortConfiguration
     */
    public function __construct(array $headers, array $rows, ?array $sortConfiguration = null)
    {
        $this->columns = $this->buildColumns($headers, $rows, $sortConfiguration);
        $this->mode = new BrowseMode;
    }

    /**
     * Get the active interaction mode.
     */
    public function mode(): DataTableMode
    {
        return $this->mode;
    }

    /**
     * Switch the interaction mode.
     */
    public function setMode(DataTableMode $mode): void
    {
        $this->mode = $mode;
    }

    /**
     * Toggle the help line.
     */
    public function toggleHelp(): void
    {
        $this->helpVisible = ! $this->helpVisible;
    }

    /**
     * Determine if sorting is available.
     */
    public function hasSortableColumns(): bool
    {
        foreach ($this->columns as $column) {
            if ($column->sortable) {
                return true;
            }
        }

        return false;
    }

    /**
     * Apply sorting via a visible shortcut key.
     */
    public function applySortShortcut(string $shortcut): bool
    {
        $shortcut = strtolower($shortcut);

        foreach ($this->columns as $column) {
            if ($column->sortable && $column->shortcut !== null && strtolower($column->shortcut) === $shortcut) {
                $this->applySort($column->index);

                return true;
            }
        }

        return false;
    }

    /**
     * Apply sort to a specific column.
     */
    public function applySort(int $columnIndex): void
    {
        $column = $this->columnByIndex($columnIndex);

        if ($column === null || ! $column->sortable) {
            return;
        }

        if ($this->sortSelection !== null && $this->sortSelection->columnIndex === $columnIndex) {
            $this->sortSelection->direction = SortDirection::toggle($this->sortSelection->direction);

            return;
        }

        $this->sortSelection = new SortSelection($columnIndex, SortDirection::ASC);
    }

    /**
     * A cache key that reflects sort state.
     */
    public function sortCacheKey(): string
    {
        if ($this->sortSelection === null) {
            return 'none';
        }

        return $this->sortSelection->columnIndex.'|'.$this->sortSelection->direction;
    }

    /**
     * @return array<int, string>
     */
    public function displayHeaders(): array
    {
        return array_map(
            fn (ColumnDefinition $column) => $this->displayHeaderForColumn($column),
            $this->columns
        );
    }

    /**
     * Summary text used in help.
     */
    public function sortSummary(): string
    {
        if ($this->sortSelection === null) {
            return 'Sort: off';
        }

        $column = $this->columnByIndex($this->sortSelection->columnIndex);

        if ($column === null) {
            return 'Sort: off';
        }

        return sprintf(
            'Sort: %s (%s)',
            $column->title,
            $this->sortSelection->direction
        );
    }

    /**
     * @param array<int|string, array<int, string>> $rows
     * @return array<int|string, array<int, string>>
     */
    public function applySorting(array $rows): array
    {
        if ($this->sortSelection === null) {
            return $rows;
        }

        $column = $this->columnByIndex($this->sortSelection->columnIndex);

        if ($column === null || ! $column->sortable) {
            return $rows;
        }

        $decorated = [];
        $position = 0;

        foreach ($rows as $key => $row) {
            $decorated[] = [
                'key' => $key,
                'row' => $row,
                'value' => $row[$column->index] ?? '',
                'position' => $position++,
            ];
        }

        usort($decorated, function (array $left, array $right) use ($column): int {
            $comparison = $this->compareValues($left['value'], $right['value'], $column);

            if ($comparison === 0) {
                return $left['position'] <=> $right['position'];
            }

            return $this->sortSelection?->direction === SortDirection::ASC
                ? $comparison
                : -$comparison;
        });

        $sorted = [];

        foreach ($decorated as $item) {
            $sorted[$item['key']] = $item['row'];
        }

        return $sorted;
    }

    /**
     * Resolve the title for a column.
     */
    public function columnTitle(int $index): string
    {
        return $this->columnByIndex($index)?->title ?? '';
    }

    /**
     * Build internal column metadata.
     *
     * @param array<int, string|array<int, string>> $headers
     * @param array<int|string, array<int, string>> $rows
     * @param array<int|string, string|bool|array<string, mixed>>|null $sortConfiguration
     * @return array<int, ColumnDefinition>
     */
    protected function buildColumns(array $headers, array $rows, ?array $sortConfiguration): array
    {
        $count = $this->columnCount($headers, $rows);
        $sortMap = $this->normalizeSortConfiguration($headers, $count, $sortConfiguration);

        $columns = [];
        $sortablePosition = 1;

        for ($index = 0; $index < $count; $index++) {
            $sortDefinition = $sortMap[$index] ?? null;
            $type = $sortDefinition['type'] ?? null;
            $sortable = $sortDefinition !== null;

            $columns[] = new ColumnDefinition(
                index: $index,
                title: $this->headerTitle($headers, $index),
                sortable: $sortable,
                type: $type ?? ColumnType::ALPHA,
                shortcut: $sortable ? $this->shortcutForPosition($sortablePosition++) : null,
                datePatterns: $sortDefinition['date_patterns'] ?? [],
            );
        }

        return $columns;
    }

    /**
     * @param array<int, string|array<int, string>> $headers
     * @param array<int|string, array<int, string>> $rows
     */
    protected function columnCount(array $headers, array $rows): int
    {
        if (! empty($headers)) {
            return count($headers);
        }

        if (! empty($rows)) {
            return max(array_map('count', $rows));
        }

        return 1;
    }

    /**
     * @param array<int, string|array<int, string>> $headers
     */
    protected function headerTitle(array $headers, int $index): string
    {
        $header = $headers[$index] ?? null;

        if (is_array($header)) {
            return implode(' ', $header);
        }

        if (is_string($header) && $header !== '') {
            return $header;
        }

        return 'Column '.($index + 1);
    }

    /**
     * @param array<int, string|array<int, string>> $headers
     * @param array<int|string, string|bool|array<string, mixed>>|null $sortConfiguration
     * @return array<int, array{type: string, date_patterns: array<int, string>}>
     */
    protected function normalizeSortConfiguration(array $headers, int $columnCount, ?array $sortConfiguration): array
    {
        if ($sortConfiguration === null) {
            return [];
        }

        if ($sortConfiguration === []) {
            $allSortable = [];

            for ($index = 0; $index < $columnCount; $index++) {
                $allSortable[$index] = [
                    'type' => ColumnType::ALPHA,
                    'date_patterns' => [],
                ];
            }

            return $allSortable;
        }

        if (array_is_list($sortConfiguration)) {
            $map = [];

            foreach ($sortConfiguration as $index => $value) {
                if ($index >= $columnCount) {
                    continue;
                }

                $definition = $this->resolveColumnSortConfiguration($value);

                if ($definition !== null) {
                    $map[$index] = $definition;
                }
            }

            return $map;
        }

        $map = [];

        foreach ($sortConfiguration as $column => $value) {
            $index = null;

            if (is_int($column) || (is_string($column) && ctype_digit($column))) {
                $index = (int) $column;
            } elseif (is_string($column)) {
                $index = $this->columnIndexFromHeader($headers, $column);
            }

            if ($index === null || $index < 0 || $index >= $columnCount) {
                continue;
            }

            $definition = $this->resolveColumnSortConfiguration($value);

            if ($definition !== null) {
                $map[$index] = $definition;
            }
        }

        return $map;
    }

    /**
     * @param string|bool|array<string, mixed> $value
     * @return array{type: string, date_patterns: array<int, string>}|null
     */
    protected function resolveColumnSortConfiguration(string|bool|array $value): ?array
    {
        if (is_bool($value)) {
            return $value
                ? ['type' => ColumnType::ALPHA, 'date_patterns' => []]
                : null;
        }

        if (is_string($value)) {
            return [
                'type' => ColumnType::normalize($value),
                'date_patterns' => [],
            ];
        }

        if (! ($value['enabled'] ?? true)) {
            return null;
        }

        $configuredType = is_string($value['type'] ?? null)
            ? ColumnType::normalize($value['type'])
            : ColumnType::ALPHA;

        return [
            'type' => $configuredType,
            'date_patterns' => $configuredType === ColumnType::DATE
                ? $this->resolveDatePatterns($value)
                : [],
        ];
    }

    /**
     * @param array<string, mixed> $value
     * @return array<int, string>
     */
    protected function resolveDatePatterns(array $value): array
    {
        $rawPatterns = $value['pattern']
            ?? $value['date_pattern']
            ?? $value['format']
            ?? $value['formats']
            ?? $value['date_formats']
            ?? [];

        if (is_string($rawPatterns)) {
            return $rawPatterns !== '' ? [$rawPatterns] : [];
        }

        if (! is_array($rawPatterns)) {
            return [];
        }

        $patterns = [];

        foreach ($rawPatterns as $pattern) {
            if (is_string($pattern) && $pattern !== '') {
                $patterns[] = $pattern;
            }
        }

        return array_values(array_unique($patterns));
    }

    /**
     * @param array<int, string|array<int, string>> $headers
     */
    protected function columnIndexFromHeader(array $headers, string $expected): ?int
    {
        foreach (array_keys($headers) as $index) {
            $title = $this->headerTitle($headers, $index);

            if (strcasecmp($title, $expected) === 0) {
                return $index;
            }
        }

        return null;
    }

    /**
     * Compare two string cell values based on configured column type.
     */
    protected function compareValues(string $left, string $right, ColumnDefinition $column): int
    {
        return match ($column->type) {
            ColumnType::NUMERIC => $this->compareNumeric($left, $right),
            ColumnType::DATE => $this->compareDates($left, $right, $column->datePatterns),
            ColumnType::ALPHA_NUMERIC => strnatcasecmp($left, $right),
            default => strcasecmp($left, $right),
        };
    }

    /**
     * Compare numeric values with a lexical fallback.
     */
    protected function compareNumeric(string $left, string $right): int
    {
        $leftValue = $this->parseNumeric($left);
        $rightValue = $this->parseNumeric($right);

        if ($leftValue !== null && $rightValue !== null) {
            return $leftValue <=> $rightValue;
        }

        if ($leftValue !== null) {
            return -1;
        }

        if ($rightValue !== null) {
            return 1;
        }

        return strnatcasecmp($left, $right);
    }

    /**
     * @param array<int, string> $patterns
     */
    protected function compareDates(string $left, string $right, array $patterns = []): int
    {
        $leftTimestamp = $this->parseDate($left, $patterns);
        $rightTimestamp = $this->parseDate($right, $patterns);

        if ($leftTimestamp !== null && $rightTimestamp !== null) {
            return $leftTimestamp <=> $rightTimestamp;
        }

        if ($leftTimestamp !== null) {
            return -1;
        }

        if ($rightTimestamp !== null) {
            return 1;
        }

        return strnatcasecmp($left, $right);
    }

    /**
     * Parse a numeric value from a string.
     */
    protected function parseNumeric(string $value): ?float
    {
        $normalized = str_replace(',', '', trim($value));
        $normalized = preg_replace('/[^0-9eE+.\-]/', '', $normalized) ?? '';

        if ($normalized === '' || in_array($normalized, ['-', '+', '.', '-.', '+.'], true)) {
            return null;
        }

        if (! is_numeric($normalized)) {
            return null;
        }

        return (float) $normalized;
    }

    /**
     * Parse a date/time string to a unix timestamp.
     *
     * @param array<int, string> $patterns
     */
    protected function parseDate(string $value, array $patterns = []): ?int
    {
        $value = trim($value);

        if ($value === '') {
            return null;
        }

        if ($patterns !== []) {
            foreach ($patterns as $pattern) {
                foreach ([$pattern, '!'.$pattern] as $candidatePattern) {
                    $date = DateTimeImmutable::createFromFormat($candidatePattern, $value);
                    $errors = DateTimeImmutable::getLastErrors();

                    if ($date !== false && $this->dateParseSucceeded($errors)) {
                        return $date->getTimestamp();
                    }
                }
            }

            return null;
        }

        $timestamp = strtotime($value);

        return $timestamp === false ? null : $timestamp;
    }

    /**
     * @param array<string, int|array<int, string>>|false $errors
     */
    protected function dateParseSucceeded(array|false $errors): bool
    {
        if ($errors === false) {
            return true;
        }

        return ($errors['warning_count'] ?? 0) === 0
            && ($errors['error_count'] ?? 0) === 0;
    }

    /**
     * Visible header label for a column.
     */
    protected function displayHeaderForColumn(ColumnDefinition $column): string
    {
        $indicator = $this->sortIndicator($column->index);

        if ($this->mode->name() === 'sort' && $column->sortable && $column->shortcut !== null) {
            return '['.$column->shortcut.'] '.$column->title.($indicator === '' ? '' : ' '.$indicator);
        }

        return $column->title.($indicator === '' ? '' : ' '.$indicator);
    }

    /**
     * Current sort indicator for a given column.
     */
    protected function sortIndicator(int $columnIndex): string
    {
        if ($this->sortSelection === null || $this->sortSelection->columnIndex !== $columnIndex) {
            return '';
        }

        return $this->sortSelection->direction === SortDirection::ASC ? '↑' : '↓';
    }

    /**
     * Convert sortable column position to an interaction shortcut.
     */
    protected function shortcutForPosition(int $position): ?string
    {
        if ($position <= 9) {
            return (string) $position;
        }

        $index = $position - 10;

        if ($index < 26) {
            return chr(ord('a') + $index);
        }

        return null;
    }

    /**
     * Find column metadata by index.
     */
    protected function columnByIndex(int $index): ?ColumnDefinition
    {
        foreach ($this->columns as $column) {
            if ($column->index === $index) {
                return $column;
            }
        }

        return null;
    }
}
