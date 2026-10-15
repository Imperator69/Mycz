<?php

namespace App\Services;

use App\Enums\RentChargeStatus;
use App\Enums\TransactionSource;
use App\Enums\TransactionType;
use App\Models\Category;
use App\Models\Lease;
use App\Models\RentCharge;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Erzeugt monatliche Soll-Mieten und bucht manuell abgehakte Zahlungen
 * als Einnahme.
 */
class RentService
{
    /**
     * Legt fehlende Soll-Positionen bis einschliesslich Monat $until an.
     * Idempotent dank Unique-Index (lease_id, period).
     *
     * @return int Anzahl neu angelegter Positionen
     */
    public function generateCharges(?Carbon $until = null): int
    {
        $until = ($until ?? today())->copy()->startOfMonth();
        $created = 0;

        Lease::query()->with('property')->each(function (Lease $lease) use ($until, &$created) {
            $created += $this->generateForLease($lease, $until);
        });

        return $created;
    }

    public function generateForLease(Lease $lease, Carbon $until): int
    {
        $from = $lease->charges_from->copy()->startOfMonth();
        if ($lease->start_date->copy()->startOfMonth()->gt($from)) {
            $from = $lease->start_date->copy()->startOfMonth();
        }
        $last = $until->copy();
        if ($lease->end_date !== null && $lease->end_date->copy()->startOfMonth()->lt($last)) {
            $last = $lease->end_date->copy()->startOfMonth();
        }

        $existing = $lease->rentCharges()->pluck('period')->map(fn ($d) => $d->format('Y-m-d'))->flip();
        $created = 0;

        for ($month = $from->copy(); $month->lte($last); $month->addMonthNoOverflow()) {
            if (isset($existing[$month->format('Y-m-d')])) {
                continue;
            }
            $dueDay = min(max(1, (int) $lease->due_day), $month->daysInMonth);
            $lease->rentCharges()->create([
                'property_id' => $lease->property_id,
                'period' => $month->copy(),
                'due_date' => $month->copy()->day($dueDay),
                'amount_due' => $lease->monthlyGross(),
                'status' => RentChargeStatus::Open,
            ]);
            $created++;
        }

        return $created;
    }

    /** Zahlung erfassen (voll oder teilweise) und Einnahme buchen. */
    public function markPaid(RentCharge $charge, ?float $amount = null, ?Carbon $paidAt = null): RentCharge
    {
        $amount ??= (float) $charge->amount_due;
        $paidAt ??= today();

        return DB::transaction(function () use ($charge, $amount, $paidAt) {
            $status = $amount + 0.005 >= (float) $charge->amount_due
                ? RentChargeStatus::Paid
                : ($amount > 0 ? RentChargeStatus::Partial : RentChargeStatus::Open);

            $charge->update([
                'amount_paid' => round($amount, 2),
                'paid_at' => $amount > 0 ? $paidAt : null,
                'status' => $status,
            ]);

            $this->syncTransaction($charge->refresh());

            return $charge;
        });
    }

    public function markOpen(RentCharge $charge): RentCharge
    {
        return $this->markPaid($charge, 0.0);
    }

    public function waive(RentCharge $charge): RentCharge
    {
        $charge->update(['status' => RentChargeStatus::Waived]);
        $this->syncTransaction($charge);

        return $charge;
    }

    /** Einnahme-Buchung an den bezahlten Betrag angleichen. */
    private function syncTransaction(RentCharge $charge): void
    {
        $paid = (float) $charge->amount_paid;
        $existing = $charge->transaction()->first();

        if ($paid <= 0) {
            $existing?->delete();

            return;
        }

        $lease = $charge->lease;
        $vatRate = (float) ($lease?->vat_rate ?? 0);
        $attributes = [
            'property_id' => $charge->property_id,
            'category_id' => Category::bySlug('miete')->id,
            'type' => TransactionType::Income,
            'booked_at' => $charge->paid_at ?? today(),
            'description' => 'Miete '.$charge->period->format('m/Y').($lease?->tenant ? ' - '.$lease->tenant->display_name : ''),
            'counterparty' => $lease?->tenant?->display_name,
            'amount_net' => null,
            'amount_gross' => $paid,
            'vat_rate' => $vatRate,
            'source' => TransactionSource::Rent,
        ];

        if ($existing) {
            $existing->fill($attributes)->save();
        } else {
            $charge->transaction()->create($attributes);
        }
    }
}
