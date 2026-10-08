<x-app-layout>
    <x-slot name="header">Sitios de proyecto y geocercas</x-slot>

    <div class="mb-4 flex justify-end">
        <a href="{{ route('sites.create') }}" class="btn btn-primary"><x-icon name="plus" class="h-4 w-4" /> Nuevo sitio</a>
    </div>

    <div class="grid gap-6 lg:grid-cols-5">
        <div class="card overflow-hidden lg:col-span-2" x-data="pointsMap({ sites: @js($sites->where('active', true)->values()->map->only(['name', 'latitude', 'longitude', 'radius_m'])), points: [] })">
            <div x-ref="map" class="h-96 w-full lg:h-full lg:min-h-[420px]"></div>
        </div>
        <div class="card lg:col-span-3">
            <div class="table-wrap">
                <table class="table">
                    <thead><tr><th>Sitio</th><th>Cliente</th><th>Coordenadas</th><th>Radio</th><th>Empleados</th><th>Estado</th><th></th></tr></thead>
                    <tbody>
                        @forelse ($sites as $site)
                            <tr>
                                <td><div class="font-medium">{{ $site->name }}</div><div class="text-xs text-gray-500">{{ $site->code }} · {{ $site->address }}</div></td>
                                <td>{{ $site->client ?? '—' }}</td>
                                <td class="whitespace-nowrap font-mono text-xs">{{ number_format($site->latitude, 5) }}, {{ number_format($site->longitude, 5) }}</td>
                                <td>{{ $site->radius_m }} m</td>
                                <td>{{ $site->employees_count }}</td>
                                <td><x-status-badge :status="$site->active ? 'active' : 'inactive'" /></td>
                                <td class="whitespace-nowrap text-right">
                                    <a href="{{ route('sites.edit', $site) }}" class="btn-link">Editar</a>
                                    <form method="POST" action="{{ route('sites.destroy', $site) }}" class="inline" onsubmit="return confirm('¿Eliminar el sitio?')">
                                        @csrf @method('DELETE')
                                        <button class="ml-3 text-sm text-red-600 hover:text-red-800">Eliminar</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <x-empty colspan="7" message="Aún no hay sitios. Crea el primero para habilitar la geocerca." />
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-app-layout>
