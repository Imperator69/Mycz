<?php

namespace App\Models\Concerns;

use Illuminate\Support\Carbon;

/**
 * Vertraege mit Ablaufdatum, automatischer Verlaengerung und Kuendigungsfrist
 * (Versicherungen, Dienstleistervertraege).
 */
trait HasRenewalTerm
{
    /** Naechstes Ablaufdatum ab heute; bei Auto-Verlaengerung jaehrlich fortgeschrieben. */
    public function nextExpiry(?Carbon $from = null): ?Carbon
    {
        $from ??= today();
        if ($this->end_date === null) {
            return null;
        }

        $expiry = $this->end_date->copy();
        if ($this->auto_renews) {
            while ($expiry->lt($from)) {
                $expiry->addYearNoOverflow();
            }
        }

        return $expiry;
    }

    /** Letzter Tag, an dem die Kuendigung beim Vertragspartner einlangen muss. */
    public function noticeDeadline(?Carbon $from = null): ?Carbon
    {
        $expiry = $this->nextExpiry($from);
        if ($expiry === null || ! $this->notice_period_months) {
            return null;
        }

        return $expiry->copy()->subMonthsNoOverflow($this->notice_period_months);
    }
}
