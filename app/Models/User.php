<?php

namespace App\Models;

use App\Casts\DateOnly;
// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable([
    'name', 'email', 'password', 'role_id', 'employee_code', 'national_id', 'phone', 'position',
    'department_id', 'site_id', 'shift_id', 'manager_id', 'contractor_id', 'pay_type', 'monthly_salary',
    'hourly_rate', 'bank_account', 'hire_date', 'termination_date', 'status', 'google_id', 'avatar',
    'device_id', 'device_bound_at',
])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    public const STATUSES = [
        'active' => 'Activo',
        'inactive' => 'Inactivo',
        'terminated' => 'Retirado',
    ];

    public const PAY_TYPES = [
        'monthly' => 'Mensual',
        'hourly' => 'Por hora',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'hire_date' => DateOnly::class,
            'termination_date' => DateOnly::class,
            'device_bound_at' => 'datetime',
            'monthly_salary' => 'float',
            'hourly_rate' => 'float',
        ];
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class);
    }

    public function manager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'manager_id');
    }

    public function contractor(): BelongsTo
    {
        return $this->belongsTo(Contractor::class);
    }

    public function subordinates(): HasMany
    {
        return $this->hasMany(User::class, 'manager_id');
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class);
    }

    public function shiftAssignments(): HasMany
    {
        return $this->hasMany(ShiftAssignment::class);
    }

    public function leaveBalances(): HasMany
    {
        return $this->hasMany(LeaveBalance::class);
    }

    public function leaveRequests(): HasMany
    {
        return $this->hasMany(LeaveRequest::class);
    }

    public function payComponents(): BelongsToMany
    {
        return $this->belongsToMany(PayComponent::class, 'employee_pay_component')
            ->withPivot('amount')
            ->withTimestamps();
    }

    public function advances(): HasMany
    {
        return $this->hasMany(Advance::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(EmployeeDocument::class);
    }

    public function payslips(): HasMany
    {
        return $this->hasMany(Payslip::class);
    }

    public function scopeActive(Builder $query): void
    {
        $query->where('status', 'active');
    }

    /**
     * Limita la consulta a los empleados que el usuario puede ver: todos (scope.all),
     * o bien él mismo, sus subordinados directos y los miembros de los departamentos que dirige.
     */
    public function scopeVisibleTo(Builder $query, User $viewer): void
    {
        if ($viewer->hasPermission('scope.all')) {
            return;
        }

        $query->where(function (Builder $q) use ($viewer) {
            $q->where('users.id', $viewer->id)
                ->orWhere('users.manager_id', $viewer->id)
                ->orWhereIn('users.department_id', Department::where('manager_id', $viewer->id)->select('id'));
        });
    }

    public function hasPermission(string $permission): bool
    {
        if (($this->status ?? 'active') !== 'active') {
            return false;
        }

        return (bool) $this->role?->hasPermission($permission);
    }

    public function isAdmin(): bool
    {
        return $this->role?->slug === 'admin';
    }

    public function canSee(User $employee): bool
    {
        return static::query()->visibleTo($this)->whereKey($employee->id)->exists();
    }

    public function initials(): string
    {
        return collect(explode(' ', trim($this->name)))
            ->filter()
            ->take(2)
            ->map(fn ($part) => mb_strtoupper(mb_substr($part, 0, 1)))
            ->implode('');
    }

    /**
     * Valor de un día de trabajo, base para descuentos, liquidaciones y compensaciones.
     */
    public function dailyRate(): float
    {
        if ($this->pay_type === 'hourly') {
            return round($this->hourly_rate * (float) setting('standard_daily_hours'), 2);
        }

        return round($this->monthly_salary / max(1, (int) setting('month_days_divisor')), 2);
    }

    public function hourlyEquivalent(): float
    {
        if ($this->pay_type === 'hourly') {
            return (float) $this->hourly_rate;
        }

        return round($this->dailyRate() / max(1, (float) setting('standard_daily_hours')), 2);
    }
}
