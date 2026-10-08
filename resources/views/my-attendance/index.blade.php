<x-app-layout>
    <x-slot name="header">Mi asistencia</x-slot>

    @php
        $openRow = $open ?? null;
        $nextType = $openRow ? 'out' : 'in';
        $doneToday = $today && $today->check_in_at && $today->check_out_at && ! $openRow;
    @endphp

    <div class="grid gap-6 lg:grid-cols-5"
         x-data="punchClock({
            punchUrl: @js(route('my-attendance.punch')),
            syncUrl: @js(route('my-attendance.sync')),
            tokenUrl: @js(route('my-attendance.token')),
            sites: @js($sites),
            requireSelfie: @js((bool) setting('require_selfie')),
            allowOffline: @js((bool) setting('allow_offline_punch')),
            nextType: @js($nextType),
         })">

        <div class="space-y-4 lg:col-span-3">
            {{-- Estado de conexión y cola sin conexión --}}
            <div class="flex flex-wrap items-center gap-2 text-sm">
                <span x-show="online" class="badge bg-emerald-100 text-emerald-800"><span class="h-2 w-2 rounded-full bg-emerald-500"></span> En línea</span>
                <span x-show="!online" x-cloak class="badge bg-amber-100 text-amber-800"><x-icon name="offline" class="h-3.5 w-3.5" /> Sin conexión</span>
                <template x-if="queue.length">
                    <span class="badge bg-indigo-100 text-indigo-800">
                        <span x-text="queue.length"></span>&nbsp;registro(s) en cola
                    </span>
                </template>
                <button type="button" x-show="queue.length && online" x-cloak class="btn btn-secondary btn-sm" @click="sync()" :disabled="syncing">
                    <x-icon name="refresh" class="h-3.5 w-3.5" /> <span x-text="syncing ? 'Sincronizando…' : 'Sincronizar ahora'"></span>
                </button>
            </div>

            <template x-if="message">
                <div class="rounded-lg border p-3 text-sm"
                     :class="{
                        'border-emerald-200 bg-emerald-50 text-emerald-800': messageType === 'success',
                        'border-amber-200 bg-amber-50 text-amber-800': messageType === 'warning',
                        'border-red-200 bg-red-50 text-red-800': messageType === 'error',
                        'border-sky-200 bg-sky-50 text-sky-800': messageType === 'info',
                     }" x-text="message"></div>
            </template>

            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">
                        <span x-text="nextType === 'in' ? 'Registrar entrada' : 'Registrar salida'"></span>
                    </h2>
                    <span class="text-sm text-gray-500" x-data="{ now: new Date() }" x-init="setInterval(() => now = new Date(), 1000)"
                          x-text="now.toLocaleTimeString('es', { hour: '2-digit', minute: '2-digit', second: '2-digit' })"></span>
                </div>
                <div class="card-body space-y-5">
                    @if ($doneToday)
                        <div class="rounded-lg bg-gray-50 p-3 text-sm text-gray-600">
                            Ya registraste entrada y salida hoy ({{ $today->check_in_at->format('H:i') }}–{{ $today->check_out_at->format('H:i') }}).
                            Si hay un error, <a class="btn-link" href="{{ route('corrections.create', ['date' => today()->toDateString()]) }}">solicita una corrección</a>.
                        </div>
                    @endif

                    {{-- Paso 1: selfie --}}
                    <div>
                        <div class="mb-2 flex items-center justify-between">
                            <h3 class="text-sm font-semibold text-gray-800">1. Selfie {{ setting('require_selfie') ? '' : '(opcional)' }}</h3>
                            <span x-show="selfie" x-cloak class="badge bg-emerald-100 text-emerald-800"><x-icon name="check" class="h-3 w-3" /> Lista</span>
                        </div>
                        <div class="relative aspect-[4/3] w-full overflow-hidden rounded-lg bg-gray-900">
                            <video x-ref="video" x-show="cameraOn" playsinline muted class="h-full w-full -scale-x-100 object-cover"></video>
                            <img x-show="selfie && !cameraOn" x-cloak :src="selfie" alt="Selfie capturada" class="h-full w-full object-cover">
                            <div x-show="!cameraOn && !selfie" class="absolute inset-0 flex flex-col items-center justify-center gap-3 text-gray-300">
                                <x-icon name="camera" class="h-12 w-12" />
                                <button type="button" class="btn btn-primary" @click="startCamera()">Activar cámara</button>
                            </div>
                            <div x-show="cameraOn" x-cloak class="absolute inset-x-0 bottom-3 flex justify-center gap-2">
                                <button type="button" class="btn btn-primary" @click="capture()"><x-icon name="camera" class="h-5 w-5" /> Tomar selfie</button>
                                <button type="button" class="btn btn-secondary" @click="stopCamera()">Cancelar</button>
                            </div>
                        </div>
                        <p x-show="cameraError" x-cloak class="mt-2 text-sm text-red-600" x-text="cameraError"></p>
                        <button type="button" x-show="selfie && !cameraOn" x-cloak class="btn-link mt-2" @click="retake()">Repetir selfie</button>
                    </div>

                    {{-- Paso 2: GPS --}}
                    <div>
                        <div class="mb-2 flex items-center justify-between">
                            <h3 class="text-sm font-semibold text-gray-800">2. Ubicación GPS</h3>
                            <button type="button" class="btn btn-secondary btn-sm" @click="locate()" :disabled="locating">
                                <x-icon name="pin" class="h-3.5 w-3.5" /> <span x-text="locating ? 'Ubicando…' : 'Actualizar'"></span>
                            </button>
                        </div>
                        <div class="rounded-lg border border-gray-200 p-3 text-sm">
                            <p x-show="locating && !position" class="text-gray-500">Obteniendo ubicación…</p>
                            <p x-show="gpsError" x-cloak class="text-red-600" x-text="gpsError"></p>
                            <template x-if="position">
                                <div class="space-y-1">
                                    <div class="text-gray-600">
                                        <span x-text="position.latitude.toFixed(6)"></span>, <span x-text="position.longitude.toFixed(6)"></span>
                                        <span class="text-gray-400">· precisión ±<span x-text="position.accuracy"></span> m</span>
                                    </div>
                                    <template x-if="nearest">
                                        <div class="flex flex-wrap items-center gap-2">
                                            <span>Sitio más cercano: <strong x-text="nearest.name"></strong> a <strong x-text="nearest.distance + ' m'"></strong></span>
                                            <span x-show="nearest.inside" class="badge bg-emerald-100 text-emerald-800">Dentro de la geocerca</span>
                                            <span x-show="!nearest.inside" class="badge bg-red-100 text-red-800">Fuera de zona (radio <span x-text="nearest.radius"></span> m)</span>
                                        </div>
                                    </template>
                                    @if (setting('out_of_zone_action') === 'block')
                                        <p x-show="nearest && !nearest.inside" class="text-xs text-red-600">La política actual bloquea registros fuera de zona.</p>
                                    @else
                                        <p x-show="nearest && !nearest.inside" class="text-xs text-amber-700">Puedes registrar, pero quedará marcado como fuera de zona para revisión.</p>
                                    @endif
                                </div>
                            </template>
                        </div>
                    </div>

                    {{-- Paso 3: enviar --}}
                    <button type="button" class="btn w-full py-3 text-base"
                            :class="nextType === 'in' ? 'btn-success' : 'btn-warning'"
                            :disabled="!canSubmit" @click="submit()">
                        <x-icon name="login" class="h-5 w-5" />
                        <span x-text="busy ? 'Enviando…' : (nextType === 'in' ? 'Registrar ENTRADA' : 'Registrar SALIDA')"></span>
                    </button>
                    <p class="text-center text-xs text-gray-500">
                        Si no hay señal, el registro se guarda en este dispositivo con la hora real y se envía al reconectar
                        @if (setting('allow_offline_punch'))(máx. {{ setting('offline_max_hours') }} h).@else(deshabilitado por la política actual).@endif
                    </p>
                </div>
            </div>
        </div>

        <div class="space-y-4 lg:col-span-2">
            <div class="card">
                <div class="card-header"><h2 class="card-title">Hoy · {{ today()->format('d/m/Y') }}</h2></div>
                <div class="card-body space-y-2 text-sm">
                    <div class="flex justify-between"><span class="text-gray-500">Turno</span><span class="font-medium">{{ $schedule['shift']?->label() ?? 'Sin turno asignado' }}</span></div>
                    <div class="flex justify-between"><span class="text-gray-500">Sitio asignado</span><span class="font-medium">{{ $schedule['site']?->name ?? '—' }}</span></div>
                    <div class="flex justify-between"><span class="text-gray-500">Tipo de día</span><span class="font-medium">{{ \App\Models\Attendance::DAY_TYPES[$schedule['day_type']] }}</span></div>
                    @php($current = $openRow ?? $today)
                    @if ($current)
                        <hr class="my-2">
                        <div class="flex justify-between"><span class="text-gray-500">Estado</span><x-status-badge :status="$current->status" /></div>
                        <div class="flex justify-between"><span class="text-gray-500">Entrada</span><span>{{ $current->check_in_at?->format('d/m H:i') ?? '—' }} @if ($current->check_in_out_of_zone)<x-badge color="red">fuera</x-badge>@endif</span></div>
                        <div class="flex justify-between"><span class="text-gray-500">Salida</span><span>{{ $current->check_out_at?->format('d/m H:i') ?? '—' }} @if ($current->check_out_out_of_zone)<x-badge color="red">fuera</x-badge>@endif</span></div>
                        @if ($current->late_minutes)
                            <div class="flex justify-between"><span class="text-gray-500">Retraso</span><span class="text-amber-700">{{ $current->late_minutes }} min</span></div>
                        @endif
                        @if ($current->worked_minutes)
                            <div class="flex justify-between"><span class="text-gray-500">Trabajado</span><span>{{ hours($current->worked_minutes) }}</span></div>
                        @endif
                        @if ($current->check_in_selfie || $current->check_out_selfie)
                            <div class="flex gap-2 pt-2">
                                @if ($current->check_in_selfie)<img src="{{ route('attendance.selfie', [$current, 'in']) }}" class="h-20 w-20 rounded-lg object-cover" alt="Selfie de entrada">@endif
                                @if ($current->check_out_selfie)<img src="{{ route('attendance.selfie', [$current, 'out']) }}" class="h-20 w-20 rounded-lg object-cover" alt="Selfie de salida">@endif
                            </div>
                        @endif
                    @endif
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">Últimos registros</h2>
                    <a href="{{ route('my-attendance.history') }}" class="btn-link">Ver historial →</a>
                </div>
                <ul class="divide-y divide-gray-100 text-sm">
                    @forelse ($recent as $row)
                        <li class="flex items-center justify-between px-4 py-2">
                            <div>
                                <div class="font-medium">{{ $row->date->locale('es')->isoFormat('ddd D MMM') }}</div>
                                <div class="text-xs text-gray-500">{{ $row->check_in_at?->format('H:i') ?? '—' }} – {{ $row->check_out_at?->format('H:i') ?? '—' }} · {{ $row->site?->name ?? 'sin sitio' }}</div>
                            </div>
                            <x-status-badge :status="$row->status" />
                        </li>
                    @empty
                        <li class="px-4 py-6 text-center text-gray-500">Sin registros todavía.</li>
                    @endforelse
                </ul>
            </div>
        </div>
    </div>
</x-app-layout>
