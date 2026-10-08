<x-app-layout>
    <x-slot name="header">Componentes de pago</x-slot>

    <div class="mb-4 flex justify-end">
        <a href="{{ route('pay-components.create') }}" class="btn btn-primary"><x-icon name="plus" class="h-4 w-4" /> Nuevo componente</a>
    </div>

    <div class="card">
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th>Componente</th><th>Código</th><th>Tipo</th><th>Cálculo</th><th>Valor</th><th>Alcance</th><th>Estado</th><th></th></tr></thead>
                <tbody>
                    @forelse ($components as $c)
                        <tr>
                            <td class="font-medium">{{ $c->name }}</td>
                            <td><code class="text-xs">{{ $c->code }}</code></td>
                            <td><x-badge :color="$c->type === 'earning' ? 'green' : 'red'">{{ \App\Models\PayComponent::TYPES[$c->type] }}</x-badge></td>
                            <td>{{ \App\Models\PayComponent::CALC_TYPES[$c->calc_type] }}</td>
                            <td>{{ $c->calc_type === 'percent' ? $c->amount.'%' : money($c->amount) }}</td>
                            <td>{{ $c->applies_to_all ? 'Todos los empleados' : $c->employees_count.' empleado(s)' }}</td>
                            <td><x-status-badge :status="$c->active ? 'active' : 'inactive'" /></td>
                            <td class="whitespace-nowrap text-right">
                                <a href="{{ route('pay-components.edit', $c) }}" class="btn-link">Editar</a>
                                @if ($c->active)
                                    <form method="POST" action="{{ route('pay-components.destroy', $c) }}" class="inline">
                                        @csrf @method('DELETE')
                                        <button class="ml-3 text-sm text-red-600 hover:text-red-800">Desactivar</button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <x-empty colspan="8" />
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <p class="mt-3 text-xs text-gray-500">Los valores fijos se prorratean para empleados mensuales que ingresan o se retiran a mitad del período. Los porcentuales se calculan sobre el salario básico del período.</p>
</x-app-layout>
