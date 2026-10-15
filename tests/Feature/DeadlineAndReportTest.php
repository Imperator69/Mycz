<?php

namespace Tests\Feature;

use App\Enums\ContractType;
use App\Enums\InsuranceLine;
use App\Models\Category;
use App\Models\Contract;
use App\Models\Deadline;
use App\Models\InsurancePolicy;
use App\Models\Loan;
use App\Models\MaintenanceTask;
use App\Models\Transaction;
use App\Services\DeadlineAggregator;
use App\Services\RentService;
use App\Services\ReportBuilder;
use App\Services\StatusExporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class DeadlineAndReportTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_renewing_contract_rolls_forward_and_notice_deadline(): void
    {
        Carbon::setTestNow('2026-10-15');
        $policy = InsurancePolicy::create(['insurer' => 'X', 'line' => InsuranceLine::Building, 'end_date' => '2020-01-01', 'auto_renews' => true, 'notice_period_months' => 3]);

        $this->assertSame('2027-01-01', $policy->nextExpiry()->toDateString());
        $this->assertSame('2026-10-01', $policy->noticeDeadline()->toDateString());

        $policy->update(['auto_renews' => false]);
        $this->assertSame('2020-01-01', $policy->nextExpiry()->toDateString());
    }

    public function test_aggregates_deadlines_from_all_modules(): void
    {
        Carbon::setTestNow('2026-10-15');
        $property = $this->makeProperty();
        InsurancePolicy::create(['property_id' => $property->id, 'insurer' => 'Uniqa', 'line' => InsuranceLine::Household, 'end_date' => '2026-12-31', 'auto_renews' => true, 'notice_period_months' => 1]);
        Contract::create(['type' => ContractType::Electricity, 'provider' => 'Wien Energie', 'end_date' => '2027-06-30', 'notice_period_months' => 1]);
        $this->makeLease($property, ['end_date' => '2026-11-30', 'is_fixed_term' => true, 'index_type' => 'vpi2020', 'index_last_adjusted_at' => '2025-11-01']);
        Loan::create(['bank' => 'Bank', 'principal' => 1000, 'interest_rate' => 1, 'start_date' => '2020-01-01', 'term_months' => 120, 'fixed_until' => '2026-12-01']);
        MaintenanceTask::create(['property_id' => $property->id, 'title' => 'Therme', 'next_due_at' => '2026-09-01']);
        Deadline::create(['title' => 'Grundsteuer', 'due_date' => '2026-11-15']);
        Deadline::create(['title' => 'Erledigt', 'due_date' => '2026-11-15', 'done_at' => '2026-10-01']);

        $titles = app(DeadlineAggregator::class)->upcoming(90)->pluck('title');

        $this->assertContains('Kündigungsfrist: Haushaltsversicherung Uniqa', $titles); // 30.11.
        $this->assertContains('Verlängerung: Haushaltsversicherung Uniqa', $titles);
        $this->assertContains('Befristung endet: Max Muster', $titles);
        $this->assertContains('Wertsicherung prüfen: Max Muster', $titles); // 01.11.2026
        $this->assertContains('Zinsbindung endet: Bank', $titles);
        $this->assertContains('Wartung: Therme', $titles); // ueberfaellig
        $this->assertContains('Grundsteuer', $titles);
        $this->assertNotContains('Erledigt', $titles);
        $this->assertNotContains('Kündigungsfrist: Strom Wien Energie', $titles); // 31.05.2027, ausserhalb

        $first = app(DeadlineAggregator::class)->upcoming(90)->first();
        $this->assertTrue($first['overdue']);
    }

    public function test_recurring_manual_deadline(): void
    {
        $d = Deadline::create(['title' => 'Quartal', 'due_date' => '2026-01-15', 'repeat_months' => 3]);
        $d->complete();
        $this->assertSame('2026-04-15', $d->refresh()->due_date->toDateString());
        $this->assertNull($d->done_at);
    }

    public function test_report_summary_result_vs_cashflow_and_yields(): void
    {
        $property = $this->makeProperty(); // Investition 220.000
        $this->makeLease($property, ['base_rent' => 1100, 'operating_costs' => 0, 'vat_rate' => 10]);
        $tx = fn (string $slug, float $gross, string $date = '2026-03-01', bool $alloc = false) => Transaction::create([
            'property_id' => $property->id, 'category_id' => Category::bySlug($slug)->id, 'booked_at' => $date,
            'description' => $slug, 'amount_gross' => $gross, 'vat_rate' => 0, 'is_allocatable' => $alloc,
        ]);
        $tx('miete', 12000);
        $tx('betriebskosten', 2000, alloc: true);
        $tx('instandhaltung', 1000);
        $tx('tilgung', 3000);
        $tx('miete', 999, '2025-06-01');

        $s = app(ReportBuilder::class)->summary(2026, [$property->id]);
        $this->assertEquals(12000, $s['income']);
        $this->assertEquals(6000, $s['expense']);
        $this->assertEquals(6000, $s['cashflow']);
        $this->assertEquals(9000, $s['result']); // Tilgung nicht im Ergebnis
        $this->assertEquals(2000, $s['allocatable_expense']);
        $this->assertEquals(4000, $s['non_allocatable_expense']);

        $k = app(ReportBuilder::class)->propertyKpis($property, 2026);
        $this->assertEqualsWithDelta(1100 * 12 / 220000 * 100, $k['gross_yield'], 0.01);
        $this->assertEqualsWithDelta((12000 - 4000) / 220000 * 100, $k['net_yield'], 0.01);

        $monthly = app(ReportBuilder::class)->monthly(2026, [$property->id]);
        $this->assertEquals(12000, $monthly[2]['income']);

        $cmp = app(ReportBuilder::class)->yearComparison(2026, 2, [$property->id]);
        $this->assertEquals(999, $cmp[0]['income']);
    }

    public function test_house_report_includes_units(): void
    {
        $house = $this->makeProperty(['type' => 'house']);
        $unit = $this->makeProperty(['parent_id' => $house->id]);
        Transaction::create(['property_id' => $unit->id, 'category_id' => Category::bySlug('miete')->id, 'booked_at' => '2026-02-01', 'description' => 'x', 'amount_gross' => 500, 'vat_rate' => 0]);

        $this->assertEquals(500, app(ReportBuilder::class)->propertyKpis($house, 2026)['income']);
        $this->assertCount(1, app(ReportBuilder::class)->portfolio(2026));
    }

    public function test_status_export_contains_no_sensitive_tenant_data(): void
    {
        Carbon::setTestNow('2026-03-20');
        $this->makeLease();
        app(RentService::class)->generateCharges();

        $status = app(StatusExporter::class)->build();
        $json = json_encode($status);

        $this->assertSame('mycz.status.v1', $status['schema']);
        $this->assertCount(3, $status['open_rents']);
        $this->assertSame('Max Muster', $status['open_rents'][0]['tenant']);
        $this->assertStringNotContainsString('AT611904300234573201', $json);
        $this->assertStringNotContainsString('max@example.com', $json);
        $this->assertNotEmpty($status['categories']);
    }
}
