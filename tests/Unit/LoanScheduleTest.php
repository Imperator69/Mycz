<?php

namespace Tests\Unit;

use App\Models\Loan;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class LoanScheduleTest extends TestCase
{
    private function loan(array $attrs = []): Loan
    {
        $loan = new Loan;
        $loan->forceFill($attrs + [
            'bank' => 'Testbank',
            'principal' => 100000,
            'interest_rate' => 3.0,
            'start_date' => Carbon::parse('2026-01-01'),
            'term_months' => 120,
            'monthly_payment' => null,
        ]);

        return $loan;
    }

    public function test_annuity_payment_is_computed(): void
    {
        // 100.000 zu 3 % auf 10 Jahre: Annuitaet ca. 965,61
        $this->assertEqualsWithDelta(965.61, $this->loan()->schedule()->payment(), 0.01);
    }

    public function test_loan_is_fully_repaid_at_end_of_term(): void
    {
        $schedule = $this->loan()->schedule();
        $rows = $schedule->rows();

        $this->assertLessThanOrEqual(120, count($rows));
        $this->assertEqualsWithDelta(0, end($rows)['balance'], 1.0);
        $this->assertEqualsWithDelta(100000, array_sum(array_column($rows, 'principal')), 1.0);
    }

    public function test_year_totals_split_interest_and_principal(): void
    {
        $totals = $this->loan()->schedule()->yearTotals(2026);

        // 11 Raten im Jahr 2026 (erste Rate 01.02.)
        $this->assertEqualsWithDelta(965.61 * 11, $totals['payment'], 1.0);
        $this->assertGreaterThan(2500, $totals['interest']);
        $this->assertEqualsWithDelta($totals['payment'], $totals['interest'] + $totals['principal'], 0.05);
    }

    public function test_balance_at_date(): void
    {
        $schedule = $this->loan()->schedule();
        $this->assertSame(100000.0, $schedule->balanceAt(Carbon::parse('2026-01-15')));
        $this->assertLessThan(100000, $schedule->balanceAt(Carbon::parse('2026-03-01')));
    }

    public function test_payment_below_interest_does_not_loop_forever(): void
    {
        $rows = $this->loan(['monthly_payment' => 100])->schedule()->rows();
        $this->assertSame([], $rows);
    }

    public function test_zero_interest(): void
    {
        $this->assertSame(1000.0, $this->loan(['interest_rate' => 0, 'principal' => 120000])->schedule()->payment());
    }
}
