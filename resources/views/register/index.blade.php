<x-app-layout>
    <x-slot name="header">Registro de asistencia</x-slot>

    <form method="GET" class="mb-4 flex flex-wrap items-end gap-3">
        <div>
            <label class="label" for="date">Fecha</label>
            <input id="date" type="date" name="date" value="{{ $date->toDateString() }}" class="input" max="{{ today()->toDateString() }}">
        </div>
        <div>
            <label class="label" for="department_id">Departamento</label>
            <select id="department_id" name="department_id" class="input">
                <option value="">Todos</option>
                @foreach ($departments as $d)
                    <option value="{{ $d->id }}" @selected(request('department_id') == $d->id)>{{ $d->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="label" for="q">Buscar</label>
            <input id="q" name="q" value="{{ request('q') }}" class="input" placeholder="Nombre o código">
        </div>
        <button class="btn btn-secondary">Filtrar</button>
        <div class="ml-auto flex gap-2">
            <a class="btn btn-secondary" href="{{ route('register.index', ['date' => $date->copy()->subDay()->toDateString()] + request()->only('department_id')) }}">← Día anterior</a>
            @if ($date->lt(today()))
                <a class="btn btn-secondary" href="{{ route('register.index', ['date' => $date->copy()->addDay()->toDateString()] + request()->only('department_id')) }}">Día siguiente →</a>
            @endif
        </div>
    </form>

    <div class="mb-3 flex flex-wrap items-center gap-2 text-sm text-gray-600">
        <span class="font-semibold text-gray-900">{{ ucfirst($date->locale('es')->isoFormat('dddd D [de] MMMM YYYY')) }}</span>
        @if ($holiday)<x-badge color="purple">Festivo: {{ $holiday->name }}</x-badge>@endif
        <span>· {{ $rows->whereNotNull('check_in_at')->count() }} con marcación de {{ $employees->count() }} empleados</span>
    </div>

    @php($canMark = auth()->user()->can('attendance.mark'))

    <form method="POST" action="{{ route('register.store') }}"
          x-data="{
            selected: [],
            all: @js($employees->pluck('id')->map(fn ($id) => (string) $id)),
            toggleAll(e) { this.selected = e.target.checked ? [...this.all] : [] },
            bulk(status) {
                this.selected.forEach(id => {
                    const el = document.getElementById('status_' + id);
                    if (el) el.value = status;
                });
            }
          }">
        @csrf
        <input type="hidden" name="date" value="{{ $date->toDateString() }}">

        @if ($canMark)
            <div class="card mb-3">
                <div class="card-body flex flex-wrap items-center gap-2 py-3 text-sm">
                    <span class="font-medium">Marcado masivo:</span>
                    <span class="text-gray-500"><span x-text="selected.length"></span> seleccionado(s) →</span>
                    <button type="button" class="btn btn-success btn-sm" @click="bulk('present')" :disabled="!selected.length">Presente (horario del turno)</button>
                    <button type="button" class="btn btn-warning btn-sm" @click="bulk('half_day')" :disabled="!selected.length">Medio día</button>
                    <button type="button" class="btn btn-danger btn-sm" @click="bulk('absent')" :disabled="!selected.length">Ausente</button>
                    <button class="btn btn-primary btn-sm ml-auto" :disabled="!selected.length">Guardar seleccionados</button>
                </div>
            </div>
        @endif

        <div class="card">
            <div class="table-wrap">
                <table class="table">
                    <thead>
                        <tr>
                            @if ($canMark)<th class="w-8"><input type="checkbox" class="checkbox" @change="toggleAll($event)" :checked="selected.length && selected.length === all.length"></th>@endif
                            <th>Empleado</th><th>Turno / sitio</th><th>Estado actual</th><th>Marcación</th>
                            @if ($canMark)<th>Marcar como</th><th>Entrada</th><th>Salida</th><th>Nota</th>@endif
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($employees as $employee)
                            @php($row = $rows->get($employee->id))
                            @php($schedule = $schedules[$employee->id])
                            <tr :class="selected.includes('{{ $employee->id }}') && 'bg-indigo-50/60'">
                                @if ($canMark)
                                    <td><input type="checkbox" class="checkbox" name="rows[{{ $employee->id }}][apply]" value="{{ $employee->id }}" x-model="selected"></td>
                                @endif
                                <td>
                                    <div class="font-medium">{{ $employee->name }}</div>
                                    <div class="text-xs text-gray-500">{{ $employee->employee_code }} · {{ $employee->department?->name }}</div>
                                </td>
                                <td class="text-xs">
                                    @if ($schedule['day_type'] !== 'workday')
                                        <x-badge color="gray">{{ \App\Models\Attendance::DAY_TYPES[$schedule['day_type']] }}</x-badge>
                                    @endif
                                    <div>{{ $schedule['shift']?->label() ?? 'Sin turno' }}</div>
                                    <div class="text-gray-500">{{ $schedule['site']?->name }}</div>
                                </td>
                                <td>
                                    @if ($row)
                                        <x-status-badge :status="$row->status" />
                                        @if ($row->late_minutes)<div class="text-xs text-amber-700">+{{ $row->late_minutes }} min</div>@endif
                                    @else
                                        <span class="text-xs text-gray-400">Sin registro</span>
                                    @endif
                                </td>
                                <td class="whitespace-nowrap text-xs">
                                    @if ($row?->check_in_at)
                                        <div>{{ $row->check_in_at->format('H:i') }} – {{ $row->check_out_at?->format('H:i') ?? '…' }}</div>
                                        <div class="text-gray-500">{{ \App\Models\Attendance::SOURCES[$row->source] ?? $row->source }}</div>
                                        @if ($row->isOutOfZone())<x-badge color="red">Fuera de zona</x-badge>@endif
                                    @else
                                        —
                                    @endif
                                </td>
                                @if ($canMark)
                                    <td>
                                        <select id="status_{{ $employee->id }}" name="rows[{{ $employee->id }}][status]" class="input py-1 text-xs"
                                                @change="if (!selected.includes('{{ $employee->id }}')) selected.push('{{ $employee->id }}')">
                                            <option value="">—</option>
                                            <option value="present">Presente</option>
                                            <option value="half_day">Medio día</option>
                                            <option value="absent">Ausente</option>
                                        </select>
                                    </td>
                                    <td><input type="time" name="rows[{{ $employee->id }}][check_in]" class="input w-24 py-1 text-xs"></td>
                                    <td><input type="time" name="rows[{{ $employee->id }}][check_out]" class="input w-24 py-1 text-xs"></td>
                                    <td><input name="rows[{{ $employee->id }}][notes]" class="input w-32 py-1 text-xs" placeholder="Opcional"></td>
                                @endif
                                <td class="text-right">
                                    @if ($canMark)
                                        <a href="{{ route('register.edit', [$employee, $date->toDateString()]) }}" class="btn-link text-xs">Detalle</a>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <x-empty colspan="10" message="No hay empleados para mostrar." />
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        <p class="mt-2 text-xs text-gray-500">Las marcaciones hechas con la app (selfie + GPS) no se sobrescriben como ausentes. Si no indicas horas, se usa el horario del turno asignado.</p>
    </form>
</x-app-layout>
