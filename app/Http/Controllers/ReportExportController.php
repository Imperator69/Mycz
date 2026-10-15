<?php

namespace App\Http\Controllers;

use App\Models\Property;
use App\Models\Transaction;
use App\Services\ReportBuilder;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Exporte fuer Steuerberater: Jahresbericht (PDF), Kategorie-Summen (CSV)
 * und Buchungsliste (CSV, Excel-kompatibel mit ; und UTF-8-BOM).
 */
class ReportExportController extends Controller
{
    public function __invoke(Request $request, string $format, ReportBuilder $reports): Response
    {
        $year = (int) $request->query('year', date('Y'));
        $property = $request->filled('property') ? Property::withTrashed()->findOrFail($request->integer('property')) : null;
        $ids = $property ? $reports->idsWithUnits($property) : null;
        $label = $property ? Str::slug($property->name) : 'portfolio';

        return match ($format) {
            'pdf' => $this->pdf($reports, $year, $property, $ids, $label),
            'csv' => $this->csv("auswertung-$label-$year.csv", $this->summaryRows($reports->summary($year, $ids))),
            'transactions' => $this->csv("buchungen-$label-$year.csv", $this->transactionRows($year, $ids)),
        };
    }

    private function pdf(ReportBuilder $reports, int $year, ?Property $property, ?array $ids, string $label): Response
    {
        $data = [
            'year' => $year,
            'property' => $property,
            'summary' => $reports->summary($year, $ids),
            'monthly' => $reports->monthly($year, $ids),
            'kpis' => $property ? $reports->propertyKpis($property, $year) : null,
            'portfolio' => $property ? null : $reports->portfolio($year),
            'comparison' => $reports->yearComparison($year, 5, $ids),
            'transactions' => Transaction::with(['category', 'property'])
                ->inYear($year)
                ->when($ids, fn ($q) => $q->whereIn('property_id', $ids))
                ->orderBy('booked_at')
                ->get(),
        ];

        return Pdf::loadView('reports.annual', $data)->setPaper('a4')->download("jahresbericht-$label-$year.pdf");
    }

    private function summaryRows(array $summary): array
    {
        $rows = [['Typ', 'Gruppe', 'Kategorie', 'Netto', 'USt', 'Brutto', 'Anzahl']];
        foreach ($summary['by_category'] as $c) {
            $rows[] = [$c['type'] === 'income' ? 'Einnahme' : 'Ausgabe', $c['group'], $c['category'], $this->n($c['net']), $this->n($c['vat']), $this->n($c['gross']), $c['count']];
        }
        $rows[] = [];
        $rows[] = ['Summe Einnahmen', '', '', $this->n($summary['income_net']), '', $this->n($summary['income'])];
        $rows[] = ['Summe Ausgaben', '', '', $this->n($summary['expense_net']), '', $this->n($summary['expense'])];
        $rows[] = ['Ergebnis (ohne Tilgung/Investition)', '', '', '', '', $this->n($summary['result'])];
        $rows[] = ['Cashflow', '', '', '', '', $this->n($summary['cashflow'])];

        return $rows;
    }

    private function transactionRows(int $year, ?array $ids): array
    {
        $rows = [['Datum', 'Objekt', 'Typ', 'Kategorie', 'Beschreibung', 'Gegenpartei', 'Netto', 'USt-Satz', 'USt', 'Brutto', 'Umlagefähig', 'Quelle', 'Beleg']];
        Transaction::with(['category', 'property', 'document'])
            ->inYear($year)
            ->when($ids, fn ($q) => $q->whereIn('property_id', $ids))
            ->orderBy('booked_at')
            ->each(function (Transaction $t) use (&$rows) {
                $rows[] = [
                    $t->booked_at->format('d.m.Y'),
                    $t->property?->name ?? 'Allgemein',
                    $t->type->getLabel(),
                    $t->category?->name,
                    $t->description,
                    $t->counterparty,
                    $this->n($t->amount_net),
                    $this->n($t->vat_rate),
                    $this->n($t->vat_amount),
                    $this->n($t->amount_gross),
                    $t->is_allocatable ? 'ja' : 'nein',
                    $t->source?->getLabel(),
                    $t->document?->url(),
                ];
            });

        return $rows;
    }

    private function csv(string $filename, array $rows): Response
    {
        $handle = fopen('php://temp', 'r+');
        fwrite($handle, "\xEF\xBB\xBF");
        foreach ($rows as $row) {
            fputcsv($handle, $row, ';');
        }
        rewind($handle);
        $content = stream_get_contents($handle);
        fclose($handle);

        return response($content, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    private function n(float|string|null $value): string
    {
        return number_format((float) $value, 2, ',', '');
    }
}
