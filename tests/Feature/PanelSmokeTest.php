<?php

namespace Tests\Feature;

use App\Enums\ContractType;
use App\Enums\DocumentType;
use App\Enums\InsuranceLine;
use App\Enums\MeterType;
use App\Models\Category;
use App\Models\Contact;
use App\Models\Contract;
use App\Models\Deadline;
use App\Models\Document;
use App\Models\InsurancePolicy;
use App\Models\Loan;
use App\Models\MaintenanceTask;
use App\Models\Meter;
use App\Models\Ticket;
use App\Models\Transaction;
use App\Services\RentService;
use App\Services\StatementImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Rendert jede Seite des Panels einmal mit Beispieldaten. */
class PanelSmokeTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/')->assertRedirect('/admin');
        $this->get('/admin')->assertRedirect('/admin/login');
        $this->get('/admin/login')->assertOk()->assertSee('MYCZ');
    }

    public function test_all_pages_render(): void
    {
        $this->actingAs($this->makeUser());
        $records = $this->seedSampleData();

        $urls = [
            '/admin', '/admin/fristenkalender', '/admin/reports', '/admin/reports?property='.$records['property']->id, '/admin/settings',
            '/admin/properties', '/admin/properties/create', '/admin/properties/'.$records['property']->id.'/edit',
            '/admin/properties/'.$records['house']->id.'/edit',
            '/admin/tenants', '/admin/tenants/create', '/admin/tenants/'.$records['lease']->tenant_id.'/edit',
            '/admin/leases', '/admin/leases/create', '/admin/leases/'.$records['lease']->id.'/edit',
            '/admin/rent-charges',
            '/admin/transactions', '/admin/transactions/create', '/admin/transactions/'.$records['transaction']->id.'/edit',
            '/admin/statements', '/admin/statements/'.$records['statement']->id.'/edit',
            '/admin/loans', '/admin/loans/create', '/admin/loans/'.$records['loan']->id.'/edit',
            '/admin/insurance-policies', '/admin/insurance-policies/create', '/admin/insurance-policies/'.$records['policy']->id.'/edit',
            '/admin/contracts', '/admin/contracts/create', '/admin/contracts/'.$records['contract']->id.'/edit',
            '/admin/contacts', '/admin/contacts/create',
            '/admin/maintenance-tasks', '/admin/maintenance-tasks/create',
            '/admin/meters', '/admin/meters/create', '/admin/meters/'.$records['meter']->id.'/edit',
            '/admin/tickets', '/admin/tickets/create',
            '/admin/deadlines', '/admin/deadlines/create',
            '/admin/documents', '/admin/documents/create', '/admin/documents/'.$records['document']->id.'/edit',
            '/admin/categories', '/admin/users', '/admin/activity-logs',
            '/status.json',
        ];

        foreach ($urls as $url) {
            $response = $this->get($url);
            $error = $response->exception ? ': '.$response->exception->getMessage().' @ '.$response->exception->getFile().':'.$response->exception->getLine() : '';
            $this->assertSame(200, $response->status(), "$url lieferte {$response->status()}$error");
        }
    }

    public function test_exports_download(): void
    {
        $this->actingAs($this->makeUser());
        $records = $this->seedSampleData();

        $this->get('/reports/export/csv?year=2026')->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $this->get('/reports/export/transactions?year=2026&property='.$records['property']->id)->assertOk()->assertSee('Miete');
        $this->get('/reports/export/pdf?year=2026')->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->get('/reports/export/pdf?year=2026&property='.$records['property']->id)->assertOk();
    }

    private function seedSampleData(): array
    {
        $house = $this->makeProperty(['name' => 'Haus Melk', 'type' => 'house']);
        $property = $this->makeProperty(['name' => 'Top 1', 'parent_id' => $house->id]);
        $lease = $this->makeLease($property, ['index_type' => 'vpi2020', 'index_base_month' => '2025-01-01']);
        app(RentService::class)->generateCharges();
        app(RentService::class)->markPaid($lease->rentCharges()->first());

        $contact = Contact::create(['name' => 'HV Muster', 'role' => 'Hausverwaltung']);
        $policy = InsurancePolicy::create(['property_id' => $house->id, 'insurer' => 'Wiener Städtische', 'line' => InsuranceLine::Building, 'premium' => 50, 'payment_interval' => 'monthly', 'end_date' => today()->addMonths(2), 'notice_period_months' => 1]);
        $policy->claims()->create(['incident_date' => today(), 'description' => 'Wasserschaden']);
        $contract = Contract::create(['property_id' => $house->id, 'contact_id' => $contact->id, 'type' => ContractType::PropertyManagement, 'provider' => 'HV Muster', 'cost_amount' => 30, 'cost_interval' => 'monthly', 'end_date' => '2025-12-31', 'notice_period_months' => 3]);
        $loan = Loan::create(['property_id' => $house->id, 'bank' => 'Raiffeisen', 'principal' => 150000, 'interest_rate' => 3.5, 'start_date' => '2024-01-01', 'term_months' => 240, 'fixed_until' => today()->addMonths(1)]);
        MaintenanceTask::create(['property_id' => $property->id, 'title' => 'Thermenwartung', 'interval_months' => 12, 'next_due_at' => today()->addDays(10)]);
        $meter = Meter::create(['property_id' => $property->id, 'type' => MeterType::Electricity, 'number' => '123']);
        $meter->readings()->create(['read_at' => today()->subYear(), 'value' => 1000]);
        $meter->readings()->create(['read_at' => today(), 'value' => 3500]);
        Ticket::create(['property_id' => $property->id, 'title' => 'Fenster undicht', 'reported_at' => today()]);
        Deadline::create(['title' => 'Grundsteuer zahlen', 'due_date' => today()->addDays(5)]);
        $document = Document::create(['title' => 'Abrechnung 2025', 'doc_type' => DocumentType::AnnualStatement, 'property_id' => $house->id, 'year' => 2025, 'local_path' => 'documents/x.pdf']);
        $transaction = Transaction::create(['property_id' => $property->id, 'category_id' => Category::bySlug('instandhaltung')->id, 'booked_at' => today(), 'description' => 'Installateur', 'amount_gross' => 240, 'vat_rate' => 20]);
        $statement = app(StatementImporter::class)->import(json_decode(file_get_contents(base_path('tests/fixtures/statement.json')), true));

        return compact('house', 'property', 'lease', 'policy', 'contract', 'loan', 'meter', 'document', 'transaction', 'statement');
    }
}
