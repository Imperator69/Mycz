<?php

namespace App\Filament\Resources;

use App\Enums\TenantType;
use App\Filament\Resources\TenantResource\Pages;
use App\Filament\Resources\TenantResource\RelationManagers;
use App\Models\Tenant;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class TenantResource extends Resource
{
    protected static ?string $model = Tenant::class;

    protected static ?string $navigationIcon = 'heroicon-o-users';

    protected static ?string $navigationGroup = 'Bestand';

    protected static ?string $modelLabel = 'Mieter';

    protected static ?string $pluralModelLabel = 'Mieter';

    protected static ?int $navigationSort = 2;

    protected static ?string $recordTitleAttribute = 'last_name';

    public static function form(Form $form): Form
    {
        return $form->columns(3)->schema([
            Forms\Components\Select::make('type')->label('Art')->options(TenantType::class)->default(TenantType::Person)->required()->live(),
            Forms\Components\TextInput::make('company')->label('Firma')->visible(fn (Get $get) => $get('type') === TenantType::Company->value || $get('type') === TenantType::Company)->columnSpan(2),
            Forms\Components\TextInput::make('first_name')->label('Vorname'),
            Forms\Components\TextInput::make('last_name')->label('Nachname')->required(fn (Get $get) => $get('type') !== TenantType::Company->value && $get('type') !== TenantType::Company),
            Forms\Components\DatePicker::make('birth_date')->label('Geburtsdatum'),
            Forms\Components\TextInput::make('email')->label('E-Mail')->email(),
            Forms\Components\TextInput::make('phone')->label('Telefon')->tel(),
            Forms\Components\TextInput::make('iban')->label('IBAN')->maxLength(40)
                ->dehydrateStateUsing(fn (?string $state) => $state ? strtoupper(preg_replace('/\s+/', '', $state)) : null),
            Forms\Components\TextInput::make('address')->label('Zustelladresse (falls abweichend)')->columnSpan(3),
            Forms\Components\Textarea::make('notes')->label('Notizen')->rows(3)->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('last_name')
            ->columns([
                Tables\Columns\TextColumn::make('display_name')->label('Name')
                    ->searchable(['first_name', 'last_name', 'company'])->sortable(['last_name'])->weight('medium'),
                Tables\Columns\TextColumn::make('email')->label('E-Mail')->searchable()->copyable(),
                Tables\Columns\TextColumn::make('phone')->label('Telefon')->searchable(),
                Tables\Columns\TextColumn::make('active_property')->label('Aktuelles Objekt')
                    ->state(fn (Tenant $r) => $r->leases()->active()->with('property')->get()->pluck('property.name')->join(', '))
                    ->placeholder('–'),
            ])
            ->filters([
                Tables\Filters\Filter::make('active')->label('Nur aktive Mieter')
                    ->query(fn (Builder $query) => $query->whereHas('leases', fn ($l) => $l->active())),
                Tables\Filters\TrashedFilter::make(),
            ])
            ->actions([Tables\Actions\EditAction::make()])
            ->bulkActions([Tables\Actions\BulkActionGroup::make([Tables\Actions\DeleteBulkAction::make()])]);
    }

    public static function getRelations(): array
    {
        return [RelationManagers\LeasesRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTenants::route('/'),
            'create' => Pages\CreateTenant::route('/create'),
            'edit' => Pages\EditTenant::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->withoutGlobalScopes([SoftDeletingScope::class]);
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['first_name', 'last_name', 'company', 'email'];
    }
}
