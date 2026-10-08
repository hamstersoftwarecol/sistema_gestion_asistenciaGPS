<?php

namespace App\Models;

use App\Casts\DateOnly;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'user_id', 'attendance_id', 'date', 'type', 'requested_check_in', 'requested_check_out', 'reason',
    'status', 'reviewed_by', 'reviewed_at', 'review_note',
])]
class AttendanceCorrection extends Model
{
    public const TYPES = [
        'missed_check_in' => 'Olvidé marcar la entrada',
        'missed_check_out' => 'Olvidé marcar la salida',
        'wrong_time' => 'Hora registrada incorrecta',
        'regularization' => 'Regularizar día completo (trabajo en campo)',
    ];

    protected function casts(): array
    {
        return [
            'date' => DateOnly::class,
            'requested_check_in' => 'datetime',
            'requested_check_out' => 'datetime',
            'reviewed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function attendance(): BelongsTo
    {
        return $this->belongsTo(Attendance::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
