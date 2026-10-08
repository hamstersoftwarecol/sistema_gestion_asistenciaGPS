<x-app-layout>
    <x-slot name="header">Días festivos {{ $year }}</x-slot>

    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <div class="flex gap-2">
            <a href="{{ route('holidays.index', ['year' => $year - 1]) }}" class="btn btn-secondary">← {{ $year - 1 }}</a>
            <a href="{{ route('holidays.index', ['year' => $year + 1]) }}" class="btn btn-secondary">{{ $year + 1 }} →</a>
        </div>
        <p class="text-sm text-gray-500">Los fines de semana se configuran en <a href="{{ route('settings.edit') }}" class="btn-link">Reglas de RR. HH.</a></p>
    </div>

    <div class="grid gap-6 lg:grid-cols-3">
        <form method="POST" action="{{ route('holidays.store') }}" class="card h-fit">
            @csrf
            <div class="card-header"><h2 class="card-title">Agregar festivo</h2></div>
            <div class="card-body space-y-3">
                <x-field name="date" label="Fecha" type="date" required />
                <x-field name="name" label="Nombre" required />
                <x-field name="paid" label="Remunerado" type="checkbox" :value="true" />
                <div class="flex justify-end"><button class="btn btn-primary">Agregar</button></div>
            </div>
        </form>

        <div class="card lg:col-span-2">
            <div class="table-wrap">
                <table class="table">
                    <thead><tr><th>Fecha</th><th>Día</th><th>Nombre</th><th>Remunerado</th><th></th></tr></thead>
                    <tbody>
                        @forelse ($holidays as $holiday)
                            <tr>
                                <td class="whitespace-nowrap font-medium">{{ $holiday->date->format('d/m/Y') }}</td>
                                <td class="capitalize">{{ $holiday->date->locale('es')->isoFormat('dddd') }}</td>
                                <td>{{ $holiday->name }}</td>
                                <td>{{ $holiday->paid ? 'Sí' : 'No' }}</td>
                                <td class="text-right">
                                    <form method="POST" action="{{ route('holidays.destroy', $holiday) }}" onsubmit="return confirm('¿Eliminar el festivo?')">
                                        @csrf @method('DELETE')
                                        <button class="text-sm text-red-600 hover:text-red-800">Eliminar</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <x-empty colspan="5" message="Sin festivos registrados para este año." />
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-app-layout>
