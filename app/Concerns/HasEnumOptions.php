<?php

namespace App\Concerns;

/**
 * Shared helpers for backed enums that expose a human readable label().
 */
trait HasEnumOptions
{
    /**
     * Value => label pairs, ready for a select/radio group.
     *
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $case) => [$case->value => $case->label()])
            ->all();
    }

    /**
     * Every backing value, e.g. for an `in:` validation rule.
     *
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
