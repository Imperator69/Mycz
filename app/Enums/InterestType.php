<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum InterestType: string implements HasLabel
{
    use LabeledEnum;

    case Fixed = 'fixed';
    case Variable = 'variable';

    public function getLabel(): string
    {
        return match ($this) {
            self::Fixed => 'Fix',
            self::Variable => 'Variabel',
        };
    }
}
