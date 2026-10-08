<x-app-layout>
    <x-slot name="header">Recibo de pago · {{ $payslip->user->name }}</x-slot>

    <div class="no-print mb-4 flex justify-end gap-2">
        <button onclick="window.print()" class="btn btn-secondary"><x-icon name="printer" class="h-4 w-4" /> Imprimir / PDF</button>
    </div>

    <div class="card mx-auto max-w-3xl">
        <div class="card-body space-y-6">
            <div class="flex flex-wrap items-start justify-between gap-4 border-b border-gray-200 pb-4">
                <div class="flex items-center gap-3">
                    <x-application-logo class="h-10 w-10" />
                    <div>
                        <div class="text-lg font-bold">{{ setting('company_name') }}</div>
                        <div class="text-xs text-gray-500">NIT {{ setting('company_tax_id') }} · {{ setting('company_address') }}</div>
                    </div>
                </div>
                <div class="text-right">
                    <div class="text-sm font-semibold uppercase tracking-wide text-gray-500">Recibo de pago</div>
                    <div class="font-medium">{{ $payslip->period->name }}</div>
                    <div class="text-xs text-gray-500">{{ $payslip->period->start_date->format('d/m/Y') }} – {{ $payslip->period->end_date->format('d/m/Y') }}</div>
                </div>
            </div>

            <div class="grid gap-2 text-sm sm:grid-cols-2">
                <div><span class="text-gray-500">Empleado:</span> <strong>{{ $payslip->user->name }}</strong></div>
                <div><span class="text-gray-500">Código:</span> {{ $payslip->user->employee_code }}</div>
                <div><span class="text-gray-500">Documento:</span> {{ $payslip->user->national_id }}</div>
                <div><span class="text-gray-500">Departamento:</span> {{ $payslip->user->department?->name }}</div>
                <div><span class="text-gray-500">Tipo de pago:</span> {{ \App\Models\User::PAY_TYPES[$payslip->pay_type] ?? '' }} ({{ $payslip->pay_type === 'hourly' ? money($payslip->hourly_rate).'/h' : money($payslip->base_salary).'/mes' }})</div>
                <div><span class="text-gray-500">Cuenta:</span> {{ $payslip->user->bank_account ?? '—' }}</div>
            </div>

            <div class="grid grid-cols-3 gap-2 rounded-lg bg-gray-50 p-3 text-center text-xs sm:grid-cols-6">
                <div><div class="text-gray-500">Días</div><div class="text-base font-semibold">{{ $payslip->days_worked + 0 }}</div></div>
                <div><div class="text-gray-500">H. ordinarias</div><div class="text-base font-semibold">{{ $payslip->regular_hours + 0 }}</div></div>
                <div><div class="text-gray-500">H. extra</div><div class="text-base font-semibold">{{ $payslip->overtime_hours + 0 }}</div></div>
                <div><div class="text-gray-500">H. nocturnas</div><div class="text-base font-semibold">{{ $payslip->night_hours + 0 }}</div></div>
                <div><div class="text-gray-500">Permisos pag.</div><div class="text-base font-semibold">{{ $payslip->paid_leave_days + 0 }}</div></div>
                <div><div class="text-gray-500">Tardes</div><div class="text-base font-semibold">{{ $payslip->late_count }}</div></div>
            </div>

            <div class="grid gap-6 sm:grid-cols-2">
                @foreach (['earning' => ['Devengos', 'gross_pay', 'text-emerald-700'], 'deduction' => ['Deducciones', 'total_deductions', 'text-red-700']] as $kind => [$title, $total, $color])
                    <div>
                        <h3 class="mb-2 text-sm font-semibold text-gray-900">{{ $title }}</h3>
                        <table class="w-full text-sm">
                            @forelse ($payslip->items->where('kind', $kind) as $item)
                                <tr class="border-b border-gray-100">
                                    <td class="py-1.5">{{ $item->label }} @if ($item->quantity !== null)<span class="text-xs text-gray-500">({{ $item->quantity + 0 }})</span>@endif</td>
                                    <td class="py-1.5 text-right tabular-nums">{{ money($item->amount) }}</td>
                                </tr>
                            @empty
                                <tr><td class="py-1.5 text-gray-400">—</td></tr>
                            @endforelse
                            <tr><td class="pt-2 font-semibold">Total</td><td class="pt-2 text-right font-semibold tabular-nums {{ $color }}">{{ money($payslip->$total) }}</td></tr>
                        </table>
                    </div>
                @endforeach
            </div>

            <div class="flex items-center justify-between rounded-lg bg-indigo-600 px-4 py-3 text-white">
                <span class="font-semibold">Neto a pagar</span>
                <span class="text-2xl font-bold tabular-nums">{{ money($payslip->net_pay) }}</span>
            </div>

            <p class="text-xs text-gray-500">Calculado a partir de los registros de asistencia del período (selfie + GPS, correcciones aprobadas y permisos). {{ $payslip->period->isLocked() ? 'Período bloqueado.' : 'Borrador: el período aún no está bloqueado.' }}</p>
        </div>
    </div>
</x-app-layout>
