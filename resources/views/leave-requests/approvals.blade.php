<x-app-layout>
    <x-slot name="header">Aprobación de permisos</x-slot>

    <div class="card mb-6">
        <div class="card-header"><h2 class="card-title">Pendientes ({{ $pending->count() }})</h2></div>
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th>Empleado</th><th>Tipo</th><th>Fechas</th><th>Días</th><th>Saldo</th><th>Motivo</th><th class="text-right">Decisión</th></tr></thead>
                <tbody>
                    @forelse ($pending as $r)
                        @php($balance = ($balances[$r->user_id] ?? collect())->firstWhere('leave_type_id', $r->leave_type_id))
                        <tr x-data="{ reject: false }">
                            <td><div class="font-medium">{{ $r->user->name }}</div><div class="text-xs text-gray-500">{{ $r->user->department?->name }}</div></td>
                            <td>{{ $r->leaveType->name }}</td>
                            <td class="whitespace-nowrap">{{ $r->start_date->format('d/m') }} – {{ $r->end_date->format('d/m/Y') }}</td>
                            <td>{{ $r->days }}</td>
                            <td>{{ $r->leaveType->hasQuota() ? ($balance ? $balance->available() : '—') : 'N/A' }}</td>
                            <td class="max-w-xs text-sm text-gray-600">{{ $r->reason }} @if ($r->attachment)<a href="{{ route('leave-requests.attachment', $r) }}" class="btn-link text-xs">Ver soporte</a>@endif</td>
                            <td class="text-right">
                                <div class="flex justify-end gap-1" x-show="!reject">
                                    <form method="POST" action="{{ route('leave-requests.approve', $r) }}">@csrf<button class="btn btn-success btn-sm">Aprobar</button></form>
                                    <button type="button" class="btn btn-secondary btn-sm" @click="reject = true">Rechazar</button>
                                </div>
                                <form x-show="reject" x-cloak method="POST" action="{{ route('leave-requests.reject', $r) }}" class="flex justify-end gap-1">
                                    @csrf
                                    <input name="review_note" class="input w-48 py-1 text-xs" placeholder="Motivo del rechazo" required>
                                    <button class="btn btn-danger btn-sm">Rechazar</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <x-empty colspan="7" message="No hay solicitudes pendientes." />
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="card">
        <div class="card-header"><h2 class="card-title">Historial reciente</h2></div>
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th>Empleado</th><th>Tipo</th><th>Fechas</th><th>Días</th><th>Estado</th><th>Revisó</th><th></th></tr></thead>
                <tbody>
                    @forelse ($recent as $r)
                        <tr>
                            <td>{{ $r->user->name }}</td>
                            <td>{{ $r->leaveType->name }}</td>
                            <td class="whitespace-nowrap">{{ $r->start_date->format('d/m') }} – {{ $r->end_date->format('d/m/Y') }}</td>
                            <td>{{ $r->days }}</td>
                            <td><x-status-badge :status="$r->status" /></td>
                            <td class="text-sm">{{ $r->reviewer?->name }}</td>
                            <td class="text-right">
                                @if ($r->status === 'approved')
                                    <form method="POST" action="{{ route('leave-requests.cancel', $r) }}" onsubmit="return confirm('¿Cancelar el permiso aprobado y devolver el saldo?')">
                                        @csrf
                                        <button class="text-xs text-red-600 hover:text-red-800">Cancelar</button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <x-empty colspan="7" />
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-app-layout>
