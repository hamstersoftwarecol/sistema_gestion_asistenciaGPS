<?php

namespace App\Models;

use App\Casts\DateOnly;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'user_id', 'date', 'shift_id', 'site_id', 'day_type', 'status',
    'check_in_at', 'check_in_lat', 'check_in_lng', 'check_in_accuracy', 'check_in_distance_m', 'check_in_out_of_zone', 'check_in_selfie',
    'check_out_at', 'check_out_site_id', 'check_out_lat', 'check_out_lng', 'check_out_accuracy', 'check_out_distance_m', 'check_out_out_of_zone', 'check_out_selfie',
    'late_minutes', 'early_leave_minutes', 'worked_minutes', 'regular_minutes', 'holiday_minutes', 'night_minutes',
    'overtime_minutes', 'approved_overtime_minutes', 'overtime_status', 'overtime_reviewed_by',
    'leave_request_id', 'leave_type_id', 'source', 'is_offline', 'device_id', 'regularized', 'marked_by', 'notes',
])]
class Attendance extends Model
{
    public const STATUSES = [
        'present' => ['Presente', 'P', 'green'],
        'late' => ['Tarde', 'T', 'yellow'],
        'half_day' => ['Medio día', 'MD', 'orange'],
        'absent' => ['Ausente', 'A', 'red'],
        'on_leave' => ['Permiso', 'L', 'blue'],
        'missed_punch' => ['Marcación omitida', 'O', 'purple'],
        'holiday' => ['Festivo', 'F', 'gray'],
        'weekend' => ['Descanso', 'D', 'gray'],
    ];

    public const DAY_TYPES = [
        'workday' => 'Laboral',
        'weekend' => 'Descanso',
        'holiday' => 'Festivo',
    ];

    public const SOURCES = [
        'app' => 'App (selfie + GPS)',
        'offline' => 'App sin conexión',
        'manual' => 'Registro manual',
        'bulk' => 'Marcado masivo',
        'correction' => 'Corrección aprobada',
        'leave' => 'Permiso aprobado',
        'system' => 'Cierre automático',
    ];

    public const OVERTIME_STATUSES = [
        'none' => 'Sin extras',
        'pending' => 'Pendiente',
        'approved' => 'Aprobada',
        'rejected' => 'Rechazada',
    ];

    protected function casts(): array
    {
        return [
            'date' => DateOnly::class,
            'check_in_at' => 'datetime',
            'check_out_at' => 'datetime',
            'check_in_lat' => 'float',
            'check_in_lng' => 'float',
            'check_out_lat' => 'float',
            'check_out_lng' => 'float',
            'check_in_out_of_zone' => 'boolean',
            'check_out_out_of_zone' => 'boolean',
            'is_offline' => 'boolean',
            'regularized' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class);
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function checkOutSite(): BelongsTo
    {
        return $this->belongsTo(Site::class, 'check_out_site_id');
    }

    public function leaveType(): BelongsTo
    {
        return $this->belongsTo(LeaveType::class);
    }

    public function leaveRequest(): BelongsTo
    {
        return $this->belongsTo(LeaveRequest::class);
    }

    public function markedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'marked_by');
    }

    public function isOutOfZone(): bool
    {
        return $this->check_in_out_of_zone || $this->check_out_out_of_zone;
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status][0] ?? $this->status;
    }

    public function statusCode(): string
    {
        return self::STATUSES[$this->status][1] ?? '?';
    }

    public function statusColor(): string
    {
        return self::STATUSES[$this->status][2] ?? 'gray';
    }
}
