<?php

namespace Laravel\Prompts\DataTable;

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
        public ?string $shortcut = null,
        public array $datePatterns = [],
    ) {
    }
}
