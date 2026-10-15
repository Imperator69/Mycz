<?php

namespace App\Services;

use App\Enums\DocumentType;
use App\Enums\StatementStatus;
use App\Enums\TransactionSource;
use App\Enums\TransactionType;
use App\Models\Category;
use App\Models\Document;
use App\Models\Property;
use App\Models\Statement;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

/**
 * Importiert von Claude erzeugte Abrechnungs-Auswertungen
 * (Schema mycz.statement.v1, siehe docs/statement-schema.json) und bucht
 * sie nach Pruefung als Transaktionen.
 */
class StatementImporter
{
    public const SCHEMA = 'mycz.statement.v1';

    public const TOLERANCE = 0.05;

    public function __construct(
        private readonly GoogleDriveService $drive,
        private readonly DocumentStorage $storage,
    ) {}

    /** Alle neuen JSON-Dateien aus _System/auswertungen importieren. */
    public function importFromDrive(): array
    {
        $result = ['imported' => 0, 'skipped' => 0, 'errors' => []];
        $outbox = $this->drive->statementsOutboxId();
        $imported = $this->drive->statementsImportedId();

        foreach ($this->drive->listChildren($outbox) as $file) {
            if (($file['mimeType'] ?? '') === GoogleDriveService::FOLDER_MIME || ! str_ends_with(strtolower($file['name']), '.json')) {
                continue;
            }
            if (Statement::where('source_file_id', $file['id'])->exists()) {
                $result['skipped']++;

                continue;
            }

            try {
                $data = json_decode($this->drive->download($file['id']), true, 512, JSON_THROW_ON_ERROR);
                $this->import($data, $file['id'], $file['name']);
                $this->drive->move($file['id'], $imported);
                $result['imported']++;
            } catch (\Throwable $e) {
                $result['errors'][] = $file['name'].': '.GoogleDriveService::describeError($e);
            }
        }

        return $result;
    }

    /** Ein JSON-Dokument validieren und als "zu pruefen" anlegen. */
    public function import(array $data, ?string $sourceFileId = null, ?string $sourceFileName = null): Statement
    {
        $this->validate($data);
        $warnings = array_values(array_filter((array) ($data['warnings'] ?? []), 'is_string'));

        $document = null;
        if (! empty($data['source_file_id'])) {
            $document = Document::where('drive_file_id', $data['source_file_id'])->first();
        }

        $property = null;
        if (! empty($data['property_id'])) {
            $property = Property::find((int) $data['property_id']);
        }
        if (! $property && ! empty($data['property_name'])) {
            $property = Property::where('name', $data['property_name'])->first();
        }
        $property ??= $document?->property;
        if (! $property) {
            $warnings[] = 'Objekt konnte nicht zugeordnet werden, bitte manuell wählen.';
        }

        $periodFrom = $this->date($data['period_from'] ?? null);
        $periodTo = $this->date($data['period_to'] ?? null);
        $categories = Category::whereNotNull('slug')->get()->keyBy('slug');

        $linesTotal = 0.0;
        $lines = [];
        foreach (array_values($data['lines']) as $i => $line) {
            $slug = $line['category'] ?? null;
            $category = $slug ? $categories->get($slug) : null;
            if ($slug && ! $category) {
                $warnings[] = "Unbekannte Kategorie \"$slug\" bei \"{$line['label']}\".";
            }
            $amount = round((float) $line['amount'], 2);
            $linesTotal += $amount;
            $lines[] = [
                'label' => mb_substr((string) $line['label'], 0, 255),
                'amount' => $amount,
                'suggested_category' => $slug,
                'category_id' => $category?->id,
                'is_allocatable' => (bool) ($line['allocatable'] ?? $category?->is_allocatable ?? false),
                'sort' => $i,
            ];
        }

        $total = isset($data['total_costs']) ? round((float) $data['total_costs'], 2) : null;
        if ($total !== null && abs($total - round($linesTotal, 2)) > self::TOLERANCE) {
            $warnings[] = sprintf('Summe der Positionen (%s) weicht vom Gesamtbetrag (%s) ab.', eur($linesTotal), eur($total));
        }

        return DB::transaction(function () use ($data, $sourceFileId, $sourceFileName, $property, $document, $periodFrom, $periodTo, $total, $lines, $warnings) {
            $statement = Statement::create([
                'property_id' => $property?->id,
                'document_id' => $document?->id,
                'source_file_id' => $sourceFileId,
                'source_file_name' => $sourceFileName,
                'issuer' => $data['issuer'] ?? null,
                'statement_type' => $data['statement_type'] ?? null,
                'period_from' => $periodFrom,
                'period_to' => $periodTo,
                'year' => $periodTo?->year ?? $periodFrom?->year ?? (isset($data['year']) ? (int) $data['year'] : null),
                'total_costs' => $total,
                'reserve_contribution' => $this->num($data['reserve_contribution'] ?? null),
                'reserve_balance' => $this->num($data['reserve_balance'] ?? null),
                'prepayments' => $this->num($data['prepayments'] ?? null),
                'balance' => $this->num($data['balance'] ?? null),
                'summary' => $data['summary'] ?? null,
                'warnings' => array_values(array_unique($warnings)),
                'raw' => $data,
                'status' => StatementStatus::PendingReview,
            ]);
            $statement->lines()->createMany($lines);

            return $statement;
        });
    }

