<?php

namespace App\Filament\Resources;

use App\Enums\ContractType;
use App\Enums\PaymentInterval;
use App\Filament\Resources\ContractResource\Pages;
use App\Filament\Resources\ContractResource\RelationManagers;
use App\Filament\Support\Fields;
use App\Models\Contract;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ContractResource extends Resource
{
    protected static ?string $model = Contract::class;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static ?string $navigationGroup = 'Verwaltung';

    protected static ?string $modelLabel = 'Vertrag';

    protected static ?string $pluralModelLabel = 'Verträge';

    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form->columns(3)->schema([
            Fields::property(required: false),
            Forms\Components\Select::make('type')->label('Art')->options(ContractType::class)->required(),
            Forms\Components\TextInput::make('provider')->label('Anbieter')->required(),
            Forms\Components\Select::make('contact_id')->label('Kontakt')->relationship('contact', 'name')->searchable()->preload()
                ->createOptionForm(fn (Form $form) => ContactResource::form($form)),
            Forms\Components\TextInput::make('contract_number')->label('Vertrags-/Kundennummer'),
            Forms\Components\Group::make([
                Fields::money('cost_amount', 'Kosten'),
                Forms\Components\Select::make('cost_interval')->label('pro')->options(PaymentInterval::class)->default(PaymentInterval::Monthly),
            ])->columns(2),
            Forms\Components\DatePicker::make('start_date')->label('Beginn'),
            Forms\Components\DatePicker::make('end_date')->label('Ablauf / Mindestlaufzeit bis'),
            Forms\Components\Group::make([
                Forms\Components\Toggle::make('auto_renews')->label('Verlängert sich automatisch')->default(true)->inline(false),
                Forms\Components\TextInput::make('notice_period_months')->label('Kündigungsfrist')->numeric()->suffix('Monate'),
            ])->columns(2),
            Forms\Components\Textarea::make('notes')->label('Notizen')->rows(3)->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('type')
            ->columns([
                Fields::propertyColumn(),
                Tables\Columns\TextColumn::make('type')->label('Art')->badge()->sortable(),
                Tables\Columns\TextColumn::make('provider')->label('Anbieter')->searchable()->sortable()->description(fn (Contract $r) => $r->contract_number),
                Tables\Columns\TextColumn::make('annual')->label('Kosten/Jahr')->state(fn (Contract $r) => $r->annualCost())->money('EUR', locale: 'de_AT')->alignEnd(),
                Tables\Columns\TextColumn::make('next_expiry')->label('Nächster Ablauf')->state(fn (Contract $r) => $r->nextExpiry())->date()->placeholder('–'),
                Tables\Columns\TextColumn::make('notice')->label('Kündigen bis')->state(fn (Contract $r) => $r->noticeDeadline())->date()->placeholder('–'),
            ])
            ->filters([
                Fields::propertyFilter()->hidden(fn ($livewire) => $livewire instanceof RelationManager),
                Tables\Filters\SelectFilter::make('type')->label('Art')->options(ContractType::class),
            ])
            ->actions([Tables\Actions\EditAction::make()])
            ->bulkActions([Tables\Actions\BulkActionGroup::make([Tables\Actions\DeleteBulkAction::make()])]);
    }

    public static function getRelations(): array
    {
        return [RelationManagers\DocumentsRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListContracts::route('/'),
            'create' => Pages\CreateContract::route('/create'),
            'edit' => Pages\EditContract::route('/{record}/edit'),
        ];
    }
}
