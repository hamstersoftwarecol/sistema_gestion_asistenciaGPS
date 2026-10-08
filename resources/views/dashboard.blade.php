<x-app-layout>
    <x-slot name="header">Panel</x-slot>

    <div class="mb-6 flex flex-wrap items-end justify-between gap-3">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Hola, {{ \Illuminate\Support\Str::of($user->name)->before(' ') }}</h1>
            <p class="text-sm text-gray-500">{{ now()->locale('es')->isoFormat('dddd D [de] MMMM [de] YYYY') }} · {{ $user->role?->name }}</p>
        </div>
        @can('attendance.self')
            <a href="{{ route('my-attendance.index') }}" class="btn btn-primary">
                <x-icon name="camera" class="h-5 w-5" />
                {{ $myToday?->check_in_at && ! $myToday?->check_out_at ? 'Registrar salida' : 'Registrar entrada' }}
            </a>
        @endcan
    </div>

    @can('attendance.self')
        <div class="mb-6 grid gap-4 lg:grid-cols-3">
            <div class="card lg:col-span-1">
                <div class="card-header"><h2 class="card-title">Mi día</h2></div>
                <div class="card-body space-y-2 text-sm">
                    @if ($myToday)
                        <div class="flex items-center justify-between"><span class="text-gray-500">Estado</span><x-status-badge :status="$myToday->status" /></div>
                        <div class="flex items-center justify-between"><span class="text-gray-500">Entrada</span><span class="font-medium">{{ $myToday->check_in_at?->format('H:i') ?? '—' }}</span></div>
                        <div class="flex items-center justify-between"><span class="text-gray-500">Salida</span><span class="font-medium">{{ $myToday->check_out_at?->format('H:i') ?? '—' }}</span></div>
                        <div class="flex items-center justify-between"><span class="text-gray-500">Sitio</span><span class="font-medium">{{ $myToday->site?->name ?? '—' }}</span></div>
                        @if ($myToday->isOutOfZone())
                            <x-badge color="red">Registro fuera de zona</x-badge>
                        @endif
                    @else
                        <p class="text-gray-500">Aún no registras asistencia hoy.</p>
                    @endif
                </div>
            </div>
            <div class="grid grid-cols-2 gap-4 lg:col-span-2 lg:grid-cols-4">
                <x-kpi label="Días trabajados (mes)" :value="$mine['present']" tone="green" />
                <x-kpi label="Llegadas tarde" :value="$mine['late']" tone="yellow" />
                <x-kpi label="Horas trabajadas" :value="hours($mine['worked'])" tone="indigo" />
                <x-kpi label="Horas extra aprobadas" :value="hours($mine['overtime'])" tone="purple" />
                <div class="card col-span-2 lg:col-span-4">
                    <div class="card-header">
                        <h2 class="card-title">Saldos de permisos {{ now()->year }}</h2>
                        @can('leave.request')<a href="{{ route('leave-requests.create') }}" class="btn-link">Solicitar permiso →</a>@endcan
                    </div>
                    <div class="card-body flex flex-wrap gap-3">
                        @forelse ($balances as $balance)
                            <div class="rounded-lg border border-gray-200 px-3 py-2 text-sm">
                                <span class="mr-1 inline-block h-2 w-2 rounded-full" style="background: {{ $balance->leaveType->color }}"></span>
                                {{ $balance->leaveType->name }}: <strong>{{ $balance->available() }}</strong> <span class="text-gray-500">de {{ $balance->allocated + $balance->carried_forward }}</span>
                            </div>
                        @empty
                            <p class="text-sm text-gray-500">Sin saldos asignados.</p>
                        @endforelse
                        @if ($lastPayslip)
                            <a href="{{ route('payslips.show', $lastPayslip) }}" class="ml-auto rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-800">
                                Último recibo ({{ $lastPayslip->period->name }}): <strong>{{ money($lastPayslip->net_pay) }}</strong>
                            </a>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    @endcan

    @if ($team)
        <h2 class="mb-3 text-lg font-semibold text-gray-900">Mi equipo hoy</h2>
        <div class="mb-6 grid grid-cols-2 gap-4 md:grid-cols-3 xl:grid-cols-6">
            <x-kpi label="Empleados" :value="$team['total']" />
            <x-kpi label="Registraron entrada" :value="$team['checked_in']" tone="green" />
            <x-kpi label="Llegaron tarde" :value="$team['late']" tone="yellow" />
            <x-kpi label="Sin marcación" :value="$team['not_in']" tone="red" />
            <x-kpi label="De permiso" :value="$team['on_leave']" tone="blue" />
            <x-kpi label="Fuera de zona" :value="$team['out_of_zone']" tone="purple" />
        </div>

        <div class="grid gap-4 lg:grid-cols-3">
            <div class="card lg:col-span-2">
                <div class="card-header">
                    <h2 class="card-title">Últimos 7 días</h2>
                    <div class="flex gap-3 text-xs text-gray-500">
                        <span class="flex items-center gap-1"><span class="h-2.5 w-2.5 rounded-sm bg-emerald-500"></span>Presentes</span>
                        <span class="flex items-center gap-1"><span class="h-2.5 w-2.5 rounded-sm bg-amber-400"></span>Tarde</span>
                        <span class="flex items-center gap-1"><span class="h-2.5 w-2.5 rounded-sm bg-sky-500"></span>Permiso</span>
                        <span class="flex items-center gap-1"><span class="h-2.5 w-2.5 rounded-sm bg-red-500"></span>Ausentes</span>
                    </div>
                </div>
                <div class="card-body">
                    @php($max = max(1, $team['week']->max(fn ($d) => $d['present'] + $d['late'] + $d['absent'] + $d['leave'])))
                    <div class="flex h-48 items-end gap-3">
                        @foreach ($team['week'] as $day)
                            @php($total = $day['present'] + $day['late'] + $day['absent'] + $day['leave'])
                            <div class="flex h-full flex-1 flex-col items-center justify-end gap-1" title="{{ $day['label'] }}: {{ $day['present'] }} presentes, {{ $day['late'] }} tarde, {{ $day['leave'] }} permiso, {{ $day['absent'] }} ausentes">
                                <span class="text-xs tabular-nums text-gray-500">{{ $total ?: '' }}</span>
                                <div class="flex w-full max-w-[3rem] flex-col-reverse gap-px overflow-hidden rounded-t" style="height: {{ round($total / $max * 100) }}%">
                                    @foreach (['present' => 'bg-emerald-500', 'late' => 'bg-amber-400', 'leave' => 'bg-sky-500', 'absent' => 'bg-red-500'] as $key => $color)
                                        @if ($day[$key])
                                            <div class="{{ $color }}" style="flex: {{ $day[$key] }}"></div>
                                        @endif
                                    @endforeach
                                </div>
                                <span class="text-xs capitalize text-gray-600">{{ $day['label'] }}</span>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header"><h2 class="card-title">Pendientes de aprobación</h2></div>
                <div class="card-body space-y-2 text-sm">
                    @can('attendance.approve')
                        <a href="{{ route('corrections.approvals') }}" class="flex items-center justify-between rounded-lg border border-gray-200 px-3 py-2 hover:bg-gray-50">Correcciones de asistencia <x-badge :color="$team['pending_corrections'] ? 'yellow' : 'gray'">{{ $team['pending_corrections'] }}</x-badge></a>
                        <a href="{{ route('overtime.index') }}" class="flex items-center justify-between rounded-lg border border-gray-200 px-3 py-2 hover:bg-gray-50">Horas extra <x-badge :color="$team['pending_overtime'] ? 'yellow' : 'gray'">{{ $team['pending_overtime'] }}</x-badge></a>
                    @endcan
                    @can('leave.approve')
                        <a href="{{ route('leave-requests.approvals') }}" class="flex items-center justify-between rounded-lg border border-gray-200 px-3 py-2 hover:bg-gray-50">Solicitudes de permiso <x-badge :color="$team['pending_leave'] ? 'yellow' : 'gray'">{{ $team['pending_leave'] }}</x-badge></a>
                    @endcan
                    @can('live_map.view')
                        <a href="{{ route('live-map.index') }}" class="btn btn-secondary mt-2 w-full"><x-icon name="map" class="h-4 w-4" /> Abrir mapa en vivo</a>
                    @endcan
                </div>
            </div>

            <div class="card lg:col-span-3">
                <div class="card-header"><h2 class="card-title">Marcaciones recientes de hoy</h2></div>
                <div class="table-wrap">
                    <table class="table">
                        <thead><tr><th>Empleado</th><th>Estado</th><th>Entrada</th><th>Salida</th><th>Sitio</th><th>Geocerca</th></tr></thead>
                        <tbody>
                            @forelse ($team['recent'] as $row)
                                <tr>
                                    <td class="font-medium">{{ $row->user->name }}</td>
                                    <td><x-status-badge :status="$row->status" /></td>
                                    <td>{{ $row->check_in_at?->format('H:i') }}</td>
                                    <td>{{ $row->check_out_at?->format('H:i') ?? '—' }}</td>
                                    <td>{{ $row->site?->name ?? '—' }}</td>
                                    <td>
                                        @if ($row->isOutOfZone())
                                            <x-badge color="red">Fuera ({{ $row->check_in_distance_m }} m)</x-badge>
                                        @else
                                            <x-badge color="green">Dentro</x-badge>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <x-empty colspan="6" message="Nadie ha registrado entrada hoy." />
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    @endif
</x-app-layout>
