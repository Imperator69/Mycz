<?php

namespace App\Enums;

/**
 * Gemeinsame Helfer fuer String-Enums mit deutscher Beschriftung.
 */
trait LabeledEnum
{
    public static function options(): array
    {
        $options = [];
        foreach (self::cases() as $case) {
            $options[$case->value] = $case->getLabel();
        }

        return $options;
    }

    public static function labelFor(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return self::tryFrom($value)?->getLabel() ?? $value;
    }
}
