# GeoAsistencia RR. HH. — guía para agentes

Aplicación Laravel 13 + Breeze (Blade/Alpine) + SQLite. Interfaz y mensajes en español.

## Comandos

- Pruebas: `php artisan test`
- Estilo: `vendor/bin/pint` (CI ejecuta `pint --test`)
- Datos de demostración: `php artisan migrate:fresh --seed` (usuarios `*@demo.test`, contraseña `password`)
- Assets: `npm run build`

## Convenciones

- La tabla `attendances` es la única fuente de verdad: informes, nómina, liquidaciones y facturas se calculan desde sus filas. Cualquier cambio de horas debe pasar por `AttendanceCalculator::recalculate()`.
- Las fechas de solo día usan el cast `App\Casts\DateOnly` (se guardan como `Y-m-d`).
- Las reglas configurables se leen con `setting('clave')`; los valores por defecto están en `App\Models\Setting::DEFAULTS`.
- Permisos: claves en `Role::PERMISSIONS`, resueltas por `Gate::before` (`can:payroll.manage`, `@can(...)`). Los gerentes solo ven a su equipo vía `User::scopeVisibleTo()`; los controladores usan `authorizeEmployee()` / `authorizeReview()`.
- Ninguna escritura de asistencia debe ocurrir en un período de nómina bloqueado (`PayrollPeriod::dateIsLocked()`).
- Selfies y documentos se guardan en el disco privado `local` y se sirven solo mediante rutas autorizadas.
