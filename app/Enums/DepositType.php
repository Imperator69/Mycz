<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum DepositType: string implements HasLabel
{
    use LabeledEnum;

    case Cash = 'cash';
    case SavingsBook = 'savings_book';
    case BankGuarantee = 'bank_guarantee';
    case DepositAccount = 'deposit_account';
    case None = 'none';

    public function getLabel(): string
    {
        return match ($this) {
            self::Cash => 'Bar / Überweisung',
            self::SavingsBook => 'Sparbuch',
            self::BankGuarantee => 'Bankgarantie',
            self::DepositAccount => 'Kautionskonto',
            self::None => 'Keine',
        };
    }
}
