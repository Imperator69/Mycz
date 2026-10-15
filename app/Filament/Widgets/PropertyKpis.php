<?php

namespace App\Filament\Widgets;

use App\Models\Property;
use App\Services\ReportBuilder;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/** Kennzahlen im Kopf der Objekt-Seite. */
class PropertyKpis extends StatsOverviewWidget
{
    protected static bool $isDiscovered = false;

    public ?Property $record = null;

    protected function getStats(): array
    {
        if (! $this->record) {
            return [];
        }

        $year = (int) date('Y');
        $k = app(ReportBuilder::class)->propertyKpis($this->record, $year);
        $pct = fn (?float $v) => $v === null ? '–' : number_format($v, 2, ',', '.').' %';

        return [
            Stat::make("Ergebnis $year", eur($k['result']))->description('Einnahmen '.eur($k['income']).' · Ausgaben '.eur($k['expense']))
                ->color($k['result'] >= 0 ? 'success' : 'danger'),
            Stat::make('Miete Soll / Jahr', eur($k['annual_rent_target']))->description('Nettomiete: '.eur($k['annual_cold_rent'])),
            Stat::make('Rendite brutto / netto', $pct($k['gross_yield']).' / '.$pct($k['net_yield']))
                ->description($k['investment'] > 0 ? 'auf Investition '.eur($k['investment']) : 'Kaufpreis fehlt'),
            Stat::make('Offene Mieten', eur($k['open_rent']))->color($k['open_rent'] > 0 ? 'danger' : 'success')
                ->description('Restschuld: '.eur($k['loan_balance'])),
        ];
    }
}
