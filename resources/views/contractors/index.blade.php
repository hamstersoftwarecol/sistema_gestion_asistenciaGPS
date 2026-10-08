<x-app-layout>
    <x-slot name="header">Contratistas</x-slot>

    <div class="mb-4 flex justify-end gap-2">
        <a href="{{ route('invoices.index') }}" class="btn btn-secondary"><x-icon name="receipt" class="h-4 w-4" /> Facturas</a>
        <a href="{{ route('contractors.create') }}" class="btn btn-primary"><x-icon name="plus" class="h-4 w-4" /> Nuevo contratista</a>
    </div>

    <div class="card">
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th>Contratista</th><th>NIT</th><th>Contacto</th><th>Tarifa</th><th>Recargo extra</th><th>IVA</th><th>Trabajadores</th><th>Estado</th><th></th></tr></thead>
                <tbody>
                    @forelse ($contractors as $c)
                        <tr>
                            <td class="font-medium">{{ $c->name }}</td>
                            <td>{{ $c->tax_id }}</td>
                            <td class="text-xs">{{ $c->contact_name }}<div class="text-gray-500">{{ $c->email }} {{ $c->phone }}</div></td>
                            <td class="whitespace-nowrap">{{ money($c->rate) }} {{ $c->rate_type === 'hourly' ? '/hora' : '/día' }}</td>
                            <td>×{{ $c->overtime_multiplier }}</td>
                            <td>{{ $c->tax_percent }}%</td>
                            <td>{{ $c->workers_count }}</td>
                            <td><x-status-badge :status="$c->active ? 'active' : 'inactive'" /></td>
                            <td class="text-right"><a href="{{ route('contractors.edit', $c) }}" class="btn-link">Editar</a></td>
                        </tr>
                    @empty
                        <x-empty colspan="9" />
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <p class="mt-3 text-xs text-gray-500">Para asignar trabajadores a un contratista, edita al empleado y selecciona el contratista. Su asistencia se factura al contratista y no entra en la nómina.</p>
</x-app-layout>
