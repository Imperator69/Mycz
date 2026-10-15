<?php

namespace App\Filament\Resources\PropertyResource\RelationManagers;

use App\Filament\Resources\TicketResource;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class TicketsRelationManager extends RelationManager
{
    protected static string $relationship = 'tickets';

    protected static ?string $title = 'Schäden & Aufgaben';

    protected static ?string $modelLabel = 'Eintrag';

    public function form(Form $form): Form
    {
        return TicketResource::form($form);
    }

    public function table(Table $table): Table
    {
        return TicketResource::table($table)
            ->headerActions([Tables\Actions\CreateAction::make()]);
    }
}
