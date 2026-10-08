<?php

namespace App\Services;

use App\Models\Holiday;
use App\Models\Shift;
use App\Models\ShiftAssignment;
use App\Models\Site;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Carbon\CarbonPeriod;

/**
 * Calendario laboral: fines de semana configurables, festivos y turnos asignados por fecha.
 * Se registra como "scoped" en el contenedor para cachear consultas durante una petición.
 */
class WorkCalendar
{
    /** @var array<string, Holiday|null> */
    protected array $holidays = [];

    public function isWeekend(CarbonInterface $date): bool
    {
        return in_array($date->dayOfWeek, array_map('intval', (array) setting('weekend_days')), true);
    }

    public function holiday(CarbonInterface $date): ?Holiday
    {
        $key = $date->toDateString();

        if (! array_key_exists($key, $this->holidays)) {
            $this->holidays[$key] = Holiday::whereDate('date', $key)->first();
        }

        return $this->holidays[$key];
    }

    public function preloadHolidays(CarbonInterface $from, CarbonInterface $to): void
    {
        foreach (CarbonPeriod::create($from, $to) as $day) {
            $this->holidays[$day->toDateString()] = null;
        }

        Holiday::whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->get()
            ->each(fn (Holiday $h) => $this->holidays[$h->date->toDateString()] = $h);
    }

    /**
     * Turno, sitio y tipo de día de un empleado en una fecha: la asignación del calendario
     * tiene prioridad sobre el turno y sitio por defecto del empleado.
     *
     * @return array{shift: ?Shift, site: ?Site, day_type: string, assignment: ?ShiftAssignment, scheduled: bool}
     */
    public function scheduleFor(User $user, CarbonInterface $date): array
    {
        $assignment = ShiftAssignment::with(['shift', 'site'])
            ->where('user_id', $user->id)
            ->whereDate('date', $date->toDateString())
            ->first();

        $shift = $assignment?->shift ?? ($assignment?->is_off ? null : $user->shift);
        $site = $assignment?->site ?? $user->site;

        if ($this->holiday($date)) {
            $dayType = 'holiday';
        } elseif ($assignment) {
            $dayType = $assignment->is_off ? 'weekend' : 'workday';
        } else {
            $dayType = $this->isWeekend($date) ? 'weekend' : 'workday';
        }

        return [
            'shift' => $assignment?->is_off ? null : $shift,
            'site' => $site,
            'day_type' => $dayType,
            'assignment' => $assignment,
            'scheduled' => $dayType === 'workday',
        ];
    }

    /**
     * Días laborables (sin fines de semana ni festivos) entre dos fechas inclusive.
     *
     * @return array<int, Carbon>
     */
    public function workingDays(CarbonInterface $from, CarbonInterface $to): array
    {
        $days = [];

        foreach (CarbonPeriod::create($from, $to) as $day) {
            if (! $this->isWeekend($day) && ! $this->holiday($day)) {
                $days[] = $day->copy();
            }
        }

        return $days;
    }
}
