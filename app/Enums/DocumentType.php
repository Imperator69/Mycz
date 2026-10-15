<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum DocumentType: string implements HasLabel
{
    use LabeledEnum;

    case AnnualStatement = 'annual_statement';
    case Invoice = 'invoice';
    case InsurancePolicy = 'insurance_policy';
    case LeaseContract = 'lease_contract';
    case PurchaseContract = 'purchase_contract';
    case LandRegister = 'land_register';
    case LoanContract = 'loan_contract';
    case Tax = 'tax';
    case Protocol = 'protocol';
    case Plan = 'plan';
    case Photo = 'photo';
    case Correspondence = 'correspondence';
    case Other = 'other';

    public function getLabel(): string
    {
        return match ($this) {
            self::AnnualStatement => 'Jahresabrechnung',
            self::Invoice => 'Rechnung / Beleg',
            self::InsurancePolicy => 'Versicherungspolizze',
            self::LeaseContract => 'Mietvertrag',
            self::PurchaseContract => 'Kaufvertrag',
            self::LandRegister => 'Grundbuchauszug',
            self::LoanContract => 'Kreditvertrag',
            self::Tax => 'Steuer / Abgaben',
            self::Protocol => 'Protokoll',
            self::Plan => 'Plan / Energieausweis',
            self::Photo => 'Foto',
            self::Correspondence => 'Korrespondenz',
            self::Other => 'Sonstiges',
        };
    }
}
