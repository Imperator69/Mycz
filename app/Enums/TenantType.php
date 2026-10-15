<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum TenantType: string implements HasLabel
{
    use LabeledEnum;

    case Person = 'person';
    case Company = 'company';

    public function getLabel(): string
    {
        return match ($this) {
            self::Person => 'Privatperson',
            self::Company => 'Firma',
        };
    }
}
