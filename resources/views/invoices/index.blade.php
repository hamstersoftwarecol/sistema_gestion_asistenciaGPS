<x-app-layout>
    <x-slot name="header">Facturas de contratistas</x-slot>

    <div class="grid gap-6 lg:grid-cols-3">
        <form method="POST" action="{{ route('invoices.store') }}" class="card h-fit">
            @csrf
            <div class="card-header"><h2 class="card-title">Generar factura</h2></div>
            <div class="card-body space-y-3">
                <x-field name="contractor_id" label="Contratista" type="select" :options="$contractors" empty="Seleccione…" required />
                <div class="grid grid-cols-2 gap-3">
                    <x-field name="period_start" label="Desde" type="date" :value="now()->subMonthNoOverflow()->startOfMonth()->toDateString()" required />
                    <x-field name="period_end" label="Hasta" type="date" :value="now()->subMonthNoOverflow()->endOfMonth()->toDateString()" required />
                </div>
                <x-field name="due_date" label="Vencimiento" type="date" />
                <x-field name="notes" label="Notas" type="textarea" />
                <p class="help">Las horas y días se toman de la asistencia de los trabajadores asignados al contratista (solo extras aprobadas).</p>
                <div class="flex justify-end"><button class="btn btn-primary">Generar</button></div>
            </div>
        </form>

        <div class="card lg:col-span-2">
            <div class="table-wrap">
                <table class="table">
                    <thead><tr><th>Número</th><th>Contratista</th><th>Período</th><th>Horas</th><th>Total</th><th>Estado</th><th></th></tr></thead>
                    <tbody>
                        @forelse ($invoices as $invoice)
                            <tr>
                                <td class="font-mono font-medium">{{ $invoice->number }}</td>
                                <td>{{ $invoice->contractor->name }}</td>
                                <td class="whitespace-nowrap">{{ $invoice->period_start->format('d/m') }} – {{ $invoice->period_end->format('d/m/Y') }}</td>
                                <td>{{ $invoice->total_hours + 0 }}</td>
                                <td class="whitespace-nowrap font-semibold">{{ money($invoice->total) }}</td>
                                <td><x-status-badge :status="$invoice->status" /></td>
                                <td class="text-right"><a href="{{ route('invoices.show', $invoice) }}" class="btn-link">Abrir →</a></td>
                            </tr>
                        @empty
                            <x-empty colspan="7" />
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="mt-4">{{ $invoices->links() }}</div>
</x-app-layout>
