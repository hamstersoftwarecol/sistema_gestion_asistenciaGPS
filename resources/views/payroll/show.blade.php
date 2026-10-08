<x-app-layout>
    <x-slot name="header">{{ $period->name }}</x-slot>

    <div class="mb-4 flex flex-wrap items-center gap-3">
        <div class="text-sm text-gray-600">
            {{ $period->start_date->format('d/m/Y') }} – {{ $period->end_date->format('d/m/Y') }} · <x-status-badge :status="$period->status" />
            @if ($period->processed_at) · generada {{ $period->processed_at->format('d/m/Y H:i') }} @endif
            @if ($period->locked_at) · bloqueada {{ $period->locked_at->format('d/m/Y H:i') }} por {{ $period->locker?->name }} @endif
        </div>
        <div class="ml-auto flex flex-wrap gap-2">
            @unless ($period->isLocked())
                <form method="POST" action="{{ route('payroll.generate', $period) }}">
                    @csrf
                    <button class="btn btn-primary"><x-icon name="refresh" class="h-4 w-4" /> {{ $period->status === 'draft' ? 'Generar nómina' : 'Regenerar' }}</button>
                </form>
                @if ($period->status === 'processed')
                    <form method="POST" action="{{ route('payroll.lock', $period) }}" onsubmit="return confirm('Al bloquear, la asistencia del período queda cerrada y se aplican las cuotas de anticipos. ¿Continuar?')">
                        @csrf
                        <button class="btn btn-warning"><x-icon name="shield" class="h-4 w-4" /> Bloquear período</button>
                    </form>
                @endif
                <form method="POST" action="{{ route('payroll.destroy', $period) }}" onsubmit="return confirm('¿Eliminar el período y sus recibos?')">
                    @csrf @method('DELETE')
                    <button class="btn btn-secondary text-red-600"><x-icon name="trash" class="h-4 w-4" /></button>
                </form>
            @else
                @if (auth()->user()->isAdmin())
                    <form method="POST" action="{{ route('payroll.unlock', $period) }}" onsubmit="return confirm('¿Desbloquear el período? Se revertirán las cuotas aplicadas.')">
                        @csrf
                        <button class="btn btn-secondary">Desbloquear</button>
                    </form>
                @endif
            @endunless
            @if ($payslips->isNotEmpty())
                <a href="{{ route('payroll.export', $period) }}" class="btn btn-secondary"><x-icon name="download" class="h-4 w-4" /> CSV</a>
            @endif
        </div>
    </div>

    <div class="mb-4 grid grid-cols-2 gap-3 md:grid-cols-4">
        <x-kpi label="Recibos" :value="$payslips->count()" />
        <x-kpi label="Devengado" :value="money($totals['gross'])" tone="green" />
        <x-kpi label="Deducciones" :value="money($totals['deductions'])" tone="red" />
        <x-kpi label="Neto a pagar" :value="money($totals['net'])" tone="indigo" />
    </div>

    <div class="card">
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th>Empleado</th><th>Tipo</th><th>Días</th><th>Horas ord.</th><th>Extra</th><th>Noct.</th><th>Desc./fest.</th><th>Permisos</th><th>No pagados</th><th>Tardes</th><th>Devengado</th><th>Deducciones</th><th>Neto</th><th></th></tr></thead>
                <tbody>
                    @forelse ($payslips as $p)
                        <tr>
                            <td><div class="font-medium">{{ $p->user->name }}</div><div class="text-xs text-gray-500">{{ $p->user->department?->name }}</div></td>
                            <td class="text-xs">{{ \App\Models\User::PAY_TYPES[$p->pay_type] ?? $p->pay_type }}</td>
                            <td>{{ $p->days_worked + 0 }}</td>
                            <td>{{ $p->regular_hours + 0 }}</td>
                            <td>{{ $p->overtime_hours + 0 }}</td>
                            <td>{{ $p->night_hours + 0 }}</td>
                            <td>{{ $p->holiday_hours + 0 }}</td>
                            <td>{{ $p->paid_leave_days + 0 }}</td>
                            <td>{{ $p->unpaid_days + 0 }}</td>
                            <td>{{ $p->late_count }}</td>
                            <td class="whitespace-nowrap">{{ money($p->gross_pay) }}</td>
                            <td class="whitespace-nowrap text-red-700">{{ money($p->total_deductions) }}</td>
                            <td class="whitespace-nowrap font-semibold">{{ money($p->net_pay) }}</td>
                            <td><a href="{{ route('payslips.show', $p) }}" class="btn-link">Recibo</a></td>
                        </tr>
                    @empty
                        <x-empty colspan="14" message="Genera la nómina para calcular los recibos a partir de la asistencia del período." />
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-app-layout>
