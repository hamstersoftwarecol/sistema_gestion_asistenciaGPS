<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'leave_type_id', 'year', 'allocated', 'carried_forward', 'used'])]
class LeaveBalance extends Model
{
    protected function casts(): array
    {
        return [
            'year' => 'integer',
            'allocated' => 'float',
            'carried_forward' => 'float',
            'used' => 'float',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function leaveType(): BelongsTo
    {
        return $this->belongsTo(LeaveType::class);
    }

    public function available(): float
    {
        return round($this->allocated + $this->carried_forward - $this->used, 2);
    }
}
