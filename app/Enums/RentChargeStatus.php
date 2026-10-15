<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum RentChargeStatus: string implements HasLabel
{
    use LabeledEnum;

    case Open = 'open';
    case Partial = 'partial';
    case Paid = 'paid';
    case Waived = 'waived';

    public function getLabel(): string
    {
        return match ($this) {
            self::Open => 'Offen',
            self::Partial => 'Teilweise bezahlt',
            self::Paid => 'Bezahlt',
            self::Waived => 'Erlassen',
        };
    }
}
