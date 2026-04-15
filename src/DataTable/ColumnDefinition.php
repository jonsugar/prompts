<?php

namespace Laravel\Prompts\DataTable;

use Laravel\Prompts\DataTable\Formatting\DisplayFormatter;

class ColumnDefinition
{
    /**
     * @param array<int, string> $datePatterns
     */
    public function __construct(
        public int $index,
        public string $title,
        public bool $sortable = false,
        public string $type = ColumnType::ALPHA,
        public array $datePatterns = [],
        public ?DisplayFormatter $displayFormatter = null,
    ) {
    }
}
