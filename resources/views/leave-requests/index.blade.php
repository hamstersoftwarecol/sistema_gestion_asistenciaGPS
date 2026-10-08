<x-app-layout>
    <x-slot name="header">Mis permisos</x-slot>

    <div class="mb-4 flex flex-wrap items-center gap-3">
        @foreach ($balances as $b)
            <div class="rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm shadow-sm">
                <span class="mr-1 inline-block h-2 w-2 rounded-full" style="background: {{ $b->leaveType->color }}"></span>
                {{ $b->leaveType->name }}: <strong>{{ $b->available() }}</strong> <span class="text-gray-500">disponibles</span>
            </div>
        @endforeach
        <a href="{{ route('leave-requests.create') }}" class="btn btn-primary ml-auto"><x-icon name="plus" class="h-4 w-4" /> Solicitar permiso</a>
    </div>

    <div class="card">
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th>Tipo</th><th>Desde</th><th>Hasta</th><th>Días hábiles</th><th>Motivo</th><th>Estado</th><th>Revisión</th><th></th></tr></thead>
                <tbody>
                    @forelse ($requests as $r)
                        <tr>
                            <td class="font-medium">{{ $r->leaveType->name }}</td>
                            <td>{{ $r->start_date->format('d/m/Y') }}</td>
                            <td>{{ $r->end_date->format('d/m/Y') }}</td>
                            <td>{{ $r->days }}</td>
                            <td class="max-w-xs text-gray-600">{{ $r->reason }} @if ($r->attachment)<a href="{{ route('leave-requests.attachment', $r) }}" class="btn-link text-xs">(soporte)</a>@endif</td>
                            <td><x-status-badge :status="$r->status" /></td>
                            <td class="text-xs text-gray-500">{{ $r->reviewer?->name }} @if ($r->review_note)<div class="italic">«{{ $r->review_note }}»</div>@endif</td>
                            <td class="text-right">
                                @if ($r->status === 'pending' || ($r->status === 'approved' && $r->start_date->isFuture()))
                                    <form method="POST" action="{{ route('leave-requests.cancel', $r) }}" onsubmit="return confirm('¿Cancelar este permiso?')">
                                        @csrf
                                        <button class="text-sm text-red-600 hover:text-red-800">Cancelar</button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <x-empty colspan="8" message="No has solicitado permisos." />
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <div class="mt-4">{{ $requests->links() }}</div>
</x-app-layout>
