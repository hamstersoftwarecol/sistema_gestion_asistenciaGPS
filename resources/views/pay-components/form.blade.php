<x-app-layout>
    <x-slot name="header">{{ $payComponent->exists ? 'Editar componente' : 'Nuevo componente de pago' }}</x-slot>

    <form method="POST" action="{{ $payComponent->exists ? route('pay-components.update', $payComponent) : route('pay-components.store') }}" class="card mx-auto max-w-2xl">
        @csrf
        @if ($payComponent->exists) @method('PUT') @endif
        <div class="card-body grid gap-4 sm:grid-cols-2">
            <x-field name="name" label="Nombre" :value="$payComponent->name" required placeholder="Ej.: Auxilio de transporte" />
            <x-field name="code" label="Código" :value="$payComponent->code" required placeholder="TRANSP" />
            <x-field name="type" label="Tipo" type="select" :options="\App\Models\PayComponent::TYPES" :value="$payComponent->type" required />
            <x-field name="calc_type" label="Cálculo" type="select" :options="\App\Models\PayComponent::CALC_TYPES" :value="$payComponent->calc_type" required />
            <x-field name="amount" label="Valor (monto o %)" type="number" step="0.01" min="0" :value="$payComponent->amount" required />
            <div class="space-y-2 self-end">
                <x-field name="applies_to_all" label="Aplica a todos los empleados" type="checkbox" :value="$payComponent->applies_to_all" />
                <x-field name="active" label="Activo" type="checkbox" :value="$payComponent->active" />
            </div>
        </div>
        <div class="flex justify-end gap-2 border-t border-gray-100 px-5 py-3">
            <a href="{{ route('pay-components.index') }}" class="btn btn-secondary">Cancelar</a>
            <button class="btn btn-primary">Guardar</button>
        </div>
    </form>
</x-app-layout>
