<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ContactResource\Pages;
use App\Models\Contact;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ContactResource extends Resource
{
    protected static ?string $model = Contact::class;

    protected static ?string $navigationIcon = 'heroicon-o-identification';

    protected static ?string $navigationGroup = 'Verwaltung';

    protected static ?string $modelLabel = 'Kontakt';

    protected static ?string $pluralModelLabel = 'Dienstleister & Kontakte';

    protected static ?int $navigationSort = 3;

    public const ROLES = [
        'Hausverwaltung', 'Installateur', 'Elektriker', 'Rauchfangkehrer', 'Maler', 'Tischler', 'Dachdecker',
        'Reinigung', 'Winterdienst', 'Gärtner', 'Versicherungsmakler', 'Bank', 'Steuerberater', 'Notar / Anwalt',
        'Makler', 'Behörde', 'Sonstiges',
    ];

    public static function form(Form $form): Form
    {
        return $form->columns(3)->schema([
            Forms\Components\TextInput::make('name')->label('Name / Ansprechperson')->required(),
            Forms\Components\TextInput::make('company')->label('Firma'),
            Forms\Components\Select::make('role')->label('Rolle')->options(array_combine(self::ROLES, self::ROLES))->searchable(),
            Forms\Components\TextInput::make('email')->label('E-Mail')->email(),
            Forms\Components\TextInput::make('phone')->label('Telefon')->tel(),
            Forms\Components\TextInput::make('website')->label('Website')->url(),
            Forms\Components\TextInput::make('address')->label('Adresse')->columnSpan(2),
            Forms\Components\Select::make('properties')->label('Zuständig für Objekte')->relationship('properties', 'name')->multiple()->preload(),
            Forms\Components\Textarea::make('notes')->label('Notizen')->rows(3)->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('name')
            ->columns([
                Tables\Columns\TextColumn::make('name')->label('Name')->searchable()->sortable()->description(fn (Contact $r) => $r->company),
                Tables\Columns\TextColumn::make('role')->label('Rolle')->badge()->sortable(),
                Tables\Columns\TextColumn::make('phone')->label('Telefon')->copyable(),
                Tables\Columns\TextColumn::make('email')->label('E-Mail')->copyable(),
                Tables\Columns\TextColumn::make('properties.name')->label('Objekte')->badge()->limitList(3),
            ])
            ->filters([Tables\Filters\SelectFilter::make('role')->label('Rolle')->options(array_combine(self::ROLES, self::ROLES))])
            ->actions([Tables\Actions\EditAction::make()])
            ->bulkActions([Tables\Actions\BulkActionGroup::make([Tables\Actions\DeleteBulkAction::make()])]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListContacts::route('/'),
            'create' => Pages\CreateContact::route('/create'),
            'edit' => Pages\EditContact::route('/{record}/edit'),
        ];
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['name', 'company', 'role'];
    }
}
