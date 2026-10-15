<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\RentChargeResource;
use App\Models\RentCharge;
use App\Services\RentService;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class OverdueRents extends TableWidget
{
    protected static ?string $heading = 'Überfällige Mieten';

    protected static ?int $sort = 4;

    protected int|string|array $columnSpan = 1;

    public function table(Table $table): Table
    {
        return $table
            ->query(RentCharge::overdue()->with(['property', 'lease.tenant'])->orderBy('due_date'))
            ->paginated([5, 10])
            ->defaultPaginationPageOption(5)
            ->emptyStateHeading('Keine überfälligen Mieten')
            ->emptyStateIcon('heroicon-o-check-circle')
            ->columns([
                Tables\Columns\TextColumn::make('period')->label('Monat')->date('m/Y'),
                Tables\Columns\TextColumn::make('property.name')->label('Objekt')
                    ->description(fn (RentCharge $r) => $r->lease?->tenant?->display_name),
                Tables\Columns\TextColumn::make('outstanding')->label('Offen')->state(fn (RentCharge $r) => $r->outstanding())->money('EUR', locale: 'de_AT'),
            ])
            ->actions([
                Tables\Actions\Action::make('paid')->label('Bezahlt')->icon('heroicon-o-check-circle')->color('success')
                    ->action(fn (RentCharge $r) => app(RentService::class)->markPaid($r)),
            ])
            ->headerActions([
                Tables\Actions\Action::make('all')->label('Alle Mieteingänge')->link()->url(RentChargeResource::getUrl('index')),
            ]);
    }
}
