<?php

namespace App\Filament\Resources;

use App\Enums\RentChargeStatus;
use App\Filament\Resources\RentChargeResource\Pages;
use App\Filament\Support\Fields;
use App\Models\RentCharge;
use App\Services\RentService;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;

class RentChargeResource extends Resource
{
    protected static ?string $model = RentCharge::class;

    protected static ?string $navigationIcon = 'heroicon-o-banknotes';

    protected static ?string $navigationGroup = 'Finanzen';

    protected static ?string $navigationLabel = 'Mieteingänge';

    protected static ?string $modelLabel = 'Soll-Miete';

    protected static ?string $pluralModelLabel = 'Mieteingänge';

    protected static ?int $navigationSort = 1;

    public static function getNavigationBadge(): ?string
    {
        $count = RentCharge::overdue()->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('due_date', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('period')->label('Monat')->date('m/Y')->sortable(),
                Fields::propertyColumn(),
                Tables\Columns\TextColumn::make('lease.tenant.display_name')->label('Mieter'),
                Tables\Columns\TextColumn::make('due_date')->label('Fällig')->date()->sortable()
                    ->color(fn (RentCharge $r) => in_array($r->status, [RentChargeStatus::Open, RentChargeStatus::Partial]) && $r->due_date->lt(today()) ? 'danger' : null),
                Fields::moneyColumn('amount_due', 'Soll')
                    ->summarize(Tables\Columns\Summarizers\Sum::make()->money('EUR', locale: 'de_AT')->label('')),
                Fields::moneyColumn('amount_paid', 'Bezahlt')
                    ->summarize(Tables\Columns\Summarizers\Sum::make()->money('EUR', locale: 'de_AT')->label('')),
                Tables\Columns\TextColumn::make('status')->label('Status')->badge()
                    ->color(fn (RentChargeStatus $state) => match ($state) {
                        RentChargeStatus::Paid => 'success',
                        RentChargeStatus::Partial => 'warning',
                        RentChargeStatus::Open => 'danger',
                        RentChargeStatus::Waived => 'gray',
                    }),
                Tables\Columns\TextColumn::make('paid_at')->label('Bezahlt am')->date()->placeholder('–')->toggleable(),
                Tables\Columns\TextColumn::make('notes')->label('Notiz')->limit(30)->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')->label('Status')->multiple()->options(RentChargeStatus::class)
                    ->default([RentChargeStatus::Open->value, RentChargeStatus::Partial->value]),
                Fields::propertyFilter()->hidden(fn ($livewire) => $livewire instanceof RelationManager),
                Tables\Filters\Filter::make('due')->label('Nur bereits fällige')->default()
                    ->query(fn (Builder $query) => $query->whereDate('due_date', '<=', today()->endOfMonth())),
                Tables\Filters\Filter::make('period')->form([
                    Forms\Components\DatePicker::make('from')->label('Monat von'),
                    Forms\Components\DatePicker::make('until')->label('Monat bis'),
                ])->query(fn (Builder $query, array $data) => $query
                    ->when($data['from'] ?? null, fn ($q, $d) => $q->whereDate('period', '>=', Carbon::parse($d)->startOfMonth()))
                    ->when($data['until'] ?? null, fn ($q, $d) => $q->whereDate('period', '<=', $d))),
            ])
            ->actions([
                Tables\Actions\Action::make('paid')->label('Bezahlt')->icon('heroicon-o-check-circle')->color('success')
                    ->visible(fn (RentCharge $r) => $r->status !== RentChargeStatus::Paid)
                    ->action(function (RentCharge $r) {
                        app(RentService::class)->markPaid($r);
                        Notification::make()->success()->title('Als bezahlt markiert')->send();
                    }),
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\Action::make('payment')->label('Zahlung erfassen')->icon('heroicon-o-currency-euro')
                        ->fillForm(fn (RentCharge $r) => ['amount' => $r->amount_paid > 0 ? $r->amount_paid : $r->amount_due, 'paid_at' => $r->paid_at ?? today(), 'notes' => $r->notes])
                        ->form([
                            Fields::money('amount', 'Bezahlter Betrag (gesamt)')->required(),
                            Forms\Components\DatePicker::make('paid_at')->label('Eingang am')->required(),
                            Forms\Components\Textarea::make('notes')->label('Notiz')->rows(2),
                        ])
                        ->action(function (RentCharge $r, array $data) {
                            app(RentService::class)->markPaid($r, (float) $data['amount'], Carbon::parse($data['paid_at']));
                            $r->update(['notes' => $data['notes']]);
                        }),
                    Tables\Actions\Action::make('open')->label('Auf offen setzen')->icon('heroicon-o-arrow-uturn-left')
                        ->visible(fn (RentCharge $r) => $r->status !== RentChargeStatus::Open)
                        ->requiresConfirmation()
                        ->action(fn (RentCharge $r) => app(RentService::class)->markOpen($r)),
                    Tables\Actions\Action::make('reminder')->label('Mahnstufe erhöhen')->icon('heroicon-o-bell-alert')
                        ->visible(fn (RentCharge $r) => in_array($r->status, [RentChargeStatus::Open, RentChargeStatus::Partial]))
                        ->action(fn (RentCharge $r) => $r->increment('reminder_level')),
                    Tables\Actions\Action::make('waive')->label('Erlassen')->icon('heroicon-o-x-circle')->color('gray')
                        ->requiresConfirmation()
                        ->action(fn (RentCharge $r) => app(RentService::class)->waive($r)),
                    Tables\Actions\EditAction::make()->label('Betrag / Notiz')->form([
                        Fields::money('amount_due', 'Soll-Betrag')->required(),
                        Forms\Components\DatePicker::make('due_date')->label('Fällig am')->required(),
                        Forms\Components\Textarea::make('notes')->label('Notiz')->rows(2),
                    ]),
                ]),
            ])
            ->bulkActions([
                Tables\Actions\BulkAction::make('markPaid')->label('Als bezahlt markieren')->icon('heroicon-o-check-circle')->color('success')
                    ->form([Forms\Components\DatePicker::make('paid_at')->label('Eingang am')->default(today())->required()])
                    ->action(function (Collection $records, array $data) {
                        $service = app(RentService::class);
                        $records->each(fn (RentCharge $r) => $service->markPaid($r, (float) $r->amount_due, Carbon::parse($data['paid_at'])));
                        Notification::make()->success()->title($records->count().' Positionen als bezahlt markiert')->send();
                    })
                    ->deselectRecordsAfterCompletion(),
            ])
            ->recordClasses(fn (RentCharge $r) => $r->reminder_level > 0 ? 'border-l-4 border-danger-500' : null);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListRentCharges::route('/'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['property', 'lease.tenant']);
    }

    public static function canCreate(): bool
    {
        return false;
    }
}
