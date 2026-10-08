<?php

namespace App\Models;

use App\Casts\DateOnly;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['date', 'name', 'paid'])]
class Holiday extends Model
{
    protected function casts(): array
    {
        return [
            'date' => DateOnly::class,
            'paid' => 'boolean',
        ];
    }
}
