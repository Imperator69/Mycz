<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum PropertyStatus: string implements HasLabel
{
    use LabeledEnum;

    case Rented = 'rented';
    case Vacant = 'vacant';
    case OwnerOccupied = 'owner_occupied';
    case Renovation = 'renovation';
    case Sold = 'sold';

    public function getLabel(): string
    {
        return match ($this) {
            self::Rented => 'Vermietet',
            self::Vacant => 'Leerstand',
            self::OwnerOccupied => 'Eigennutzung',
            self::Renovation => 'In Sanierung',
            self::Sold => 'Verkauft',
        };
    }
}
