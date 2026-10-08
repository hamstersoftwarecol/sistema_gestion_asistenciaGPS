<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['payslip_id', 'kind', 'code', 'label', 'quantity', 'amount', 'advance_id'])]
class PayslipItem extends Model
{
    protected function casts(): array
    {
        return [
            'quantity' => 'float',
            'amount' => 'float',
        ];
    }

    public function payslip(): BelongsTo
    {
        return $this->belongsTo(Payslip::class);
    }

    public function advance(): BelongsTo
    {
        return $this->belongsTo(Advance::class);
    }
}
