<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'name', 'tax_id', 'contact_name', 'email', 'phone', 'address', 'rate_type', 'rate',
    'overtime_multiplier', 'tax_percent', 'active',
])]
class Contractor extends Model
{
    public const RATE_TYPES = ['hourly' => 'Por hora', 'daily' => 'Por día'];

    protected function casts(): array
    {
        return [
            'rate' => 'float',
            'overtime_multiplier' => 'float',
            'tax_percent' => 'float',
            'active' => 'boolean',
        ];
    }

    public function workers(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(ContractorInvoice::class);
    }
}
