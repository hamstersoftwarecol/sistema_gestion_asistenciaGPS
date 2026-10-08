<?php

namespace App\Services;

use App\Models\Advance;
use App\Models\AdvanceRepayment;
use App\Models\FinalSettlement;
use App\Models\LeaveBalance;
use App\Models\PayrollPeriod;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Liquidación final: salario pendiente, gratificación por antigüedad, compensación de
 * vacaciones no disfrutadas y recuperación de anticipos/préstamos pendientes.
 */
class SettlementService
{
    public function __construct(protected PayrollService $payroll) {}

    /**
     * @return array<string, mixed>
     */
    public function compute(User $employee, Carbon $terminationDate, float $otherEarnings = 0, float $otherDeductions = 0): array
    {
        $daily = $employee->dailyRate();
        $hire = $employee->hire_date ?? $terminationDate->copy();
        $years = round(max(0, $hire->diffInDays($terminationDate)) / 365, 2);

        $gratuity = 0.0;
        if ($years >= (float) setting('gratuity_min_years')) {
            $gratuity = round($daily * (float) setting('gratuity_days_per_year') * $years, 2);
        }

        $encashDays = (float) LeaveBalance::with('leaveType')
            ->where('user_id', $employee->id)
            ->where('year', $terminationDate->year)
            ->get()
            ->filter(fn (LeaveBalance $b) => $b->leaveType?->encashable)
            ->sum(fn (LeaveBalance $b) => max(0, $b->available()));
        $encashAmount = round($encashDays * $daily, 2);

        // Salario pendiente desde el último período bloqueado hasta la fecha de retiro.
        $lastLocked = PayrollPeriod::where('status', 'locked')
            ->whereDate('end_date', '<', $terminationDate->toDateString())
            ->orderByDesc('end_date')
            ->first();
        $from = $lastLocked ? $lastLocked->end_date->copy()->addDay() : $terminationDate->copy()->startOfMonth();
        $from = $from->lessThan($hire) ? $hire->copy() : $from;
        $from = $from->greaterThan($terminationDate) ? $terminationDate->copy() : $from;

        $virtual = $employee->replicate();
        $virtual->id = $employee->id;
        $virtual->setRelation('payComponents', collect());
        $virtual->setRelation('shift', $employee->shift);
        $virtual->termination_date = $terminationDate;

        $pending = $this->payroll->calculate($virtual, $from, $terminationDate, includeAdvances: false);

        if ($employee->pay_type === 'monthly') {
            // En liquidación el básico se paga por días calendario, no como mes completo.
            $days = (int) $from->diffInDays($terminationDate) + 1;
            $basicLine = round($daily * $days, 2);
            $pending['net'] = round($pending['net'] - $pending['summary']['basic'] + $basicLine, 2);
        }

        $advances = (float) Advance::where('user_id', $employee->id)->where('status', 'active')->sum('balance');
        $net = round($pending['net'] + $gratuity + $encashAmount + $otherEarnings - $advances - $otherDeductions, 2);

        return [
            'years_of_service' => $years,
            'daily_rate' => $daily,
            'pending_salary' => max(0, $pending['net']),
            'pending_from' => $from->toDateString(),
            'gratuity_amount' => $gratuity,
            'leave_encashment_days' => $encashDays,
            'leave_encashment_amount' => $encashAmount,
            'other_earnings' => $otherEarnings,
            'advances_recovered' => $advances,
            'other_deductions' => $otherDeductions,
            'net_amount' => $net,
            'details' => [
                'pending_from' => $from->toDateString(),
                'pending_summary' => $pending['summary'],
                'pending_lines' => array_merge($pending['earnings'], array_map(fn ($l) => $l + ['negative' => true], $pending['deductions'])),
                'gratuity_rule' => setting('gratuity_days_per_year').' días por año (mínimo '.setting('gratuity_min_years').' año)',
            ],
        ];
    }

    public function approve(FinalSettlement $settlement, User $by): void
    {
        DB::transaction(function () use ($settlement, $by) {
            $employee = $settlement->user;

            Advance::where('user_id', $employee->id)->where('status', 'active')->get()
                ->each(function (Advance $advance) use ($settlement) {
                    AdvanceRepayment::create([
                        'advance_id' => $advance->id,
                        'amount' => $advance->balance,
                        'paid_on' => $settlement->termination_date,
                        'note' => "Liquidación final #{$settlement->id}",
                    ]);
                    $advance->update(['balance' => 0, 'status' => 'closed']);
                });

            $employee->update([
                'status' => 'terminated',
                'termination_date' => $settlement->termination_date,
            ]);

            $settlement->update(['status' => 'approved', 'approved_by' => $by->id, 'approved_at' => now()]);
        });
    }
}
