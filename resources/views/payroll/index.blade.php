<x-app-layout>
    <x-slot name="header">Períodos de nómina</x-slot>

    <div class="grid gap-6 lg:grid-cols-3">
        <form method="POST" action="{{ route('payroll.store') }}" class="card h-fit"
              x-data="{
                month: @js($suggested->format('Y-m')),
                start: @js($suggested->toDateString()),
                end: @js($suggested->copy()->endOfMonth()->toDateString()),
                name: @js('Nómina '.ucfirst($suggested->locale('es')->isoFormat('MMMM YYYY'))),
                setMonth() {
                    const [y, m] = this.month.split('-').map(Number);
                    const last = new Date(y, m, 0).getDate();
                    this.start = `${this.month}-01`;
                    this.end = `${this.month}-${String(last).padStart(2, '0')}`;
                    const label = new Date(y, m - 1, 1).toLocaleDateString('es', { month: 'long', year: 'numeric' });
                    this.name = 'Nómina ' + label.charAt(0).toUpperCase() + label.slice(1);
                }
              }">
            @csrf
            <div class="card-header"><h2 class="card-title">Nuevo período</h2></div>
            <div class="card-body space-y-3">
                <div>
                    <label class="label" for="month">Mes (atajo)</label>
                    <input id="month" type="month" class="input" x-model="month" @change="setMonth()">
                </div>
                <div><label class="label" for="name">Nombre</label><input id="name" name="name" class="input" x-model="name" required></div>
                <div class="grid grid-cols-2 gap-3">
                    <div><label class="label" for="start_date">Desde</label><input id="start_date" type="date" name="start_date" class="input" x-model="start" required></div>
                    <div><label class="label" for="end_date">Hasta</label><input id="end_date" type="date" name="end_date" class="input" x-model="end" required></div>
                </div>
                <p class="help">Puedes crear períodos quincenales o mensuales; no deben cruzarse.</p>
                <div class="flex justify-end"><button class="btn btn-primary">Crear período</button></div>
            </div>
        </form>

        <div class="card lg:col-span-2">
            <div class="table-wrap">
                <table class="table">
                    <thead><tr><th>Período</th><th>Fechas</th><th>Recibos</th><th>Neto total</th><th>Estado</th><th></th></tr></thead>
                    <tbody>
                        @forelse ($periods as $period)
                            <tr>
                                <td class="font-medium">{{ $period->name }}</td>
                                <td class="whitespace-nowrap">{{ $period->start_date->format('d/m/Y') }} – {{ $period->end_date->format('d/m/Y') }}</td>
                                <td>{{ $period->payslips_count }}</td>
                                <td class="whitespace-nowrap">{{ money($period->payslips_sum_net_pay) }}</td>
                                <td><x-status-badge :status="$period->status" /></td>
                                <td class="text-right"><a href="{{ route('payroll.show', $period) }}" class="btn-link">Abrir →</a></td>
                            </tr>
                        @empty
                            <x-empty colspan="6" message="Aún no hay períodos de nómina." />
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="mt-4">{{ $periods->links() }}</div>
</x-app-layout>
