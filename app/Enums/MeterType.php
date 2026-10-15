<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum MeterType: string implements HasLabel
{
    use LabeledEnum;

    case Electricity = 'electricity';
    case Gas = 'gas';
    case Water = 'water';
    case HotWater = 'hot_water';
    case Heat = 'heat';
    case Other = 'other';

    public function getLabel(): string
    {
        return match ($this) {
            self::Electricity => 'Strom',
            self::Gas => 'Gas',
            self::Water => 'Kaltwasser',
            self::HotWater => 'Warmwasser',
            self::Heat => 'Heizung / Wärme',
            self::Other => 'Sonstiges',
        };
    }
}
