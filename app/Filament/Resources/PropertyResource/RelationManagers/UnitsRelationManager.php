<?php

namespace App\Filament\Resources\PropertyResource\RelationManagers;

use App\Filament\Resources\PropertyResource;
use App\Models\Property;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class UnitsRelationManager extends RelationManager
{
    protected static string $relationship = 'units';

    protected static ?string $title = 'Einheiten';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return $ownerRecord->units()->exists() || in_array($ownerRecord->type?->value, ['house', 'commercial', 'land'], true);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->label('Einheit'),
                Tables\Columns\TextColumn::make('type')->label('Art')->badge(),
                Tables\Columns\TextColumn::make('status')->label('Status')->badge(),
                Tables\Columns\TextColumn::make('area_m2')->label('m²')->numeric(1, locale: 'de_AT'),
                Tables\Columns\TextColumn::make('tenant')->label('Mieter')->state(fn (Property $r) => $r->activeLease()?->tenant?->display_name)->placeholder('–'),
            ])
            ->headerActions([
                Tables\Actions\Action::make('new')->label('Neue Einheit')->icon('heroicon-o-plus')
                    ->url(fn () => PropertyResource::getUrl('create').'?parent='.$this->getOwnerRecord()->id),
            ])
            ->actions([
                Tables\Actions\Action::make('edit')->label('Öffnen')->icon('heroicon-o-pencil-square')
                    ->url(fn (Property $r) => PropertyResource::getUrl('edit', ['record' => $r])),
            ]);
    }
}
