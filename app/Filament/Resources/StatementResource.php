<?php

namespace App\Filament\Resources;

use App\Enums\StatementStatus;
use App\Enums\TransactionType;
use App\Filament\Resources\StatementResource\Pages;
use App\Filament\Support\Fields;
use App\Models\Category;
use App\Models\Statement;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\HtmlString;

class StatementResource extends Resource
{
    protected static ?string $model = Statement::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-chart-bar';

    protected static ?string $navigationGroup = 'Finanzen';

    protected static ?string $modelLabel = 'Jahresabrechnung';

    protected static ?string $pluralModelLabel = 'Jahresabrechnungen';

    protected static ?int $navigationSort = 3;

    public static function getNavigationBadge(): ?string
    {
        $count = Statement::where('status', StatementStatus::PendingReview)->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function form(Form $form): Form
    {
        $locked = fn (?Statement $record) => $record?->status === StatementStatus::Booked;

        return $form->schema([
            Forms\Components\Section::make('Auswertung durch Claude')
                ->visible(fn (?Statement $record) => filled($record?->summary) || filled($record?->warnings))
                ->schema([
                    Forms\Components\Placeholder::make('summary')->label('Zusammenfassung')
                        ->content(fn (?Statement $record) => new HtmlString(nl2br(e($record?->summary ?? '–')))),
                    Forms\Components\Placeholder::make('warnings_view')->label('Hinweise')
                        ->visible(fn (?Statement $record) => filled($record?->warnings))
                        ->content(fn (?Statement $record) => new HtmlString('<ul class="mycz-list mycz-warn">'.collect($record?->warnings)->map(fn ($w) => '<li>'.e($w).'</li>')->join('').'</ul>')),
                ]),
            Forms\Components\Section::make('Abrechnung')->columns(4)->disabled($locked)->schema([
                Fields::property()->columnSpan(2),
                Forms\Components\TextInput::make('issuer')->label('Aussteller (z.B. Hausverwaltung)'),
                Forms\Components\TextInput::make('statement_type')->label('Art')->placeholder('WEG-Jahresabrechnung'),
                Forms\Components\DatePicker::make('period_from')->label('Zeitraum von'),
                Forms\Components\DatePicker::make('period_to')->label('Zeitraum bis')->helperText('Buchungsdatum'),
                Forms\Components\TextInput::make('year')->label('Jahr')->numeric(),
                Forms\Components\Placeholder::make('pdf')->label('Original')
                    ->content(fn (?Statement $record) => $record?->document?->url()
                        ? new HtmlString('<a class="text-primary-600 underline" target="_blank" href="'.e($record->document->url()).'">PDF öffnen</a>')
                        : '–'),
                Fields::money('total_costs', 'Gesamtkosten'),
                Fields::money('prepayments', 'Geleistete Vorauszahlungen'),
                Fields::money('balance', 'Saldo')->helperText('+ Nachzahlung / - Guthaben'),
                Fields::money('reserve_contribution', 'Rücklage-Beitrag'),
                Fields::money('reserve_balance', 'Rücklage-Stand'),
            ]),
            Forms\Components\Section::make('Positionen')->disabled($locked)->schema([
                Forms\Components\Repeater::make('lines')->relationship()->hiddenLabel()
                    ->orderColumn('sort')
                    ->columns(12)
                    ->addActionLabel('Position hinzufügen')
                    ->schema([
                        Forms\Components\TextInput::make('label')->label('Bezeichnung')->required()->columnSpan(5),
                        Fields::money('amount', 'Betrag')->required()->columnSpan(2)->live(onBlur: true),
                        Forms\Components\Select::make('category_id')->label('Kategorie')->columnSpan(3)
                            ->options(fn () => Category::orderBy('type')->orderBy('sort')->get()
                                ->groupBy(fn ($c) => $c->type === TransactionType::Income ? 'Einnahmen' : 'Ausgaben')
                                ->map(fn ($g) => $g->pluck('name', 'id'))->toArray())
                            ->searchable()
                            ->placeholder('Betriebskosten (Standard)'),
                        Forms\Components\Toggle::make('is_allocatable')->label('Umlagef.')->inline(false)->columnSpan(2),
                    ]),
                Forms\Components\Placeholder::make('lines_total')->label('Summe Positionen')
                    ->content(function (Get $get) {
                        $sum = collect($get('lines'))->sum(fn ($l) => (float) ($l['amount'] ?? 0));
                        $total = (float) $get('total_costs');
                        $diff = round($sum - $total, 2);

                        return new HtmlString(e(eur($sum)).($get('total_costs') !== null && abs($diff) > 0.05
                            ? ' <span class="mycz-neg">(Abweichung zum Gesamtbetrag: '.e(eur($diff)).')</span>'
                            : ' <span class="mycz-pos">✓</span>'));
                    }),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('status')->label('Status')->badge()
                    ->color(fn (StatementStatus $state) => match ($state) {
                        StatementStatus::PendingReview => 'warning', StatementStatus::Booked => 'success', StatementStatus::Rejected => 'gray',
                    }),
                Fields::propertyColumn(),
                Tables\Columns\TextColumn::make('issuer')->label('Aussteller')->searchable()->description(fn (Statement $r) => $r->statement_type),
                Tables\Columns\TextColumn::make('year')->label('Jahr')->sortable(),
                Fields::moneyColumn('total_costs', 'Gesamtkosten'),
                Fields::moneyColumn('balance', 'Saldo'),
                Tables\Columns\IconColumn::make('has_warnings')->label('Hinweise')->state(fn (Statement $r) => filled($r->warnings))
                    ->boolean()->trueIcon('heroicon-o-exclamation-triangle')->falseIcon('heroicon-o-check')->trueColor('warning')->falseColor('success'),
                Tables\Columns\TextColumn::make('created_at')->label('Importiert')->date()->toggleable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')->label('Status')->options(StatementStatus::class),
                Fields::propertyFilter(),
            ])
            ->actions([Tables\Actions\EditAction::make()->label('Prüfen')]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListStatements::route('/'),
            'edit' => Pages\EditStatement::route('/{record}/edit'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }
}
