<?php

namespace App\Filament\Resources;

use App\Enums\InsuranceLine;
use App\Enums\PaymentInterval;
use App\Filament\Resources\InsurancePolicyResource\Pages;
use App\Filament\Resources\InsurancePolicyResource\RelationManagers;
use App\Filament\Support\Fields;
use App\Models\InsurancePolicy;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class InsurancePolicyResource extends Resource
{
    protected static ?string $model = InsurancePolicy::class;

    protected static ?string $navigationIcon = 'heroicon-o-shield-check';

    protected static ?string $navigationGroup = 'Verwaltung';

    protected static ?string $modelLabel = 'Versicherung';

    protected static ?string $pluralModelLabel = 'Versicherungen';

    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Polizze')->columns(3)->schema([
                Fields::property(required: false)->helperText('Leer für objektübergreifende Polizzen'),
                Forms\Components\Select::make('line')->label('Sparte')->options(InsuranceLine::class)->required(),
                Forms\Components\TextInput::make('insurer')->label('Versicherer')->required()
                    ->datalist(fn () => InsurancePolicy::query()->distinct()->pluck('insurer')->all()),
                Forms\Components\TextInput::make('policy_number')->label('Polizzennummer'),
                Forms\Components\TextInput::make('agent')->label('Betreuer / Makler'),
            ]),
            Forms\Components\Section::make('Deckung & Prämie')->columns(4)->schema([
                Fields::money('coverage_amount', 'Versicherungssumme'),
                Fields::money('deductible', 'Selbstbehalt'),
                Fields::money('premium', 'Prämie'),
                Forms\Components\Select::make('payment_interval')->label('Zahlweise')->options(PaymentInterval::class)->default(PaymentInterval::Annual)->required(),
            ]),
            Forms\Components\Section::make('Laufzeit')->columns(4)->schema([
                Forms\Components\DatePicker::make('start_date')->label('Beginn'),
                Forms\Components\DatePicker::make('end_date')->label('Ablauf / Hauptfälligkeit')->helperText('Bei automatischer Verlängerung das ursprüngliche Ablaufdatum'),
                Forms\Components\Toggle::make('auto_renews')->label('Verlängert sich automatisch')->default(true)->inline(false),
                Forms\Components\TextInput::make('notice_period_months')->label('Kündigungsfrist')->numeric()->suffix('Monate')->default(3),
            ]),
            Forms\Components\Textarea::make('notes')->label('Notizen / Deckungsdetails')->rows(3)->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('end_date')
            ->columns([
                Fields::propertyColumn(),
                Tables\Columns\TextColumn::make('line')->label('Sparte')->badge()->sortable(),
                Tables\Columns\TextColumn::make('insurer')->label('Versicherer')->searchable()->sortable()
                    ->description(fn (InsurancePolicy $r) => $r->policy_number),
                Tables\Columns\TextColumn::make('annual')->label('Prämie/Jahr')->state(fn (InsurancePolicy $r) => $r->annualPremium())->money('EUR', locale: 'de_AT')->alignEnd(),
                Fields::moneyColumn('coverage_amount', 'Summe')->toggleable(),
                Tables\Columns\TextColumn::make('next_expiry')->label('Nächster Ablauf')
                    ->state(fn (InsurancePolicy $r) => $r->nextExpiry())->date()->placeholder('–'),
                Tables\Columns\TextColumn::make('notice')->label('Kündigen bis')
                    ->state(fn (InsurancePolicy $r) => $r->noticeDeadline())->date()->placeholder('–')
                    ->color(fn (InsurancePolicy $r) => $r->noticeDeadline()?->lt(today()->addDays(60)) ? 'warning' : null),
                Tables\Columns\TextColumn::make('claims_count')->label('Schäden')->counts('claims')->toggleable(),
            ])
            ->filters([
                Fields::propertyFilter()->hidden(fn ($livewire) => $livewire instanceof RelationManager),
                Tables\Filters\SelectFilter::make('line')->label('Sparte')->options(InsuranceLine::class),
            ])
            ->actions([Tables\Actions\EditAction::make()->url(fn (InsurancePolicy $r) => static::getUrl('edit', ['record' => $r]))])
            ->bulkActions([Tables\Actions\BulkActionGroup::make([Tables\Actions\DeleteBulkAction::make()])]);
    }

    public static function getRelations(): array
    {
        return [RelationManagers\ClaimsRelationManager::class, RelationManagers\DocumentsRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListInsurancePolicies::route('/'),
            'create' => Pages\CreateInsurancePolicy::route('/create'),
            'edit' => Pages\EditInsurancePolicy::route('/{record}/edit'),
        ];
    }
}
