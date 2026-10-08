<?php

namespace App\Services;

use App\Models\Advance;
use App\Models\AdvanceRepayment;
use App\Models\Attendance;
use App\Models\PayComponent;
use App\Models\PayrollPeriod;
use App\Models\Payslip;
use App\Models\Shift;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Genera la nómina de un período a partir de las mismas filas de asistencia
 * que alimentan la lista de asistencia y los informes.
 */
class PayrollService
{
    public function __construct(protected WorkCalendar $calendar) {}

    public function generate(PayrollPeriod $period): int
    {
        if ($period->isLocked()) {
            throw new RuntimeException('El período está bloqueado; desbloquéalo para regenerar la nómina.');
        }

        $employees = $this->employeesFor($period->start_date, $period->end_date);

        DB::transaction(function () use ($period, $employees) {
            $period->payslips()->delete();

            foreach ($employees as $employee) {
                $result = $this->calculate($employee, $period->start_date, $period->end_date);
                $this->persist($period, $employee, $result);
            }

            $period->update(['status' => 'processed', 'processed_at' => now()]);
        });

        return $employees->count();
    }

    /**
     * Empleados de planta (no contratistas) con relación laboral vigente en el rango.
     *
     * @return Collection<int, User>
     */
    public function employeesFor(CarbonInterface $from, CarbonInterface $to): Collection
    {
        return User::query()
            ->whereNull('contractor_id')
            ->where(fn ($q) => $q->where('monthly_salary', '>', 0)->orWhere('hourly_rate', '>', 0))
            ->where(fn ($q) => $q->whereNull('hire_date')->orWhereDate('hire_date', '<=', $to->toDateString()))
            ->where(fn ($q) => $q->whereNull('termination_date')->orWhereDate('termination_date', '>=', $from->toDateString()))
            ->where('status', '!=', 'inactive')
            ->with(['payComponents', 'shift'])
            ->orderBy('name')
            ->get();
    }

