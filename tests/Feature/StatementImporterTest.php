<?php

namespace Tests\Feature;

use App\Enums\DocumentStatus;
use App\Enums\DocumentType;
use App\Enums\StatementStatus;
use App\Enums\TransactionType;
use App\Models\Document;
use App\Models\Transaction;
use App\Services\StatementImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class StatementImporterTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(): array
    {
        return json_decode(file_get_contents(base_path('tests/fixtures/statement.json')), true);
    }

    public function test_import_creates_pending_statement_with_lines(): void
    {
        $property = $this->makeProperty(['name' => 'Haus Melk']);
        $doc = Document::create(['title' => 'HV 2025', 'doc_type' => DocumentType::AnnualStatement, 'drive_file_id' => 'pdf-file-123', 'status' => DocumentStatus::Inbox]);

        $statement = app(StatementImporter::class)->import($this->fixture(), 'json-1', 'a.json');

        $this->assertSame(StatementStatus::PendingReview, $statement->status);
        $this->assertSame($property->id, $statement->property_id);
        $this->assertSame($doc->id, $statement->document_id);
        $this->assertSame(2025, $statement->year);
        $this->assertCount(6, $statement->lines);
        $this->assertSame([], $statement->warnings);
        $this->assertSame('wasser-kanal-muell', $statement->lines->first()->suggested_category);
        $this->assertNotNull($statement->lines->first()->category_id);
    }

    public function test_warns_on_sum_mismatch_unknown_category_and_missing_property(): void
    {
        $data = $this->fixture();
        $data['total_costs'] = 3000;
        $data['property_name'] = 'Gibt es nicht';
        $data['lines'][0]['category'] = 'fantasie';

        $statement = app(StatementImporter::class)->import($data);
        $warnings = implode(' | ', $statement->warnings);

        $this->assertStringContainsString('weicht vom Gesamtbetrag', $warnings);
        $this->assertStringContainsString('Unbekannte Kategorie "fantasie"', $warnings);
        $this->assertStringContainsString('Objekt konnte nicht zugeordnet', $warnings);
        $this->assertNull($statement->property_id);
    }

    public function test_rejects_invalid_payloads(): void
    {
        $importer = app(StatementImporter::class);

        foreach ([
            ['schema' => 'x'],
            ['schema' => StatementImporter::SCHEMA, 'lines' => []],
            ['schema' => StatementImporter::SCHEMA, 'lines' => [['label' => 'A', 'amount' => 'viel']]],
            ['schema' => StatementImporter::SCHEMA, 'lines' => [['label' => 'A', 'amount' => 1]], 'period_to' => '31.12.2025'],
            ['schema' => StatementImporter::SCHEMA, 'lines' => [['label' => 'A', 'amount' => 1]], 'balance' => 'abc'],
        ] as $payload) {
            try {
                $importer->import($payload);
                $this->fail('Kein Fehler für '.json_encode($payload));
            } catch (InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_book_lines_creates_expenses_and_archives_document(): void
    {
        $this->makeProperty(['name' => 'Haus Melk']);
        $doc = Document::create(['title' => 'HV 2025', 'doc_type' => DocumentType::AnnualStatement, 'local_path' => 'x.pdf', 'drive_file_id' => 'pdf-file-123', 'status' => DocumentStatus::Inbox]);
        $statement = app(StatementImporter::class)->import($this->fixture());

        $count = app(StatementImporter::class)->book($statement, 'lines');

        $this->assertSame(6, $count);
        $this->assertSame(StatementStatus::Booked, $statement->refresh()->status);
        $this->assertEqualsWithDelta(2970.65, (float) Transaction::sum('amount_gross'), 0.001);
        $this->assertSame(6, Transaction::where('type', TransactionType::Expense)->whereDate('booked_at', '2025-12-31')->count());
        $this->assertSame(5, Transaction::where('is_allocatable', true)->count());
        $this->assertSame(DocumentStatus::Archived, $doc->refresh()->status);
        $this->assertSame($statement->property_id, $doc->property_id);

        $this->expectExceptionMessage('bereits gebucht');
        app(StatementImporter::class)->book($statement->refresh());
    }

    public function test_book_balance_only(): void
    {
        $this->makeProperty(['name' => 'Haus Melk']);
        $statement = app(StatementImporter::class)->import($this->fixture());

        $this->assertSame(1, app(StatementImporter::class)->book($statement, 'balance'));
        $tx = Transaction::first();
        $this->assertSame(TransactionType::Expense, $tx->type);
        $this->assertEquals(170.65, (float) $tx->amount_gross);

        $data = $this->fixture();
        $data['balance'] = -50;
        $credit = app(StatementImporter::class)->import($data);
        app(StatementImporter::class)->book($credit, 'balance');
        $this->assertSame(TransactionType::Income, Transaction::latest('id')->first()->type);
    }

    public function test_booking_requires_property(): void
    {
        $data = $this->fixture();
        $data['property_name'] = null;
        $statement = app(StatementImporter::class)->import($data);

        $this->expectExceptionMessage('Objekt zuordnen');
        app(StatementImporter::class)->book($statement);
    }

    public function test_schema_doc_matches_fixture_fields(): void
    {
        $schema = json_decode(file_get_contents(base_path('docs/statement-schema.json')), true);
        $this->assertSame(StatementImporter::SCHEMA, $schema['properties']['schema']['const']);
        foreach (array_keys($this->fixture()) as $key) {
            $this->assertArrayHasKey($key, $schema['properties'], "Schema dokumentiert $key nicht");
        }
    }
}
