<?php

namespace App\Filament\Resources\MaintenanceTaskResource\Pages;

use App\Filament\Resources\MaintenanceTaskResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListMaintenanceTasks extends ListRecords
{
    protected static string $resource = MaintenanceTaskResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()->label('Neue Wartung')];
    }
}
