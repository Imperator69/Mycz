<?php

namespace App\Filament\Resources\LeaseResource\RelationManagers;

use App\Filament\Resources\RentChargeResource;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Table;

class RentChargesRelationManager extends RelationManager
{
    protected static string $relationship = 'rentCharges';

    protected static ?string $title = 'Soll-Mieten & Zahlungen';

    public function isReadOnly(): bool
    {
        return false;
    }

    public function table(Table $table): Table
    {
        return RentChargeResource::table($table)->defaultSort('period', 'desc');
    }
}
