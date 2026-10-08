<x-app-layout>
    <x-slot name="header">{{ $department->exists ? 'Editar departamento' : 'Nuevo departamento' }}</x-slot>

    <form method="POST" action="{{ $department->exists ? route('departments.update', $department) : route('departments.store') }}" class="card mx-auto max-w-2xl">
        @csrf
        @if ($department->exists) @method('PUT') @endif
        <div class="card-body grid gap-4 sm:grid-cols-2">
            <x-field name="name" label="Nombre" :value="$department->name" required />
            <x-field name="code" label="Código" :value="$department->code" />
            <x-field name="manager_id" label="Gerente del departamento" type="select" :options="$managers" :value="$department->manager_id" empty="Sin gerente" class="sm:col-span-2"
                     help="El gerente ve y aprueba la asistencia, correcciones y permisos de los miembros del departamento." />
            <x-field name="description" label="Descripción" type="textarea" :value="$department->description" class="sm:col-span-2" />
        </div>
        <div class="flex justify-end gap-2 border-t border-gray-100 px-5 py-3">
            <a href="{{ route('departments.index') }}" class="btn btn-secondary">Cancelar</a>
            <button class="btn btn-primary">Guardar</button>
        </div>
    </form>
</x-app-layout>
