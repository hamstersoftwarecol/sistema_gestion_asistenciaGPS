<x-app-layout>
    <x-slot name="header">Mis correcciones de asistencia</x-slot>

    <div class="mb-4 flex justify-end">
        <a href="{{ route('corrections.create') }}" class="btn btn-primary"><x-icon name="plus" class="h-4 w-4" /> Nueva solicitud</a>
    </div>

    <div class="card">
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th>Fecha</th><th>Tipo</th><th>Entrada solicitada</th><th>Salida solicitada</th><th>Motivo</th><th>Estado</th><th>Revisión</th></tr></thead>
                <tbody>
                    @forelse ($corrections as $c)
                        <tr>
                            <td class="whitespace-nowrap font-medium">{{ $c->date->format('d/m/Y') }}</td>
                            <td>{{ \App\Models\AttendanceCorrection::TYPES[$c->type] ?? $c->type }}</td>
                            <td>{{ $c->requested_check_in?->format('d/m H:i') ?? '—' }}</td>
                            <td>{{ $c->requested_check_out?->format('d/m H:i') ?? '—' }}</td>
                            <td class="max-w-xs text-gray-600">{{ $c->reason }}</td>
                            <td><x-status-badge :status="$c->status" /></td>
                            <td class="text-xs text-gray-500">
                                @if ($c->reviewer){{ $c->reviewer->name }} · {{ $c->reviewed_at?->format('d/m H:i') }}@endif
                                @if ($c->review_note)<div class="italic">«{{ $c->review_note }}»</div>@endif
                            </td>
                        </tr>
                    @empty
                        <x-empty colspan="7" message="No has enviado solicitudes de corrección." />
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <div class="mt-4">{{ $corrections->links() }}</div>
</x-app-layout>
