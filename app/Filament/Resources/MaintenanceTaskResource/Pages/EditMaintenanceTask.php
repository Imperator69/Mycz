<?php

namespace App\Filament\Resources\MaintenanceTaskResource\Pages;

use App\Filament\Resources\MaintenanceTaskResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditMaintenanceTask extends EditRecord
{
    protected static string $resource = MaintenanceTaskResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\DeleteAction::make()];
    }
}
