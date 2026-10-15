<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum TicketPriority: string implements HasLabel
{
    use LabeledEnum;

    case Low = 'low';
    case Normal = 'normal';
    case High = 'high';
    case Urgent = 'urgent';

    public function getLabel(): string
    {
        return match ($this) {
            self::Low => 'Niedrig',
            self::Normal => 'Normal',
            self::High => 'Hoch',
            self::Urgent => 'Dringend',
        };
    }
}
