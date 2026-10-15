<?php

namespace App\Filament\Resources\PropertyResource\RelationManagers;

use App\Filament\Resources\LoanResource;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class LoansRelationManager extends RelationManager
{
    protected static string $relationship = 'loans';

    protected static ?string $title = 'Kredite';

    protected static ?string $modelLabel = 'Kredit';

    public function form(Form $form): Form
    {
        return LoanResource::form($form);
    }

    public function table(Table $table): Table
    {
        return LoanResource::table($table)
            ->headerActions([Tables\Actions\CreateAction::make()]);
    }
}
