<?php

namespace App\Filament\Resources;

use App\Enums\InterestType;
use App\Filament\Resources\LoanResource\Pages;
use App\Filament\Resources\LoanResource\RelationManagers;
use App\Filament\Support\Fields;
use App\Models\Loan;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class LoanResource extends Resource
{
    protected static ?string $model = Loan::class;

    protected static ?string $navigationIcon = 'heroicon-o-building-library';

    protected static ?string $navigationGroup = 'Finanzen';

    protected static ?string $modelLabel = 'Kredit';

    protected static ?string $pluralModelLabel = 'Kredite';

    protected static ?int $navigationSort = 4;

    public static function form(Form $form): Form
    {
        return $form->columns(3)->schema([
            Fields::property(required: false),
            Forms\Components\TextInput::make('bank')->label('Bank')->required(),
            Forms\Components\TextInput::make('contract_number')->label('Kontonummer / Vertrag'),
            Fields::money('principal', 'Kreditbetrag')->required(),
            Fields::percent('interest_rate', 'Zinssatz p.a.')->required()->step('0.001'),
            Forms\Components\Select::make('interest_type')->label('Zinsart')->options(InterestType::class)->default(InterestType::Fixed)->required(),
            Forms\Components\DatePicker::make('start_date')->label('Auszahlung / Beginn')->required(),
            Forms\Components\TextInput::make('term_months')->label('Laufzeit')->numeric()->suffix('Monate')->required()->helperText('z.B. 300 = 25 Jahre'),
            Forms\Components\DatePicker::make('fixed_until')->label('Zinsbindung bis'),
            Fields::money('monthly_payment', 'Monatsrate')->helperText('Leer = Annuität wird berechnet'),
            Forms\Components\Textarea::make('notes')->label('Notizen')->rows(3)->columnSpanFull(),
            Forms\Components\Section::make('Tilgungsplan (jährlich)')->collapsible()->columnSpanFull()
                ->visible(fn (?Loan $record) => $record !== null)
                ->schema([
                    Forms\Components\Placeholder::make('schedule')->hiddenLabel()
                        ->content(fn (?Loan $record) => $record ? view('filament.loan-schedule', ['rows' => $record->schedule()->yearly(), 'payment' => $record->schedule()->payment()]) : null),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Fields::propertyColumn(),
                Tables\Columns\TextColumn::make('bank')->label('Bank')->searchable()->description(fn (Loan $r) => $r->contract_number),
                Fields::moneyColumn('principal', 'Kreditbetrag'),
                Tables\Columns\TextColumn::make('interest_rate')->label('Zins')->suffix(' %')->numeric(3, locale: 'de_AT')
                    ->description(fn (Loan $r) => $r->interest_type?->getLabel()),
                Tables\Columns\TextColumn::make('rate')->label('Rate')->state(fn (Loan $r) => $r->schedule()->payment())->money('EUR', locale: 'de_AT')->alignEnd(),
                Tables\Columns\TextColumn::make('balance')->label('Restschuld heute')->state(fn (Loan $r) => $r->schedule()->balanceAt(today()))->money('EUR', locale: 'de_AT')->alignEnd(),
                Tables\Columns\TextColumn::make('fixed_until')->label('Zinsbindung bis')->date()->placeholder('–'),
                Tables\Columns\TextColumn::make('end')->label('Laufzeitende')->state(fn (Loan $r) => $r->schedule()->endDate())->date(),
            ])
            ->filters([Fields::propertyFilter()->hidden(fn ($livewire) => $livewire instanceof RelationManager)])
            ->actions([Tables\Actions\EditAction::make()->url(fn (Loan $r) => static::getUrl('edit', ['record' => $r]))])
            ->bulkActions([Tables\Actions\BulkActionGroup::make([Tables\Actions\DeleteBulkAction::make()])]);
    }

    public static function getRelations(): array
    {
        return [RelationManagers\DocumentsRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListLoans::route('/'),
            'create' => Pages\CreateLoan::route('/create'),
            'edit' => Pages\EditLoan::route('/{record}/edit'),
        ];
    }
}
