<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'name', 'code', 'paid', 'annual_quota', 'carry_forward', 'max_carry_forward', 'encashable',
    'requires_attachment', 'color', 'active',
])]
class LeaveType extends Model
{
    protected function casts(): array
    {
        return [
            'paid' => 'boolean',
            'annual_quota' => 'float',
            'carry_forward' => 'boolean',
            'max_carry_forward' => 'float',
            'encashable' => 'boolean',
            'requires_attachment' => 'boolean',
            'active' => 'boolean',
        ];
    }

    public function scopeActive(Builder $query): void
    {
        $query->where('active', true);
    }

    /**
     * Un tipo sin cuota anual (p. ej. licencia no remunerada) no consume saldo.
     */
    public function hasQuota(): bool
    {
        return $this->annual_quota > 0;
    }

    public function balances(): HasMany
    {
        return $this->hasMany(LeaveBalance::class);
    }
}
