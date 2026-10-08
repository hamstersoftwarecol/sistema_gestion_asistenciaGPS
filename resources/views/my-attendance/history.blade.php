<x-app-layout>
    <x-slot name="header">Mi historial de asistencia</x-slot>

    <form method="GET" class="mb-4 flex flex-wrap items-end gap-3">
        <div>
            <label class="label" for="month">Mes</label>
            <input id="month" type="month" name="month" value="{{ $month->format('Y-m') }}" class="input" onchange="this.form.submit()">
        </div>
        <a href="{{ route('corrections.create') }}" class="btn btn-secondary ml-auto"><x-icon name="pencil" class="h-4 w-4" /> Solicitar corrección</a>
    </form>

    <div class="mb-4 grid grid-cols-2 gap-3 md:grid-cols-4 xl:grid-cols-8">
        <x-kpi label="Trabajados" :value="$summary['present']" tone="green" />
        <x-kpi label="Tarde" :value="$summary['late']" tone="yellow" />
        <x-kpi label="Ausencias" :value="$summary['absent']" tone="red" />
        <x-kpi label="Permisos" :value="$summary['leave']" tone="blue" />
        <x-kpi label="Horas" :value="hours($summary['worked'])" tone="indigo" />
        <x-kpi label="Extra aprob." :value="hours($summary['overtime'])" tone="purple" />
        <x-kpi label="Nocturnas" :value="hours($summary['night'])" />
        <x-kpi label="Fuera de zona" :value="$summary['out_of_zone']" tone="red" />
    </div>

    <div class="card">
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr><th>Fecha</th><th>Estado</th><th>Turno</th><th>Entrada</th><th>Salida</th><th>Sitio</th><th>Trabajado</th><th>Extra</th><th>Selfies</th><th></th></tr>
                </thead>
                <tbody>
                    @forelse ($rows as $row)
                        <tr>
                            <td class="whitespace-nowrap font-medium">{{ $row->date->locale('es')->isoFormat('ddd D/MM') }}</td>
                            <td>
                                <x-status-badge :status="$row->status" />
                                @if ($row->leaveType)<div class="text-xs text-gray-500">{{ $row->leaveType->name }}</div>@endif
                                @if ($row->is_offline)<div class="text-xs text-indigo-600">sin conexión</div>@endif
                                @if ($row->regularized)<div class="text-xs text-gray-500">regularizado</div>@endif
                            </td>
                            <td class="text-xs text-gray-600">{{ $row->shift?->name ?? '—' }}</td>
                            <td class="whitespace-nowrap">
                                {{ $row->check_in_at?->format('H:i') ?? '—' }}
                                @if ($row->late_minutes)<span class="text-xs text-amber-700">(+{{ $row->late_minutes }}m)</span>@endif
                                @if ($row->check_in_out_of_zone)<x-badge color="red">{{ $row->check_in_distance_m }} m</x-badge>@endif
                            </td>
                            <td class="whitespace-nowrap">
                                {{ $row->check_out_at?->format('H:i') ?? '—' }}
                                @if ($row->check_out_out_of_zone)<x-badge color="red">{{ $row->check_out_distance_m }} m</x-badge>@endif
                            </td>
                            <td>{{ $row->site?->name ?? '—' }}</td>
                            <td>{{ hours($row->worked_minutes) }}</td>
                            <td>
                                @if ($row->overtime_minutes)
                                    {{ hours($row->overtime_minutes) }} <x-status-badge :status="$row->overtime_status" />
                                @else — @endif
                            </td>
                            <td>
                                <div class="flex gap-1">
                                    @if ($row->check_in_selfie)<a href="{{ route('attendance.selfie', [$row, 'in']) }}" target="_blank"><img src="{{ route('attendance.selfie', [$row, 'in']) }}" class="h-9 w-9 rounded object-cover" alt="Entrada" loading="lazy"></a>@endif
                                    @if ($row->check_out_selfie)<a href="{{ route('attendance.selfie', [$row, 'out']) }}" target="_blank"><img src="{{ route('attendance.selfie', [$row, 'out']) }}" class="h-9 w-9 rounded object-cover" alt="Salida" loading="lazy"></a>@endif
                                </div>
                            </td>
                            <td class="text-right">
                                <a href="{{ route('corrections.create', ['date' => $row->date->toDateString()]) }}" class="btn-link text-xs">Corregir</a>
                            </td>
                        </tr>
                    @empty
                        <x-empty colspan="10" message="Sin registros en este mes." />
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-app-layout>
