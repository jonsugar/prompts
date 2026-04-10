<?php

namespace Laravel\Prompts\DataTable;

final class ColumnType
{
    public const ALPHA = 'alpha';

    public const ALPHA_NUMERIC = 'alpha-numeric';

    public const NUMERIC = 'numeric';

    public const DATE = 'date';

    /**
     * Normalize a configured column type.
     */
    public static function normalize(string $type): string
    {
        return match (strtolower(trim($type))) {
            'alpha', 'text', 'string' => self::ALPHA,
            'alpha-numeric', 'alphanumeric', 'alpha_numeric', 'natural' => self::ALPHA_NUMERIC,
            'numeric', 'number', 'int', 'float', 'decimal' => self::NUMERIC,
            'date', 'datetime', 'time', 'timestamp' => self::DATE,
            default => self::ALPHA,
        };
    }
}
