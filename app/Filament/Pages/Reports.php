<?php

namespace App\Filament\Pages;

use App\Models\Property;
use App\Services\ReportBuilder;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Pages\Page;
use Livewire\Attributes\Url;

class Reports extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-chart-bar';

    protected static ?string $navigationGroup = 'Übersicht';

    protected static ?string $title = 'Auswertungen';

    protected static ?int $navigationSort = 2;

    protected static string $view = 'filament.pages.reports';

    #[Url]
    public ?int $year = null;

    #[Url]
    public ?int $property = null;

    public function mount(): void
    {
        $this->year ??= (int) date('Y');
    }

    protected function getHeaderActions(): array
    {
        $params = fn () => array_filter(['year' => $this->year, 'property' => $this->property]);

        return [
            ActionGroup::make([
                Action::make('pdf')->label('Jahresbericht (PDF)')->icon('heroicon-o-document-arrow-down')
                    ->url(fn () => route('reports.export', ['format' => 'pdf'] + $params()), true),
                Action::make('csv')->label('Kategorie-Summen (CSV)')->icon('heroicon-o-table-cells')
                    ->url(fn () => route('reports.export', ['format' => 'csv'] + $params()), true),
                Action::make('transactions')->label('Buchungsliste (CSV)')->icon('heroicon-o-list-bullet')
                    ->url(fn () => route('reports.export', ['format' => 'transactions'] + $params()), true),
            ])->label('Exportieren')->icon('heroicon-o-arrow-down-tray')->button(),
        ];
    }

    protected function getViewData(): array
    {
        $reports = app(ReportBuilder::class);
        $property = $this->property ? Property::find($this->property) : null;
        $ids = $property ? $reports->idsWithUnits($property) : null;

        return [
            'properties' => Property::whereNull('parent_id')->orderBy('name')->pluck('name', 'id'),
            'years' => range((int) date('Y') + 1, (int) date('Y') - 10),
            'selected' => $property,
            'summary' => $reports->summary($this->year, $ids),
            'kpis' => $property ? $reports->propertyKpis($property, $this->year) : null,
            'portfolio' => $property ? [] : $reports->portfolio($this->year),
            'comparison' => $reports->yearComparison($this->year, 5, $ids),
            'chartIds' => $ids,
        ];
    }
}
