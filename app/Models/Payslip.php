<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'payroll_period_id', 'user_id', 'pay_type', 'base_salary', 'hourly_rate', 'days_worked', 'regular_hours',
    'overtime_hours', 'night_hours', 'holiday_hours', 'paid_leave_days', 'unpaid_days', 'late_count',
    'gross_pay', 'total_deductions', 'net_pay', 'details',
])]
class Payslip extends Model
{
    protected function casts(): array
    {
        return [
            'base_salary' => 'float',
            'hourly_rate' => 'float',
            'days_worked' => 'float',
            'regular_hours' => 'float',
            'overtime_hours' => 'float',
            'night_hours' => 'float',
            'holiday_hours' => 'float',
            'paid_leave_days' => 'float',
            'unpaid_days' => 'float',
            'gross_pay' => 'float',
            'total_deductions' => 'float',
            'net_pay' => 'float',
            'details' => 'array',
        ];
    }

    public function period(): BelongsTo
    {
        return $this->belongsTo(PayrollPeriod::class, 'payroll_period_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(PayslipItem::class);
    }

    public function earnings(): HasMany
    {
        return $this->items()->where('kind', 'earning');
    }

    public function deductions(): HasMany
    {
        return $this->items()->where('kind', 'deduction');
    }
}
