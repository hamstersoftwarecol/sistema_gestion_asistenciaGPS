<x-app-layout>
    <x-slot name="header">Liquidaciones finales</x-slot>

    <div class="mb-4 flex justify-end">
        <a href="{{ route('settlements.create') }}" class="btn btn-primary"><x-icon name="plus" class="h-4 w-4" /> Nueva liquidación</a>
    </div>

    <div class="card">
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th>Empleado</th><th>Retiro</th><th>Motivo</th><th>Antigüedad</th><th>Gratificación</th><th>Vacaciones</th><th>Neto</th><th>Estado</th><th></th></tr></thead>
                <tbody>
                    @forelse ($settlements as $s)
                        <tr>
                            <td class="font-medium">{{ $s->user->name }}</td>
                            <td>{{ $s->termination_date->format('d/m/Y') }}</td>
                            <td>{{ \App\Models\FinalSettlement::REASONS[$s->reason] ?? $s->reason }}</td>
                            <td>{{ $s->years_of_service }} años</td>
                            <td>{{ money($s->gratuity_amount) }}</td>
                            <td>{{ money($s->leave_encashment_amount) }}</td>
                            <td class="font-semibold">{{ money($s->net_amount) }}</td>
                            <td><x-status-badge :status="$s->status" /></td>
                            <td class="text-right"><a href="{{ route('settlements.show', $s) }}" class="btn-link">Abrir →</a></td>
                        </tr>
                    @empty
                        <x-empty colspan="9" />
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <div class="mt-4">{{ $settlements->links() }}</div>
</x-app-layout>
