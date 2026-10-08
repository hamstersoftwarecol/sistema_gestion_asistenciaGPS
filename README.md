# GeoAsistencia RR. HH. — Asistencia con selfie y GPS

Sistema completo de gestión de RR. HH. para **personal de campo**, construido con **PHP 8.3, Laravel 13, Breeze (Blade + Alpine.js) y SQLite**.

Los trabajadores registran su entrada y salida con **selfie + ubicación GPS**; la aplicación elige el **sitio más cercano (Haversine)** y marca los registros **fuera de la geocerca**. Los gerentes aprueban permisos, correcciones y horas extra, y RR. HH. genera la **lista de asistencia y la nómina a partir de las mismas filas**: una única tabla `attendances` alimenta informes, nómina, liquidaciones y facturas de contratistas.

## Funcionalidades

| Capítulo | Dónde está |
| --- | --- |
| Zonas GPS y demostración del mapa en vivo | `Sitios y geocercas` (mapa para fijar centro y radio) · `Mapa en vivo` |
| Inicio de sesión, roles y panel de administración | Breeze + Google (Socialite) · panel por rol con KPIs, gráfico de 7 días y pendientes |
| Mi asistencia: entrada, salida e historial con selfie | `Mi asistencia` (cámara, GPS, sitio más cercano, cola sin conexión) · `Mi historial` |
| Solicitudes de corrección y aprobación | `Mis correcciones` → `Correcciones` (gerente) regulariza la fila de asistencia |
| Registro de asistencia: marcado masivo e individual | `Registro de asistencia` (por fecha, selección múltiple) · detalle con selfies y mapa |
| Calendario de turnos y asignación de zonas | `Calendario de turnos` (empleado × día, asignación masiva por rango y días de la semana) |
| Mapa en vivo de los registros | Leaflet + OpenStreetMap, se actualiza cada **30 s** |
| Roles y control de permisos | `Roles y permisos`: matriz rol × permiso; gerentes ven solo a su equipo |
| Departamentos, sitios de proyecto y radio de geocerca | CRUD con selector en mapa y radio en vivo |
| Turnos, gracia, tarifas de horas extras y festivos | Gracia, descanso no remunerado, franja nocturna, límite de extras, recargos |
| Tipos de licencia, cuotas y saldos | Cuota anual, arrastre, compensable, soporte obligatorio; asignación anual prorrateada |
| Contratistas y componentes de pago | Tarifa por hora/día, IVA; subsidios y deducciones fijos o porcentuales |
| Reglas de RR. HH. | Umbrales de retraso, fichaje sin conexión, vinculación de dispositivo, geocerca, alertas |
| Regularización y aprobación de horas extras | Aprobación total o parcial (minutos) en lote |
| Anticipos, préstamos y documentos | Cuotas descontadas en nómina; documentos privados con alerta de vencimiento |
| Generación de nómina, recibos y bloqueo de período | Mensual y por hora, recibos imprimibles, bloqueo que congela la asistencia |
| Liquidación final y facturas de contratistas | Gratificación, vacaciones compensadas, salario pendiente; factura desde la asistencia |
| Informes de RR. HH. y exportación CSV | Resumen, lista de asistencia (muster roll), retrasos, extras, fuera de zona, permisos, nómina |
| Alertas cron, Google Auth y copia en Google Sheets | `hr:alerts`, `attendance:close-day`, `backup:google-sheets` |

### Cómo se calcula cada fila de asistencia

`App\Services\AttendanceCalculator` deriva todo a partir de la entrada, la salida y el turno del día (calendario → turno por defecto):

- **Retraso**: entrada después de `inicio + gracia`. Un retraso mayor al umbral configurado cuenta como **medio día**.
- **Trabajado**: `salida − entrada − descanso no remunerado` (si la jornada supera el umbral configurado).
- **Extra**: `trabajado − jornada programada`, desde un mínimo y con **tope diario por turno**; queda *pendiente* hasta su aprobación.
- **Nocturnas**: minutos dentro de la franja nocturna del turno (cruza la medianoche).
- **Descanso/festivo**: todo lo trabajado en días de descanso o festivos se paga con su recargo.
- Los turnos nocturnos que empiezan antes de la medianoche se asignan al día de inicio.

### Nómina (desde las mismas filas)

- **Mensual**: salario completo − ausencias − licencias no remuneradas − medios días − descuento por bloques de retrasos.
- **Por hora**: horas ordinarias × tarifa + permisos remunerados.
- Ambos: extras aprobadas × recargo, recargo nocturno, trabajo en descanso/festivo, componentes de pago y cuotas de anticipos.
- **Bloquear el período** congela la asistencia (no se puede marcar, corregir ni editar) y aplica las cuotas a los saldos de anticipos. Solo el administrador desbloquea.

