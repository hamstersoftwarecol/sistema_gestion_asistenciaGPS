<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\PayrollPeriod;
use App\Models\Site;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

/**
 * Registro de entrada/salida con selfie y GPS: aplica reglas de RR. HH. (vinculación de
 * dispositivo, fichaje sin conexión, precisión GPS, geocerca) y guarda la fila de asistencia.
 */
class PunchService
{
    public function __construct(
        protected AttendanceCalculator $calculator,
        protected SelfieStore $selfies,
        protected WorkCalendar $calendar,
    ) {}

    /**
     * @param  array{type: string, latitude: float, longitude: float, accuracy?: ?float, selfie?: ?string, device_id?: ?string, captured_at?: ?string, offline?: bool}  $data
     * @return array{attendance: Attendance, site: ?Site, distance: ?int, out_of_zone: bool, message: string}
     *
     * @throws RuntimeException cuando una regla impide registrar la marcación
     */
    public function punch(User $user, array $data): array
    {
        $offline = (bool) ($data['offline'] ?? false);
        $at = $this->resolvePunchTime($data['captured_at'] ?? null, $offline);

        $this->guardDevice($user, $data['device_id'] ?? null);

        if (setting('require_selfie') && empty($data['selfie'])) {
            throw new RuntimeException('Debes tomar una selfie para registrar la asistencia.');
        }

        $accuracy = isset($data['accuracy']) ? (int) round((float) $data['accuracy']) : null;
        $maxAccuracy = (int) setting('max_gps_accuracy_m');

        if ($maxAccuracy > 0 && $accuracy !== null && $accuracy > $maxAccuracy) {
            throw new RuntimeException("La precisión del GPS ({$accuracy} m) es insuficiente. Máximo permitido: {$maxAccuracy} m.");
        }

        $lat = (float) $data['latitude'];
        $lng = (float) $data['longitude'];
        $nearest = Geo::nearestSite($lat, $lng);
        $outOfZone = $nearest === null || ! $nearest['inside'];

        if ($outOfZone && setting('out_of_zone_action') === 'block') {
            $where = $nearest ? "Estás a {$nearest['distance']} m de {$nearest['site']->name}." : 'No hay sitios configurados.';
            throw new RuntimeException("Fuera de la geocerca: {$where} La política actual bloquea los registros fuera de zona.");
        }

        return DB::transaction(function () use ($user, $data, $at, $offline, $accuracy, $lat, $lng, $nearest, $outOfZone) {
            $attendance = $data['type'] === 'in'
                ? $this->checkIn($user, $at)
                : $this->checkOut($user, $at);

            if (PayrollPeriod::dateIsLocked($attendance->date)) {
                throw new RuntimeException('El período de nómina de esta fecha está bloqueado.');
            }

            $prefix = $data['type'] === 'in' ? 'check_in' : 'check_out';
            $selfie = ! empty($data['selfie']) ? $this->storeSelfie($data['selfie'], $user->id) : null;

            $attendance->fill([
                "{$prefix}_at" => $at,
                "{$prefix}_lat" => $lat,
                "{$prefix}_lng" => $lng,
                "{$prefix}_accuracy" => $accuracy,
                "{$prefix}_distance_m" => $nearest['distance'] ?? null,
                "{$prefix}_out_of_zone" => $outOfZone,
                "{$prefix}_selfie" => $selfie,
                'device_id' => $data['device_id'] ?? $attendance->device_id,
            ]);

            if ($data['type'] === 'in') {
                $attendance->site_id = $nearest['site']->id ?? null;
                $attendance->source = $offline ? 'offline' : 'app';
            } else {
                $attendance->check_out_site_id = $nearest['site']->id ?? null;
            }

            if ($offline) {
                $attendance->is_offline = true;
            }

            $this->calculator->recalculate($attendance)->save();

            $verb = $data['type'] === 'in' ? 'Entrada' : 'Salida';
            $where = $nearest ? "{$nearest['site']->name} ({$nearest['distance']} m)" : 'sin sitio cercano';

            return [
                'attendance' => $attendance,
                'site' => $nearest['site'] ?? null,
                'distance' => $nearest['distance'] ?? null,
                'out_of_zone' => $outOfZone,
                'message' => "{$verb} registrada a las {$at->format('H:i')} en {$where}".($outOfZone ? ' — FUERA DE ZONA' : '').'.',
            ];
        });
    }

