<?php

namespace App\Filament\Resources\InsurancePolicyResource\RelationManagers;

use App\Enums\ClaimStatus;
use App\Filament\Support\Fields;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class ClaimsRelationManager extends RelationManager
{
    protected static string $relationship = 'claims';

    protected static ?string $title = 'Schadensfälle';

    protected static ?string $modelLabel = 'Schadensfall';

    public function form(Form $form): Form
    {
        return $form->columns(3)->schema([
            Forms\Components\DatePicker::make('incident_date')->label('Schadensdatum')->required(),
            Forms\Components\TextInput::make('claim_number')->label('Schadennummer'),
            Forms\Components\Select::make('status')->label('Status')->options(ClaimStatus::class)->default(ClaimStatus::Reported)->required(),
            Forms\Components\Textarea::make('description')->label('Beschreibung')->required()->columnSpanFull(),
            Fields::money('damage_amount', 'Schadenshöhe'),
            Fields::money('payout_amount', 'Auszahlung'),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('incident_date', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('incident_date')->label('Datum')->date(),
                Tables\Columns\TextColumn::make('description')->label('Beschreibung')->limit(50),
                Tables\Columns\TextColumn::make('status')->label('Status')->badge(),
                Fields::moneyColumn('damage_amount', 'Schaden'),
                Fields::moneyColumn('payout_amount', 'Auszahlung'),
            ])
            ->headerActions([Tables\Actions\CreateAction::make()])
            ->actions([Tables\Actions\EditAction::make(), Tables\Actions\DeleteAction::make()]);
    }
}
