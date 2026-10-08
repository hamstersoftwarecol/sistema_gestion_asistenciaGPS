<x-app-layout>
    <x-slot name="header">Reglas de RR. HH.</x-slot>

    @php($v = $values)

    <form method="POST" action="{{ route('settings.update') }}" class="space-y-6">
        @csrf @method('PUT')

        <div class="card">
            <div class="card-header"><h2 class="card-title">Empresa y calendario</h2></div>
            <div class="card-body grid gap-4 md:grid-cols-3">
                <x-field name="company_name" label="Razón social" :value="$v['company_name']" required />
                <x-field name="company_tax_id" label="NIT" :value="$v['company_tax_id']" />
                <x-field name="company_address" label="Dirección" :value="$v['company_address']" />
                <x-field name="currency_symbol" label="Símbolo de moneda" :value="$v['currency_symbol']" required />
                <x-field name="currency_decimals" label="Decimales" type="number" min="0" max="2" :value="$v['currency_decimals']" required />
                <x-field name="standard_daily_hours" label="Horas de jornada estándar" type="number" step="0.5" :value="$v['standard_daily_hours']" required />
                <x-field name="month_days_divisor" label="Divisor de días del mes (salario diario)" type="number" :value="$v['month_days_divisor']" required />
                <div class="md:col-span-2">
                    <span class="label">Días de descanso (fin de semana)</span>
                    <div class="flex flex-wrap gap-3">
                        @foreach (['1' => 'Lunes', '2' => 'Martes', '3' => 'Miércoles', '4' => 'Jueves', '5' => 'Viernes', '6' => 'Sábado', '0' => 'Domingo'] as $day => $label)
                            <label class="inline-flex items-center gap-1 text-sm"><input type="checkbox" class="checkbox" name="weekend_days[]" value="{{ $day }}" @checked(in_array((int) $day, (array) $v['weekend_days']))> {{ $label }}</label>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><h2 class="card-title">Umbrales de retraso y jornada</h2></div>
            <div class="card-body grid gap-4 md:grid-cols-3">
                <x-field name="default_grace_minutes" label="Gracia por defecto (sin turno), min" type="number" :value="$v['default_grace_minutes']" required />
                <x-field name="late_half_day_after_minutes" label="Retraso que cuenta como medio día (min)" type="number" :value="$v['late_half_day_after_minutes']" required help="0 = desactivado." />
                <x-field name="half_day_minutes" label="Jornada mínima para día completo (min)" type="number" :value="$v['half_day_minutes']" required />
                <x-field name="lates_per_deduction" label="Llegadas tarde por cada descuento" type="number" :value="$v['lates_per_deduction']" required help="Ej.: 3 tardes = descuento. 0 = sin descuento." />
                <x-field name="late_deduction_days" label="Días descontados por bloque de tardes" type="number" step="0.25" :value="$v['late_deduction_days']" required />
                <x-field name="break_deduct_after_minutes" label="Descontar descanso si la jornada supera (min)" type="number" :value="$v['break_deduct_after_minutes']" required />
            </div>
        </div>

        <div class="card">
            <div class="card-header"><h2 class="card-title">Horas extra</h2></div>
            <div class="card-body grid gap-4 md:grid-cols-3">
                <x-field name="overtime_min_minutes" label="Mínimo para contar horas extra (min)" type="number" :value="$v['overtime_min_minutes']" required />
                <x-field name="overtime_requires_approval" label="Las horas extra requieren aprobación" type="checkbox" :value="$v['overtime_requires_approval']" class="self-end md:col-span-2" />
            </div>
        </div>

        <div class="card">
            <div class="card-header"><h2 class="card-title">Fichaje: selfie, GPS, sin conexión y dispositivos</h2></div>
            <div class="card-body grid gap-4 md:grid-cols-3">
                <x-field name="require_selfie" label="Selfie obligatoria" type="checkbox" :value="$v['require_selfie']" />
                <x-field name="allow_offline_punch" label="Permitir fichaje sin conexión (cola local)" type="checkbox" :value="$v['allow_offline_punch']" />
                <x-field name="device_binding" label="Vincular cada empleado a un único dispositivo" type="checkbox" :value="$v['device_binding']" />
                <x-field name="offline_max_hours" label="Antigüedad máxima de un fichaje sin conexión (h)" type="number" :value="$v['offline_max_hours']" required />
                <x-field name="max_gps_accuracy_m" label="Precisión GPS máxima aceptada (m)" type="number" :value="$v['max_gps_accuracy_m']" required help="0 = no validar precisión." />
                <x-field name="out_of_zone_action" label="Registro fuera de la geocerca" type="select" :options="['flag' => 'Permitir y marcar para revisión', 'block' => 'Bloquear el registro']" :value="$v['out_of_zone_action']" required />
            </div>
        </div>

        <div class="card">
            <div class="card-header"><h2 class="card-title">Alertas (cron)</h2></div>
            <div class="card-body grid gap-4 md:grid-cols-4">
                <x-field name="alert_late_after_minutes" label="Alertar sin marcación tras (min)" type="number" :value="$v['alert_late_after_minutes']" required />
                <x-field name="alert_missed_checkout_hours" label="Recordar salida tras fin de turno (h)" type="number" :value="$v['alert_missed_checkout_hours']" required />
                <x-field name="document_expiry_alert_days" label="Avisar documentos que vencen en (días)" type="number" :value="$v['document_expiry_alert_days']" required />
                <x-field name="mail_alerts" label="Enviar alertas también por correo" type="checkbox" :value="$v['mail_alerts']" class="self-end" />
            </div>
            <p class="px-5 pb-4 text-xs text-gray-500">Programa en el servidor: <code>* * * * * php artisan schedule:run</code>. Tareas: <code>hr:alerts</code> (cada 15 min), <code>attendance:close-day</code> (00:15) y <code>backup:google-sheets</code> (02:00).</p>
        </div>

        <div class="card">
            <div class="card-header"><h2 class="card-title">Liquidación final</h2></div>
            <div class="card-body grid gap-4 md:grid-cols-3">
                <x-field name="gratuity_days_per_year" label="Días de gratificación por año de servicio" type="number" step="0.5" :value="$v['gratuity_days_per_year']" required />
                <x-field name="gratuity_min_years" label="Años mínimos para gratificación" type="number" step="0.5" :value="$v['gratuity_min_years']" required />
            </div>
        </div>

        <div class="card">
            <div class="card-header"><h2 class="card-title">Autenticación con Google y copia en Google Sheets</h2></div>
            <div class="card-body grid gap-4 md:grid-cols-3">
                <x-field name="google_signup" label="Crear cuenta de empleado al entrar con Google si el correo no existe" type="checkbox" :value="$v['google_signup']" class="md:col-span-3" />
                <x-field name="sheets_spreadsheet_id" label="ID de la hoja de cálculo" :value="$v['sheets_spreadsheet_id']" class="md:col-span-2" help="Comparte la hoja con el correo de la cuenta de servicio." />
                <x-field name="sheets_credentials_path" label="Credenciales (ruta en storage/)" :value="$v['sheets_credentials_path']" placeholder="app/private/google-service-account.json" />
            </div>
            <p class="px-5 pb-4 text-xs text-gray-500">El login con Google usa <code>GOOGLE_CLIENT_ID</code> y <code>GOOGLE_CLIENT_SECRET</code> del archivo <code>.env</code> ({{ config('services.google.client_id') ? 'configurado' : 'no configurado' }}). Sin hoja configurada, la copia se guarda como CSV en <code>storage/app/private/backups</code>.</p>
        </div>

        <div class="flex justify-end">
            <button class="btn btn-primary">Guardar reglas</button>
        </div>
    </form>

    <form method="POST" action="{{ route('settings.backup') }}" class="mt-4 flex justify-end">
        @csrf
        <button class="btn btn-secondary"><x-icon name="download" class="h-4 w-4" /> Ejecutar copia de seguridad ahora</button>
    </form>
</x-app-layout>
