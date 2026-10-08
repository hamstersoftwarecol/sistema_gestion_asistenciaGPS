<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use App\Services\GoogleSheetsBackup;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Throwable;

/**
 * Reglas de RR. HH.: umbrales de retraso, fichaje sin conexión, vinculación de dispositivos,
 * geocerca, horas extra, alertas, liquidación y copia de seguridad.
 */
class SettingController extends Controller
{
    public function edit(): View
    {
        $values = collect(Setting::DEFAULTS)->mapWithKeys(fn ($default, $key) => [$key => setting($key)]);

        return view('settings.edit', ['values' => $values]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'company_name' => ['required', 'string', 'max:150'],
            'company_tax_id' => ['nullable', 'string', 'max:50'],
            'company_address' => ['nullable', 'string', 'max:255'],
            'currency_symbol' => ['required', 'string', 'max:5'],
            'currency_decimals' => ['required', 'integer', 'between:0,2'],
            'weekend_days' => ['nullable', 'array'],
            'weekend_days.*' => ['integer', 'between:0,6'],
            'standard_daily_hours' => ['required', 'numeric', 'min:1', 'max:24'],
            'month_days_divisor' => ['required', 'integer', 'min:20', 'max:31'],
            'default_grace_minutes' => ['required', 'integer', 'min:0', 'max:120'],
            'late_half_day_after_minutes' => ['required', 'integer', 'min:0', 'max:600'],
            'lates_per_deduction' => ['required', 'integer', 'min:0', 'max:31'],
            'late_deduction_days' => ['required', 'numeric', 'min:0', 'max:5'],
            'half_day_minutes' => ['required', 'integer', 'min:0', 'max:720'],
            'break_deduct_after_minutes' => ['required', 'integer', 'min:0', 'max:720'],
            'overtime_min_minutes' => ['required', 'integer', 'min:0', 'max:240'],
            'max_gps_accuracy_m' => ['required', 'integer', 'min:0', 'max:10000'],
            'out_of_zone_action' => ['required', Rule::in(['flag', 'block'])],
            'offline_max_hours' => ['required', 'integer', 'min:1', 'max:168'],
            'alert_late_after_minutes' => ['required', 'integer', 'min:5', 'max:600'],
            'alert_missed_checkout_hours' => ['required', 'integer', 'min:1', 'max:24'],
            'document_expiry_alert_days' => ['required', 'integer', 'min:1', 'max:365'],
            'gratuity_days_per_year' => ['required', 'numeric', 'min:0', 'max:60'],
            'gratuity_min_years' => ['required', 'numeric', 'min:0', 'max:10'],
            'sheets_spreadsheet_id' => ['nullable', 'string', 'max:200'],
            'sheets_credentials_path' => ['nullable', 'string', 'max:255'],
        ]);

        foreach (['overtime_requires_approval', 'require_selfie', 'allow_offline_punch', 'device_binding', 'mail_alerts', 'google_signup'] as $flag) {
            $data[$flag] = $request->boolean($flag);
        }

        $data['weekend_days'] = array_map('intval', $data['weekend_days'] ?? []);

        foreach ($data as $key => $value) {
            $default = Setting::DEFAULTS[$key] ?? null;
            $value = match (true) {
                is_int($default) && ! is_bool($default) => (int) $value,
                is_float($default) => (float) $value,
                default => $value ?? '',
            };
            Setting::write($key, $value);
        }

        return back()->with('status', 'Reglas de RR. HH. guardadas.');
    }

    public function backup(GoogleSheetsBackup $backup): RedirectResponse
    {
        try {
            $result = $backup->run();
        } catch (Throwable $e) {
            return back()->with('error', 'La copia de seguridad falló: '.$e->getMessage());
        }

        $rows = collect($result['sheets'])->map(fn ($n, $s) => "{$s}: {$n}")->implode(', ');

        return back()->with('status', "Copia de seguridad completada ({$rows}) → {$result['target']}");
    }
}
