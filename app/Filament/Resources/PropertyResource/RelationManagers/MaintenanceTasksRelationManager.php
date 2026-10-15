<?php

namespace App\Filament\Resources\PropertyResource\RelationManagers;

use App\Filament\Resources\MaintenanceTaskResource;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class MaintenanceTasksRelationManager extends RelationManager
{
    protected static string $relationship = 'maintenanceTasks';

    protected static ?string $title = 'Wartung';

    protected static ?string $modelLabel = 'Wartung';

    public function form(Form $form): Form
    {
        return MaintenanceTaskResource::form($form);
    }

    public function table(Table $table): Table
    {
        return MaintenanceTaskResource::table($table)
            ->headerActions([Tables\Actions\CreateAction::make()]);
    }
}
