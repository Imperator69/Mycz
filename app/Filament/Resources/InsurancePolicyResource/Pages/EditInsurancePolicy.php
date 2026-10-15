<?php

namespace App\Filament\Resources\InsurancePolicyResource\Pages;

use App\Filament\Resources\InsurancePolicyResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditInsurancePolicy extends EditRecord
{
    protected static string $resource = InsurancePolicyResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\DeleteAction::make()];
    }
}
