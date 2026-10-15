<?php

namespace App\Filament\Support;

use App\Models\Property;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;

/** Wiederkehrende Formular- und Tabellenbausteine. */
class Fields
{
    public static function money(string $name, string $label): TextInput
    {
        return TextInput::make($name)->label($label)->numeric()->inputMode('decimal')->step('0.01')->suffix('€');
    }

    public static function percent(string $name, string $label): TextInput
    {
        return TextInput::make($name)->label($label)->numeric()->inputMode('decimal')->step('0.01')->suffix('%');
    }

    public static function property(string $name = 'property_id', bool $required = true): Select
    {
        return Select::make($name)
            ->label('Objekt')
            ->relationship('property', 'name')
            ->searchable()
            ->preload()
            ->required($required)
            ->hidden(fn ($livewire) => $livewire instanceof RelationManager);
    }

    /** Spalte/Filter ausblenden, wenn die Tabelle bereits unter einem Objekt haengt. */
    public static function inRelationManager($livewire): bool
    {
        return $livewire instanceof RelationManager;
    }

    public static function propertyColumn(): TextColumn
    {
        return TextColumn::make('property.name')->label('Objekt')->sortable()->searchable()->placeholder('Allgemein')
            ->hidden(fn ($livewire) => $livewire instanceof RelationManager);
    }

    public static function propertyFilter(): SelectFilter
    {
        return SelectFilter::make('property_id')->label('Objekt')->options(fn () => Property::orderBy('name')->pluck('name', 'id'))->searchable();
    }

    public static function moneyColumn(string $name, string $label): TextColumn
    {
        return TextColumn::make($name)->label($label)->money('EUR', locale: 'de_AT')->alignEnd()->sortable();
    }
}
