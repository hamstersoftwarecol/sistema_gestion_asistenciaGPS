<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    public const PERMISSIONS = [
        'admin' => ['*'],
        'hr' => [
            'attendance.self', 'attendance.view', 'attendance.mark', 'attendance.approve', 'live_map.view', 'shifts.assign',
            'leave.request', 'leave.approve', 'leave.manage', 'employees.manage', 'org.manage', 'documents.manage', 'scope.all',
            'payroll.view_own', 'payroll.manage', 'advances.manage', 'contractors.manage', 'settlements.manage',
            'reports.view', 'settings.manage',
        ],
        'manager' => [
            'attendance.self', 'attendance.view', 'attendance.mark', 'attendance.approve', 'live_map.view', 'shifts.assign',
            'leave.request', 'leave.approve', 'payroll.view_own', 'reports.view',
        ],
        'employee' => ['attendance.self', 'leave.request', 'payroll.view_own'],
    ];

    public function run(): void
    {
        $roles = [
            'admin' => ['Administrador', 'Acceso total, incluida la gestión de roles y permisos.'],
            'hr' => ['RR. HH.', 'Gestiona empleados, reglas, permisos, nómina, liquidaciones e informes de toda la empresa.'],
            'manager' => ['Gerente', 'Supervisa a su equipo: mapa en vivo, registro de asistencia, turnos y aprobaciones.'],
            'employee' => ['Empleado', 'Registra su asistencia con selfie + GPS, solicita permisos y consulta sus recibos.'],
        ];

        foreach ($roles as $slug => [$name, $description]) {
            Role::updateOrCreate(['slug' => $slug], [
                'name' => $name,
                'description' => $description,
                'permissions' => self::PERMISSIONS[$slug],
                'is_system' => true,
            ]);
        }
    }
}
