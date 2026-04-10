<?php

namespace Laravel\Prompts\DataTable;

class SortSelection
{
    public function __construct(
        public int $columnIndex,
        public string $direction = SortDirection::ASC,
    ) {
    }
}
