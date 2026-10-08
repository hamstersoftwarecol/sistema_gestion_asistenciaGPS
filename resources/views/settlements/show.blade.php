<x-app-layout>
    <x-slot name="header">Liquidación final · {{ $settlement->user->name }}</x-slot>

    <div class="no-print mb-4 flex flex-wrap justify-end gap-2">
        @if ($settlement->status === 'draft')
            <form method="POST" action="{{ route('settlements.destroy', $settlement) }}" onsubmit="return confirm('¿Eliminar el borrador?')">
                @csrf @method('DELETE')
                <button class="btn btn-secondary text-red-600"><x-icon name="trash" class="h-4 w-4" /></button>
            </form>
            <form method="POST" action="{{ route('settlements.approve', $settlement) }}" onsubmit="return confirm('Al aprobar, el empleado queda retirado y sus anticipos saldados. ¿Continuar?')">
                @csrf
                <button class="btn btn-success"><x-icon name="check" class="h-4 w-4" /> Aprobar liquidación</button>
            </form>
        @elseif ($settlement->status === 'approved')
            <form method="POST" action="{{ route('settlements.paid', $settlement) }}">
                @csrf
                <button class="btn btn-primary">Marcar como pagada</button>
            </form>
        @endif
        <button onclick="window.print()" class="btn btn-secondary"><x-icon name="printer" class="h-4 w-4" /> Imprimir</button>
    </div>

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="card lg:col-span-2">
            <div class="card-body space-y-5">
                <div class="flex flex-wrap items-start justify-between gap-4 border-b border-gray-200 pb-4">
                    <div>
                        <div class="text-lg font-bold">{{ setting('company_name') }}</div>
                        <div class="text-xs text-gray-500">NIT {{ setting('company_tax_id') }}</div>
                    </div>
                    <div class="text-right">
                        <div class="text-sm font-semibold uppercase tracking-wide text-gray-500">Liquidación final #{{ $settlement->id }}</div>
                        <x-status-badge :status="$settlement->status" />
                    </div>
                </div>

                <div class="grid gap-2 text-sm sm:grid-cols-2">
                    <div><span class="text-gray-500">Empleado:</span> <strong>{{ $settlement->user->name }}</strong> ({{ $settlement->user->employee_code }})</div>
                    <div><span class="text-gray-500">Departamento:</span> {{ $settlement->user->department?->name }}</div>
                    <div><span class="text-gray-500">Ingreso:</span> {{ $settlement->user->hire_date?->format('d/m/Y') ?? '—' }}</div>
                    <div><span class="text-gray-500">Retiro:</span> {{ $settlement->termination_date->format('d/m/Y') }} · {{ \App\Models\FinalSettlement::REASONS[$settlement->reason] }}</div>
                    <div><span class="text-gray-500">Antigüedad:</span> {{ $settlement->years_of_service }} años</div>
                    <div><span class="text-gray-500">Salario diario:</span> {{ money($settlement->daily_rate) }}</div>
                </div>

                <table class="w-full text-sm">
                    <thead><tr class="border-b border-gray-200 text-left text-xs uppercase text-gray-500"><th class="py-2">Concepto</th><th class="py-2 text-right">Valor</th></tr></thead>
                    <tbody>
                        <tr class="border-b border-gray-100"><td class="py-2">Salario pendiente desde {{ \Carbon\Carbon::parse($settlement->details['pending_from'] ?? $settlement->termination_date)->format('d/m/Y') }}</td><td class="py-2 text-right tabular-nums">{{ money($settlement->pending_salary) }}</td></tr>
                        <tr class="border-b border-gray-100"><td class="py-2">Gratificación por antigüedad <span class="text-xs text-gray-500">({{ $settlement->details['gratuity_rule'] ?? '' }})</span></td><td class="py-2 text-right tabular-nums">{{ money($settlement->gratuity_amount) }}</td></tr>
                        <tr class="border-b border-gray-100"><td class="py-2">Compensación de vacaciones ({{ $settlement->leave_encashment_days + 0 }} días)</td><td class="py-2 text-right tabular-nums">{{ money($settlement->leave_encashment_amount) }}</td></tr>
                        <tr class="border-b border-gray-100"><td class="py-2">Otros devengos</td><td class="py-2 text-right tabular-nums">{{ money($settlement->other_earnings) }}</td></tr>
                        <tr class="border-b border-gray-100 text-red-700"><td class="py-2">(−) Anticipos y préstamos pendientes</td><td class="py-2 text-right tabular-nums">{{ money($settlement->advances_recovered) }}</td></tr>
                        <tr class="border-b border-gray-100 text-red-700"><td class="py-2">(−) Otras deducciones</td><td class="py-2 text-right tabular-nums">{{ money($settlement->other_deductions) }}</td></tr>
                    </tbody>
                </table>

                <div class="flex items-center justify-between rounded-lg bg-indigo-600 px-4 py-3 text-white">
                    <span class="font-semibold">Neto de la liquidación</span>
                    <span class="text-2xl font-bold tabular-nums">{{ money($settlement->net_amount) }}</span>
                </div>

                @if (! empty($settlement->details['pending_lines']))
                    <div>
                        <h3 class="mb-1 text-sm font-semibold">Detalle del salario pendiente (desde la asistencia)</h3>
                        <ul class="text-xs text-gray-600">
                            @foreach ($settlement->details['pending_lines'] as $line)
                                <li class="flex justify-between border-b border-gray-50 py-1"><span>{{ ! empty($line['negative']) ? '(−) ' : '' }}{{ $line['label'] }}</span><span class="tabular-nums">{{ money($line['amount']) }}</span></li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                @if ($settlement->notes)<p class="text-sm text-gray-600"><strong>Notas:</strong> {{ $settlement->notes }}</p>@endif

                <div class="grid grid-cols-2 gap-8 pt-10 text-center text-xs text-gray-500">
                    <div class="border-t border-gray-300 pt-2">Firma del empleado</div>
                    <div class="border-t border-gray-300 pt-2">Firma RR. HH. {{ $settlement->approver ? '· '.$settlement->approver->name : '' }}</div>
                </div>
            </div>
        </div>

        @if ($settlement->status === 'draft')
            <form method="POST" action="{{ route('settlements.recalculate', $settlement) }}" class="no-print card h-fit">
                @csrf
                <div class="card-header"><h2 class="card-title">Ajustes y recálculo</h2></div>
                <div class="card-body space-y-3">
                    <x-field name="other_earnings" label="Otros devengos" type="number" step="0.01" min="0" :value="$settlement->other_earnings" />
                    <x-field name="other_deductions" label="Otras deducciones" type="number" step="0.01" min="0" :value="$settlement->other_deductions" />
                    <p class="help">Recalcula con la asistencia, saldos y anticipos actuales.</p>
                    <div class="flex justify-end"><button class="btn btn-secondary"><x-icon name="refresh" class="h-4 w-4" /> Recalcular</button></div>
                </div>
            </form>
        @endif
    </div>
</x-app-layout>
