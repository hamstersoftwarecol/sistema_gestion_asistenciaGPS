<x-app-layout>
    <x-slot name="header">{{ $type->exists ? 'Editar tipo de permiso' : 'Nuevo tipo de permiso' }}</x-slot>

    <form method="POST" action="{{ $type->exists ? route('leave-types.update', $type) : route('leave-types.store') }}" class="card mx-auto max-w-2xl">
        @csrf
        @if ($type->exists) @method('PUT') @endif
        <div class="card-body grid gap-4 sm:grid-cols-3">
            <x-field name="name" label="Nombre" :value="$type->name" required class="sm:col-span-2" />
            <x-field name="code" label="Código" :value="$type->code" required maxlength="10" />
            <x-field name="annual_quota" label="Cuota anual (días)" type="number" step="0.5" min="0" :value="$type->annual_quota ?? 0" required help="0 = sin límite (no consume saldo)." />
            <x-field name="max_carry_forward" label="Arrastre máximo (días)" type="number" step="0.5" min="0" :value="$type->max_carry_forward ?? 0" />
            <x-field name="color" label="Color" type="color" :value="$type->color" required />
            <div class="space-y-2 sm:col-span-3">
                <x-field name="paid" label="Remunerado (se paga en nómina)" type="checkbox" :value="$type->paid" />
                <x-field name="carry_forward" label="Permite arrastrar saldo al año siguiente" type="checkbox" :value="$type->carry_forward" />
                <x-field name="encashable" label="Saldo compensable en la liquidación final" type="checkbox" :value="$type->encashable" />
                <x-field name="requires_attachment" label="Requiere soporte adjunto (p. ej. incapacidad médica)" type="checkbox" :value="$type->requires_attachment" />
                <x-field name="active" label="Activo" type="checkbox" :value="$type->active" />
            </div>
        </div>
        <div class="flex justify-end gap-2 border-t border-gray-100 px-5 py-3">
            <a href="{{ route('leave-types.index') }}" class="btn btn-secondary">Cancelar</a>
            <button class="btn btn-primary">Guardar</button>
        </div>
    </form>
</x-app-layout>
