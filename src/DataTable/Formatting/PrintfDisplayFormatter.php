<?php

namespace Laravel\Prompts\DataTable\Formatting;

class PrintfDisplayFormatter implements DisplayFormatter
{
    public function __construct(protected string $pattern)
    {
    }

    /**
     * @param array<int|string, string> $row
     */
    public function format(string $value, array $row): string
    {
        return sprintf($this->pattern, $value);
    }
}
