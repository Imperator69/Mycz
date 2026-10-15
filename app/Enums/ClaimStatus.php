<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum ClaimStatus: string implements HasLabel
{
    use LabeledEnum;

    case Reported = 'reported';
    case InProgress = 'in_progress';
    case Paid = 'paid';
    case Rejected = 'rejected';

    public function getLabel(): string
    {
        return match ($this) {
            self::Reported => 'Gemeldet',
            self::InProgress => 'In Bearbeitung',
            self::Paid => 'Ausbezahlt',
            self::Rejected => 'Abgelehnt',
        };
    }
}
