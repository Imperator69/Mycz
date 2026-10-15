<?php

namespace App\Filament\Resources;

use App\Enums\PropertyStatus;
use App\Enums\PropertyType;
use App\Filament\Resources\PropertyResource\Pages;
use App\Filament\Resources\PropertyResource\RelationManagers;
use App\Filament\Support\Fields;
use App\Models\Property;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class PropertyResource extends Resource
{
    protected static ?string $model = Property::class;

    protected static ?string $navigationIcon = 'heroicon-o-home-modern';

    protected static ?string $navigationGroup = 'Bestand';

    protected static ?string $modelLabel = 'Objekt';

    protected static ?string $pluralModelLabel = 'Objekte';

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Tabs::make()->columnSpanFull()->tabs([
                Forms\Components\Tabs\Tab::make('Stammdaten')->icon('heroicon-o-home')->columns(3)->schema([
                    Forms\Components\TextInput::make('name')->label('Bezeichnung')->required()->maxLength(255)
                        ->placeholder('z.B. Wohnung Wien 1, Top 5')->columnSpan(2),
                    Forms\Components\Select::make('type')->label('Art')->options(PropertyType::class)->required()->live(),
                    Forms\Components\Select::make('status')->label('Status')->options(PropertyStatus::class)->required()->default(PropertyStatus::Vacant),
                    Forms\Components\Select::make('parent_id')->label('Gehört zu (Haus / Anlage)')
                        ->relationship('parent', 'name', fn (Builder $query, ?Property $record) => $query->when($record, fn ($q) => $q->whereKeyNot($record->id)))
                        ->searchable()->preload()
                        ->helperText('Für Einheiten in einem eigenen Haus'),
                    Forms\Components\TextInput::make('unit_number')->label('Top / Einheit'),
                    Forms\Components\TextInput::make('street')->label('Straße und Hausnummer')->columnSpan(2),
                    Forms\Components\TextInput::make('zip')->label('PLZ'),
                    Forms\Components\TextInput::make('city')->label('Ort'),
                    Forms\Components\TextInput::make('country')->label('Land')->default('AT')->maxLength(2),
                    Forms\Components\TextInput::make('area_m2')->label('Nutzfläche')->numeric()->suffix('m²'),
                    Forms\Components\TextInput::make('land_area_m2')->label('Grundfläche')->numeric()->suffix('m²'),
                    Forms\Components\TextInput::make('construction_year')->label('Baujahr')->numeric()->minValue(1500)->maxValue(2100),
                ]),
                Forms\Components\Tabs\Tab::make('Grundbuch & Kauf')->icon('heroicon-o-scale')->columns(3)->schema([
                    Forms\Components\TextInput::make('cadastral_community')->label('Katastralgemeinde (KG)'),
                    Forms\Components\TextInput::make('land_register_number')->label('Einlagezahl (EZ)'),
                    Forms\Components\TextInput::make('plot_number')->label('Grundstücksnummer'),
                    Forms\Components\TextInput::make('ownership_share')->label('Anteil / Nutzwert')->placeholder('z.B. 85/1240'),
                    Forms\Components\DatePicker::make('purchase_date')->label('Kaufdatum'),
                    Fields::money('purchase_price', 'Kaufpreis'),
                    Fields::money('purchase_costs', 'Kaufnebenkosten')->helperText('GrESt, Eintragung, Notar, Makler'),
                    Fields::money('assessed_value', 'Einheitswert'),
                    Fields::money('market_value', 'Aktueller Marktwert (Schätzung)'),
                ]),
                Forms\Components\Tabs\Tab::make('Notizen')->icon('heroicon-o-pencil-square')->schema([
                    Forms\Components\Textarea::make('notes')->label('Notizen')->rows(6),
                    Forms\Components\TextInput::make('drive_folder_id')->label('Google-Drive-Ordner-ID')
                        ->helperText('Wird automatisch angelegt. Nur ändern, um einen bestehenden Ordner zu verwenden.'),
                ]),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('name')
            ->columns([
                Tables\Columns\TextColumn::make('name')->label('Objekt')->searchable()->sortable()->weight('medium')
                    ->description(fn (Property $r) => $r->parent ? 'in '.$r->parent->name : null),
                Tables\Columns\TextColumn::make('type')->label('Art')->badge()->sortable(),
                Tables\Columns\TextColumn::make('status')->label('Status')->badge()->sortable()
                    ->color(fn (PropertyStatus $state) => match ($state) {
                        PropertyStatus::Rented => 'success',
                        PropertyStatus::Vacant => 'danger',
                        PropertyStatus::Renovation => 'warning',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('city')->label('Ort')->sortable()->searchable()
                    ->description(fn (Property $r) => $r->street),
                Tables\Columns\TextColumn::make('area_m2')->label('m²')->numeric(1, locale: 'de_AT')->alignEnd()->sortable()->toggleable(),
                Tables\Columns\TextColumn::make('current_tenant')->label('Mieter')
                    ->state(fn (Property $r) => $r->activeLease()?->tenant?->display_name)->placeholder('–'),
                Tables\Columns\TextColumn::make('current_rent')->label('Miete/Monat (brutto)')
                    ->state(fn (Property $r) => $r->activeLease()?->monthlyGross())->money('EUR', locale: 'de_AT')->alignEnd()->placeholder('–'),
                Fields::moneyColumn('purchase_price', 'Kaufpreis')->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('type')->label('Art')->options(PropertyType::class),
                Tables\Filters\SelectFilter::make('status')->label('Status')->options(PropertyStatus::class),
                Tables\Filters\TrashedFilter::make(),
            ])
            ->actions([
                Tables\Actions\Action::make('drive')->label('Drive')->icon('heroicon-o-folder-open')->color('gray')
                    ->url(fn (Property $r) => $r->drive_folder_id ? 'https://drive.google.com/drive/folders/'.$r->drive_folder_id : null, true)
                    ->visible(fn (Property $r) => (bool) $r->drive_folder_id),
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    Tables\Actions\RestoreBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\UnitsRelationManager::class,
            RelationManagers\LeasesRelationManager::class,
            RelationManagers\TransactionsRelationManager::class,
            RelationManagers\DocumentsRelationManager::class,
            RelationManagers\InsurancePoliciesRelationManager::class,
            RelationManagers\LoansRelationManager::class,
            RelationManagers\ContractsRelationManager::class,
            RelationManagers\MaintenanceTasksRelationManager::class,
            RelationManagers\MetersRelationManager::class,
            RelationManagers\TicketsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListProperties::route('/'),
            'create' => Pages\CreateProperty::route('/create'),
            'edit' => Pages\EditProperty::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with('parent')->withoutGlobalScopes([SoftDeletingScope::class]);
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['name', 'street', 'city'];
    }
}
