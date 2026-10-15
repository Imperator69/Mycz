<?php

namespace App\Services;

use App\Enums\PropertyStatus;
use App\Enums\TransactionType;
use App\Models\Lease;
use App\Models\Loan;
use App\Models\Property;
use App\Models\RentCharge;
use App\Models\Transaction;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Kennzahlen und Auswertungen. Grundlage sind die Buchungen (transactions);
 * "Ergebnis" zaehlt nur Kategorien mit affects_result (ohne Tilgung,
 * Investitionen), "Cashflow" alle Zahlungen.
 */
class ReportBuilder
{
    /** @param  int[]|null  $propertyIds  null = gesamtes Portfolio */
    public function summary(int $year, ?array $propertyIds = null, ?int $month = null): array
    {
        $from = Carbon::create($year, $month ?? 1, 1)->startOfDay();
        $to = $month ? $from->copy()->endOfMonth() : Carbon::create($year, 12, 31);

        $rows = $this->query($propertyIds)
            ->whereBetween('booked_at', [$from->toDateString(), $to->toDateString()])
            ->with('category')
            ->get();

        $income = $rows->where('type', TransactionType::Income);
        $expense = $rows->where('type', TransactionType::Expense);
        $resultRows = $rows->filter(fn (Transaction $t) => $t->category?->affects_result ?? true);

        $byCategory = $rows->groupBy('category_id')->map(fn (Collection $g) => [
            'category' => $g->first()->category?->name ?? '-',
            'group' => $g->first()->category?->group,
            'type' => $g->first()->type->value,
            'net' => round($g->sum('amount_net'), 2),
            'vat' => round($g->sum('vat_amount'), 2),
            'gross' => round($g->sum('amount_gross'), 2),
            'count' => $g->count(),
        ])->sortBy([['type', 'desc'], ['gross', 'desc']])->values();

        $sumGross = fn (Collection $c) => round($c->sum(fn ($t) => (float) $t->amount_gross), 2);

        return [
            'year' => $year,
            'month' => $month,
            'income' => $sumGross($income),
            'expense' => $sumGross($expense),
            'income_net' => round($income->sum(fn ($t) => (float) $t->amount_net), 2),
            'expense_net' => round($expense->sum(fn ($t) => (float) $t->amount_net), 2),
            'cashflow' => round($sumGross($income) - $sumGross($expense), 2),
            'result' => round(
                $resultRows->where('type', TransactionType::Income)->sum(fn ($t) => (float) $t->amount_gross)
                - $resultRows->where('type', TransactionType::Expense)->sum(fn ($t) => (float) $t->amount_gross),
                2
            ),
            'allocatable_expense' => $sumGross($expense->where('is_allocatable', true)),
            'non_allocatable_expense' => $sumGross($expense->where('is_allocatable', false)),
            'by_category' => $byCategory->all(),
        ];
    }

    /** 12 Monatswerte Einnahmen/Ausgaben. */
    public function monthly(int $year, ?array $propertyIds = null): array
    {
        $rows = $this->query($propertyIds)->inYear($year)->get(['booked_at', 'type', 'amount_gross']);
        $months = [];
        for ($m = 1; $m <= 12; $m++) {
            $inMonth = $rows->filter(fn ($t) => $t->booked_at->month === $m);
            $months[] = [
                'month' => $m,
                'income' => round($inMonth->where('type', TransactionType::Income)->sum(fn ($t) => (float) $t->amount_gross), 2),
                'expense' => round($inMonth->where('type', TransactionType::Expense)->sum(fn ($t) => (float) $t->amount_gross), 2),
            ];
        }

        return $months;
    }

    /** Jahresvergleich der letzten $years Jahre. */
    public function yearComparison(int $toYear, int $years = 5, ?array $propertyIds = null): array
    {
        $out = [];
        for ($y = $toYear - $years + 1; $y <= $toYear; $y++) {
            $s = $this->summary($y, $propertyIds);
            $out[] = ['year' => $y, 'income' => $s['income'], 'expense' => $s['expense'], 'result' => $s['result'], 'cashflow' => $s['cashflow']];
        }

        return $out;
    }

