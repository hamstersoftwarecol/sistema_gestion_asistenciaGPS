<?php

namespace App\Console\Commands;

use App\Models\Attendance;
use App\Models\AttendanceCorrection;
use App\Models\EmployeeDocument;
use App\Models\LeaveRequest;
use App\Models\User;
use App\Notifications\HrAlert;
use App\Services\WorkCalendar;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

#[Signature('hr:alerts')]
#[Description('Envía alertas: retrasos sin marcación, salidas no registradas, documentos por vencer y aprobaciones pendientes')]
class SendHrAlerts extends Command
{
    public function handle(WorkCalendar $calendar): int
    {
        $today = now()->startOfDay();
        $sent = 0;

        $hr = User::active()->whereHas('role', fn ($q) => $q->whereIn('slug', ['admin', 'hr']))->get();

        // 1. Empleados con turno que aún no registran entrada.
        $lateAfter = (int) setting('alert_late_after_minutes');

        User::active()->with(['manager', 'department.manager'])->each(function (User $user) use ($calendar, $today, $lateAfter, $hr, &$sent) {
            $schedule = $calendar->scheduleFor($user, $today);

            if (! $schedule['scheduled'] || ! $schedule['shift']) {
                return;
            }

            [$start] = $schedule['shift']->windowFor($today);

            if (now()->lessThan($start->copy()->addMinutes($lateAfter))) {
                return;
            }

            $hasRow = Attendance::where('user_id', $user->id)->whereDate('date', $today->toDateString())->exists();

            if ($hasRow || ! Cache::add("alert:no-show:{$user->id}:{$today->toDateString()}", true, now()->addDay())) {
                return;
            }

            $this->notify(
                $this->supervisorsOf($user, $hr),
                'Empleado sin marcación',
                "{$user->name} no ha registrado entrada (turno desde las {$schedule['shift']->start_time}).",
                route('register.index', ['date' => $today->toDateString()]),
                'warning',
            );
            $sent++;
        });

        // 2. Salidas no registradas: recordatorio al empleado.
        $hours = (int) setting('alert_missed_checkout_hours');

        Attendance::with(['user', 'shift'])
            ->whereNotNull('check_in_at')
            ->whereNull('check_out_at')
            ->whereDate('date', '>=', $today->copy()->subDay()->toDateString())
            ->get()
            ->each(function (Attendance $a) use ($hours, &$sent) {
                $end = $a->shift ? $a->shift->windowFor($a->date)[1] : $a->check_in_at->copy()->addHours(9);

                if (now()->lessThan($end->copy()->addHours($hours))) {
                    return;
                }

                if (! Cache::add("alert:checkout:{$a->id}", true, now()->addDay())) {
                    return;
                }

                $a->user->notify(new HrAlert(
                    'Salida sin registrar',
                    'No registraste tu salida del '.$a->date->format('d/m/Y').'. Márcala o solicita una corrección.',
                    route('my-attendance.index'),
                    'warning',
                ));
                $sent++;
            });

        // 3. Documentos por vencer (una vez al día).
        if (Cache::add('alert:documents:'.$today->toDateString(), true, now()->addDay())) {
            $docs = EmployeeDocument::with('user')
                ->whereNotNull('expires_on')
                ->whereDate('expires_on', '<=', now()->addDays((int) setting('document_expiry_alert_days'))->toDateString())
                ->whereDate('expires_on', '>=', $today->toDateString())
                ->get();

            if ($docs->isNotEmpty()) {
                $list = $docs->map(fn ($d) => "{$d->user->name}: {$d->title} ({$d->expires_on->format('d/m/Y')})")->implode('; ');
                $this->notify($hr, 'Documentos por vencer', "{$docs->count()} documento(s) vencen pronto. {$list}", route('documents.index', ['expiring' => 1]), 'warning');
                $sent++;
            }
        }

        // 4. Resumen diario de aprobaciones pendientes para gerentes y RR. HH.
        if (now()->hour >= 8 && Cache::add('alert:pending:'.$today->toDateString(), true, now()->addDay())) {
            $approvers = User::active()->get()->filter(fn (User $u) => $u->hasPermission('attendance.approve') || $u->hasPermission('leave.approve'));

            foreach ($approvers as $approver) {
                $team = User::visibleTo($approver)->whereKeyNot($approver->id)->pluck('id');
                $corrections = AttendanceCorrection::where('status', 'pending')->whereIn('user_id', $team)->count();
                $leaves = LeaveRequest::where('status', 'pending')->whereIn('user_id', $team)->count();
                $overtime = Attendance::where('overtime_status', 'pending')->whereIn('user_id', $team)->count();

                if ($corrections + $leaves + $overtime === 0) {
                    continue;
                }

                $approver->notify(new HrAlert(
                    'Aprobaciones pendientes',
                    "Tienes {$corrections} corrección(es), {$leaves} permiso(s) y {$overtime} registro(s) de horas extra por revisar.",
                    route('dashboard'),
                ));
                $sent++;
            }
        }

        $this->info("Alertas enviadas: {$sent}");

        return self::SUCCESS;
    }

    /**
     * @return Collection<int, User>
     */
    protected function supervisorsOf(User $user, Collection $hr): Collection
    {
        return collect([$user->manager, $user->department?->manager])
            ->filter()
            ->merge($hr)
            ->unique('id')
            ->reject(fn (User $u) => $u->id === $user->id);
    }

    protected function notify(Collection $users, string $title, string $message, ?string $url, string $level = 'info'): void
    {
        $users->each(fn (User $u) => $u->notify(new HrAlert($title, $message, $url, $level)));
    }
}
