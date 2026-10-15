<?php

namespace App\Filament\Resources\PropertyResource\RelationManagers;

use App\Filament\Resources\ContractResource;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class ContractsRelationManager extends RelationManager
{
    protected static string $relationship = 'contracts';

    protected static ?string $title = 'Verträge';

    protected static ?string $modelLabel = 'Vertrag';

    public function form(Form $form): Form
    {
        return ContractResource::form($form);
    }

    public function table(Table $table): Table
    {
        return ContractResource::table($table)
            ->headerActions([Tables\Actions\CreateAction::make()]);
    }
}
