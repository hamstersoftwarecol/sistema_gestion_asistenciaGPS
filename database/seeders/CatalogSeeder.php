<?php

namespace Database\Seeders;

use App\Models\Holiday;
use App\Models\LeaveType;
use App\Models\PayComponent;
use Illuminate\Database\Seeder;

/**
 * Catálogos base: festivos de Colombia, tipos de permiso y componentes de pago.
 */
class CatalogSeeder extends Seeder
{
    public function run(): void
    {
        $holidays = [
            '2026-01-01' => 'Año Nuevo', '2026-01-12' => 'Día de los Reyes Magos', '2026-03-23' => 'Día de San José',
            '2026-04-02' => 'Jueves Santo', '2026-04-03' => 'Viernes Santo', '2026-05-01' => 'Día del Trabajo',
            '2026-05-18' => 'Ascensión del Señor', '2026-06-08' => 'Corpus Christi', '2026-06-15' => 'Sagrado Corazón',
            '2026-06-29' => 'San Pedro y San Pablo', '2026-07-20' => 'Día de la Independencia', '2026-08-07' => 'Batalla de Boyacá',
            '2026-08-17' => 'Asunción de la Virgen', '2026-10-12' => 'Día de la Raza', '2026-11-02' => 'Todos los Santos',
            '2026-11-16' => 'Independencia de Cartagena', '2026-12-08' => 'Inmaculada Concepción', '2026-12-25' => 'Navidad',
        ];

        foreach ($holidays as $date => $name) {
            Holiday::updateOrCreate(['date' => $date], ['name' => $name, 'paid' => true]);
        }

        $types = [
            ['name' => 'Vacaciones', 'code' => 'VAC', 'paid' => true, 'annual_quota' => 15, 'carry_forward' => true, 'max_carry_forward' => 15, 'encashable' => true, 'color' => '#0ea5e9'],
            ['name' => 'Incapacidad médica', 'code' => 'INC', 'paid' => true, 'annual_quota' => 0, 'requires_attachment' => true, 'color' => '#ef4444'],
            ['name' => 'Calamidad doméstica', 'code' => 'CAL', 'paid' => true, 'annual_quota' => 3, 'color' => '#f59e0b'],
            ['name' => 'Licencia no remunerada', 'code' => 'LNR', 'paid' => false, 'annual_quota' => 0, 'color' => '#6b7280'],
        ];

        foreach ($types as $type) {
            LeaveType::updateOrCreate(['code' => $type['code']], $type + ['active' => true]);
        }

        $components = [
            ['name' => 'Salud (aporte empleado)', 'code' => 'SALUD', 'type' => 'deduction', 'calc_type' => 'percent', 'amount' => 4, 'applies_to_all' => true],
            ['name' => 'Pensión (aporte empleado)', 'code' => 'PENSION', 'type' => 'deduction', 'calc_type' => 'percent', 'amount' => 4, 'applies_to_all' => true],
            ['name' => 'Auxilio de transporte', 'code' => 'TRANSP', 'type' => 'earning', 'calc_type' => 'fixed', 'amount' => 200000],
            ['name' => 'Bono de campo', 'code' => 'CAMPO', 'type' => 'earning', 'calc_type' => 'fixed', 'amount' => 150000],
            ['name' => 'Fondo de empleados', 'code' => 'FONDO', 'type' => 'deduction', 'calc_type' => 'fixed', 'amount' => 50000],
        ];

        foreach ($components as $component) {
            PayComponent::updateOrCreate(['code' => $component['code']], $component + ['active' => true]);
        }
    }
}
