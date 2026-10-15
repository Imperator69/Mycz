<?php

namespace App\Filament\Widgets;

use App\Services\ReportBuilder;
use Filament\Widgets\ChartWidget;

class IncomeExpenseChart extends ChartWidget
{
    protected static ?string $heading = 'Einnahmen und Ausgaben je Monat';

    protected static ?int $sort = 2;

    protected static ?string $maxHeight = '280px';

    public ?string $filter = null;

    /** Wird von der Auswertungsseite gesetzt. */
    public ?array $propertyIds = null;

    protected function getFilters(): ?array
    {
        $years = range((int) date('Y'), (int) date('Y') - 5);

        return array_combine($years, $years);
    }

    protected function getData(): array
    {
        $year = (int) ($this->filter ?? date('Y'));
        $months = app(ReportBuilder::class)->monthly($year, $this->propertyIds);

        return [
            'datasets' => [
                ['label' => 'Einnahmen', 'data' => array_column($months, 'income'), 'backgroundColor' => '#10b981', 'borderColor' => '#10b981'],
                ['label' => 'Ausgaben', 'data' => array_column($months, 'expense'), 'backgroundColor' => '#f43f5e', 'borderColor' => '#f43f5e'],
            ],
            'labels' => ['Jän', 'Feb', 'Mär', 'Apr', 'Mai', 'Jun', 'Jul', 'Aug', 'Sep', 'Okt', 'Nov', 'Dez'],
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
