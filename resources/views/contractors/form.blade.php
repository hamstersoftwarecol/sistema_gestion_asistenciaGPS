<x-app-layout>
    <x-slot name="header">{{ $contractor->exists ? 'Editar contratista' : 'Nuevo contratista' }}</x-slot>

    <form method="POST" action="{{ $contractor->exists ? route('contractors.update', $contractor) : route('contractors.store') }}" class="card mx-auto max-w-3xl">
        @csrf
        @if ($contractor->exists) @method('PUT') @endif
        <div class="card-body grid gap-4 sm:grid-cols-3">
            <x-field name="name" label="Razón social" :value="$contractor->name" required class="sm:col-span-2" />
            <x-field name="tax_id" label="NIT" :value="$contractor->tax_id" />
            <x-field name="contact_name" label="Contacto" :value="$contractor->contact_name" />
            <x-field name="email" label="Correo" type="email" :value="$contractor->email" />
            <x-field name="phone" label="Teléfono" :value="$contractor->phone" />
            <x-field name="address" label="Dirección" :value="$contractor->address" class="sm:col-span-3" />
            <x-field name="rate_type" label="Tipo de tarifa" type="select" :options="\App\Models\Contractor::RATE_TYPES" :value="$contractor->rate_type" required />
            <x-field name="rate" label="Tarifa" type="number" step="0.01" min="0" :value="$contractor->rate" required />
            <x-field name="overtime_multiplier" label="Multiplicador hora extra" type="number" step="0.01" min="1" :value="$contractor->overtime_multiplier" required />
            <x-field name="tax_percent" label="IVA (%)" type="number" step="0.01" min="0" :value="$contractor->tax_percent" required />
            <x-field name="active" label="Activo" type="checkbox" :value="$contractor->active" class="sm:col-span-2 self-end" />
        </div>
        @if ($contractor->exists && $contractor->relationLoaded('workers'))
            <div class="border-t border-gray-100 px-5 py-3 text-sm">
                <span class="font-medium">Trabajadores asignados:</span>
                {{ $contractor->workers->pluck('name')->implode(', ') ?: 'ninguno' }}
            </div>
        @endif
        <div class="flex justify-end gap-2 border-t border-gray-100 px-5 py-3">
            <a href="{{ route('contractors.index') }}" class="btn btn-secondary">Cancelar</a>
            <button class="btn btn-primary">Guardar</button>
        </div>
    </form>
</x-app-layout>
