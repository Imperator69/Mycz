<?php

namespace App\Filament\Resources;

use App\Enums\MeterType;
use App\Filament\Resources\MeterResource\Pages;
use App\Filament\Resources\MeterResource\RelationManagers;
use App\Filament\Support\Fields;
use App\Models\Meter;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class MeterResource extends Resource
{
    protected static ?string $model = Meter::class;

    protected static ?string $navigationIcon = 'heroicon-o-bolt';

    protected static ?string $navigationGroup = 'Verwaltung';

    protected static ?string $modelLabel = 'Zähler';

    protected static ?string $pluralModelLabel = 'Zähler';

    protected static ?int $navigationSort = 5;

    public static function form(Form $form): Form
    {
        return $form->columns(3)->schema([
            Fields::property(),
            Forms\Components\Select::make('type')->label('Art')->options(MeterType::class)->required(),
            Forms\Components\TextInput::make('number')->label('Zählernummer'),
            Forms\Components\TextInput::make('unit')->label('Einheit')->default('kWh')->datalist(['kWh', 'm³', 'MWh']),
            Forms\Components\TextInput::make('location')->label('Standort')->columnSpan(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Fields::propertyColumn(),
                Tables\Columns\TextColumn::make('type')->label('Art')->badge(),
                Tables\Columns\TextColumn::make('number')->label('Nummer')->searchable(),
                Tables\Columns\TextColumn::make('last')->label('Letzter Stand')
                    ->state(fn (Meter $r) => ($l = $r->readings()->first()) ? number_format((float) $l->value, 1, ',', '.').' '.$r->unit.' ('.$l->read_at->format('d.m.Y').')' : null)
                    ->placeholder('–'),
            ])
            ->filters([Fields::propertyFilter()->hidden(fn ($livewire) => $livewire instanceof RelationManager)])
            ->actions([
                Tables\Actions\Action::make('read')->label('Stand erfassen')->icon('heroicon-o-pencil')
                    ->form([
                        Forms\Components\DatePicker::make('read_at')->label('Datum')->default(today())->required(),
                        Forms\Components\TextInput::make('value')->label('Zählerstand')->numeric()->required(),
                        Forms\Components\TextInput::make('notes')->label('Notiz'),
                    ])
                    ->action(fn (Meter $r, array $data) => $r->readings()->create($data)),
                Tables\Actions\EditAction::make()->url(fn (Meter $r) => static::getUrl('edit', ['record' => $r])),
            ]);
    }

    public static function getRelations(): array
    {
        return [RelationManagers\ReadingsRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListMeters::route('/'),
            'create' => Pages\CreateMeter::route('/create'),
            'edit' => Pages\EditMeter::route('/{record}/edit'),
        ];
    }
}