    /**
     * Calcula devengos y deducciones de un empleado entre dos fechas.
     *
     * @return array{summary: array<string, float|int>, earnings: array<int, array>, deductions: array<int, array>, gross: float, deductions_total: float, net: float}
     */
    public function calculate(User $employee, CarbonInterface $from, CarbonInterface $to, bool $includeAdvances = true): array
    {
        $from = Carbon::parse($from->toDateString());
        $to = Carbon::parse($to->toDateString());

        $windowStart = $employee->hire_date && $employee->hire_date->greaterThan($from) ? $employee->hire_date->copy() : $from->copy();
        $windowEnd = $employee->termination_date && $employee->termination_date->lessThan($to) ? $employee->termination_date->copy() : $to->copy();
        $employedDays = $windowEnd->lessThan($windowStart) ? 0 : (int) $windowStart->diffInDays($windowEnd) + 1;
        $periodDays = (int) $from->diffInDays($to) + 1;

        $rows = Attendance::with(['shift', 'leaveType'])
            ->where('user_id', $employee->id)
            ->whereBetween('date', [$windowStart->toDateString(), $windowEnd->toDateString()])
            ->get();

        $defaultShift = $employee->shift ?? new Shift(['overtime_rate' => 1.25, 'night_rate' => 1.35, 'weekend_rate' => 1.75, 'holiday_rate' => 1.75]);
        $hourly = $employee->hourlyEquivalent();
        $daily = $employee->dailyRate();
        $stdHours = (float) setting('standard_daily_hours');

        $earnings = [];
        $deductions = [];

        $worked = $rows->filter(fn ($r) => $r->worked_minutes > 0 || $r->check_in_at);
        $regularMinutes = (int) $rows->sum('regular_minutes');
        $approvedOt = (int) $rows->sum('approved_overtime_minutes');
        $nightMinutes = (int) $rows->sum('night_minutes');
        $holidayMinutes = (int) $rows->sum('holiday_minutes');
        $paidLeave = $rows->where('status', 'on_leave')->filter(fn ($r) => $r->leaveType?->paid ?? true)->count();
        $unpaidLeave = $rows->where('status', 'on_leave')->filter(fn ($r) => $r->leaveType && ! $r->leaveType->paid)->count();
        $absent = $rows->where('status', 'absent')->where('day_type', 'workday')->count();
        $halfDays = $rows->where('status', 'half_day')->count();
        $lateCount = $rows->where('late_minutes', '>', 0)->count();

        // Salario básico.
        if ($employee->pay_type === 'hourly') {
            $basic = round($regularMinutes / 60 * $hourly, 2);
            $earnings[] = $this->line('BASIC', 'Horas ordinarias', round($regularMinutes / 60, 2), $basic);

            if ($paidLeave > 0) {
                $earnings[] = $this->line('LEAVE', 'Permisos remunerados', $paidLeave, round($paidLeave * $stdHours * $hourly, 2));
            }
        } else {
            $fullPeriod = $employedDays >= $periodDays;
            $basic = $fullPeriod && $periodDays >= 28
                ? (float) $employee->monthly_salary
                : round($daily * min($employedDays, (int) setting('month_days_divisor')), 2);
            $earnings[] = $this->line('BASIC', 'Salario básico', $fullPeriod && $periodDays >= 28 ? (float) setting('month_days_divisor') : $employedDays, $basic);

            if ($absent > 0) {
                $deductions[] = $this->line('ABSENT', 'Ausencias injustificadas', $absent, round($absent * $daily, 2));
            }
            if ($unpaidLeave > 0) {
                $deductions[] = $this->line('UNPAID', 'Licencias no remuneradas', $unpaidLeave, round($unpaidLeave * $daily, 2));
            }
            if ($halfDays > 0) {
                $deductions[] = $this->line('HALFDAY', 'Medios días', $halfDays, round($halfDays * 0.5 * $daily, 2));
            }
        }

        // Umbral de retrasos: cada N llegadas tarde descuentan una fracción de día.
        $perDeduction = (int) setting('lates_per_deduction');
        if ($perDeduction > 0 && $lateCount >= $perDeduction) {
            $blocks = intdiv($lateCount, $perDeduction);
            $days = $blocks * (float) setting('late_deduction_days');
            $deductions[] = $this->line('LATE', "Retrasos ({$lateCount} llegadas tarde)", $days, round($days * $daily, 2));
        }

        // Recargos: horas extra aprobadas, nocturnas y trabajo en descanso/festivo.
        $otPay = 0.0;
        $nightPay = 0.0;
        $holidayPay = 0.0;

        foreach ($rows as $row) {
            $shift = $row->shift ?? $defaultShift;
            $otPay += $row->approved_overtime_minutes / 60 * $hourly * $shift->overtime_rate;
            $nightPay += $row->night_minutes / 60 * $hourly * max(0, $shift->night_rate - 1);
            $rate = $row->day_type === 'holiday' ? $shift->holiday_rate : $shift->weekend_rate;
            $holidayPay += $row->holiday_minutes / 60 * $hourly * $rate;
        }

        if ($approvedOt > 0) {
            $earnings[] = $this->line('OT', 'Horas extra aprobadas', round($approvedOt / 60, 2), round($otPay, 2));
        }
        if ($nightPay > 0) {
            $earnings[] = $this->line('NIGHT', 'Recargo nocturno', round($nightMinutes / 60, 2), round($nightPay, 2));
        }
        if ($holidayPay > 0) {
            $earnings[] = $this->line('HOLIDAY', 'Trabajo en descanso/festivo', round($holidayMinutes / 60, 2), round($holidayPay, 2));
        }

        // Componentes de pago: subsidios y deducciones fijas o porcentuales.
        $components = $employee->payComponents->keyBy('id');

        foreach (PayComponent::active()->where('applies_to_all', true)->get() as $global) {
            if (! $components->has($global->id)) {
                $components->put($global->id, $global);
            }
        }

        foreach ($components as $component) {
            if (! $component->active) {
                continue;
            }

            $value = $component->pivot?->amount ?? $component->amount;
            $amount = $component->calc_type === 'percent'
                ? round($basic * $value / 100, 2)
                : round((float) $value * ($employee->pay_type === 'monthly' ? min(1, $employedDays / max(1, $periodDays)) : 1), 2);

            if ($amount <= 0) {
                continue;
            }

            $label = $component->calc_type === 'percent' ? "{$component->name} ({$value}%)" : $component->name;
            $line = $this->line($component->code, $label, null, $amount);

            if ($component->type === 'earning') {
                $earnings[] = $line;
            } else {
                $deductions[] = $line;
            }
        }

        // Cuotas de anticipos y préstamos activos.
        if ($includeAdvances) {
            $advances = Advance::where('user_id', $employee->id)
                ->where('status', 'active')
                ->where('balance', '>', 0)
                ->whereDate('deduct_from', '<=', $to->toDateString())
                ->get();

            foreach ($advances as $advance) {
                $installment = round(min($advance->installment_amount, $advance->balance), 2);
                $label = (Advance::TYPES[$advance->type] ?? 'Anticipo')." #{$advance->id}";
                $deductions[] = $this->line('ADVANCE', $label, null, $installment) + ['advance_id' => $advance->id];
            }
        }

        $gross = round(array_sum(array_column($earnings, 'amount')), 2);
        $totalDeductions = round(array_sum(array_column($deductions, 'amount')), 2);

        return [
            'summary' => [
                'base_salary' => (float) $employee->monthly_salary,
                'hourly_rate' => $hourly,
                'daily_rate' => $daily,
                'days_worked' => $worked->count(),
                'regular_hours' => round($regularMinutes / 60, 2),
                'overtime_hours' => round($approvedOt / 60, 2),
                'night_hours' => round($nightMinutes / 60, 2),
                'holiday_hours' => round($holidayMinutes / 60, 2),
                'paid_leave_days' => $paidLeave,
                'unpaid_days' => $absent + $unpaidLeave + $halfDays * 0.5,
                'late_count' => $lateCount,
                'employed_days' => $employedDays,
                'basic' => $basic,
            ],
            'earnings' => $earnings,
            'deductions' => $deductions,
            'gross' => $gross,
            'deductions_total' => $totalDeductions,
            'net' => round($gross - $totalDeductions, 2),
        ];
    }

