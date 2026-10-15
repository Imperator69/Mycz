<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum PropertyType: string implements HasLabel
{
    use LabeledEnum;

    case Apartment = 'apartment';
    case House = 'house';
    case Garage = 'garage';
    case Land = 'land';
    case Commercial = 'commercial';
    case Other = 'other';

    public function getLabel(): string
    {
        return match ($this) {
            self::Apartment => 'Wohnung',
            self::House => 'Haus',
            self::Garage => 'Garage / Stellplatz',
            self::Land => 'Grundstück',
            self::Commercial => 'Gewerbe',
            self::Other => 'Sonstiges',
        };
    }
}
