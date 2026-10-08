<x-app-layout>
    <x-slot name="header">Tipos de permiso y cuotas</x-slot>

    <div class="mb-4 flex justify-end">
        <a href="{{ route('leave-types.create') }}" class="btn btn-primary"><x-icon name="plus" class="h-4 w-4" /> Nuevo tipo</a>
    </div>

    <div class="card">
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th>Tipo</th><th>Código</th><th>Remunerado</th><th>Cuota anual</th><th>Arrastre</th><th>Compensable</th><th>Soporte</th><th>Estado</th><th></th></tr></thead>
                <tbody>
                    @forelse ($types as $type)
                        <tr>
                            <td><span class="mr-2 inline-block h-3 w-3 rounded-full align-middle" style="background: {{ $type->color }}"></span><span class="font-medium">{{ $type->name }}</span></td>
                            <td>{{ $type->code }}</td>
                            <td>{{ $type->paid ? 'Sí' : 'No' }}</td>
                            <td>{{ $type->hasQuota() ? $type->annual_quota.' días' : 'Sin límite' }}</td>
                            <td>{{ $type->carry_forward ? 'Hasta '.$type->max_carry_forward.' días' : 'No' }}</td>
                            <td>{{ $type->encashable ? 'Sí' : 'No' }}</td>
                            <td>{{ $type->requires_attachment ? 'Obligatorio' : 'Opcional' }}</td>
                            <td><x-status-badge :status="$type->active ? 'active' : 'inactive'" /></td>
                            <td class="whitespace-nowrap text-right">
                                <a href="{{ route('leave-types.edit', $type) }}" class="btn-link">Editar</a>
                                @if ($type->active)
                                    <form method="POST" action="{{ route('leave-types.destroy', $type) }}" class="inline">
                                        @csrf @method('DELETE')
                                        <button class="ml-3 text-sm text-red-600 hover:text-red-800">Desactivar</button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <x-empty colspan="9" />
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <p class="mt-3 text-xs text-gray-500">Los días se cuentan como hábiles: se excluyen los fines de semana y festivos configurados. «Compensable» indica que el saldo disponible se paga en la liquidación final.</p>
</x-app-layout>
