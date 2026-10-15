<?php

namespace Tests\Feature;

use App\Enums\RentChargeStatus;
use App\Enums\TransactionSource;
use App\Models\RentCharge;
use App\Models\Transaction;
use App\Services\RentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class RentServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_generates_monthly_charges_idempotently(): void
    {
        Carbon::setTestNow('2026-04-10');
        $lease = $this->makeLease();
        $service = app(RentService::class);

        // Beim Speichern des Vertrags angelegt, weitere Laeufe sind idempotent.
        $this->assertSame(4, $lease->rentCharges()->count());
        $this->assertSame(0, $service->generateCharges());
        $lease->rentCharges()->whereDate('period', '2026-02-01')->delete();
        $this->assertSame(1, $service->generateCharges());

        $charge = $lease->rentCharges()->orderBy('period')->first();
        $this->assertSame('2026-01-01', $charge->period->toDateString());
        $this->assertSame('2026-01-05', $charge->due_date->toDateString());
        $this->assertEquals(1100.00, (float) $charge->amount_due); // (800 + 200) * 1,10
    }

    public function test_respects_charges_from_and_end_date(): void
    {
        Carbon::setTestNow('2026-10-01');
        $lease = $this->makeLease(attributes: ['charges_from' => '2026-03-01', 'end_date' => '2026-06-30']);

        app(RentService::class)->generateCharges();

        $periods = $lease->rentCharges()->orderBy('period')->pluck('period')->map->format('Y-m')->all();
        $this->assertSame(['2026-03', '2026-04', '2026-05', '2026-06'], $periods);
    }

    public function test_due_day_is_clamped_to_month_length(): void
    {
        Carbon::setTestNow('2026-02-15');
        $lease = $this->makeLease(attributes: ['due_day' => 31, 'charges_from' => '2026-02-01']);
        app(RentService::class)->generateCharges();

        $this->assertSame('2026-02-28', $lease->rentCharges()->first()->due_date->toDateString());
    }

    public function test_mark_paid_books_income_and_unmark_removes_it(): void
    {
        Carbon::setTestNow('2026-01-20');
        $lease = $this->makeLease();
        $service = app(RentService::class);
        $service->generateCharges();
        $charge = RentCharge::first();

        $service->markPaid($charge, null, Carbon::parse('2026-01-04'));
        $charge->refresh();

        $this->assertSame(RentChargeStatus::Paid, $charge->status);
        $tx = Transaction::first();
        $this->assertSame(TransactionSource::Rent, $tx->source);
        $this->assertEquals(1100.00, (float) $tx->amount_gross);
        $this->assertEquals(1000.00, (float) $tx->amount_net);
        $this->assertEquals(100.00, (float) $tx->vat_amount);
        $this->assertSame('2026-01-04', $tx->booked_at->toDateString());
        $this->assertSame($lease->property_id, $tx->property_id);

        $service->markOpen($charge);
        $this->assertSame(RentChargeStatus::Open, $charge->refresh()->status);
        $this->assertSame(0, Transaction::count());
    }

    public function test_partial_payment(): void
    {
        Carbon::setTestNow('2026-01-20');
        $this->makeLease();
        $service = app(RentService::class);
        $service->generateCharges();
        $charge = RentCharge::first();

        $service->markPaid($charge, 500.0);
        $this->assertSame(RentChargeStatus::Partial, $charge->refresh()->status);
        $this->assertEquals(600.0, $charge->outstanding());
        $this->assertEquals(500.0, (float) Transaction::first()->amount_gross);

        $service->markPaid($charge, 1100.0);
        $this->assertSame(RentChargeStatus::Paid, $charge->refresh()->status);
        $this->assertSame(1, Transaction::count());
        $this->assertEquals(1100.0, (float) Transaction::first()->amount_gross);
    }

    public function test_overdue_scope(): void
    {
        Carbon::setTestNow('2026-02-03');
        $this->makeLease();
        app(RentService::class)->generateCharges();

        // Jaenner faellig 05.01. -> ueberfaellig; Februar faellig 05.02. -> noch nicht
        $this->assertSame(1, RentCharge::overdue()->count());
    }
}
