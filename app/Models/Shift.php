<?php

namespace App\Models;

use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'name', 'code', 'start_time', 'end_time', 'grace_minutes', 'break_minutes', 'night_start', 'night_end',
    'overtime_cap_minutes', 'overtime_rate', 'night_rate', 'weekend_rate', 'holiday_rate', 'color', 'active',
])]
class Shift extends Model
{
    protected function casts(): array
    {
        return [
            'grace_minutes' => 'integer',
            'break_minutes' => 'integer',
            'overtime_cap_minutes' => 'integer',
            'overtime_rate' => 'float',
            'night_rate' => 'float',
            'weekend_rate' => 'float',
            'holiday_rate' => 'float',
            'active' => 'boolean',
        ];
    }

    public function scopeActive(Builder $query): void
    {
        $query->where('active', true);
    }

    public function isOvernight(): bool
    {
        return $this->end_time <= $this->start_time;
    }

    /**
     * Inicio y fin programados del turno para una fecha (el fin pasa al día siguiente en turnos nocturnos).
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    public function windowFor(CarbonInterface $date): array
    {
        $start = Carbon::parse($date->toDateString().' '.$this->start_time);
        $end = Carbon::parse($date->toDateString().' '.$this->end_time);

        if ($end->lessThanOrEqualTo($start)) {
            $end->addDay();
        }

        return [$start, $end];
    }

    public function scheduledMinutes(): int
    {
        [$start, $end] = $this->windowFor(Carbon::today());

        return max(0, (int) $start->diffInMinutes($end) - $this->break_minutes);
    }

    public function label(): string
    {
        return "{$this->name} ({$this->start_time}–{$this->end_time})";
    }
}
