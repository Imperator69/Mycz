<?php

namespace App\Services;

use App\Models\Loan;
use Illuminate\Support\Carbon;

/**
 * Annuitaeten-Tilgungsplan (monatliche Raten). Bei variablem Zins wird der
 * aktuell hinterlegte Zinssatz fortgeschrieben.
 */
class LoanSchedule
{
    private ?array $rows = null;

    public function __construct(private readonly Loan $loan) {}

    public function monthlyRate(): float
    {
        return (float) $this->loan->interest_rate / 100 / 12;
    }

    public function payment(): float
    {
        if ($this->loan->monthly_payment !== null && (float) $this->loan->monthly_payment > 0) {
            return (float) $this->loan->monthly_payment;
        }

        $n = max(1, (int) $this->loan->term_months);
        $p = (float) $this->loan->principal;
        $r = $this->monthlyRate();

        return $r == 0.0 ? round($p / $n, 2) : round($p * $r / (1 - (1 + $r) ** -$n), 2);
    }

    /** @return array<int, array{date: Carbon, payment: float, interest: float, principal: float, balance: float}> */
    public function rows(): array
    {
        if ($this->rows !== null) {
            return $this->rows;
        }

        $balance = (float) $this->loan->principal;
        $payment = $this->payment();
        $r = $this->monthlyRate();
        $date = $this->loan->start_date->copy();
        $rows = [];

        for ($i = 1; $i <= (int) $this->loan->term_months && $balance > 0.004; $i++) {
            $date = $this->loan->start_date->copy()->addMonthsNoOverflow($i);
            $interest = round($balance * $r, 2);
            $principal = min($balance, round($payment - $interest, 2));
            if ($principal <= 0) {
                // Rate deckt die Zinsen nicht: Plan endet hier, um Endlosschleifen zu vermeiden.
                break;
            }
            $balance = round($balance - $principal, 2);
            $rows[] = [
                'date' => $date,
                'payment' => round($interest + $principal, 2),
                'interest' => $interest,
                'principal' => $principal,
                'balance' => $balance,
            ];
        }

        return $this->rows = $rows;
    }

    public function balanceAt(Carbon $date): float
    {
        $balance = (float) $this->loan->principal;
        foreach ($this->rows() as $row) {
            if ($row['date']->gt($date)) {
                break;
            }
            $balance = $row['balance'];
        }

        return $balance;
    }

    /** @return array{interest: float, principal: float, payment: float} */
    public function yearTotals(int $year): array
    {
        $totals = ['interest' => 0.0, 'principal' => 0.0, 'payment' => 0.0];
        foreach ($this->rows() as $row) {
            if ($row['date']->year === $year) {
                foreach ($totals as $key => $value) {
                    $totals[$key] = round($value + $row[$key], 2);
                }
            }
        }

        return $totals;
    }

    /** @return array<int, array{year: int, interest: float, principal: float, payment: float, balance: float}> */
    public function yearly(): array
    {
        $years = [];
        foreach ($this->rows() as $row) {
            $y = $row['date']->year;
            $years[$y] ??= ['year' => $y, 'interest' => 0.0, 'principal' => 0.0, 'payment' => 0.0, 'balance' => 0.0];
            foreach (['interest', 'principal', 'payment'] as $key) {
                $years[$y][$key] = round($years[$y][$key] + $row[$key], 2);
            }
            $years[$y]['balance'] = $row['balance'];
        }

        return array_values($years);
    }

    public function endDate(): ?Carbon
    {
        $rows = $this->rows();

        return $rows === [] ? null : end($rows)['date'];
    }
}
