<x-app-layout>
    <x-slot name="header">Empleados</x-slot>

    <form method="GET" class="mb-4 flex flex-wrap items-end gap-3">
        <div>
            <label class="label" for="q">Buscar</label>
            <input id="q" name="q" value="{{ request('q') }}" class="input" placeholder="Nombre, correo o código">
        </div>
        <div>
            <label class="label" for="department_id">Departamento</label>
            <select id="department_id" name="department_id" class="input">
                <option value="">Todos</option>
                @foreach ($departments as $d)
                    <option value="{{ $d->id }}" @selected(request('department_id') == $d->id)>{{ $d->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="label" for="status">Estado</label>
            <select id="status" name="status" class="input">
                @foreach (['active' => 'Activos', 'inactive' => 'Inactivos', 'terminated' => 'Retirados', 'all' => 'Todos'] as $key => $label)
                    <option value="{{ $key }}" @selected(request('status', 'active') === $key)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <button class="btn btn-secondary">Filtrar</button>
        <a href="{{ route('employees.create') }}" class="btn btn-primary ml-auto"><x-icon name="plus" class="h-4 w-4" /> Nuevo empleado</a>
    </form>

    <div class="card">
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th>Empleado</th><th>Rol</th><th>Departamento</th><th>Turno / sitio</th><th>Pago</th><th>Estado</th><th></th></tr></thead>
                <tbody>
                    @forelse ($employees as $e)
                        <tr>
                            <td>
                                <a href="{{ route('employees.show', $e) }}" class="flex items-center gap-3">
                                    <x-avatar :user="$e" />
                                    <span>
                                        <span class="block font-medium text-gray-900">{{ $e->name }}</span>
                                        <span class="block text-xs text-gray-500">{{ $e->employee_code }} · {{ $e->position }}</span>
                                    </span>
                                </a>
                            </td>
                            <td>{{ $e->role?->name }}</td>
                            <td>{{ $e->department?->name ?? '—' }}</td>
                            <td class="text-xs">{{ $e->shift?->name ?? '—' }}<div class="text-gray-500">{{ $e->site?->name }}</div></td>
                            <td class="whitespace-nowrap text-xs">
                                @if ($e->contractor)
                                    <x-badge color="orange">Contratista: {{ $e->contractor->name }}</x-badge>
                                @elseif ($e->pay_type === 'hourly')
                                    {{ money($e->hourly_rate) }}/h
                                @else
                                    {{ money($e->monthly_salary) }}/mes
                                @endif
                            </td>
                            <td><x-status-badge :status="$e->status" /></td>
                            <td class="whitespace-nowrap text-right">
                                <a href="{{ route('employees.show', $e) }}" class="btn-link">Ver</a>
                                <a href="{{ route('employees.edit', $e) }}" class="btn-link ml-3">Editar</a>
                            </td>
                        </tr>
                    @empty
                        <x-empty colspan="7" />
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <div class="mt-4">{{ $employees->links() }}</div>
</x-app-layout>
