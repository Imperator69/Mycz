<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum DocumentStatus: string implements HasLabel
{
    use LabeledEnum;

    case Inbox = 'inbox';
    case Archived = 'archived';

    public function getLabel(): string
    {
        return match ($this) {
            self::Inbox => 'Eingang',
            self::Archived => 'Archiviert',
        };
    }
}
