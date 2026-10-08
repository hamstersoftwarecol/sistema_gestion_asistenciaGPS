<x-app-layout>
    <x-slot name="header">Nueva liquidación final</x-slot>

    <form method="POST" action="{{ route('settlements.store') }}" class="card mx-auto max-w-2xl">
        @csrf
        <div class="card-body grid gap-4 sm:grid-cols-2">
            <x-field name="user_id" label="Empleado" type="select" :options="$employees" :value="$selected" empty="Seleccione…" required class="sm:col-span-2" />
            <x-field name="termination_date" label="Fecha de retiro" type="date" :value="today()->toDateString()" required />
            <x-field name="reason" label="Motivo" type="select" :options="\App\Models\FinalSettlement::REASONS" required />
            <x-field name="other_earnings" label="Otros devengos" type="number" step="0.01" min="0" value="0" help="Bonificaciones pendientes, reintegros…" />
            <x-field name="other_deductions" label="Otras deducciones" type="number" step="0.01" min="0" value="0" help="Equipos no devueltos, multas…" />
            <x-field name="notes" label="Notas" type="textarea" class="sm:col-span-2" />
            <div class="rounded-lg bg-indigo-50 p-3 text-xs text-indigo-900 sm:col-span-2">
                La liquidación incluye: salario pendiente desde el último período bloqueado (calculado con la asistencia),
                gratificación de {{ setting('gratuity_days_per_year') }} días por año de servicio (mínimo {{ setting('gratuity_min_years') }} año),
                compensación del saldo de permisos compensables (vacaciones) y descuento de anticipos/préstamos pendientes.
            </div>
        </div>
        <div class="flex justify-end gap-2 border-t border-gray-100 px-5 py-3">
            <a href="{{ route('settlements.index') }}" class="btn btn-secondary">Cancelar</a>
            <button class="btn btn-primary">Calcular liquidación</button>
        </div>
    </form>
</x-app-layout>
