<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum ContractType: string implements HasLabel
{
    use LabeledEnum;

    case PropertyManagement = 'property_management';
    case Electricity = 'electricity';
    case Gas = 'gas';
    case Heating = 'heating';
    case Water = 'water';
    case Internet = 'internet';
    case Maintenance = 'maintenance';
    case Cleaning = 'cleaning';
    case WinterService = 'winter_service';
    case Garden = 'garden';
    case Other = 'other';

    public function getLabel(): string
    {
        return match ($this) {
            self::PropertyManagement => 'Hausverwaltung',
            self::Electricity => 'Strom',
            self::Gas => 'Gas',
            self::Heating => 'Fernwärme / Heizung',
            self::Water => 'Wasser',
            self::Internet => 'Internet / TV',
            self::Maintenance => 'Wartungsvertrag',
            self::Cleaning => 'Reinigung',
            self::WinterService => 'Winterdienst',
            self::Garden => 'Garten',
            self::Other => 'Sonstiges',
        };
    }
}
