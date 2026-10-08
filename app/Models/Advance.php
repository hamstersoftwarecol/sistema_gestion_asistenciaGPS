<?php

namespace App\Models;

use App\Casts\DateOnly;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'user_id', 'type', 'amount', 'installments', 'installment_amount', 'balance', 'issued_on', 'deduct_from',
    'status', 'reason', 'approved_by',
])]
class Advance extends Model
{
    public const TYPES = ['advance' => 'Anticipo', 'loan' => 'Préstamo'];

    public const STATUSES = ['active' => 'Activo', 'closed' => 'Pagado', 'cancelled' => 'Anulado'];

    protected function casts(): array
    {
        return [
            'amount' => 'float',
            'installment_amount' => 'float',
            'balance' => 'float',
            'issued_on' => DateOnly::class,
            'deduct_from' => DateOnly::class,
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function repayments(): HasMany
    {
        return $this->hasMany(AdvanceRepayment::class);
    }
}
