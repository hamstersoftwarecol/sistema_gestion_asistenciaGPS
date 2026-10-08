<?php

namespace App\Console\Commands;

use App\Models\Attendance;
use App\Models\PayrollPeriod;
use App\Models\User;
use App\Services\WorkCalendar;
use Carbon\Carbon;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('attendance:close-day {date? : Fecha a cerrar (por defecto, ayer)}')]
#[Description('Cierra un día: marca ausencias de quienes tenían turno y marcaciones omitidas sin salida')]
class CloseAttendanceDay extends Command
{
    public function handle(WorkCalendar $calendar): int
    {
        $date = Carbon::parse($this->argument('date') ?? now()->subDay()->toDateString());

        if (PayrollPeriod::dateIsLocked($date)) {
            $this->warn("El {$date->toDateString()} pertenece a un período bloqueado.");

            return self::SUCCESS;
        }

        $absent = 0;
        $missed = 0;

        // Entradas sin salida cuyo turno terminó hace más del margen configurado (incluye turnos nocturnos).
        $margin = (int) setting('alert_missed_checkout_hours');

        Attendance::with('shift')
            ->whereNotNull('check_in_at')
            ->whereNull('check_out_at')
            ->where('status', '!=', 'missed_punch')
            ->whereBetween('date', [$date->copy()->subDays(7)->toDateString(), $date->toDateString()])
            ->get()
            ->each(function (Attendance $row) use ($margin, &$missed) {
                $end = $row->shift ? $row->shift->windowFor($row->date)[1] : $row->date->copy()->endOfDay();

                if (now()->lessThan($end->copy()->addHours($margin)) || PayrollPeriod::dateIsLocked($row->date)) {
                    return;
                }

                $row->update(['status' => 'missed_punch']);
                $missed++;
            });

        User::active()
            ->whereNull('contractor_id')
            ->where(fn ($q) => $q->whereNull('hire_date')->orWhereDate('hire_date', '<=', $date->toDateString()))
            ->each(function (User $user) use ($calendar, $date, &$absent) {
                if (Attendance::where('user_id', $user->id)->whereDate('date', $date->toDateString())->exists()) {
                    return;
                }

                $schedule = $calendar->scheduleFor($user, $date);

                if (! $schedule['scheduled']) {
                    return;
                }

                Attendance::create([
                    'user_id' => $user->id,
                    'date' => $date,
                    'shift_id' => $schedule['shift']?->id,
                    'site_id' => $schedule['site']?->id,
                    'day_type' => 'workday',
                    'status' => 'absent',
                    'source' => 'system',
                ]);
                $absent++;
            });

        $this->info("Día {$date->toDateString()} cerrado: {$absent} ausencias, {$missed} marcaciones omitidas.");

        return self::SUCCESS;
    }
}
