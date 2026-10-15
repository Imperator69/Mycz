<?php

namespace App\Filament\Resources\MeterResource\RelationManagers;

use App\Models\MeterReading;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class ReadingsRelationManager extends RelationManager
{
    protected static string $relationship = 'readings';

    protected static ?string $title = 'Zählerstände';

    protected static ?string $modelLabel = 'Zählerstand';

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\DatePicker::make('read_at')->label('Datum')->default(today())->required(),
            Forms\Components\TextInput::make('value')->label('Stand')->numeric()->required(),
            Forms\Components\TextInput::make('notes')->label('Notiz'),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('read_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('read_at')->label('Datum')->date(),
                Tables\Columns\TextColumn::make('value')->label('Stand')->numeric(3, locale: 'de_AT'),
                Tables\Columns\TextColumn::make('consumption')->label('Verbrauch seit Vorablesung')
                    ->state(function (MeterReading $r) {
                        $prev = MeterReading::where('meter_id', $r->meter_id)->where('read_at', '<', $r->read_at)->orderByDesc('read_at')->first();

                        return $prev ? round((float) $r->value - (float) $prev->value, 3) : null;
                    })->numeric(3, locale: 'de_AT')->placeholder('–'),
                Tables\Columns\TextColumn::make('notes')->label('Notiz'),
            ])
            ->headerActions([Tables\Actions\CreateAction::make()])
            ->actions([Tables\Actions\EditAction::make(), Tables\Actions\DeleteAction::make()]);
    }
}
