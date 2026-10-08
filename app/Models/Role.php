<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'slug', 'description', 'permissions', 'is_system'])]
class Role extends Model
{
    /**
     * Catálogo de permisos agrupados que se muestran en la matriz de roles.
     */
    public const PERMISSIONS = [
        'Asistencia' => [
            'attendance.self' => 'Registrar mi asistencia (selfie + GPS)',
            'attendance.view' => 'Ver asistencia del equipo',
            'attendance.mark' => 'Marcar asistencia (masiva e individual)',
            'attendance.approve' => 'Aprobar correcciones y horas extras',
            'live_map.view' => 'Ver mapa en vivo',
            'shifts.assign' => 'Asignar turnos y zonas (calendario)',
        ],
        'Permisos y licencias' => [
            'leave.request' => 'Solicitar permisos',
            'leave.approve' => 'Aprobar permisos del equipo',
            'leave.manage' => 'Tipos de permiso, cuotas y saldos',
        ],
        'Organización' => [
            'employees.manage' => 'Gestionar empleados',
            'org.manage' => 'Departamentos, sitios, turnos y festivos',
            'documents.manage' => 'Documentos de empleados',
            'scope.all' => 'Ver a todos los empleados (no solo su equipo)',
        ],
        'Nómina' => [
            'payroll.view_own' => 'Ver mis recibos de pago',
            'payroll.manage' => 'Generar nómina y bloquear períodos',
            'advances.manage' => 'Anticipos y préstamos',
            'contractors.manage' => 'Contratistas, componentes de pago y facturas',
            'settlements.manage' => 'Liquidaciones finales',
        ],
        'Administración' => [
            'reports.view' => 'Informes y exportación CSV',
            'settings.manage' => 'Reglas de RR. HH. y copias de seguridad',
            'roles.manage' => 'Roles y permisos',
        ],
    ];

    protected function casts(): array
    {
        return [
            'permissions' => 'array',
            'is_system' => 'boolean',
        ];
    }

    public static function allPermissionKeys(): array
    {
        return collect(self::PERMISSIONS)->flatMap(fn ($group) => array_keys($group))->all();
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function hasPermission(string $permission): bool
    {
        if ($this->slug === 'admin') {
            return true;
        }

        $permissions = $this->permissions ?? [];

        return in_array('*', $permissions, true) || in_array($permission, $permissions, true);
    }
}
