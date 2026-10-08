<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\Contractor;
use App\Models\ContractorInvoice;
use App\Models\User;
use Carbon\Carbon;

/**
 * Factura de contratista: horas y días de su personal tomados de la tabla de asistencia.
 */
class ContractorInvoiceService
{
    public function generate(Contractor $contractor, Carbon $from, Carbon $to, User $by, ?Carbon $dueDate = null, ?string $notes = null): ContractorInvoice
    {
        $lines = [];
        $totalHours = 0.0;
        $totalDays = 0;
        $subtotal = 0.0;

        foreach ($contractor->workers()->orderBy('name')->get() as $worker) {
            $rows = Attendance::where('user_id', $worker->id)
                ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
                ->where('worked_minutes', '>', 0)
                ->get();

            if ($rows->isEmpty()) {
                continue;
            }

            $regular = ($rows->sum('regular_minutes') + $rows->sum('holiday_minutes')) / 60;
            $overtime = $rows->sum('approved_overtime_minutes') / 60;
            $days = $rows->count();

            $amount = $contractor->rate_type === 'daily'
                ? $days * $contractor->rate + $overtime * ($contractor->rate / max(1, (float) setting('standard_daily_hours'))) * $contractor->overtime_multiplier
                : $regular * $contractor->rate + $overtime * $contractor->rate * $contractor->overtime_multiplier;

            $lines[] = [
                'user_id' => $worker->id,
                'name' => $worker->name,
                'code' => $worker->employee_code,
                'days' => $days,
                'regular_hours' => round($regular, 2),
                'overtime_hours' => round($overtime, 2),
                'amount' => round($amount, 2),
            ];

            $totalHours += $regular + $overtime;
            $totalDays += $days;
            $subtotal += $amount;
        }

        $subtotal = round($subtotal, 2);
        $tax = round($subtotal * $contractor->tax_percent / 100, 2);

        return ContractorInvoice::create([
            'contractor_id' => $contractor->id,
            'number' => $this->nextNumber(),
            'period_start' => $from,
            'period_end' => $to,
            'total_hours' => round($totalHours, 2),
            'total_days' => $totalDays,
            'subtotal' => $subtotal,
            'tax_percent' => $contractor->tax_percent,
            'tax_amount' => $tax,
            'total' => round($subtotal + $tax, 2),
            'lines' => $lines,
            'status' => 'draft',
            'due_date' => $dueDate ?? $to->copy()->addDays(30),
            'notes' => $notes,
            'created_by' => $by->id,
        ]);
    }

    protected function nextNumber(): string
    {
        $prefix = 'FC-'.now()->format('Y').'-';
        $last = ContractorInvoice::where('number', 'like', $prefix.'%')->orderByDesc('number')->value('number');
        $next = $last ? ((int) substr($last, strlen($prefix))) + 1 : 1;

        return $prefix.str_pad((string) $next, 4, '0', STR_PAD_LEFT);
    }
}
