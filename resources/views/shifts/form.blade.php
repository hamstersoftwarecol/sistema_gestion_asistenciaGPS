<x-app-layout>
    <x-slot name="header">{{ $shift->exists ? 'Editar turno' : 'Nuevo turno' }}</x-slot>

    <form method="POST" action="{{ $shift->exists ? route('shifts.update', $shift) : route('shifts.store') }}" class="card mx-auto max-w-3xl">
        @csrf
        @if ($shift->exists) @method('PUT') @endif
        <div class="card-body space-y-6">
            <div class="grid gap-4 sm:grid-cols-4">
                <x-field name="name" label="Nombre" :value="$shift->name" required class="sm:col-span-2" />
                <x-field name="code" label="Código corto" :value="$shift->code" maxlength="10" />
                <x-field name="color" label="Color" type="color" :value="$shift->color" />
            </div>
            <fieldset>
                <legend class="mb-2 text-sm font-semibold text-gray-900">Horario y tolerancias</legend>
                <div class="grid gap-4 sm:grid-cols-4">
                    <x-field name="start_time" label="Inicio" type="time" :value="$shift->start_time" required />
                    <x-field name="end_time" label="Fin" type="time" :value="$shift->end_time" required help="Si es menor al inicio, el turno termina al día siguiente." />
                    <x-field name="grace_minutes" label="Tiempo de gracia (min)" type="number" min="0" :value="$shift->grace_minutes" required />
                    <x-field name="break_minutes" label="Descanso no remunerado (min)" type="number" min="0" :value="$shift->break_minutes" required />
                </div>
            </fieldset>
            <fieldset>
                <legend class="mb-2 text-sm font-semibold text-gray-900">Horas nocturnas y extras</legend>
                <div class="grid gap-4 sm:grid-cols-3">
                    <x-field name="night_start" label="Inicio franja nocturna" type="time" :value="$shift->night_start" required />
                    <x-field name="night_end" label="Fin franja nocturna" type="time" :value="$shift->night_end" required />
                    <x-field name="overtime_cap_minutes" label="Límite diario de extras (min)" type="number" min="0" :value="$shift->overtime_cap_minutes" required />
                </div>
            </fieldset>
            <fieldset>
                <legend class="mb-2 text-sm font-semibold text-gray-900">Tarifas (multiplicador sobre la hora ordinaria)</legend>
                <div class="grid gap-4 sm:grid-cols-4">
                    <x-field name="overtime_rate" label="Hora extra" type="number" step="0.01" min="1" :value="$shift->overtime_rate" required />
                    <x-field name="night_rate" label="Hora nocturna" type="number" step="0.01" min="1" :value="$shift->night_rate" required />
                    <x-field name="weekend_rate" label="Día de descanso" type="number" step="0.01" min="1" :value="$shift->weekend_rate" required />
                    <x-field name="holiday_rate" label="Festivo" type="number" step="0.01" min="1" :value="$shift->holiday_rate" required />
                </div>
            </fieldset>
            <x-field name="active" label="Turno activo" type="checkbox" :value="$shift->active" />
        </div>
        <div class="flex justify-end gap-2 border-t border-gray-100 px-5 py-3">
            <a href="{{ route('shifts.index') }}" class="btn btn-secondary">Cancelar</a>
            <button class="btn btn-primary">Guardar turno</button>
        </div>
    </form>
</x-app-layout>
