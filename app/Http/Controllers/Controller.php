<?php

namespace App\Http\Controllers;

use App\Models\PayrollPeriod;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Validation\ValidationException;

abstract class Controller
{
    /**
     * Los gerentes solo pueden actuar sobre empleados de su equipo (ver User::scopeVisibleTo).
     */
    protected function authorizeEmployee(User $employee): void
    {
        abort_unless(request()->user()->canSee($employee), 403, 'Este empleado no pertenece a tu equipo.');
    }

    /**
     * Nadie revisa sus propias solicitudes.
     */
    protected function authorizeReview(User $employee): void
    {
        $this->authorizeEmployee($employee);
        abort_if(request()->user()->id === $employee->id && ! request()->user()->isAdmin(), 403, 'No puedes aprobar tus propias solicitudes.');
    }

    protected function ensureNotLocked(CarbonInterface|string $date, string $field = 'date'): void
    {
        if (PayrollPeriod::dateIsLocked($date)) {
            throw ValidationException::withMessages([$field => 'La fecha pertenece a un período de nómina bloqueado.']);
        }
    }
}
