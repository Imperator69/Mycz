<?php

namespace App\Filament\Resources\InsurancePolicyResource\Pages;

use App\Filament\Resources\InsurancePolicyResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListInsurancePolicies extends ListRecords
{
    protected static string $resource = InsurancePolicyResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()->label('Neue Versicherung')];
    }
}
