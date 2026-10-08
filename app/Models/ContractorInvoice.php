<?php

namespace App\Models;

use App\Casts\DateOnly;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'contractor_id', 'number', 'period_start', 'period_end', 'total_hours', 'total_days', 'subtotal',
    'tax_percent', 'tax_amount', 'total', 'lines', 'status', 'due_date', 'notes', 'created_by',
])]
class ContractorInvoice extends Model
{
    public const STATUSES = ['draft' => 'Borrador', 'issued' => 'Emitida', 'paid' => 'Pagada'];

    protected function casts(): array
    {
        return [
            'period_start' => DateOnly::class,
            'period_end' => DateOnly::class,
            'due_date' => DateOnly::class,
            'total_hours' => 'float',
            'total_days' => 'float',
            'subtotal' => 'float',
            'tax_percent' => 'float',
            'tax_amount' => 'float',
            'total' => 'float',
            'lines' => 'array',
        ];
    }

    public function contractor(): BelongsTo
    {
        return $this->belongsTo(Contractor::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
