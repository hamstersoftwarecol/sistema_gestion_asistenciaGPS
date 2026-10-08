<x-app-layout>
    <x-slot name="header">{{ $meta[0] }}</x-slot>

    <div class="mb-2"><a href="{{ route('reports.index') }}" class="btn-link">← Informes</a></div>
    <p class="mb-4 text-sm text-gray-500">{{ $meta[1] }}</p>

    <form method="GET" class="card mb-4">
        <div class="card-body flex flex-wrap items-end gap-3">
            @if ($report === 'payroll')
                <x-field name="period_id" label="Período" type="select" :options="$periods" :value="$filters['period_id'] ?? null" />
            @else
                <x-field name="from" label="Desde" type="date" :value="$filters['from']" />
                <x-field name="to" label="Hasta" type="date" :value="$filters['to']" />
                <x-field name="site_id" label="Sitio" type="select" :options="$sites" :value="$filters['site_id'] ?? null" empty="Todos" />
            @endif
            <x-field name="department_id" label="Departamento" type="select" :options="$departments" :value="$filters['department_id'] ?? null" empty="Todos" />
            <x-field name="user_id" label="Empleado" type="select" :options="$employees" :value="$filters['user_id'] ?? null" empty="Todos" />
            <button class="btn btn-secondary">Aplicar</button>
            <button name="export" value="csv" class="btn btn-primary ml-auto"><x-icon name="download" class="h-4 w-4" /> Exportar CSV</button>
        </div>
    </form>

    <div class="card">
        <div class="card-header"><h2 class="card-title">{{ $result['title'] }}</h2><span class="text-sm text-gray-500">{{ count($result['rows']) }} fila(s)</span></div>
        <div class="table-wrap">
            <table class="table {{ ! empty($result['codes']) ? 'text-xs' : '' }}">
                <thead><tr>@foreach ($result['columns'] as $column)<th>{{ $column }}</th>@endforeach</tr></thead>
                <tbody>
                    @php
                        $codeColors = ['P' => 'bg-emerald-100 text-emerald-800', 'T' => 'bg-amber-100 text-amber-800', 'MD' => 'bg-orange-100 text-orange-800', 'A' => 'bg-red-100 text-red-800', 'L' => 'bg-sky-100 text-sky-800', 'O' => 'bg-purple-100 text-purple-800', 'F' => 'bg-gray-100 text-gray-600', 'D' => 'bg-gray-50 text-gray-400', '-' => 'text-red-400'];
                    @endphp
                    @forelse ($result['rows'] as $row)
                        <tr>
                            @foreach ($row as $i => $cell)
                                @if (! empty($result['codes']) && $i >= 2 && $i < count($row) - 2)
                                    <td class="px-1 text-center"><span class="inline-block min-w-[1.75rem] rounded px-1 py-0.5 font-semibold {{ $codeColors[$cell] ?? '' }}">{{ $cell }}</span></td>
                                @else
                                    <td class="whitespace-nowrap">{{ is_float($cell) ? number_format($cell, 2, ',', '.') : $cell }}</td>
                                @endif
                            @endforeach
                        </tr>
                    @empty
                        <x-empty :colspan="count($result['columns'])" message="Sin datos para los filtros seleccionados." />
                    @endforelse
                </tbody>
            </table>
        </div>
        @if (! empty($result['codes']))
            <p class="px-5 py-3 text-xs text-gray-500">P presente · T tarde · MD medio día · A ausente · L permiso · O marcación omitida · F festivo · D descanso · − sin registro</p>
        @endif
    </div>
</x-app-layout>
