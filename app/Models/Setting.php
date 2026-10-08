<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['key', 'value'])]
class Setting extends Model
{
    protected $primaryKey = 'key';

    public $incrementing = false;

    protected $keyType = 'string';

    /**
     * Reglas de RR. HH. por defecto. Cualquier valor guardado en la tabla las sobrescribe.
     */
    public const DEFAULTS = [
        'company_name' => 'Mi Empresa S.A.S.',
        'company_tax_id' => '900.000.000-0',
        'company_address' => 'Bogotá, Colombia',
        'currency_symbol' => '$',
        'currency_decimals' => 0,
        'weekend_days' => [0],
        'standard_daily_hours' => 8.0,
        'month_days_divisor' => 30,

        'default_grace_minutes' => 10,
        'late_half_day_after_minutes' => 120,
        'lates_per_deduction' => 3,
        'late_deduction_days' => 0.5,
        'half_day_minutes' => 240,
        'break_deduct_after_minutes' => 300,
        'overtime_min_minutes' => 15,
        'overtime_requires_approval' => true,

        'require_selfie' => true,
        'max_gps_accuracy_m' => 500,
        'out_of_zone_action' => 'flag',
        'allow_offline_punch' => true,
        'offline_max_hours' => 24,
        'device_binding' => false,

        'alert_late_after_minutes' => 30,
        'alert_missed_checkout_hours' => 2,
        'document_expiry_alert_days' => 30,
        'mail_alerts' => false,

        'gratuity_days_per_year' => 15.0,
        'gratuity_min_years' => 1.0,

        'google_signup' => false,
        'sheets_spreadsheet_id' => '',
        'sheets_credentials_path' => '',
    ];

    protected static ?array $loaded = null;

    public static function read(string $key, mixed $default = null): mixed
    {
        if (static::$loaded === null) {
            static::$loaded = static::query()->pluck('value', 'key')->all();
        }

        if (array_key_exists($key, static::$loaded)) {
            return json_decode(static::$loaded[$key], true);
        }

        return self::DEFAULTS[$key] ?? $default;
    }

    public static function write(string $key, mixed $value): void
    {
        static::query()->updateOrCreate(['key' => $key], ['value' => json_encode($value)]);
        static::flush();
    }

    public static function flush(): void
    {
        static::$loaded = null;
    }
}
