<x-app-layout>
    <x-slot name="header">Documentos de empleados</x-slot>

    <div class="grid gap-6 lg:grid-cols-3">
        <form method="POST" action="{{ route('documents.store') }}" enctype="multipart/form-data" class="card h-fit">
            @csrf
            <div class="card-header"><h2 class="card-title">Cargar documento</h2></div>
            <div class="card-body space-y-3">
                <x-field name="user_id" label="Empleado" type="select" :options="$employees" :value="request('user_id')" empty="Seleccione…" required />
                <x-field name="title" label="Título" required placeholder="Ej.: Curso de alturas" />
                <x-field name="category" label="Categoría" type="select" :options="\App\Models\EmployeeDocument::CATEGORIES" required />
                <x-field name="expires_on" label="Vence el" type="date" help="Se enviará una alerta antes del vencimiento." />
                <div>
                    <label class="label" for="file">Archivo (máx. 10 MB)</label>
                    <input id="file" type="file" name="file" required class="block w-full text-sm text-gray-600 file:mr-3 file:rounded-md file:border-0 file:bg-indigo-50 file:px-3 file:py-2 file:text-indigo-700">
                </div>
                <div class="flex justify-end"><button class="btn btn-primary">Cargar</button></div>
            </div>
        </form>

        <div class="lg:col-span-2">
            <form method="GET" class="mb-3 flex flex-wrap items-end gap-3">
                <x-field name="user_id" label="Empleado" type="select" :options="$employees" :value="request('user_id')" empty="Todos" />
                <x-field name="category" label="Categoría" type="select" :options="\App\Models\EmployeeDocument::CATEGORIES" :value="request('category')" empty="Todas" />
                <label class="mb-2 inline-flex items-center gap-2 text-sm"><input type="checkbox" class="checkbox" name="expiring" value="1" @checked(request()->boolean('expiring'))> Por vencer</label>
                <button class="btn btn-secondary">Filtrar</button>
            </form>
            <div class="card">
                <div class="table-wrap">
                    <table class="table">
                        <thead><tr><th>Documento</th><th>Empleado</th><th>Categoría</th><th>Vence</th><th>Cargado</th><th></th></tr></thead>
                        <tbody>
                            @forelse ($documents as $doc)
                                <tr>
                                    <td><div class="font-medium">{{ $doc->title }}</div><div class="text-xs text-gray-500">{{ $doc->original_name }}</div></td>
                                    <td>{{ $doc->user->name }}</td>
                                    <td>{{ \App\Models\EmployeeDocument::CATEGORIES[$doc->category] ?? $doc->category }}</td>
                                    <td>
                                        @if ($doc->expires_on)
                                            <span class="{{ $doc->expires_on->isPast() ? 'font-semibold text-red-700' : ($doc->isExpiringSoon() ? 'font-semibold text-amber-700' : '') }}">{{ $doc->expires_on->format('d/m/Y') }}</span>
                                            @if ($doc->expires_on->isPast())<x-badge color="red">vencido</x-badge>@elseif ($doc->isExpiringSoon())<x-badge color="yellow">pronto</x-badge>@endif
                                        @else — @endif
                                    </td>
                                    <td class="text-xs text-gray-500">{{ $doc->created_at->format('d/m/Y') }} · {{ $doc->uploader?->name }}</td>
                                    <td class="whitespace-nowrap text-right">
                                        <a href="{{ route('documents.download', $doc) }}" class="btn-link">Descargar</a>
                                        <form method="POST" action="{{ route('documents.destroy', $doc) }}" class="inline" onsubmit="return confirm('¿Eliminar el documento?')">
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
            <div class="mt-4">{{ $documents->links() }}</div>
        </div>
    </div>
</x-app-layout>