    public function validate(array $data): void
    {
        if (($data['schema'] ?? null) !== self::SCHEMA) {
            throw new InvalidArgumentException('Unbekanntes Schema, erwartet "'.self::SCHEMA.'".');
        }
        if (empty($data['lines']) || ! is_array($data['lines'])) {
            throw new InvalidArgumentException('"lines" fehlt oder ist leer.');
        }
        foreach ($data['lines'] as $i => $line) {
            if (! is_array($line) || empty($line['label']) || ! isset($line['amount']) || ! is_numeric($line['amount'])) {
                throw new InvalidArgumentException('Position '.($i + 1).' braucht "label" und numerisches "amount".');
            }
        }
        foreach (['total_costs', 'reserve_contribution', 'reserve_balance', 'prepayments', 'balance'] as $field) {
            if (isset($data[$field]) && ! is_numeric($data[$field])) {
                throw new InvalidArgumentException("\"$field\" muss eine Zahl sein.");
            }
        }
        foreach (['period_from', 'period_to'] as $field) {
            if (! empty($data[$field]) && $this->date($data[$field]) === null) {
                throw new InvalidArgumentException("\"$field\" ist kein gültiges Datum (YYYY-MM-DD).");
            }
        }
    }

    /**
     * Pruefung abschliessen und buchen.
     *
     * mode "lines":   jede Position als Ausgabe (wenn Akonto-Zahlungen nicht laufend gebucht werden)
     * mode "balance": nur Nachzahlung/Guthaben (wenn monatliche Vorschreibungen bereits gebucht sind)
     */
    public function book(Statement $statement, string $mode = 'lines'): int
    {
        if ($statement->status === StatementStatus::Booked) {
            throw new RuntimeException('Diese Abrechnung wurde bereits gebucht.');
        }
        if (! $statement->property_id) {
            throw new RuntimeException('Bitte zuerst ein Objekt zuordnen.');
        }

        $date = $statement->period_to ?? today();
        $prefix = trim(($statement->issuer ?? 'Abrechnung').' '.($statement->year ?? ''));

        $count = DB::transaction(function () use ($statement, $mode, $date, $prefix) {
            $count = 0;
            $base = [
                'property_id' => $statement->property_id,
                'booked_at' => $date,
                'counterparty' => $statement->issuer,
                'source' => TransactionSource::Statement,
                'statement_id' => $statement->id,
                'document_id' => $statement->document_id,
                'vat_rate' => 0,
                'amount_net' => null,
            ];

            if ($mode === 'balance') {
                $balance = (float) $statement->balance;
                if (abs($balance) >= 0.01) {
                    $category = $balance > 0 ? Category::bySlug('betriebskosten') : Category::bySlug('gutschrift');
                    $statement->transactions()->create($base + [
                        'category_id' => $category->id,
                        'type' => $balance > 0 ? TransactionType::Expense : TransactionType::Income,
                        'description' => $prefix.($balance > 0 ? ' Nachzahlung' : ' Guthaben'),
                        'amount_gross' => abs($balance),
                        'is_allocatable' => $balance > 0,
                    ]);
                    $count++;
                }
            } else {
                $fallback = Category::bySlug('betriebskosten');
                foreach ($statement->lines as $line) {
                    if (abs((float) $line->amount) < 0.01) {
                        continue;
                    }
                    $category = $line->category ?? $fallback;
                    $amount = (float) $line->amount;
                    $type = $category->type === TransactionType::Income ? TransactionType::Income : TransactionType::Expense;
                    $statement->transactions()->create($base + [
                        'category_id' => $category->id,
                        'type' => $type,
                        'description' => $prefix.': '.$line->label,
                        'amount_gross' => abs($amount),
                        'is_allocatable' => $line->is_allocatable,
                    ]);
                    $count++;
                }
            }

            $statement->update(['status' => StatementStatus::Booked, 'booked_at' => now()]);

            return $count;
        });

        if ($statement->document) {
            $doc = $statement->document;
            $doc->update([
                'property_id' => $doc->property_id ?? $statement->property_id,
                'year' => $doc->year ?? $statement->year,
                'doc_type' => DocumentType::AnnualStatement,
            ]);
            $this->storage->archive($doc->refresh());
        }

        return $count;
    }

    private function date(mixed $value): ?Carbon
    {
        if (! is_string($value) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return null;
        }
        try {
            return Carbon::createFromFormat('!Y-m-d', $value);
        } catch (\Throwable) {
            return null;
        }
    }

    private function num(mixed $value): ?float
    {
        return is_numeric($value) ? round((float) $value, 2) : null;
    }
}
