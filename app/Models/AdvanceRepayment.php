<?php

namespace App\Models;

use App\Casts\DateOnly;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['advance_id', 'payslip_id', 'amount', 'paid_on', 'note'])]
class AdvanceRepayment extends Model
{
    protected function casts(): array
    {
        return [
            'amount' => 'float',
            'paid_on' => DateOnly::class,
        ];
    }

    public function advance(): BelongsTo
    {
        return $this->belongsTo(Advance::class);
    }

    public function payslip(): BelongsTo
    {
        return $this->belongsTo(Payslip::class);
    }
}
