<x-app-layout>
    <x-slot name="header">Solicitar corrección de asistencia</x-slot>

    <div class="mx-auto max-w-2xl">
        <div class="card">
            <form method="POST" action="{{ route('corrections.store') }}" class="card-body space-y-4" x-data="{ type: @js(old('type', $attendance && $attendance->check_in_at && ! $attendance->check_out_at ? 'missed_check_out' : 'missed_check_in')) }">
                @csrf
                <p class="text-sm text-gray-600">Usa este formulario si olvidaste marcar, si la hora quedó mal o si trabajaste en campo sin poder registrar. Tu gerente revisará la solicitud; al aprobarse, la fila de asistencia se regulariza y la nómina usará las horas corregidas.</p>

                <div class="grid gap-4 sm:grid-cols-2">
                    <x-field name="date" label="Fecha" type="date" :value="$date" required max="{{ today()->toDateString() }}" onchange="window.location='?date='+this.value" />
                    <div>
                        <label class="label" for="type">Tipo de corrección</label>
                        <select id="type" name="type" class="input" x-model="type">
                            @foreach (\App\Models\AttendanceCorrection::TYPES as $key => $label)
                                <option value="{{ $key }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                @if ($attendance)
                    <div class="rounded-lg bg-gray-50 p-3 text-sm text-gray-600">
                        Registro actual: <x-status-badge :status="$attendance->status" />
                        entrada {{ $attendance->check_in_at?->format('H:i') ?? '—' }}, salida {{ $attendance->check_out_at?->format('H:i') ?? '—' }}.
                    </div>
                @else
                    <div class="rounded-lg bg-gray-50 p-3 text-sm text-gray-600">No hay registro de asistencia para esta fecha.</div>
                @endif

                <div class="grid gap-4 sm:grid-cols-2">
                    <div x-show="type !== 'missed_check_out'">
                        <x-field name="check_in" label="Hora de entrada" type="time" :value="$attendance?->check_in_at?->format('H:i')" />
                    </div>
                    <div x-show="type !== 'missed_check_in'">
                        <x-field name="check_out" label="Hora de salida" type="time" :value="$attendance?->check_out_at?->format('H:i')" help="Si es menor que la entrada se toma como el día siguiente (turno nocturno)." />
                    </div>
                </div>

                <x-field name="reason" label="Motivo" type="textarea" required placeholder="Ej.: sin señal en el sitio de obra, batería agotada…" />

                <div class="flex justify-end gap-2">
                    <a href="{{ route('corrections.index') }}" class="btn btn-secondary">Cancelar</a>
                    <button class="btn btn-primary">Enviar solicitud</button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
