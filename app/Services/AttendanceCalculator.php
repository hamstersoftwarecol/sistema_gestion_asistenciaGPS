<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\Shift;
use Carbon\Carbon;
use Carbon\CarbonInterface;

/**
 * Calcula los minutos trabajados, retrasos, horas extras y horas nocturnas de una fila de asistencia.
 * Todos los valores que usan la nómina y los informes se derivan de aquí.
 */
class AttendanceCalculator
{
    public function __construct(protected WorkCalendar $calendar) {}

    public function recalculate(Attendance $attendance): Attendance
    {
        $schedule = $this->calendar->scheduleFor($attendance->user, $attendance->date);

        if (! $attendance->shift_id && $schedule['shift']) {
            $attendance->shift_id = $schedule['shift']->id;
        }

        $shift = $attendance->shift_id ? Shift::find($attendance->shift_id) : null;
        $attendance->day_type = $schedule['day_type'];

        $previousOvertime = (int) $attendance->getOriginal('overtime_minutes');

        $attendance->fill([
            'late_minutes' => 0,
            'early_leave_minutes' => 0,
            'worked_minutes' => 0,
            'regular_minutes' => 0,
            'holiday_minutes' => 0,
            'night_minutes' => 0,
            'overtime_minutes' => 0,
        ]);

        // Filas sin marcación (ausencia, permiso, festivo) conservan el estado asignado.
        if (! $attendance->check_in_at) {
            $attendance->fill(['approved_overtime_minutes' => 0, 'overtime_status' => 'none']);

            return $attendance;
        }

        $checkIn = Carbon::parse($attendance->check_in_at);
        $isWorkday = $attendance->day_type === 'workday';

        if ($shift && $isWorkday) {
            [$start] = $shift->windowFor($attendance->date);
            $lateBy = max(0, (int) $start->diffInMinutes($checkIn, false));
            $attendance->late_minutes = $lateBy > $shift->grace_minutes ? $lateBy : 0;
        } elseif ($isWorkday) {
            $attendance->late_minutes = 0;
        }

        if (! $attendance->check_out_at) {
            if ($attendance->status !== 'missed_punch') {
                $attendance->status = $attendance->late_minutes > 0 ? 'late' : 'present';
            }
            $attendance->fill(['approved_overtime_minutes' => 0, 'overtime_status' => 'none']);

            return $attendance;
        }

        $checkOut = Carbon::parse($attendance->check_out_at);
        $raw = max(0, (int) $checkIn->diffInMinutes($checkOut, false));
        $break = $shift?->break_minutes ?? 60;
        $worked = $raw > (int) setting('break_deduct_after_minutes') ? max(0, $raw - $break) : $raw;
        $scheduled = $shift?->scheduledMinutes() ?? (int) round((float) setting('standard_daily_hours') * 60);

        $attendance->worked_minutes = $worked;
        $attendance->night_minutes = $this->nightMinutes(
            $checkIn,
            $checkOut,
            $shift?->night_start ?? '21:00',
            $shift?->night_end ?? '06:00',
        );

        if ($shift && $isWorkday) {
            [, $end] = $shift->windowFor($attendance->date);
            $attendance->early_leave_minutes = max(0, (int) $checkOut->diffInMinutes($end, false));
        }

        if ($isWorkday) {
            $attendance->regular_minutes = min($worked, $scheduled);
            $extra = $worked - $scheduled;
            $cap = $shift?->overtime_cap_minutes ?? 240;
            $attendance->overtime_minutes = $extra >= (int) setting('overtime_min_minutes') ? min($extra, $cap) : 0;

            if ($worked < (int) setting('half_day_minutes')) {
                $attendance->status = 'half_day';
            } elseif ((int) setting('late_half_day_after_minutes') > 0
                && $attendance->late_minutes >= (int) setting('late_half_day_after_minutes')) {
                $attendance->status = 'half_day';
            } else {
                $attendance->status = $attendance->late_minutes > 0 ? 'late' : 'present';
            }
        } else {
            // Trabajo en descanso o festivo: todas las horas se pagan con el recargo correspondiente.
            $attendance->holiday_minutes = $worked;
            $attendance->status = 'present';
        }

        $this->resolveOvertimeStatus($attendance, $previousOvertime);

        return $attendance;
    }

    protected function resolveOvertimeStatus(Attendance $attendance, int $previousOvertime): void
    {
        $overtime = $attendance->overtime_minutes;

        if ($overtime <= 0) {
            $attendance->fill(['overtime_status' => 'none', 'approved_overtime_minutes' => 0]);

            return;
        }

        if (! setting('overtime_requires_approval')) {
            $attendance->fill(['overtime_status' => 'approved', 'approved_overtime_minutes' => $overtime]);

            return;
        }

        $alreadyReviewed = in_array($attendance->overtime_status, ['approved', 'rejected'], true);

        if ($alreadyReviewed && $overtime === $previousOvertime) {
            $attendance->approved_overtime_minutes = min($attendance->approved_overtime_minutes, $overtime);

            return;
        }

        $attendance->fill(['overtime_status' => 'pending', 'approved_overtime_minutes' => 0]);
    }

    /**
     * Minutos de la jornada que caen dentro de la franja nocturna (que puede cruzar la medianoche).
     */
    public function nightMinutes(CarbonInterface $in, CarbonInterface $out, string $nightStart, string $nightEnd): int
    {
        if ($out->lessThanOrEqualTo($in)) {
            return 0;
        }

        $total = 0;
        $day = Carbon::parse($in->toDateString())->subDay();
        $last = Carbon::parse($out->toDateString());

        while ($day->lessThanOrEqualTo($last)) {
            $windowStart = Carbon::parse($day->toDateString().' '.$nightStart);
            $windowEnd = Carbon::parse($day->toDateString().' '.$nightEnd);

            if ($windowEnd->lessThanOrEqualTo($windowStart)) {
                $windowEnd->addDay();
            }

            $from = $in->greaterThan($windowStart) ? $in : $windowStart;
            $to = $out->lessThan($windowEnd) ? $out : $windowEnd;

            if ($to->greaterThan($from)) {
                $total += (int) $from->diffInMinutes($to);
            }

            $day->addDay();
        }

        return $total;
    }
}
