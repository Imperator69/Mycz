<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum TransactionSource: string implements HasLabel
{
    use LabeledEnum;

    case Manual = 'manual';
    case Rent = 'rent';
    case Statement = 'statement';
    case Loan = 'loan';

    public function getLabel(): string
    {
        return match ($this) {
            self::Manual => 'Manuell',
            self::Rent => 'Miete',
            self::Statement => 'Jahresabrechnung',
            self::Loan => 'Kredit',
        };
    }
}
