<?php

namespace Laravel\Prompts\DataTable;

final class SortDirection
{
    public const ASC = 'asc';

    public const DESC = 'desc';

    /**
     * Toggle the current direction.
     */
    public static function toggle(string $direction): string
    {
        return $direction === self::ASC ? self::DESC : self::ASC;
    }
}
