<x-app-layout>
    <x-slot name="header">Asistencia de {{ $employee->name }} · {{ $day->format('d/m/Y') }}</x-slot>

    @php
        $points = [];
        if ($attendance->check_in_lat) {
            $points[] = ['lat' => $attendance->check_in_lat, 'lng' => $attendance->check_in_lng, 'label' => 'Entrada '.$attendance->check_in_at?->format('H:i'), 'color' => $attendance->check_in_out_of_zone ? '#dc2626' : '#16a34a'];
        }
        if ($attendance->check_out_lat) {
            $points[] = ['lat' => $attendance->check_out_lat, 'lng' => $attendance->check_out_lng, 'label' => 'Salida '.$attendance->check_out_at?->format('H:i'), 'color' => $attendance->check_out_out_of_zone ? '#dc2626' : '#6b7280'];
        }
        $siteData = $sites->whereIn('id', array_filter([$attendance->site_id, $attendance->check_out_site_id, $employee->site_id]))->values()
            ->map(fn ($s) => $s->only(['name', 'latitude', 'longitude', 'radius_m']));
    @endphp

    <div class="mb-4">
        <a href="{{ route('register.index', ['date' => $day->toDateString()]) }}" class="btn-link">← Volver al registro del día</a>
    </div>

    <div class="grid gap-6 lg:grid-cols-5">
        <div class="space-y-4 lg:col-span-2">
            <div class="card">
                <div class="card-header"><h2 class="card-title">Evidencia</h2>@if ($attendance->exists)<x-status-badge :status="$attendance->status" />@endif</div>
                <div class="card-body space-y-3 text-sm">
                    <div class="grid grid-cols-2 gap-3">
                        @foreach (['in' => 'Entrada', 'out' => 'Salida'] as $type => $label)
                            @php($selfie = $type === 'in' ? $attendance->check_in_selfie : $attendance->check_out_selfie)
                            <div>
                                <div class="mb-1 text-xs font-medium text-gray-500">{{ $label }}</div>
                                @if ($selfie)
                                    <a href="{{ route('attendance.selfie', [$attendance, $type]) }}" target="_blank"><img src="{{ route('attendance.selfie', [$attendance, $type]) }}" class="aspect-square w-full rounded-lg object-cover" alt="Selfie {{ $label }}"></a>
                                @else
                                    <div class="flex aspect-square w-full items-center justify-center rounded-lg bg-gray-100 text-xs text-gray-400">Sin selfie</div>
                                @endif
                            </div>
                        @endforeach
                    </div>
                    @if ($points)
                        <div x-data="pointsMap({ sites: @js($siteData), points: @js($points) })" class="h-64 overflow-hidden rounded-lg"><div x-ref="map" class="h-full w-full"></div></div>
                    @endif
                    <dl class="grid grid-cols-2 gap-x-3 gap-y-1">
                        <dt class="text-gray-500">Sitio entrada</dt><dd>{{ $attendance->site?->name ?? '—' }} @if ($attendance->check_in_distance_m !== null)({{ $attendance->check_in_distance_m }} m)@endif</dd>
                        <dt class="text-gray-500">Sitio salida</dt><dd>{{ $attendance->checkOutSite?->name ?? '—' }} @if ($attendance->check_out_distance_m !== null)({{ $attendance->check_out_distance_m }} m)@endif</dd>
                        <dt class="text-gray-500">Precisión GPS</dt><dd>{{ $attendance->check_in_accuracy ? '±'.$attendance->check_in_accuracy.' m' : '—' }}</dd>
                        <dt class="text-gray-500">Origen</dt><dd>{{ \App\Models\Attendance::SOURCES[$attendance->source] ?? '—' }} @if ($attendance->is_offline)<x-badge color="indigo">sin conexión</x-badge>@endif</dd>
                        <dt class="text-gray-500">Trabajado</dt><dd>{{ hours($attendance->worked_minutes) }}</dd>
                        <dt class="text-gray-500">Retraso</dt><dd>{{ $attendance->late_minutes ? $attendance->late_minutes.' min' : '—' }}</dd>
                        <dt class="text-gray-500">Salida anticipada</dt><dd>{{ $attendance->early_leave_minutes ? $attendance->early_leave_minutes.' min' : '—' }}</dd>
                        <dt class="text-gray-500">Nocturnas</dt><dd>{{ hours($attendance->night_minutes) }}</dd>
                        <dt class="text-gray-500">Desc./festivo</dt><dd>{{ hours($attendance->holiday_minutes) }}</dd>
                        <dt class="text-gray-500">Marcado por</dt><dd>{{ $attendance->markedBy?->name ?? '—' }}</dd>
                    </dl>
                </div>
            </div>
        </div>

        <div class="lg:col-span-3">
            <form method="POST" action="{{ route('register.update', [$employee, $day->toDateString()]) }}" class="card">
                @csrf @method('PUT')
                <div class="card-header"><h2 class="card-title">Edición individual</h2></div>
                <div class="card-body grid gap-4 sm:grid-cols-2" x-data="{ status: @js(old('status', $attendance->status ?? 'present')) }">
                    <div>
                        <label class="label" for="status">Estado</label>
                        <select id="status" name="status" class="input" x-model="status">
                            @foreach (\App\Models\Attendance::STATUSES as $key => [$label])
                                <option value="{{ $key }}">{{ $label }}</option>
                            @endforeach
                        </select>
                        <p class="help">Con horas registradas el estado se recalcula (presente/tarde/medio día) según el turno.</p>
                    </div>
                    <div x-show="status === 'on_leave'" x-cloak>
                        <x-field name="leave_type_id" label="Tipo de permiso" type="select" :options="$leaveTypes->pluck('name', 'id')" :value="$attendance->leave_type_id" empty="Seleccione…" />
                    </div>
                    <x-field name="check_in_at" label="Entrada" type="datetime-local" :value="$attendance->check_in_at" />
                    <x-field name="check_out_at" label="Salida" type="datetime-local" :value="$attendance->check_out_at" />
                    <x-field name="shift_id" label="Turno" type="select" :options="$shifts->mapWithKeys(fn ($s) => [$s->id => $s->label()])" :value="$attendance->shift_id" empty="Turno del calendario / por defecto" />
                    <x-field name="site_id" label="Sitio" type="select" :options="$sites->pluck('name', 'id')" :value="$attendance->site_id" empty="—" />

                    @if ($attendance->overtime_minutes > 0)
                        <div class="rounded-lg border border-purple-200 bg-purple-50 p-3 sm:col-span-2">
                            <div class="mb-2 text-sm font-medium text-purple-900">Horas extra calculadas: {{ hours($attendance->overtime_minutes) }} · <x-status-badge :status="$attendance->overtime_status" /></div>
                            <div class="grid gap-3 sm:grid-cols-2">
                                <x-field name="overtime_status" label="Decisión" type="select" :options="['pending' => 'Pendiente', 'approved' => 'Aprobar', 'rejected' => 'Rechazar']" :value="$attendance->overtime_status" />
                                <x-field name="approved_overtime_minutes" label="Minutos aprobados" type="number" min="0" :max="$attendance->overtime_minutes" :value="$attendance->approved_overtime_minutes ?: $attendance->overtime_minutes" />
                            </div>
                        </div>
                    @endif

                    <x-field name="notes" label="Notas" type="textarea" class="sm:col-span-2" :value="$attendance->notes" />
                </div>
                <div class="flex items-center justify-between border-t border-gray-100 px-5 py-3">
                    @if ($attendance->exists)
                        <button type="submit" form="delete-row" class="btn btn-secondary text-red-600" onclick="return confirm('¿Eliminar la fila de asistencia de este día?')"><x-icon name="trash" class="h-4 w-4" /> Eliminar fila</button>
                    @else
                        <span></span>
                    @endif
                    <button class="btn btn-primary">Guardar y recalcular</button>
                </div>
            </form>
            @if ($attendance->exists)
                <form id="delete-row" method="POST" action="{{ route('register.destroy', [$employee, $day->toDateString()]) }}">@csrf @method('DELETE')</form>
            @endif
        </div>
    </div>
</x-app-layout>
