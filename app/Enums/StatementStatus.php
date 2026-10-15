<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum StatementStatus: string implements HasLabel
{
    use LabeledEnum;

    case PendingReview = 'pending_review';
    case Booked = 'booked';
    case Rejected = 'rejected';

    public function getLabel(): string
    {
        return match ($this) {
            self::PendingReview => 'Zu prüfen',
            self::Booked => 'Gebucht',
            self::Rejected => 'Verworfen',
        };
    }
}