    protected function persist(PayrollPeriod $period, User $employee, array $result): Payslip
    {
        $s = $result['summary'];

        $payslip = Payslip::create([
            'payroll_period_id' => $period->id,
            'user_id' => $employee->id,
            'pay_type' => $employee->pay_type,
            'base_salary' => $s['base_salary'],
            'hourly_rate' => $s['hourly_rate'],
            'days_worked' => $s['days_worked'],
            'regular_hours' => $s['regular_hours'],
            'overtime_hours' => $s['overtime_hours'],
            'night_hours' => $s['night_hours'],
            'holiday_hours' => $s['holiday_hours'],
            'paid_leave_days' => $s['paid_leave_days'],
            'unpaid_days' => $s['unpaid_days'],
            'late_count' => $s['late_count'],
            'gross_pay' => $result['gross'],
            'total_deductions' => $result['deductions_total'],
            'net_pay' => $result['net'],
            'details' => $s,
        ]);

        foreach (['earning' => $result['earnings'], 'deduction' => $result['deductions']] as $kind => $lines) {
            foreach ($lines as $line) {
                $payslip->items()->create([
                    'kind' => $kind,
                    'code' => $line['code'],
                    'label' => $line['label'],
                    'quantity' => $line['quantity'],
                    'amount' => $line['amount'],
                    'advance_id' => $line['advance_id'] ?? null,
                ]);
            }
        }

        return $payslip;
    }

    /**
     * Bloquea el período: la asistencia queda inmutable y se aplican las cuotas de anticipos.
     */
    public function lock(PayrollPeriod $period, User $by): void
    {
        if ($period->status !== 'processed') {
            throw new RuntimeException('Genera la nómina antes de bloquear el período.');
        }

        DB::transaction(function () use ($period, $by) {
            $items = $period->payslips()->with('items')->get()->flatMap->items->whereNotNull('advance_id');

            foreach ($items as $item) {
                $advance = Advance::find($item->advance_id);

                if (! $advance) {
                    continue;
                }

                AdvanceRepayment::create([
                    'advance_id' => $advance->id,
                    'payslip_id' => $item->payslip_id,
                    'amount' => $item->amount,
                    'paid_on' => $period->end_date,
                    'note' => "Nómina {$period->name}",
                ]);

                $balance = round(max(0, $advance->balance - $item->amount), 2);
                $advance->update(['balance' => $balance, 'status' => $balance <= 0 ? 'closed' : 'active']);
            }

            $period->update(['status' => 'locked', 'locked_at' => now(), 'locked_by' => $by->id]);
        });
    }

    /**
     * Desbloqueo (solo administrador): revierte las cuotas aplicadas al bloquear.
     */
    public function unlock(PayrollPeriod $period): void
    {
        DB::transaction(function () use ($period) {
            $repayments = AdvanceRepayment::whereIn('payslip_id', $period->payslips()->select('id'))->get();

            foreach ($repayments as $repayment) {
                $advance = $repayment->advance;
                $advance->update(['balance' => round($advance->balance + $repayment->amount, 2), 'status' => 'active']);
                $repayment->delete();
            }

            $period->update(['status' => 'processed', 'locked_at' => null, 'locked_by' => null]);
        });
    }

    protected function line(string $code, string $label, float|int|null $quantity, float $amount): array
    {
        return ['code' => $code, 'label' => $label, 'quantity' => $quantity, 'amount' => round($amount, 2)];
    }
}
