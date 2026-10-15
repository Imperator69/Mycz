<?php

namespace App\Filament\Resources;

use App\Enums\TransactionSource;
use App\Enums\TransactionType;
use App\Filament\Resources\TransactionResource\Pages;
use App\Filament\Support\Fields;
use App\Models\Category;
use App\Models\Document;
use App\Models\Transaction;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class TransactionResource extends Resource
{
    protected static ?string $model = Transaction::class;

    protected static ?string $navigationIcon = 'heroicon-o-arrows-right-left';

    protected static ?string $navigationGroup = 'Finanzen';

    protected static ?string $modelLabel = 'Buchung';

    protected static ?string $pluralModelLabel = 'Einnahmen & Ausgaben';

    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        $recalc = function (Get $get, Set $set) {
            $gross = (float) $get('amount_gross');
            $rate = (float) $get('vat_rate');
            $net = round($gross / (1 + $rate / 100), 2);
            $set('amount_net', $net);
            $set('vat_amount', round($gross - $net, 2));
        };

        return $form->columns(3)->schema([
            Forms\Components\ToggleButtons::make('type')->label('Art')->options(TransactionType::class)->inline()->required()
                ->default(TransactionType::Expense)->live()
                ->colors([TransactionType::Income->value => 'success', TransactionType::Expense->value => 'danger'])
                ->afterStateUpdated(fn (Set $set) => $set('category_id', null)),
            Forms\Components\Select::make('category_id')->label('Kategorie')->required()->searchable()->preload()->live()
                ->options(fn (Get $get) => Category::where('type', $get('type') instanceof TransactionType ? $get('type')->value : $get('type'))
                    ->orderBy('sort')->get()->groupBy('group')->map(fn ($g) => $g->pluck('name', 'id'))->toArray())
                ->afterStateUpdated(fn (Set $set, ?string $state) => $set('is_allocatable', (bool) Category::find($state)?->is_allocatable)),
            Fields::property(required: false)->helperText('Leer lassen für objektübergreifende Kosten'),
            Forms\Components\DatePicker::make('booked_at')->label('Datum')->default(today())->required(),
            Forms\Components\TextInput::make('description')->label('Beschreibung')->required()->columnSpan(2),
            Forms\Components\TextInput::make('counterparty')->label('Empfänger / Zahler')
                ->datalist(fn () => Transaction::query()->distinct()->limit(200)->pluck('counterparty')->filter()->all()),
            Fields::money('amount_gross', 'Betrag brutto')->required()->live(onBlur: true)->afterStateUpdated($recalc),
            Fields::percent('vat_rate', 'USt-Satz')->default(0)->required()->live(onBlur: true)->afterStateUpdated($recalc),
            Forms\Components\Group::make([
                Fields::money('amount_net', 'Netto')->readOnly(),
                Fields::money('vat_amount', 'USt')->readOnly(),
            ])->columns(2),
            Forms\Components\Toggle::make('is_allocatable')->label('Umlagefähig auf Mieter')->inline(false),
            Forms\Components\Select::make('document_id')->label('Beleg')
                ->relationship('document', 'title')->searchable()->preload()
                ->getOptionLabelFromRecordUsing(fn (Document $d) => $d->title.' ('.$d->year.')'),
            Forms\Components\Select::make('loan_id')->label('Kredit')->relationship('loan', 'bank')->visible(fn (Get $get) => in_array(
                Category::find($get('category_id'))?->slug, ['kreditzinsen', 'tilgung', 'bankspesen'], true)),
            Forms\Components\Textarea::make('notes')->label('Notizen')->rows(2)->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('booked_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('booked_at')->label('Datum')->date()->sortable(),
                Fields::propertyColumn(),
                Tables\Columns\TextColumn::make('category.name')->label('Kategorie')->sortable()->badge()
                    ->color(fn (Transaction $r) => $r->type === TransactionType::Income ? 'success' : 'gray'),
                Tables\Columns\TextColumn::make('description')->label('Beschreibung')->searchable()->limit(45)
                    ->description(fn (Transaction $r) => $r->counterparty),
                Tables\Columns\TextColumn::make('signed')->label('Betrag')->alignEnd()
                    ->state(fn (Transaction $r) => $r->type === TransactionType::Expense ? -1 * (float) $r->amount_gross : (float) $r->amount_gross)
                    ->money('EUR', locale: 'de_AT')
                    ->color(fn (Transaction $r) => $r->type === TransactionType::Expense ? 'danger' : 'success'),
                Fields::moneyColumn('amount_gross', 'Brutto')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->summarize(Tables\Columns\Summarizers\Sum::make()->money('EUR', locale: 'de_AT')->label('Summe brutto')),
                Fields::moneyColumn('vat_amount', 'USt')->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\IconColumn::make('is_allocatable')->label('Uml.')->boolean()->toggleable(),
                Tables\Columns\TextColumn::make('source')->label('Quelle')->badge()->color('gray')->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\IconColumn::make('document_id')->label('Beleg')->icon(fn ($state) => $state ? 'heroicon-o-paper-clip' : null)
                    ->url(fn (Transaction $r) => $r->document?->url(), true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('year')->label('Jahr')
                    ->options(fn () => collect(range((int) date('Y') + 1, (int) date('Y') - 10))->mapWithKeys(fn ($y) => [$y => $y])->all())
                    ->query(fn (Builder $query, array $data) => $data['value'] ? $query->inYear((int) $data['value']) : $query),
                Fields::propertyFilter()->hidden(fn ($livewire) => $livewire instanceof RelationManager),
                Tables\Filters\SelectFilter::make('type')->label('Art')->options(TransactionType::class),
                Tables\Filters\SelectFilter::make('category_id')->label('Kategorie')->relationship('category', 'name')->multiple()->preload(),
                Tables\Filters\SelectFilter::make('source')->label('Quelle')->options(TransactionSource::class),
                Tables\Filters\TernaryFilter::make('is_allocatable')->label('Umlagefähig'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\ReplicateAction::make()->label('Duplizieren')->excludeAttributes(['rent_charge_id', 'statement_id'])
                    ->beforeReplicaSaved(fn (Transaction $replica) => $replica->fill(['booked_at' => today(), 'source' => TransactionSource::Manual])),
            ])
            ->bulkActions([Tables\Actions\BulkActionGroup::make([Tables\Actions\DeleteBulkAction::make()])]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTransactions::route('/'),
            'create' => Pages\CreateTransaction::route('/create'),
            'edit' => Pages\EditTransaction::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['property', 'category', 'document']);
    }
}
