<x-app-layout>
    <x-slot name="header">Regularización y aprobación de horas extra</x-slot>

    <div class="mb-4 flex flex-wrap items-center gap-2">
        @foreach (['pending' => 'Pendientes', 'approved' => 'Aprobadas', 'rejected' => 'Rechazadas', 'all' => 'Todas'] as $key => $label)
            <a href="{{ route('overtime.index', ['status' => $key]) }}" class="rounded-full px-3 py-1 text-sm {{ $status === $key ? 'bg-indigo-600 text-white' : 'bg-white text-gray-700 shadow-sm' }}">{{ $label }}</a>
        @endforeach
    </div>

    <form method="POST" action="{{ route('overtime.review') }}" x-data="{ ids: [] }">
        @csrf
        <div class="card">
            <div class="card-header">
                <span class="text-sm text-gray-500"><span x-text="ids.length"></span> seleccionado(s)</span>
                <div class="flex gap-2">
                    <button name="decision" value="approved" class="btn btn-success btn-sm" :disabled="!ids.length">Aprobar seleccionadas</button>
                    <button name="decision" value="rejected" class="btn btn-danger btn-sm" :disabled="!ids.length">Rechazar seleccionadas</button>
                </div>
            </div>
            <div class="table-wrap">
                <table class="table">
                    <thead><tr><th class="w-8"></th><th>Fecha</th><th>Empleado</th><th>Turno</th><th>Entrada – salida</th><th>Trabajado</th><th>Extra calculada</th><th>Minutos a aprobar</th><th>Estado</th></tr></thead>
                    <tbody>
                        @forelse ($rows as $row)
                            <tr>
                                <td><input type="checkbox" class="checkbox" name="ids[]" value="{{ $row->id }}" x-model="ids"></td>
                                <td class="whitespace-nowrap">{{ $row->date->locale('es')->isoFormat('ddd D/MM') }}</td>
                                <td><div class="font-medium">{{ $row->user->name }}</div><div class="text-xs text-gray-500">{{ $row->user->department?->name }}</div></td>
                                <td class="text-xs">{{ $row->shift?->label() ?? '—' }}</td>
                                <td class="whitespace-nowrap">{{ $row->check_in_at?->format('H:i') }} – {{ $row->check_out_at?->format('H:i') }}</td>
                                <td>{{ hours($row->worked_minutes) }}</td>
                                <td class="font-semibold">{{ hours($row->overtime_minutes) }}</td>
                                <td><input type="number" name="minutes[{{ $row->id }}]" min="0" max="{{ $row->overtime_minutes }}" value="{{ $row->overtime_status === 'approved' ? $row->approved_overtime_minutes : $row->overtime_minutes }}" class="input w-24 py-1 text-xs"></td>
                                <td><x-status-badge :status="$row->overtime_status" /></td>
                            </tr>
                        @empty
                            <x-empty colspan="9" message="No hay horas extra en este estado." />
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </form>
    <div class="mt-4">{{ $rows->links() }}</div>
    <p class="mt-2 text-xs text-gray-500">Solo las horas extra aprobadas se pagan en la nómina (con la tarifa del turno). Puedes aprobar parcialmente ajustando los minutos.</p>
</x-app-layout>
