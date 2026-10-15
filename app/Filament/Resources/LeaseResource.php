<?php

namespace App\Filament\Resources;

use App\Enums\DepositType;
use App\Enums\IndexType;
use App\Filament\Resources\LeaseResource\Pages;
use App\Filament\Resources\LeaseResource\RelationManagers;
use App\Filament\Support\Fields;
use App\Models\Lease;
use App\Models\Tenant;
use App\Services\DeadlineAggregator;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class LeaseResource extends Resource
{
    protected static ?string $model = Lease::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static ?string $navigationGroup = 'Bestand';

    protected static ?string $modelLabel = 'Mietvertrag';

    protected static ?string $pluralModelLabel = 'Mietverträge';

    protected static ?int $navigationSort = 3;

    public static function form(Form $form): Form
    {
        $gross = function (Get $get): string {
            $net = (float) $get('base_rent') + (float) $get('operating_costs') + (float) $get('heating_costs') + (float) $get('other_costs');

            return eur($net * (1 + (float) $get('vat_rate') / 100)).' brutto / Monat';
        };

        return $form->schema([
            Forms\Components\Section::make('Vertrag')->columns(3)->schema([
                Fields::property(),
                Forms\Components\Select::make('tenant_id')->label('Mieter')
                    ->relationship('tenant', 'last_name')
                    ->getOptionLabelFromRecordUsing(fn (Tenant $t) => $t->display_name)
                    ->searchable(['first_name', 'last_name', 'company'])->preload()->required()
                    ->createOptionForm(fn (Form $form) => TenantResource::form($form))
                    ->hidden(fn ($livewire) => $livewire instanceof RelationManager && $livewire->getOwnerRecord() instanceof Tenant),
                Forms\Components\DatePicker::make('start_date')->label('Mietbeginn')->required()->live(),
                Forms\Components\Toggle::make('is_fixed_term')->label('Befristet')->inline(false)->live(),
                Forms\Components\DatePicker::make('end_date')->label(fn (Get $get) => $get('is_fixed_term') ? 'Befristet bis' : 'Mietende (gekündigt zum)')
                    ->required(fn (Get $get) => (bool) $get('is_fixed_term'))->afterOrEqual('start_date'),
                Forms\Components\TextInput::make('notice_period_months')->label('Kündigungsfrist')->numeric()->suffix('Monate'),
            ]),
            Forms\Components\Section::make('Miete (netto, monatlich)')->columns(4)->schema([
                Fields::money('base_rent', 'Hauptmietzins')->required()->default(0)->live(onBlur: true),
                Fields::money('operating_costs', 'Betriebskosten-Akonto')->default(0)->live(onBlur: true),
                Fields::money('heating_costs', 'Heizkosten-Akonto')->default(0)->live(onBlur: true),
                Fields::money('other_costs', 'Sonstiges (z.B. Garage, Möbel)')->default(0)->live(onBlur: true),
                Fields::percent('vat_rate', 'USt-Satz')->default(10)->required()->live(onBlur: true)
                    ->helperText('Wohnen 10 %, Garage/Geschäft 20 %, unecht befreit 0 %'),
                Forms\Components\TextInput::make('due_day')->label('Fällig am')->numeric()->minValue(1)->maxValue(28)->default(5)->prefix('Tag')->required(),
                Forms\Components\DatePicker::make('charges_from')->label('Soll-Mieten erfassen ab')
                    ->default(fn () => today()->startOfMonth())->required()
                    ->helperText('Ab diesem Monat werden monatliche Soll-Positionen angelegt.'),
                Forms\Components\Placeholder::make('gross_preview')->label('Gesamt')->content($gross),
            ]),
            Forms\Components\Section::make('Kaution')->columns(3)->collapsible()->schema([
                Fields::money('deposit_amount', 'Kaution'),
                Forms\Components\Select::make('deposit_type')->label('Form')->options(DepositType::class),
                Forms\Components\DatePicker::make('deposit_returned_at')->label('Zurückgegeben am'),
            ]),
            Forms\Components\Section::make('Wertsicherung')->columns(4)->collapsible()->schema([
                Forms\Components\Select::make('index_type')->label('Index')->options(IndexType::class)->default(IndexType::None)->required()->live(),
                Forms\Components\TextInput::make('index_base_value')->label('Basiswert')->numeric(),
                Forms\Components\DatePicker::make('index_base_month')->label('Basismonat'),
                Fields::percent('index_threshold_percent', 'Schwellenwert'),
                Forms\Components\DatePicker::make('index_last_adjusted_at')->label('Letzte Anpassung'),
            ]),
            Forms\Components\Textarea::make('notes')->label('Notizen')->rows(3)->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('start_date', 'desc')
            ->columns([
                Fields::propertyColumn(),
                Tables\Columns\TextColumn::make('tenant.display_name')->label('Mieter')
                    ->searchable(['first_name', 'last_name', 'company'])
                    ->hidden(fn ($livewire) => $livewire instanceof RelationManager && $livewire->getOwnerRecord() instanceof Tenant),
                Tables\Columns\TextColumn::make('start_date')->label('Beginn')->date()->sortable(),
                Tables\Columns\TextColumn::make('end_date')->label('Ende')->date()->sortable()->placeholder('unbefristet')
                    ->description(fn (Lease $r) => $r->is_fixed_term ? 'befristet' : null),
                Tables\Columns\TextColumn::make('gross')->label('Miete brutto')->state(fn (Lease $r) => $r->monthlyGross())->money('EUR', locale: 'de_AT')->alignEnd(),
                Tables\Columns\TextColumn::make('index_check')->label('Wertsicherung')
                    ->state(fn (Lease $r) => $r->index_type && $r->index_type !== IndexType::None && $r->isActive() ? app(DeadlineAggregator::class)->nextIndexCheck($r)->format('d.m.Y') : null)
                    ->placeholder('–')->toggleable(),
                Tables\Columns\IconColumn::make('active')->label('Aktiv')->state(fn (Lease $r) => $r->isActive())->boolean(),
            ])
            ->filters([
                Fields::propertyFilter()->hidden(fn ($livewire) => $livewire instanceof RelationManager),
                Tables\Filters\TernaryFilter::make('active')->label('Status')->placeholder('Alle')
                    ->trueLabel('Aktiv')->falseLabel('Beendet')
                    ->queries(
                        true: fn (Builder $query) => $query->active(),
                        false: fn (Builder $query) => $query->whereDate('end_date', '<', today()),
                    )->default(true),
                Tables\Filters\TrashedFilter::make(),
            ])
            ->actions([
                Tables\Actions\EditAction::make()->url(fn (Lease $r) => static::getUrl('edit', ['record' => $r])),
            ])
            ->bulkActions([Tables\Actions\BulkActionGroup::make([Tables\Actions\DeleteBulkAction::make()])]);
    }

    public static function getRelations(): array
    {
        return [RelationManagers\RentChargesRelationManager::class, RelationManagers\DocumentsRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListLeases::route('/'),
            'create' => Pages\CreateLease::route('/create'),
            'edit' => Pages\EditLease::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['property', 'tenant'])->withoutGlobalScopes([SoftDeletingScope::class]);
    }
}
