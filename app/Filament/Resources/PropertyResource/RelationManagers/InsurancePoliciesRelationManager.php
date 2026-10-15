<?php

namespace App\Filament\Resources\PropertyResource\RelationManagers;

use App\Filament\Resources\InsurancePolicyResource;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class InsurancePoliciesRelationManager extends RelationManager
{
    protected static string $relationship = 'insurancePolicies';

    protected static ?string $title = 'Versicherungen';

    protected static ?string $modelLabel = 'Versicherung';

    public function form(Form $form): Form
    {
        return InsurancePolicyResource::form($form);
    }

    public function table(Table $table): Table
    {
        return InsurancePolicyResource::table($table)
            ->headerActions([Tables\Actions\CreateAction::make()]);
    }
}
