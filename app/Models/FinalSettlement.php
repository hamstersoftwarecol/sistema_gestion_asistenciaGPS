<?php

namespace App\Models;

use App\Casts\DateOnly;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'user_id', 'termination_date', 'reason', 'years_of_service', 'daily_rate', 'pending_salary', 'gratuity_amount',
    'leave_encashment_days', 'leave_encashment_amount', 'other_earnings', 'advances_recovered', 'other_deductions',
    'net_amount', 'details', 'status', 'notes', 'created_by', 'approved_by', 'approved_at',
])]
class FinalSettlement extends Model
{
    public const REASONS = [
        'resignation' => 'Renuncia voluntaria',
        'termination' => 'Despido / terminación',
        'contract_end' => 'Fin de contrato',
        'retirement' => 'Jubilación',
    ];

    public const STATUSES = ['draft' => 'Borrador', 'approved' => 'Aprobada', 'paid' => 'Pagada'];

    protected function casts(): array
    {
        return [
            'termination_date' => DateOnly::class,
            'years_of_service' => 'float',
            'daily_rate' => 'float',
            'pending_salary' => 'float',
            'gratuity_amount' => 'float',
            'leave_encashment_days' => 'float',
            'leave_encashment_amount' => 'float',
            'other_earnings' => 'float',
            'advances_recovered' => 'float',
            'other_deductions' => 'float',
            'net_amount' => 'float',
            'details' => 'array',
            'approved_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
