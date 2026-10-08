<?php

use App\Models\Setting;

if (! function_exists('setting')) {
    /**
     * Lee una regla de RR. HH. configurable (ver App\Models\Setting::DEFAULTS).
     */
    function setting(string $key, mixed $default = null): mixed
    {
        return Setting::read($key, $default);
    }
}

if (! function_exists('money')) {
    function money(float|int|string|null $amount): string
    {
        $decimals = (int) setting('currency_decimals');

        return setting('currency_symbol').' '.number_format((float) $amount, $decimals, ',', '.');
    }
}

if (! function_exists('hours')) {
    /**
     * Convierte minutos a "7h 30m".
     */
    function hours(?int $minutes): string
    {
        $minutes = (int) $minutes;

        if ($minutes <= 0) {
            return '—';
        }

        $h = intdiv($minutes, 60);
        $m = $minutes % 60;

        return $m ? "{$h}h {$m}m" : "{$h}h";
    }
}
