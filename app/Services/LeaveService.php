<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\LeaveBalance;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\PayrollPeriod;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LeaveService
{
    public function __construct(protected WorkCalendar $calendar) {}

    /**
     * Días hábiles que consume un permiso: excluye fines de semana y festivos configurados.
     */
    public function countDays(Carbon $from, Carbon $to): float
    {
        return (float) count($this->calendar->workingDays($from, $to));
    }

    public function balanceFor(User $user, LeaveType $type, int $year): LeaveBalance
    {
        return LeaveBalance::firstOrCreate(
            ['user_id' => $user->id, 'leave_type_id' => $type->id, 'year' => $year],
            ['allocated' => $type->annual_quota, 'carried_forward' => 0, 'used' => 0],
        );
    }

    public function pendingDays(User $user, LeaveType $type, int $year, ?int $exceptId = null): float
    {
        return (float) LeaveRequest::where('user_id', $user->id)
            ->where('leave_type_id', $type->id)
            ->where('status', 'pending')
            ->whereYear('start_date', $year)
            ->when($exceptId, fn ($q) => $q->whereKeyNot($exceptId))
            ->sum('days');
    }

    /**
     * Valida y crea una solicitud de permiso.
     */
    public function request(User $user, LeaveType $type, Carbon $from, Carbon $to, ?string $reason, ?string $attachment = null): LeaveRequest
    {
        if ($to->lessThan($from)) {
            throw ValidationException::withMessages(['end_date' => 'La fecha final debe ser igual o posterior a la inicial.']);
        }

        $days = $this->countDays($from, $to);

        if ($days <= 0) {
            throw ValidationException::withMessages(['start_date' => 'El rango seleccionado no contiene días hábiles.']);
        }

        $overlap = LeaveRequest::where('user_id', $user->id)
            ->whereIn('status', ['pending', 'approved'])
            ->whereDate('start_date', '<=', $to->toDateString())
            ->whereDate('end_date', '>=', $from->toDateString())
            ->exists();

        if ($overlap) {
            throw ValidationException::withMessages(['start_date' => 'Ya tienes un permiso pendiente o aprobado que se cruza con estas fechas.']);
        }

        if ($type->hasQuota()) {
            $balance = $this->balanceFor($user, $type, $from->year);
            $available = $balance->available() - $this->pendingDays($user, $type, $from->year);

            if ($days > $available) {
                throw ValidationException::withMessages([
                    'leave_type_id' => "Saldo insuficiente: solicitas {$days} día(s) y tienes {$available} disponible(s).",
                ]);
            }
        }

        return LeaveRequest::create([
            'user_id' => $user->id,
            'leave_type_id' => $type->id,
            'start_date' => $from,
            'end_date' => $to,
            'days' => $days,
            'reason' => $reason,
            'attachment' => $attachment,
            'status' => 'pending',
        ]);
    }

    /**
     * Aprueba el permiso, descuenta el saldo y escribe una fila "on_leave" por cada día hábil.
     */
    public function approve(LeaveRequest $leave, User $reviewer, ?string $note = null): void
    {
        DB::transaction(function () use ($leave, $reviewer, $note) {
            $leave->loadMissing(['user', 'leaveType']);

            if ($leave->leaveType->hasQuota()) {
                $balance = $this->balanceFor($leave->user, $leave->leaveType, $leave->start_date->year);
                $balance->increment('used', $leave->days);
            }

            foreach ($this->calendar->workingDays($leave->start_date, $leave->end_date) as $day) {
                if (PayrollPeriod::dateIsLocked($day)) {
                    continue;
                }

                $row = Attendance::firstOrNew(['user_id' => $leave->user_id, 'date' => $day->toDateString()]);

                if ($row->check_in_at) {
                    continue;
                }

                $row->fill([
                    'status' => 'on_leave',
                    'day_type' => 'workday',
                    'leave_request_id' => $leave->id,
                    'leave_type_id' => $leave->leave_type_id,
                    'source' => 'leave',
                    'marked_by' => $reviewer->id,
                ])->save();
            }

            $leave->update([
                'status' => 'approved',
                'reviewed_by' => $reviewer->id,
                'reviewed_at' => now(),
                'review_note' => $note,
            ]);
        });
    }

    public function reject(LeaveRequest $leave, User $reviewer, ?string $note = null): void
    {
        $leave->update([
            'status' => 'rejected',
            'reviewed_by' => $reviewer->id,
            'reviewed_at' => now(),
            'review_note' => $note,
        ]);
    }

    /**
     * Cancela un permiso; si estaba aprobado devuelve el saldo y elimina sus filas de asistencia.
     */
    public function cancel(LeaveRequest $leave): void
    {
        DB::transaction(function () use ($leave) {
            if ($leave->status === 'approved') {
                $leave->loadMissing(['user', 'leaveType']);

                if ($leave->leaveType->hasQuota()) {
                    $balance = $this->balanceFor($leave->user, $leave->leaveType, $leave->start_date->year);
                    $balance->update(['used' => max(0, $balance->used - $leave->days)]);
                }

                Attendance::where('leave_request_id', $leave->id)
                    ->whereNull('check_in_at')
                    ->get()
                    ->reject(fn (Attendance $row) => PayrollPeriod::dateIsLocked($row->date))
                    ->each->delete();
            }

            $leave->update(['status' => 'cancelled']);
        });
    }

    /**
     * Asigna las cuotas anuales a todos los empleados activos y traslada el saldo permitido del año anterior.
     */
    public function allocateYear(int $year): int
    {
        $count = 0;
        $types = LeaveType::active()->where('annual_quota', '>', 0)->get();

        User::active()->each(function (User $user) use ($types, $year, &$count) {
            foreach ($types as $type) {
                $carried = 0.0;

                if ($type->carry_forward) {
                    $previous = LeaveBalance::where([
                        'user_id' => $user->id, 'leave_type_id' => $type->id, 'year' => $year - 1,
                    ])->first();
                    $carried = $previous ? min(max(0, $previous->available()), $type->max_carry_forward) : 0.0;
                }

                $allocated = $type->annual_quota;

                // Prorrateo para quienes ingresan durante el año.
                if ($user->hire_date && $user->hire_date->year === $year) {
                    $allocated = round($type->annual_quota * (13 - $user->hire_date->month) / 12, 1);
                }

                LeaveBalance::updateOrCreate(
                    ['user_id' => $user->id, 'leave_type_id' => $type->id, 'year' => $year],
                    ['allocated' => $allocated, 'carried_forward' => $carried],
                );
                $count++;
            }
        });

        return $count;
    }
}
