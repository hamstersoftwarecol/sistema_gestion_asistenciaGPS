<x-app-layout>
    <x-slot name="header">{{ $employee->name }}</x-slot>

    <div class="mb-4 flex flex-wrap items-center gap-3">
        <x-avatar :user="$employee" size="h-14 w-14" />
        <div>
            <h1 class="text-xl font-bold text-gray-900">{{ $employee->name }}</h1>
            <p class="text-sm text-gray-500">{{ $employee->position }} · {{ $employee->department?->name }} · <x-status-badge :status="$employee->status" /></p>
        </div>
        <div class="ml-auto flex flex-wrap gap-2">
            <a href="{{ route('employees.edit', $employee) }}" class="btn btn-secondary"><x-icon name="pencil" class="h-4 w-4" /> Editar</a>
            @can('settlements.manage')
                @if ($employee->status !== 'terminated')
                    <a href="{{ route('settlements.create', ['user_id' => $employee->id]) }}" class="btn btn-secondary"><x-icon name="scale" class="h-4 w-4" /> Liquidación final</a>
                @endif
            @endcan
            <form method="POST" action="{{ route('employees.destroy', $employee) }}" onsubmit="return confirm('¿Eliminar o desactivar este empleado?')">
                @csrf @method('DELETE')
                <button class="btn btn-secondary text-red-600"><x-icon name="trash" class="h-4 w-4" /></button>
            </form>
        </div>
    </div>

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-6">
            <div class="card">
                <div class="card-header"><h2 class="card-title">Ficha</h2></div>
                <dl class="card-body grid grid-cols-2 gap-x-3 gap-y-2 text-sm">
                    <dt class="text-gray-500">Código</dt><dd>{{ $employee->employee_code ?? '—' }}</dd>
                    <dt class="text-gray-500">Correo</dt><dd class="truncate">{{ $employee->email }}</dd>
                    <dt class="text-gray-500">Teléfono</dt><dd>{{ $employee->phone ?? '—' }}</dd>
                    <dt class="text-gray-500">Documento</dt><dd>{{ $employee->national_id ?? '—' }}</dd>
                    <dt class="text-gray-500">Rol</dt><dd>{{ $employee->role?->name }}</dd>
                    <dt class="text-gray-500">Jefe directo</dt><dd>{{ $employee->manager?->name ?? '—' }}</dd>
                    <dt class="text-gray-500">Turno</dt><dd>{{ $employee->shift?->label() ?? '—' }}</dd>
                    <dt class="text-gray-500">Sitio</dt><dd>{{ $employee->site?->name ?? '—' }}</dd>
                    <dt class="text-gray-500">Ingreso</dt><dd>{{ $employee->hire_date?->format('d/m/Y') ?? '—' }}</dd>
                    <dt class="text-gray-500">Retiro</dt><dd>{{ $employee->termination_date?->format('d/m/Y') ?? '—' }}</dd>
                    <dt class="text-gray-500">Pago</dt>
                    <dd>
                        @if ($employee->contractor) Contratista: {{ $employee->contractor->name }}
                        @elseif ($employee->pay_type === 'hourly') {{ money($employee->hourly_rate) }}/hora
                        @else {{ money($employee->monthly_salary) }}/mes @endif
                    </dd>
                    <dt class="text-gray-500">Login Google</dt><dd>{{ $employee->google_id ? 'Vinculado' : 'No' }}</dd>
                </dl>
            </div>

            <div class="card">
                <div class="card-header"><h2 class="card-title">Dispositivo vinculado</h2></div>
                <div class="card-body text-sm">
                    @if ($employee->device_id)
                        <p>Vinculado el {{ $employee->device_bound_at?->format('d/m/Y H:i') }}</p>
                        <p class="truncate font-mono text-xs text-gray-500">{{ $employee->device_id }}</p>
                        <form method="POST" action="{{ route('employees.reset-device', $employee) }}" class="mt-3">
                            @csrf
                            <button class="btn btn-secondary btn-sm">Restablecer vinculación</button>
                        </form>
                    @else
                        <p class="text-gray-500">Sin dispositivo vinculado. {{ setting('device_binding') ? 'Se vinculará en su próximo registro.' : 'La vinculación de dispositivos está desactivada.' }}</p>
                    @endif
                </div>
            </div>

            <div class="card">
                <div class="card-header"><h2 class="card-title">Saldos de permisos {{ now()->year }}</h2></div>
                <ul class="divide-y divide-gray-100 text-sm">
                    @forelse ($balances as $b)
                        <li class="flex justify-between px-5 py-2"><span>{{ $b->leaveType->name }}</span><span><strong>{{ $b->available() }}</strong> / {{ $b->allocated + $b->carried_forward }}</span></li>
                    @empty
                        <li class="px-5 py-4 text-gray-500">Sin saldos.</li>
                    @endforelse
                </ul>
            </div>
        </div>

        <div class="space-y-6 lg:col-span-2">
            <div class="card">
                <div class="card-header"><h2 class="card-title">Asistencia reciente</h2>@can('reports.view')<a href="{{ route('reports.show', ['summary', 'user_id' => $employee->id]) }}" class="btn-link">Informe →</a>@endcan</div>
                <div class="table-wrap">
                    <table class="table">
                        <thead><tr><th>Fecha</th><th>Estado</th><th>Entrada</th><th>Salida</th><th>Sitio</th><th>Trabajado</th><th></th></tr></thead>
                        <tbody>
                            @forelse ($recent as $row)
                                <tr>
                                    <td>{{ $row->date->format('d/m/Y') }}</td>
                                    <td><x-status-badge :status="$row->status" /></td>
                                    <td>{{ $row->check_in_at?->format('H:i') ?? '—' }}</td>
                                    <td>{{ $row->check_out_at?->format('H:i') ?? '—' }}</td>
                                    <td>{{ $row->site?->name ?? '—' }} @if ($row->isOutOfZone())<x-badge color="red">fuera</x-badge>@endif</td>
                                    <td>{{ hours($row->worked_minutes) }}</td>
                                    <td class="text-right">@can('attendance.mark')<a href="{{ route('register.edit', [$employee, $row->date->toDateString()]) }}" class="btn-link text-xs">Detalle</a>@endcan</td>
                                </tr>
                            @empty
                                <x-empty colspan="7" />
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="grid gap-6 md:grid-cols-2">
                <div class="card">
                    <div class="card-header"><h2 class="card-title">Anticipos y préstamos</h2>@can('advances.manage')<a href="{{ route('advances.index') }}" class="btn-link">Gestionar →</a>@endcan</div>
                    <ul class="divide-y divide-gray-100 text-sm">
                        @forelse ($employee->advances as $a)
                            <li class="flex items-center justify-between px-5 py-2">
                                <span>{{ \App\Models\Advance::TYPES[$a->type] }} · {{ money($a->amount) }}</span>
                                <span>saldo <strong>{{ money($a->balance) }}</strong> <x-status-badge :status="$a->status" /></span>
                            </li>
                        @empty
                            <li class="px-5 py-4 text-gray-500">Sin anticipos ni préstamos.</li>
                        @endforelse
                    </ul>
                </div>
                <div class="card">
                    <div class="card-header"><h2 class="card-title">Documentos</h2>@can('documents.manage')<a href="{{ route('documents.index', ['user_id' => $employee->id]) }}" class="btn-link">Gestionar →</a>@endcan</div>
                    <ul class="divide-y divide-gray-100 text-sm">
                        @forelse ($employee->documents as $doc)
                            <li class="flex items-center justify-between px-5 py-2">
                                <span>{{ $doc->title }}</span>
                                <span class="text-xs {{ $doc->isExpiringSoon() ? 'font-semibold text-red-600' : 'text-gray-500' }}">{{ $doc->expires_on ? 'vence '.$doc->expires_on->format('d/m/Y') : 'sin vencimiento' }}</span>
                            </li>
                        @empty
                            <li class="px-5 py-4 text-gray-500">Sin documentos.</li>
                        @endforelse
                    </ul>
                </div>
            </div>

            <div class="card">
                <div class="card-header"><h2 class="card-title">Componentes de pago</h2></div>
                <ul class="divide-y divide-gray-100 text-sm">
                    @forelse ($employee->payComponents as $c)
                        <li class="flex justify-between px-5 py-2">
                            <span>{{ $c->name }} <span class="text-xs text-gray-500">({{ \App\Models\PayComponent::TYPES[$c->type] }})</span></span>
                            <span>{{ $c->calc_type === 'percent' ? ($c->pivot->amount ?? $c->amount).'%' : money($c->pivot->amount ?? $c->amount) }}</span>
                        </li>
                    @empty
                        <li class="px-5 py-4 text-gray-500">Solo aplican los componentes generales.</li>
                    @endforelse
                </ul>
            </div>
        </div>
    </div>
</x-app-layout>
