<x-app-layout>
    <x-slot name="header">Turnos</x-slot>

    <div class="mb-4 flex justify-end">
        <a href="{{ route('shifts.create') }}" class="btn btn-primary"><x-icon name="plus" class="h-4 w-4" /> Nuevo turno</a>
    </div>

    <div class="card">
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th>Turno</th><th>Horario</th><th>Gracia</th><th>Descanso no remun.</th><th>Franja nocturna</th><th>Límite extra</th><th>Recargos (extra / noct. / desc. / fest.)</th><th>Estado</th><th></th></tr></thead>
                <tbody>
                    @forelse ($shifts as $shift)
                        <tr>
                            <td><span class="mr-2 inline-block h-3 w-3 rounded-full align-middle" style="background: {{ $shift->color }}"></span><span class="font-medium">{{ $shift->name }}</span> <span class="text-xs text-gray-500">{{ $shift->code }}</span></td>
                            <td class="whitespace-nowrap">{{ $shift->start_time }} – {{ $shift->end_time }} @if ($shift->isOvernight())<x-badge color="indigo">nocturno</x-badge>@endif</td>
                            <td>{{ $shift->grace_minutes }} min</td>
                            <td>{{ $shift->break_minutes }} min</td>
                            <td class="whitespace-nowrap">{{ $shift->night_start }} – {{ $shift->night_end }}</td>
                            <td>{{ hours($shift->overtime_cap_minutes) }}</td>
                            <td class="whitespace-nowrap">×{{ $shift->overtime_rate }} / ×{{ $shift->night_rate }} / ×{{ $shift->weekend_rate }} / ×{{ $shift->holiday_rate }}</td>
                            <td><x-status-badge :status="$shift->active ? 'active' : 'inactive'" /></td>
                            <td class="whitespace-nowrap text-right">
                                <a href="{{ route('shifts.edit', $shift) }}" class="btn-link">Editar</a>
                                <form method="POST" action="{{ route('shifts.destroy', $shift) }}" class="inline" onsubmit="return confirm('¿Eliminar el turno?')">
                                    @csrf @method('DELETE')
                                    <button class="ml-3 text-sm text-red-600 hover:text-red-800">Eliminar</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <x-empty colspan="9" />
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <p class="mt-3 text-xs text-gray-500">Jornada programada = (fin − inicio) − descanso. Retraso = entrada después del inicio + gracia. Extra = trabajado − jornada (mínimo según reglas de RR. HH., tope por turno). Las horas nocturnas reciben el recargo adicional (tarifa − 1).</p>
</x-app-layout>
