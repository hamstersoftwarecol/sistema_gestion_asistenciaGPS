<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable(['name', 'code', 'type', 'calc_type', 'amount', 'applies_to_all', 'active'])]
class PayComponent extends Model
{
    public const TYPES = ['earning' => 'Subsidio / devengo', 'deduction' => 'Deducción'];

    public const CALC_TYPES = ['fixed' => 'Valor fijo', 'percent' => '% del salario básico'];

    protected function casts(): array
    {
        return [
            'amount' => 'float',
            'applies_to_all' => 'boolean',
            'active' => 'boolean',
        ];
    }

    public function scopeActive(Builder $query): void
    {
        $query->where('active', true);
    }

    public function employees(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'employee_pay_component')->withPivot('amount');
    }
}
