<?php

namespace App\Filament\Resources\TenantResource\RelationManagers;

use App\Filament\Resources\LeaseResource;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class LeasesRelationManager extends RelationManager
{
    protected static string $relationship = 'leases';

    protected static ?string $title = 'Mietverträge';

    protected static ?string $modelLabel = 'Mietvertrag';

    public function form(Form $form): Form
    {
        return LeaseResource::form($form);
    }

    public function table(Table $table): Table
    {
        return LeaseResource::table($table)->headerActions([Tables\Actions\CreateAction::make()]);
    }
}
