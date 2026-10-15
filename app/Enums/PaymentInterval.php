<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum PaymentInterval: string implements HasLabel
{
    use LabeledEnum;

    case Monthly = 'monthly';
    case Quarterly = 'quarterly';
    case Semiannual = 'semiannual';
    case Annual = 'annual';
    case Once = 'once';

    public function getLabel(): string
    {
        return match ($this) {
            self::Monthly => 'Monatlich',
            self::Quarterly => 'Vierteljährlich',
            self::Semiannual => 'Halbjährlich',
            self::Annual => 'Jährlich',
            self::Once => 'Einmalig',
        };
    }
}
