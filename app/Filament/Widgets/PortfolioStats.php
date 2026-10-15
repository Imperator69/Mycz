<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\RentChargeResource;
use App\Services\ReportBuilder;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class PortfolioStats extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected int|string|array $columnSpan = 'full';

    protected function getStats(): array
    {
        $s = app(ReportBuilder::class)->dashboardStats();
        $year = date('Y');

        return [
            Stat::make("Einnahmen $year", eur($s['income_ytd']))->description('Soll/Monat: '.eur($s['monthly_target']))->color('success')->icon('heroicon-o-arrow-trending-up'),
            Stat::make("Ausgaben $year", eur($s['expense_ytd']))->icon('heroicon-o-arrow-trending-down'),
            Stat::make("Ergebnis $year", eur($s['result_ytd']))->description('Cashflow: '.eur($s['cashflow_ytd']))
                ->color($s['result_ytd'] >= 0 ? 'success' : 'danger')->icon('heroicon-o-scale'),
            Stat::make('Offene Mieten', $s['overdue_count'] > 0 ? eur($s['overdue_sum']) : 'Keine')
                ->description($s['overdue_count'] > 0 ? $s['overdue_count'].' überfällige Position(en)' : 'alles bezahlt')
                ->color($s['overdue_count'] > 0 ? 'danger' : 'success')->icon('heroicon-o-banknotes')
                ->url(RentChargeResource::getUrl('index')),
            Stat::make('Objekte', $s['properties'])->description($s['vacant'].' von '.$s['rentable'].' Einheiten leer')
                ->color($s['vacant'] > 0 ? 'warning' : 'gray')->icon('heroicon-o-home-modern'),
            Stat::make('Restschuld Kredite', eur($s['loan_balance']))->icon('heroicon-o-building-library'),
        ];
    }
}
