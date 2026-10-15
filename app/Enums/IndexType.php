<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum IndexType: string implements HasLabel
{
    use LabeledEnum;

    case None = 'none';
    case Vpi2020 = 'vpi2020';
    case Vpi2015 = 'vpi2015';
    case Vpi2010 = 'vpi2010';
    case Other = 'other';

    public function getLabel(): string
    {
        return match ($this) {
            self::None => 'Keine',
            self::Vpi2020 => 'VPI 2020',
            self::Vpi2015 => 'VPI 2015',
            self::Vpi2010 => 'VPI 2010',
            self::Other => 'Sonstiger Index',
        };
    }
}
