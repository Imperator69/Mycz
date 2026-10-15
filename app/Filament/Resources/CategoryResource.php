<?php

namespace App\Filament\Resources;

use App\Enums\TransactionType;
use App\Filament\Resources\CategoryResource\Pages;
use App\Models\Category;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class CategoryResource extends Resource
{
    protected static ?string $model = Category::class;

    protected static ?string $navigationIcon = 'heroicon-o-tag';

    protected static ?string $navigationGroup = 'System';

    protected static ?string $modelLabel = 'Kategorie';

    protected static ?string $pluralModelLabel = 'Kategorien';

    protected static ?int $navigationSort = 3;

    public static function form(Form $form): Form
    {
        return $form->columns(2)->schema([
            Forms\Components\TextInput::make('name')->label('Name')->required(),
            Forms\Components\Select::make('type')->label('Art')->options(TransactionType::class)->required(),
            Forms\Components\TextInput::make('group')->label('Gruppe')
                ->datalist(fn () => Category::query()->distinct()->pluck('group')->filter()->all()),
            Forms\Components\TextInput::make('slug')->label('Kürzel (für Claude-Auswertungen)')->unique(ignoreRecord: true)
                ->alphaDash()->helperText('Wird in status.json an Claude übergeben. Bestehende Kürzel nicht ändern.'),
            Forms\Components\Toggle::make('is_allocatable')->label('Standardmäßig umlagefähig'),
            Forms\Components\Toggle::make('affects_result')->label('Zählt ins Ergebnis')->default(true)
                ->helperText('Aus für Tilgung, Investitionen, Kautionen (nur Cashflow).'),
            Forms\Components\TextInput::make('sort')->label('Reihenfolge')->numeric()->default(100),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('sort')
            ->reorderable('sort')
            ->columns([
                Tables\Columns\TextColumn::make('name')->label('Name')->searchable(),
                Tables\Columns\TextColumn::make('type')->label('Art')->badge(),
                Tables\Columns\TextColumn::make('group')->label('Gruppe'),
                Tables\Columns\TextColumn::make('slug')->label('Kürzel')->fontFamily('mono')->color('gray'),
                Tables\Columns\IconColumn::make('is_allocatable')->label('Umlagefähig')->boolean(),
                Tables\Columns\IconColumn::make('affects_result')->label('Ergebnis')->boolean(),
                Tables\Columns\TextColumn::make('transactions_count')->label('Buchungen')->counts('transactions'),
            ])
            ->filters([Tables\Filters\SelectFilter::make('type')->label('Art')->options(TransactionType::class)])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make()->disabled(fn (Category $r) => $r->transactions()->exists()),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCategories::route('/'),
            'create' => Pages\CreateCategory::route('/create'),
            'edit' => Pages\EditCategory::route('/{record}/edit'),
        ];
    }
}
