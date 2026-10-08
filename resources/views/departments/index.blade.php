<x-app-layout>
    <x-slot name="header">Departamentos</x-slot>

    <div class="mb-4 flex justify-end">
        <a href="{{ route('departments.create') }}" class="btn btn-primary"><x-icon name="plus" class="h-4 w-4" /> Nuevo departamento</a>
    </div>

    <div class="card">
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th>Nombre</th><th>Código</th><th>Gerente</th><th>Empleados</th><th>Descripción</th><th></th></tr></thead>
                <tbody>
                    @forelse ($departments as $department)
                        <tr>
                            <td class="font-medium">{{ $department->name }}</td>
                            <td>{{ $department->code }}</td>
                            <td>{{ $department->manager?->name ?? '—' }}</td>
                            <td>{{ $department->employees_count }}</td>
                            <td class="text-gray-500">{{ $department->description }}</td>
                            <td class="whitespace-nowrap text-right">
                                <a href="{{ route('departments.edit', $department) }}" class="btn-link">Editar</a>
                                <form method="POST" action="{{ route('departments.destroy', $department) }}" class="inline" onsubmit="return confirm('¿Eliminar el departamento? Los empleados quedarán sin departamento.')">
                                    @csrf @method('DELETE')
                                    <button class="ml-3 text-sm text-red-600 hover:text-red-800">Eliminar</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <x-empty colspan="6" />
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-app-layout>