    /** Kennzahlen je Objekt (inkl. Einheiten bei Haeusern). */
    public function propertyKpis(Property $property, int $year): array
    {
        $ids = $this->idsWithUnits($property);
        $summary = $this->summary($year, $ids);

        $leases = Lease::whereIn('property_id', $ids)->active()->get();
        $annualColdRent = round($leases->sum(fn (Lease $l) => (float) $l->base_rent) * 12, 2);
        $annualTarget = round($leases->sum(fn (Lease $l) => $l->monthlyGross()) * 12, 2);
        $investment = $property->totalInvestment();

        $loans = Loan::whereIn('property_id', $ids)->get();
        $loanBalance = round($loans->sum(fn (Loan $l) => $l->schedule()->balanceAt(today())), 2);
        $interest = round($loans->sum(fn (Loan $l) => $l->schedule()->yearTotals($year)['interest']), 2);

        $operatingResult = $summary['income'] - $summary['non_allocatable_expense'];

        return [
            'property_id' => $property->id,
            'name' => $property->name,
            'type' => $property->type?->getLabel(),
            'status' => $property->status?->getLabel(),
            'income' => $summary['income'],
            'expense' => $summary['expense'],
            'result' => $summary['result'],
            'cashflow' => $summary['cashflow'],
            'annual_cold_rent' => $annualColdRent,
            'annual_rent_target' => $annualTarget,
            'investment' => $investment,
            'gross_yield' => $investment > 0 ? round($annualColdRent / $investment * 100, 2) : null,
            'net_yield' => $investment > 0 ? round($operatingResult / $investment * 100, 2) : null,
            'loan_balance' => $loanBalance,
            'loan_interest' => $interest,
            'market_value' => (float) $property->market_value ?: null,
            'open_rent' => round((float) RentCharge::whereIn('property_id', $ids)->unpaid()->whereDate('due_date', '<', today())->sum(DB::raw('amount_due - amount_paid')), 2),
        ];
    }

    /** Portfolio-Tabelle: nur oberste Objekte (Haeuser enthalten ihre Einheiten). */
    public function portfolio(int $year): array
    {
        return Property::whereNull('parent_id')
            ->where('status', '!=', PropertyStatus::Sold->value)
            ->orderBy('name')
            ->get()
            ->map(fn (Property $p) => $this->propertyKpis($p, $year))
            ->all();
    }

    public function dashboardStats(): array
    {
        $year = (int) date('Y');
        $ytd = $this->summary($year);
        $overdue = RentCharge::overdue()->get();
        $properties = Property::where('status', '!=', PropertyStatus::Sold->value)->get();
        $rentable = $properties->filter(fn (Property $p) => $p->units()->doesntExist());

        return [
            'income_ytd' => $ytd['income'],
            'expense_ytd' => $ytd['expense'],
            'result_ytd' => $ytd['result'],
            'cashflow_ytd' => $ytd['cashflow'],
            'overdue_count' => $overdue->count(),
            'overdue_sum' => round($overdue->sum(fn (RentCharge $c) => $c->outstanding()), 2),
            'properties' => $properties->count(),
            'vacant' => $rentable->where('status', PropertyStatus::Vacant)->count(),
            'rentable' => $rentable->count(),
            'monthly_target' => round(Lease::active()->get()->sum(fn (Lease $l) => $l->monthlyGross()), 2),
            'loan_balance' => round(Loan::all()->sum(fn (Loan $l) => $l->schedule()->balanceAt(today())), 2),
        ];
    }

    public function idsWithUnits(Property $property): array
    {
        $ids = [$property->id];
        foreach ($property->units as $unit) {
            $ids = array_merge($ids, $this->idsWithUnits($unit));
        }

        return $ids;
    }

    private function query(?array $propertyIds): Builder
    {
        return Transaction::query()->when($propertyIds !== null, fn (Builder $q) => $q->whereIn('property_id', $propertyIds));
    }
}
