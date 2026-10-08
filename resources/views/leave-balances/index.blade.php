<x-app-layout>
    <x-slot name="header">Saldos de permisos {{ $year }}</x-slot>

    <div class="mb-4 flex flex-wrap items-end gap-3">
        <form method="GET" class="flex flex-wrap items-end gap-3">
            <x-field name="year" label="Año" type="number" :value="$year" class="w-28" />
            <x-field name="department_id" label="Departamento" type="select" :options="$departments->pluck('name', 'id')" :value="request('department_id')" empty="Todos" />
            <button class="btn btn-secondary">Ver</button>
        </form>
        <form method="POST" action="{{ route('leave-balances.allocate') }}" class="ml-auto" onsubmit="return confirm('¿Asignar las cuotas de {{ $year }} a todos los empleados activos?')">
            @csrf
            <input type="hidden" name="year" value="{{ $year }}">
            <button class="btn btn-primary"><x-icon name="refresh" class="h-4 w-4" /> Asignar cuotas {{ $year }}</button>
        </form>
    </div>

    <div class="card">
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>Empleado</th>
                        @foreach ($types as $type)
                            <th class="text-center"><span class="mr-1 inline-block h-2 w-2 rounded-full" style="background: {{ $type->color }}"></span>{{ $type->name }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @forelse ($employees as $employee)
                        <tr>
                            <td class="whitespace-nowrap font-medium">{{ $employee->name }}</td>
                            @foreach ($types as $type)
                                @php($b = $balances->get($employee->id)?->get($type->id))
                                <td class="text-center" x-data="{ edit: false }">
                                    @if ($b)
                                        <button type="button" x-show="!edit" @click="edit = true" class="rounded px-2 py-1 hover:bg-gray-100" title="Asignado {{ $b->allocated }} + arrastre {{ $b->carried_forward }} − usado {{ $b->used }}">
                                            <span class="text-base font-semibold {{ $b->available() <= 0 ? 'text-red-600' : 'text-gray-900' }}">{{ $b->available() }}</span>
                                            <span class="block text-[11px] text-gray-500">{{ $b->used }} usados de {{ $b->allocated + $b->carried_forward }}</span>
                                        </button>
                                        <form x-show="edit" x-cloak method="POST" action="{{ route('leave-balances.update', $b) }}" class="flex flex-col items-center gap-1">
                                            @csrf @method('PUT')
                                            <label class="text-[11px] text-gray-500">Asignado <input name="allocated" type="number" step="0.5" value="{{ $b->allocated }}" class="input w-20 py-0.5 text-xs"></label>
                                            <label class="text-[11px] text-gray-500">Arrastre <input name="carried_forward" type="number" step="0.5" value="{{ $b->carried_forward }}" class="input w-20 py-0.5 text-xs"></label>
                                            <label class="text-[11px] text-gray-500">Usado <input name="used" type="number" step="0.5" value="{{ $b->used }}" class="input w-20 py-0.5 text-xs"></label>
                                            <div class="flex gap-1"><button class="btn btn-primary btn-sm">OK</button><button type="button" class="btn btn-secondary btn-sm" @click="edit = false">×</button></div>
                                        </form>
                                    @else
                                        <form method="POST" action="{{ route('leave-balances.store') }}">
                                            @csrf
                                            <input type="hidden" name="user_id" value="{{ $employee->id }}">
                                            <input type="hidden" name="leave_type_id" value="{{ $type->id }}">
                                            <input type="hidden" name="year" value="{{ $year }}">
                                            <button class="text-xs text-indigo-600 hover:underline">+ asignar</button>
                                        </form>
                                    @endif
                                </td>
                            @endforeach
                        </tr>
                    @empty
                        <x-empty :colspan="$types->count() + 1" />
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <p class="mt-3 text-xs text-gray-500">Disponible = asignado + arrastre − usado. La asignación anual prorratea a quienes ingresaron durante el año y traslada el saldo permitido del año anterior. Haz clic en un saldo para ajustarlo.</p>
</x-app-layout>
