<x-app-layout>
    <x-slot name="header">Factura {{ $invoice->number }}</x-slot>

    <div class="no-print mb-4 flex flex-wrap justify-end gap-2">
        @if ($invoice->status === 'draft')
            <form method="POST" action="{{ route('invoices.destroy', $invoice) }}" onsubmit="return confirm('¿Eliminar el borrador?')">
                @csrf @method('DELETE')
                <button class="btn btn-secondary text-red-600"><x-icon name="trash" class="h-4 w-4" /></button>
            </form>
        @endif
        @foreach (['issued' => 'Marcar emitida', 'paid' => 'Marcar pagada'] as $status => $label)
            @if ($invoice->status !== $status && ! ($invoice->status === 'paid'))
                <form method="POST" action="{{ route('invoices.update', $invoice) }}">
                    @csrf @method('PUT')
                    <input type="hidden" name="status" value="{{ $status }}">
                    <button class="btn btn-secondary">{{ $label }}</button>
                </form>
            @endif
        @endforeach
        <button onclick="window.print()" class="btn btn-primary"><x-icon name="printer" class="h-4 w-4" /> Imprimir</button>
    </div>

    <div class="card mx-auto max-w-4xl">
        <div class="card-body space-y-6">
            <div class="flex flex-wrap items-start justify-between gap-4 border-b border-gray-200 pb-4">
                <div>
                    <div class="text-xs uppercase text-gray-500">Contratista</div>
                    <div class="text-lg font-bold">{{ $invoice->contractor->name }}</div>
                    <div class="text-xs text-gray-500">NIT {{ $invoice->contractor->tax_id }} · {{ $invoice->contractor->address }}</div>
                </div>
                <div class="text-right">
                    <div class="text-sm font-semibold uppercase tracking-wide text-gray-500">Cuenta de cobro / factura</div>
                    <div class="font-mono text-lg font-bold">{{ $invoice->number }}</div>
                    <x-status-badge :status="$invoice->status" />
                </div>
            </div>

            <div class="grid gap-2 text-sm sm:grid-cols-3">
                <div><span class="text-gray-500">Cliente:</span> {{ setting('company_name') }}</div>
                <div><span class="text-gray-500">Período:</span> {{ $invoice->period_start->format('d/m/Y') }} – {{ $invoice->period_end->format('d/m/Y') }}</div>
                <div><span class="text-gray-500">Vence:</span> {{ $invoice->due_date?->format('d/m/Y') ?? '—' }}</div>
                <div><span class="text-gray-500">Tarifa:</span> {{ money($invoice->contractor->rate) }} {{ $invoice->contractor->rate_type === 'hourly' ? 'por hora' : 'por día' }}</div>
                <div><span class="text-gray-500">Recargo extra:</span> ×{{ $invoice->contractor->overtime_multiplier }}</div>
            </div>

            <table class="w-full text-sm">
                <thead><tr class="border-b border-gray-200 text-left text-xs uppercase text-gray-500"><th class="py-2">Trabajador</th><th class="py-2 text-right">Días</th><th class="py-2 text-right">Horas ord.</th><th class="py-2 text-right">Horas extra</th><th class="py-2 text-right">Valor</th></tr></thead>
                <tbody>
                    @forelse ($invoice->lines ?? [] as $line)
                        <tr class="border-b border-gray-100">
                            <td class="py-2">{{ $line['name'] }} <span class="text-xs text-gray-500">{{ $line['code'] }}</span></td>
                            <td class="py-2 text-right tabular-nums">{{ $line['days'] }}</td>
                            <td class="py-2 text-right tabular-nums">{{ $line['regular_hours'] }}</td>
                            <td class="py-2 text-right tabular-nums">{{ $line['overtime_hours'] }}</td>
                            <td class="py-2 text-right tabular-nums">{{ money($line['amount']) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="py-6 text-center text-gray-500">Sin asistencia registrada en el período.</td></tr>
                    @endforelse
                </tbody>
                <tfoot>
                    <tr><td colspan="4" class="pt-3 text-right">Subtotal</td><td class="pt-3 text-right tabular-nums">{{ money($invoice->subtotal) }}</td></tr>
                    <tr><td colspan="4" class="text-right">IVA ({{ $invoice->tax_percent + 0 }}%)</td><td class="text-right tabular-nums">{{ money($invoice->tax_amount) }}</td></tr>
                    <tr><td colspan="4" class="pt-1 text-right text-base font-bold">Total</td><td class="pt-1 text-right text-base font-bold tabular-nums">{{ money($invoice->total) }}</td></tr>
                </tfoot>
            </table>

            @if ($invoice->notes)<p class="text-sm text-gray-600"><strong>Notas:</strong> {{ $invoice->notes }}</p>@endif
            <p class="text-xs text-gray-500">Generada el {{ $invoice->created_at->format('d/m/Y H:i') }} por {{ $invoice->creator?->name }} a partir de los registros de asistencia (selfie + GPS) del personal del contratista.</p>
        </div>
    </div>
</x-app-layout>
