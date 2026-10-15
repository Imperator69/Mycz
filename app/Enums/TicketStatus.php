<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum TicketStatus: string implements HasLabel
{
    use LabeledEnum;

    case Open = 'open';
    case InProgress = 'in_progress';
    case Waiting = 'waiting';
    case Done = 'done';

    public function getLabel(): string
    {
        return match ($this) {
            self::Open => 'Offen',
            self::InProgress => 'In Arbeit',
            self::Waiting => 'Wartet',
            self::Done => 'Erledigt',
        };
    }
}