## Roles de demostración

| Correo | Rol | Ve |
| --- | --- | --- |
| `admin@demo.test` | Administrador | Todo, incluidos roles y permisos |
| `rrhh@demo.test` | RR. HH. | Toda la empresa: empleados, reglas, nómina, informes |
| `gerente@demo.test` | Gerente | Su equipo: mapa, registro, turnos, aprobaciones, informes |
| `empleado@demo.test` | Empleado | Su asistencia, correcciones, permisos y recibos |

Contraseña de todas: `password`. El sembrado crea 16 personas, 4 sitios en Bogotá y alrededores, 4 turnos (incluido nocturno), 60 días de asistencia con selfies, festivos de Colombia 2026, permisos, correcciones, anticipos, documentos por vencer, una nómina bloqueada, otra generada, una factura de contratista y una liquidación en borrador.

## Instalación

Requisitos: PHP 8.3+ con `pdo_sqlite` y `gd`, Composer y Node 20+.

```bash
composer install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
php artisan migrate --seed      # datos de demostración
npm install && npm run build
php artisan serve
```

Abre `http://localhost:8000`. La cámara y el GPS del navegador requieren **HTTPS** (o `localhost`).

### Tareas programadas (cron)

```cron
* * * * * cd /ruta/al/proyecto && php artisan schedule:run >> /dev/null 2>&1
```

| Comando | Frecuencia | Qué hace |
| --- | --- | --- |
| `hr:alerts` | cada 15 min | Avisa empleados sin marcación, salidas no registradas, documentos por vencer y aprobaciones pendientes |
| `attendance:close-day {fecha?}` | 00:15 | Crea las ausencias del día anterior y marca las entradas sin salida |
| `backup:google-sheets` | 02:00 | Copia asistencia, empleados y nómina a Google Sheets (o a CSV en `storage/app/private/backups`) |

### Inicio de sesión con Google

1. Crea credenciales OAuth en Google Cloud con la URI de redirección `https://tu-dominio/auth/google/callback`.
2. Completa `GOOGLE_CLIENT_ID`, `GOOGLE_CLIENT_SECRET` y `GOOGLE_REDIRECT_URI` en `.env`.
3. Por defecto solo entran correos ya registrados como empleados; la regla «Crear cuenta al entrar con Google» permite el alta automática.

### Copia de seguridad en Google Sheets

1. Crea una cuenta de servicio con la API de Google Sheets habilitada y descarga su JSON en `storage/app/private/`.
2. Comparte la hoja con el correo de la cuenta de servicio.
3. En **Reglas de RR. HH.** indica el ID de la hoja y la ruta del JSON (relativa a `storage/`). Sin configuración, la copia se guarda en CSV.

### Fichaje sin conexión

`resources/js/punch.js` guarda en `localStorage` las marcaciones hechas sin señal (con la hora real de captura, la selfie y las coordenadas). Al volver la conexión se envían en orden a `POST /my-attendance/sync`; el servidor valida la antigüedad máxima configurada y las marca como *sin conexión*. El *service worker* (`public/sw.js`) guarda la página «Mi asistencia» para poder abrirla sin señal, y la app se puede instalar como PWA.

## Estructura

```
app/Services/
  Geo.php                     Haversine y sitio más cercano
  PunchService.php            Reglas de fichaje (dispositivo, GPS, geocerca, sin conexión, período bloqueado)
  AttendanceCalculator.php    Minutos trabajados, retrasos, extras, nocturnas
  WorkCalendar.php            Fines de semana, festivos y turno/sitio de cada día
  LeaveService.php            Días hábiles, saldos, aprobación y cancelación de permisos
  PayrollService.php          Nómina mensual y por hora, bloqueo y anticipos
  SettlementService.php       Liquidación final
  ContractorInvoiceService.php Facturas de contratistas
  ReportService.php           Informes y CSV
  GoogleSheetsBackup.php      Copia en Google Sheets (JWT de cuenta de servicio)
app/Console/Commands/         hr:alerts, attendance:close-day, backup:google-sheets
resources/js/                 punch.js (cámara + GPS + cola), live-map.js, site-picker.js
```

## Pruebas

```bash
php artisan test
vendor/bin/pint --test
```

Cubren Haversine y geocercas, fichaje con selfie (dentro/fuera de zona, política de bloqueo, retrasos, turnos nocturnos, dispositivo vinculado, cola sin conexión), correcciones, marcado masivo, permisos y saldos, nómina con bloqueo y anticipos, liquidación, facturas, cierre de día, informes CSV, matriz de permisos y un recorrido de todas las pantallas con los datos de demostración.
