<?php

namespace Laravel\Prompts\DataTable\Formatting;

interface DisplayFormatter
{
    /**
     * @param array<int|string, string> $row
     */
    public function format(string $value, array $row): string;
}
