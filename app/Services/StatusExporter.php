<?php

namespace App\Services;

use App\Enums\DocumentStatus;
use App\Enums\StatementStatus;
use App\Models\Category;
use App\Models\Document;
use App\Models\Property;
use App\Models\RentCharge;
use App\Models\Setting;
use App\Models\Statement;

/**
 * Erstellt _System/status.json im Drive. Diese Datei liest der monatliche
 * Claude-Task (offene Mieten, Fristen, Kennzahlen, Kategorien).
 * Enthaelt bewusst keine IBANs, Geburtsdaten oder Kontaktdaten.
 */
class StatusExporter
{
    public const FILE_NAME = 'status.json';

    public function __construct(
        private readonly ReportBuilder $reports,
        private readonly DeadlineAggregator $deadlines,
        private readonly GoogleDriveService $drive,
    ) {}

    public function build(): array
    {
        $year = (int) date('Y');
        $lastMonth = today()->subMonthNoOverflow();

        return [
            'schema' => 'mycz.status.v1',
            'generated_at' => now()->toIso8601String(),
            'app_url' => url('/admin'),
            'open_rents_url' => url('/admin/rent-charges'),
            'open_rents' => RentCharge::unpaid()
                ->whereDate('due_date', '<=', today())
                ->with(['property', 'lease.tenant'])
                ->orderBy('due_date')
                ->get()
                ->map(fn (RentCharge $c) => [
                    'property' => $c->property?->name,
                    'tenant' => $c->lease?->tenant?->display_name,
                    'period' => $c->period->format('Y-m'),
                    'due_date' => $c->due_date->toDateString(),
                    'amount_due' => (float) $c->amount_due,
                    'amount_paid' => (float) $c->amount_paid,
                    'outstanding' => $c->outstanding(),
                    'days_overdue' => max(0, (int) $c->due_date->diffInDays(today())),
                ])->all(),
            'deadlines_next_90_days' => $this->deadlines->upcoming(90)->map(fn ($d) => [
                'date' => $d['date']->toDateString(),
                'title' => $d['title'],
                'category' => $d['category'],
                'property' => $d['property'],
                'overdue' => $d['overdue'],
            ])->all(),
            'kpis' => [
                'last_month' => array_diff_key($this->reports->summary($lastMonth->year, null, $lastMonth->month), ['by_category' => 1]),
                'year_to_date' => array_diff_key($this->reports->summary($year), ['by_category' => 1]),
                'dashboard' => $this->reports->dashboardStats(),
            ],
            'statements_pending_review' => Statement::where('status', StatementStatus::PendingReview)->count(),
            'inbox_documents' => Document::where('status', DocumentStatus::Inbox)->with('property')->get()->map(fn (Document $d) => [
                'document_id' => $d->id,
                'drive_file_id' => $d->drive_file_id,
                'title' => $d->title,
                'doc_type' => $d->doc_type->value,
                'year' => $d->year,
                'property_id' => $d->property_id,
                'property' => $d->property?->name,
            ])->all(),
            'properties' => Property::orderBy('name')->get()->map(fn (Property $p) => [
                'id' => $p->id,
                'name' => $p->name,
                'type' => $p->type?->value,
                'address' => $p->address(),
                'parent_id' => $p->parent_id,
            ])->all(),
            'categories' => Category::orderBy('type')->orderBy('sort')->get()->map(fn (Category $c) => [
                'slug' => $c->slug,
                'name' => $c->name,
                'type' => $c->type->value,
                'allocatable_default' => $c->is_allocatable,
            ])->all(),
            'statement_schema' => 'mycz.statement.v1 (siehe docs/statement-schema.json im Repository)',
        ];
    }

    /** Nach Drive schreiben. Gibt die Datei-ID zurueck. */
    public function export(): string
    {
        $json = json_encode($this->build(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $id = $this->drive->putFile($this->drive->systemId(), self::FILE_NAME, $json, 'application/json');
        Setting::put('drive.status_exported_at', now()->toIso8601String());

        return $id;
    }
}
