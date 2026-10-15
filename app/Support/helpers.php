<?php

use App\Enums\PaymentInterval;

if (! function_exists('annualize')) {
    /** Betrag pro Intervall auf ein Jahr hochrechnen. */
    function annualize(float $amount, ?PaymentInterval $interval): float
    {
        return round($amount * match ($interval) {
            PaymentInterval::Monthly => 12,
            PaymentInterval::Quarterly => 4,
            PaymentInterval::Semiannual => 2,
            PaymentInterval::Annual, null => 1,
            PaymentInterval::Once => 0,
        }, 2);
    }
}

if (! function_exists('eur')) {
    function eur(float|string|null $amount): string
    {
        return number_format((float) $amount, 2, ',', '.').' €';
    }
}
