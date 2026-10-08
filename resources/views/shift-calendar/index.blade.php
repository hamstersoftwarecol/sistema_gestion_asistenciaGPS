<x-app-layout>
    <x-slot name="header">Calendario de turnos y asignación de zonas</x-slot>

    <form method="GET" class="mb-4 flex flex-wrap items-end gap-3">
        <div>
            <label class="label" for="month">Mes</label>
            <input id="month" type="month" name="month" value="{{ $month->format('Y-m') }}" class="input">
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
        <button class="btn btn-secondary">Ver</button>
        <div class="ml-auto flex flex-wrap items-center gap-2 text-xs">
            @foreach ($shifts as $shift)
                <span class="badge text-white" style="background: {{ $shift->color }}">{{ $shift->code ?: $shift->name }} · {{ $shift->start_time }}–{{ $shift->end_time }}</span>
            @endforeach
            <span class="badge bg-gray-200 text-gray-700">Desc = descanso</span>
            <span class="badge bg-purple-100 text-purple-800">F = festivo</span>
        </div>
    </form>

    <form method="POST" action="{{ route('shift-calendar.assign') }}"
          x-data="{ users: [], mode: 'shift', all: @js($employees->pluck('id')->map(fn ($id) => (string) $id)) }">
        @csrf
        <input type="hidden" name="department_id" value="{{ request('department_id') }}">

        <div class="card mb-4">
            <div class="card-header"><h2 class="card-title">Asignación masiva</h2><span class="text-sm text-gray-500"><span x-text="users.length"></span> empleado(s) seleccionado(s)</span></div>
            <div class="card-body grid gap-4 md:grid-cols-6">
                <x-field name="from" label="Desde" type="date" :value="$month->copy()->startOfMonth()->toDateString()" required />
                <x-field name="to" label="Hasta" type="date" :value="$month->copy()->endOfMonth()->toDateString()" required />
                <div>
                    <label class="label" for="mode">Acción</label>
                    <select id="mode" name="mode" class="input" x-model="mode">
                        <option value="shift">Asignar turno</option>
                        <option value="off">Marcar descanso</option>
                        <option value="clear">Quitar asignación</option>
                    </select>
                </div>
                <div x-show="mode === 'shift'">
                    <x-field name="shift_id" label="Turno" type="select" :options="$shifts->mapWithKeys(fn ($s) => [$s->id => $s->label()])" empty="Seleccione…" />
                </div>
                <div x-show="mode === 'shift'" class="md:col-span-2">
                    <x-field name="site_id" label="Zona / sitio" type="select" :options="$sites->pluck('name', 'id')" empty="Sitio por defecto del empleado" />
                </div>
                <div class="md:col-span-4">
                    <span class="label">Días de la semana</span>
                    <div class="flex flex-wrap gap-3">
                        @foreach (['1' => 'Lun', '2' => 'Mar', '3' => 'Mié', '4' => 'Jue', '5' => 'Vie', '6' => 'Sáb', '0' => 'Dom'] as $value => $label)
                            <label class="inline-flex items-center gap-1 text-sm">
                                <input type="checkbox" class="checkbox" name="weekdays[]" value="{{ $value }}" @checked(! in_array((int) $value, $weekendDays, true))> {{ $label }}
                            </label>
                        @endforeach
                    </div>
                </div>
                <div class="flex items-end justify-end md:col-span-2">
                    <button class="btn btn-primary" :disabled="!users.length">Aplicar a seleccionados</button>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="table-wrap">
                <table class="min-w-full border-collapse text-xs">
                    <thead>
                        <tr class="bg-gray-50">
                            <th class="sticky left-0 z-10 bg-gray-50 px-2 py-2 text-left">
                                <label class="inline-flex items-center gap-2 font-semibold text-gray-600">
                                    <input type="checkbox" class="checkbox" @change="users = $event.target.checked ? [...all] : []"> Empleado
                                </label>
                            </th>
                            @foreach ($days as $day)
                                @php($isHoliday = $holidays->has($day->toDateString()))
                                @php($isWeekend = in_array($day->dayOfWeek, $weekendDays, true))
                                <th @class(['min-w-[2.6rem] border-l border-gray-100 px-1 py-1 text-center font-medium', 'bg-purple-50 text-purple-800' => $isHoliday, 'bg-gray-100 text-gray-500' => $isWeekend && ! $isHoliday, 'ring-2 ring-inset ring-indigo-400' => $day->isToday()])
                                    title="{{ $isHoliday ? $holidays[$day->toDateString()]->name : '' }}">
                                    <div class="uppercase">{{ mb_substr($day->locale('es')->isoFormat('dd'), 0, 2) }}</div>
                                    <div class="text-sm">{{ $day->day }}</div>
                                </th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($employees as $employee)
                            @php($mine = $assignments->get($employee->id, collect()))
                            <tr class="border-t border-gray-100">
                                <td class="sticky left-0 z-10 whitespace-nowrap bg-white px-2 py-1.5">
                                    <label class="inline-flex items-center gap-2">
                                        <input type="checkbox" class="checkbox" name="user_ids[]" value="{{ $employee->id }}" x-model="users">
                                        <span>
                                            <span class="font-medium text-gray-800">{{ $employee->name }}</span>
                                            <span class="block text-[11px] text-gray-500">Por defecto: {{ $employee->shift?->code ?? $employee->shift?->name ?? '—' }} · {{ $employee->site?->code ?? $employee->site?->name ?? '—' }}</span>
                                        </span>
                                    </label>
                                </td>
                                @foreach ($days as $day)
                                    @php($a = $mine->get($day->toDateString()))
                                    @php($isHoliday = $holidays->has($day->toDateString()))
                                    @php($isWeekend = in_array($day->dayOfWeek, $weekendDays, true))
                                    <td class="border-l border-gray-100 p-0.5 text-center">
                                        @if ($a && $a->is_off)
                                            <span class="block rounded bg-gray-200 py-1 text-[10px] font-semibold text-gray-600" title="Descanso">Desc</span>
                                        @elseif ($a && $a->shift)
                                            <span class="block rounded py-1 font-semibold text-white" style="background: {{ $a->shift->color }}" title="{{ $a->shift->label() }}{{ $a->site ? ' · '.$a->site->name : '' }}">
                                                {{ $a->shift->code ?: mb_substr($a->shift->name, 0, 2) }}
                                                @if ($a->site)<span class="block text-[9px] font-normal opacity-90">{{ $a->site->code ?: mb_substr($a->site->name, 0, 4) }}</span>@endif
                                            </span>
                                        @elseif ($isHoliday)
                                            <span class="block rounded bg-purple-50 py-1 text-purple-700">F</span>
                                        @elseif ($isWeekend)
                                            <span class="block py-1 text-[10px] text-gray-300" title="Fin de semana">Desc</span>
                                        @elseif ($employee->shift)
                                            <span class="block rounded border border-dashed py-1 text-gray-400" style="border-color: {{ $employee->shift->color }}" title="Turno por defecto">{{ $employee->shift->code ?: '·' }}</span>
                                        @else
                                            <span class="block py-1 text-gray-300">·</span>
                                        @endif
                                    </td>
                                @endforeach
                            </tr>
                        @empty
                            <x-empty :colspan="$days->count() + 1" message="No hay empleados para mostrar." />
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        <p class="mt-2 text-xs text-gray-500">Las celdas sólidas son asignaciones del calendario; las punteadas, el turno por defecto del empleado. Las asignaciones definen el turno, la zona y si el día es laboral para el cálculo de retrasos, horas extra y ausencias.</p>
    </form>
</x-app-layout>
