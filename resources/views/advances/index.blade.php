<x-app-layout>
    <x-slot name="header">Anticipos y préstamos</x-slot>

    <div class="grid gap-6 lg:grid-cols-3">
        <form method="POST" action="{{ route('advances.store') }}" class="card h-fit" x-data="{ amount: {{ (float) old('amount', 0) }}, installments: {{ (int) old('installments', 1) }} }">
            @csrf
            <div class="card-header"><h2 class="card-title">Registrar anticipo o préstamo</h2></div>
            <div class="card-body space-y-3">
                <x-field name="user_id" label="Empleado" type="select" :options="$employees" empty="Seleccione…" required />
                <x-field name="type" label="Tipo" type="select" :options="\App\Models\Advance::TYPES" required />
                <div>
                    <label class="label" for="amount">Monto</label>
                    <input id="amount" type="number" name="amount" step="0.01" min="1" class="input" x-model.number="amount" required>
                </div>
                <div>
                    <label class="label" for="installments">Número de cuotas</label>
                    <input id="installments" type="number" name="installments" min="1" max="60" class="input" x-model.number="installments" required>
                    <p class="help">Cuota por nómina: <strong x-text="installments > 0 ? (amount / installments).toLocaleString('es-CO', { maximumFractionDigits: 2 }) : '—'"></strong></p>
                </div>
                <x-field name="issued_on" label="Fecha de desembolso" type="date" :value="today()->toDateString()" required />
                <x-field name="deduct_from" label="Descontar desde" type="date" :value="today()->addMonthNoOverflow()->startOfMonth()->toDateString()" required />
                <x-field name="reason" label="Motivo" type="textarea" />
                <div class="flex justify-end"><button class="btn btn-primary">Registrar</button></div>
            </div>
        </form>

        <div class="lg:col-span-2">
            <div class="mb-3 flex flex-wrap gap-2">
                @foreach (['active' => 'Activos', 'closed' => 'Pagados', 'cancelled' => 'Anulados', 'all' => 'Todos'] as $key => $label)
                    <a href="{{ route('advances.index', ['status' => $key]) }}" class="rounded-full px-3 py-1 text-sm {{ request('status', 'active') === $key ? 'bg-indigo-600 text-white' : 'bg-white text-gray-700 shadow-sm' }}">{{ $label }}</a>
                @endforeach
            </div>
            <div class="card">
                <div class="table-wrap">
                    <table class="table">
                        <thead><tr><th>Empleado</th><th>Tipo</th><th>Monto</th><th>Cuota</th><th>Saldo</th><th>Desde</th><th>Pagos</th><th>Estado</th><th></th></tr></thead>
                        <tbody>
                            @forelse ($advances as $a)
                                <tr>
                                    <td><div class="font-medium">{{ $a->user->name }}</div><div class="text-xs text-gray-500">{{ $a->reason }}</div></td>
                                    <td>{{ \App\Models\Advance::TYPES[$a->type] }}</td>
                                    <td class="whitespace-nowrap">{{ money($a->amount) }}</td>
                                    <td class="whitespace-nowrap">{{ money($a->installment_amount) }} <span class="text-xs text-gray-500">× {{ $a->installments }}</span></td>
                                    <td class="whitespace-nowrap font-semibold">{{ money($a->balance) }}</td>
                                    <td>{{ $a->deduct_from->format('d/m/Y') }}</td>
                                    <td class="text-xs">{{ $a->repayments->count() }} · {{ money($a->repayments->sum('amount')) }}</td>
                                    <td><x-status-badge :status="$a->status" /></td>
                                    <td class="text-right">
                                        @if ($a->status === 'active')
                                            <form method="POST" action="{{ route('advances.destroy', $a) }}" onsubmit="return confirm('¿Anular este registro?')">
                                                @csrf @method('DELETE')
                                                <button class="text-xs text-red-600 hover:text-red-800">Anular</button>
                                            </form>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <x-empty colspan="9" />
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="mt-4">{{ $advances->links() }}</div>
            <p class="mt-2 text-xs text-gray-500">Las cuotas se descuentan en cada nómina generada y se aplican al saldo cuando el período se bloquea. Los saldos pendientes se recuperan en la liquidación final.</p>
        </div>
    </div>
</x-app-layout>
