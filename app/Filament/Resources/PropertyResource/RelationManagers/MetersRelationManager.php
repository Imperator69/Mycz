<?php

namespace App\Filament\Resources\PropertyResource\RelationManagers;

use App\Filament\Resources\MeterResource;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class MetersRelationManager extends RelationManager
{
    protected static string $relationship = 'meters';

    protected static ?string $title = 'Zähler';

    protected static ?string $modelLabel = 'Zähler';

    public function form(Form $form): Form
    {
        return MeterResource::form($form);
    }

    public function table(Table $table): Table
    {
        return MeterResource::table($table)
            ->headerActions([Tables\Actions\CreateAction::make()]);
    }
}
