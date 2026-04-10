<?php

namespace Laravel\Prompts\DataTable\Formatting;

use Closure;

class CallableDisplayFormatter implements DisplayFormatter
{
    /**
     * @var Closure(string, array<int|string, string>): mixed
     */
    protected Closure $formatter;

    /**
     * @param callable(string, array<int|string, string>): mixed $formatter
     */
    public function __construct(callable $formatter)
    {
        $this->formatter = $formatter instanceof Closure
            ? $formatter
            : $formatter(...);
    }

    /**
     * @param array<int|string, string> $row
     */
    public function format(string $value, array $row): string
    {
        return $this->stringifyValue(($this->formatter)($value, $row));
    }

    protected function stringifyValue(mixed $value): string
    {
        if (is_string($value)) {
            return $value;
        }

        if (is_scalar($value) || (is_object($value) && method_exists($value, '__toString'))) {
            return (string)$value;
        }

        return '';
    }
}
