<x-app-layout>
    <x-slot name="header">Aprobación de correcciones</x-slot>

    <div class="card mb-6">
        <div class="card-header"><h2 class="card-title">Pendientes ({{ $pending->count() }})</h2></div>
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th>Empleado</th><th>Fecha</th><th>Tipo</th><th>Registro actual</th><th>Solicitado</th><th>Motivo</th><th class="text-right">Decisión</th></tr></thead>
                <tbody>
                    @forelse ($pending as $c)
                        <tr x-data="{ reject: false }">
                            <td><div class="font-medium">{{ $c->user->name }}</div><div class="text-xs text-gray-500">{{ $c->user->department?->name }}</div></td>
                            <td class="whitespace-nowrap">{{ $c->date->locale('es')->isoFormat('ddd D/MM/YYYY') }}</td>
                            <td class="text-sm">{{ \App\Models\AttendanceCorrection::TYPES[$c->type] ?? $c->type }}</td>
                            <td class="text-xs">
                                @if ($c->attendance)
                                    <x-status-badge :status="$c->attendance->status" /><br>
                                    {{ $c->attendance->check_in_at?->format('H:i') ?? '—' }} – {{ $c->attendance->check_out_at?->format('H:i') ?? '—' }}
                                @else
                                    <span class="text-gray-400">Sin registro</span>
                                @endif
                            </td>
                            <td class="whitespace-nowrap text-sm font-medium">
                                {{ $c->requested_check_in?->format('H:i') ?? '·' }} – {{ $c->requested_check_out?->format('H:i') ?? '·' }}
                            </td>
                            <td class="max-w-xs text-sm text-gray-600">{{ $c->reason }}</td>
                            <td class="text-right">
                                <div class="flex justify-end gap-1" x-show="!reject">
                                    <form method="POST" action="{{ route('corrections.approve', $c) }}">@csrf<button class="btn btn-success btn-sm"><x-icon name="check" class="h-3.5 w-3.5" /> Aprobar</button></form>
                                    <button type="button" class="btn btn-secondary btn-sm" @click="reject = true">Rechazar</button>
                                </div>
                                <form x-show="reject" x-cloak method="POST" action="{{ route('corrections.reject', $c) }}" class="flex justify-end gap-1">
                                    @csrf
                                    <input name="review_note" class="input w-48 py-1 text-xs" placeholder="Motivo del rechazo" required>
                                    <button class="btn btn-danger btn-sm">Rechazar</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <x-empty colspan="7" message="No hay correcciones pendientes de tu equipo." />
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="card">
        <div class="card-header"><h2 class="card-title">Revisadas recientemente</h2></div>
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th>Empleado</th><th>Fecha</th><th>Tipo</th><th>Estado</th><th>Revisó</th><th>Nota</th></tr></thead>
                <tbody>
                    @forelse ($reviewed as $c)
                        <tr>
                            <td>{{ $c->user->name }}</td>
                            <td>{{ $c->date->format('d/m/Y') }}</td>
                            <td class="text-sm">{{ \App\Models\AttendanceCorrection::TYPES[$c->type] ?? $c->type }}</td>
                            <td><x-status-badge :status="$c->status" /></td>
                            <td class="text-sm">{{ $c->reviewer?->name }}</td>
                            <td class="text-sm text-gray-500">{{ $c->review_note }}</td>
                        </tr>
                    @empty
                        <x-empty colspan="6" />
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-app-layout>
