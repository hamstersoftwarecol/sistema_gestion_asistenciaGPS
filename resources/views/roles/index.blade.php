<x-app-layout>
    <x-slot name="header">Roles y control de permisos</x-slot>

    <form method="POST" action="{{ route('roles.update') }}" class="card mb-6">
        @csrf @method('PUT')
        <div class="card-header">
            <h2 class="card-title">Matriz de permisos</h2>
            <button class="btn btn-primary">Guardar permisos</button>
        </div>
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>Permiso</th>
                        @foreach ($roles as $role)
                            <th class="text-center">
                                {{ $role->name }}
                                <div class="text-[10px] font-normal normal-case text-gray-400">{{ $role->users_count }} usuario(s)</div>
                            </th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach ($groups as $group => $permissions)
                        <tr class="bg-gray-50"><td colspan="{{ $roles->count() + 1 }}" class="text-xs font-semibold uppercase tracking-wide text-indigo-700">{{ $group }}</td></tr>
                        @foreach ($permissions as $key => $label)
                            <tr>
                                <td>
                                    <div>{{ $label }}</div>
                                    <code class="text-[11px] text-gray-400">{{ $key }}</code>
                                </td>
                                @foreach ($roles as $role)
                                    <td class="text-center">
                                        @if ($role->slug === 'admin')
                                            <x-icon name="check" class="mx-auto h-4 w-4 text-emerald-600" />
                                        @else
                                            <input type="checkbox" class="checkbox" name="permissions[{{ $role->id }}][]" value="{{ $key }}" @checked($role->hasPermission($key))>
                                        @endif
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    @endforeach
                </tbody>
            </table>
        </div>
        <p class="px-5 py-3 text-xs text-gray-500">El rol Administrador tiene todos los permisos. Sin «Ver a todos los empleados», un rol solo ve a su equipo: subordinados directos y miembros de los departamentos que dirige.</p>
    </form>

    <div class="grid gap-6 lg:grid-cols-2">
        <form method="POST" action="{{ route('roles.store') }}" class="card">
            @csrf
            <div class="card-header"><h2 class="card-title">Nuevo rol</h2></div>
            <div class="card-body space-y-3">
                <x-field name="name" label="Nombre" required placeholder="Ej.: Supervisor de obra" />
                <x-field name="description" label="Descripción" />
                <x-field name="copy_from" label="Copiar permisos de" type="select" :options="$roles->pluck('name', 'id')" empty="Solo «Registrar mi asistencia»" />
                <div class="flex justify-end"><button class="btn btn-primary">Crear rol</button></div>
            </div>
        </form>

        <div class="card">
            <div class="card-header"><h2 class="card-title">Roles</h2></div>
            <ul class="divide-y divide-gray-100">
                @foreach ($roles as $role)
                    <li class="flex items-center justify-between px-5 py-3 text-sm">
                        <div>
                            <div class="font-medium">{{ $role->name }} @if ($role->is_system)<x-badge color="indigo">sistema</x-badge>@endif</div>
                            <div class="text-xs text-gray-500">{{ $role->description }}</div>
                        </div>
                        @unless ($role->is_system)
                            <form method="POST" action="{{ route('roles.destroy', $role) }}" onsubmit="return confirm('¿Eliminar el rol?')">
                                @csrf @method('DELETE')
                                <button class="btn btn-secondary btn-sm text-red-600" @disabled($role->users_count)>Eliminar</button>
                            </form>
                        @endunless
                    </li>
                @endforeach
            </ul>
        </div>
    </div>
</x-app-layout>
