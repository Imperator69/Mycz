<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum InsuranceLine: string implements HasLabel
{
    use LabeledEnum;

    case Building = 'building';
    case Household = 'household';
    case Liability = 'liability';
    case Legal = 'legal';
    case Storm = 'storm';
    case Water = 'water';
    case Glass = 'glass';
    case RentLoss = 'rent_loss';
    case Other = 'other';

    public function getLabel(): string
    {
        return match ($this) {
            self::Building => 'Gebäudeversicherung',
            self::Household => 'Haushaltsversicherung',
            self::Liability => 'Haftpflicht',
            self::Legal => 'Rechtsschutz',
            self::Storm => 'Sturm / Elementar',
            self::Water => 'Leitungswasser',
            self::Glass => 'Glasbruch',
            self::RentLoss => 'Mietausfall',
            self::Other => 'Sonstige',
        };
    }
}