    protected function resolvePunchTime(?string $capturedAt, bool $offline): Carbon
    {
        if (! $offline || ! $capturedAt) {
            return now();
        }

        if (! setting('allow_offline_punch')) {
            throw new RuntimeException('Los registros sin conexión están deshabilitados por la política de RR. HH.');
        }

        $at = Carbon::parse($capturedAt)->setTimezone(config('app.timezone'));

        if ($at->greaterThan(now()->addMinutes(5))) {
            throw new RuntimeException('La hora capturada sin conexión está en el futuro.');
        }

        $maxHours = (int) setting('offline_max_hours');

        if ($at->lessThan(now()->subHours($maxHours))) {
            throw new RuntimeException("El registro sin conexión tiene más de {$maxHours} h y ya no puede sincronizarse. Solicita una corrección.");
        }

        return $at;
    }

    protected function guardDevice(User $user, ?string $deviceId): void
    {
        if (! setting('device_binding')) {
            return;
        }

        if (! $deviceId) {
            throw new RuntimeException('No se pudo identificar el dispositivo.');
        }

        if (! $user->device_id) {
            $user->forceFill(['device_id' => $deviceId, 'device_bound_at' => now()])->save();

            return;
        }

        if (! hash_equals($user->device_id, $deviceId)) {
            throw new RuntimeException('Este dispositivo no está vinculado a tu cuenta. Pide a RR. HH. que restablezca la vinculación.');
        }
    }

    protected function checkIn(User $user, Carbon $at): Attendance
    {
        $date = $this->workDateFor($user, $at);

        $attendance = Attendance::firstOrNew([
            'user_id' => $user->id,
            'date' => $date->toDateString(),
        ]);

        if ($attendance->check_in_at) {
            throw new RuntimeException('Ya registraste tu entrada el '.$date->format('d/m/Y').' a las '.$attendance->check_in_at->format('H:i').'.');
        }

        if ($attendance->exists && $attendance->status === 'on_leave') {
            throw new RuntimeException('Tienes un permiso aprobado para hoy. Cancélalo antes de registrar asistencia.');
        }

        $schedule = $this->calendar->scheduleFor($user, $date);
        $attendance->setRelation('user', $user);
        $attendance->fill([
            'shift_id' => $schedule['shift']?->id,
            'status' => 'present',
        ]);

        return $attendance;
    }

    /**
     * Día laboral al que pertenece una entrada: un turno nocturno que empezó ayer y aún no
     * termina (p. ej. entrada a las 00:30 en un turno de 22:00 a 06:00) se asigna al día anterior.
     */
    protected function workDateFor(User $user, Carbon $at): Carbon
    {
        $yesterday = $at->copy()->subDay()->startOfDay();
        $shift = $this->calendar->scheduleFor($user, $yesterday)['shift'];

        if ($shift && $shift->isOvernight()) {
            [$start, $end] = $shift->windowFor($yesterday);

            $alreadyIn = Attendance::where('user_id', $user->id)
                ->whereDate('date', $yesterday->toDateString())
                ->whereNotNull('check_in_at')
                ->exists();

            if (! $alreadyIn && $at->greaterThan($start) && $at->lessThan($end)) {
                return $yesterday;
            }
        }

        return $at->copy()->startOfDay();
    }

    protected function checkOut(User $user, Carbon $at): Attendance
    {
        $attendance = Attendance::where('user_id', $user->id)
            ->whereNotNull('check_in_at')
            ->whereNull('check_out_at')
            ->where('check_in_at', '>=', $at->copy()->subHours(20))
            ->where('check_in_at', '<=', $at)
            ->latest('check_in_at')
            ->first();

        if (! $attendance) {
            throw new RuntimeException('No tienes una entrada abierta. Si olvidaste marcarla, envía una solicitud de corrección.');
        }

        $attendance->setRelation('user', $user);

        return $attendance;
    }

    protected function storeSelfie(string $dataUrl, int $userId): string
    {
        try {
            return $this->selfies->store($dataUrl, $userId);
        } catch (InvalidArgumentException $e) {
            throw new RuntimeException($e->getMessage(), previous: $e);
        }
    }
}
