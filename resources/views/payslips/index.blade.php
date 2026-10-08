<x-app-layout>
    <x-slot name="header">Mis recibos de pago</x-slot>

    <div class="card">
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th>Período</th><th>Fechas</th><th>Devengado</th><th>Deducciones</th><th>Neto</th><th></th></tr></thead>
                <tbody>
                    @forelse ($payslips as $p)
                        <tr>
                            <td class="font-medium">{{ $p->period->name }}</td>
                            <td>{{ $p->period->start_date->format('d/m/Y') }} – {{ $p->period->end_date->format('d/m/Y') }}</td>
                            <td>{{ money($p->gross_pay) }}</td>
                            <td>{{ money($p->total_deductions) }}</td>
                            <td class="font-semibold">{{ money($p->net_pay) }}</td>
                            <td class="text-right"><a href="{{ route('payslips.show', $p) }}" class="btn-link">Ver recibo</a></td>
                        </tr>
                    @empty
                        <x-empty colspan="6" message="Aún no tienes recibos de períodos cerrados." />
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <div class="mt-4">{{ $payslips->links() }}</div>
</x-app-layout>
