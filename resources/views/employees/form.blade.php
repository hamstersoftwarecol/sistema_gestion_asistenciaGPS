<x-app-layout>
    <x-slot name="header">{{ $employee->exists ? 'Editar: '.$employee->name : 'Nuevo empleado' }}</x-slot>

    @php($assigned = $employee->relationLoaded('payComponents') ? $employee->payComponents->keyBy('id') : collect())

    <form method="POST" action="{{ $employee->exists ? route('employees.update', $employee) : route('employees.store') }}" class="space-y-6" x-data="{ payType: @js(old('pay_type', $employee->pay_type)) }">
        @csrf
        @if ($employee->exists) @method('PUT') @endif

        <div class="card">
            <div class="card-header"><h2 class="card-title">Datos personales y acceso</h2></div>
            <div class="card-body grid gap-4 md:grid-cols-3">
                <x-field name="name" label="Nombre completo" :value="$employee->name" required />
                <x-field name="email" label="Correo (también para Google)" type="email" :value="$employee->email" required />
                <x-field name="password" label="{{ $employee->exists ? 'Nueva contraseña (opcional)' : 'Contraseña' }}" type="password" :required="! $employee->exists" autocomplete="new-password" />
                <x-field name="employee_code" label="Código de empleado" :value="$employee->employee_code" />
                <x-field name="national_id" label="Documento de identidad" :value="$employee->national_id" />
                <x-field name="phone" label="Teléfono" :value="$employee->phone" />
                <x-field name="role_id" label="Rol" type="select" :options="$roles" :value="$employee->role_id" required />
                <x-field name="status" label="Estado" type="select" :options="\App\Models\User::STATUSES" :value="$employee->status" required />
                <x-field name="bank_account" label="Cuenta bancaria" :value="$employee->bank_account" />
            </div>
        </div>

        <div class="card">
            <div class="card-header"><h2 class="card-title">Organización y asignación</h2></div>
            <div class="card-body grid gap-4 md:grid-cols-3">
                <x-field name="position" label="Cargo" :value="$employee->position" />
                <x-field name="department_id" label="Departamento" type="select" :options="$departments" :value="$employee->department_id" empty="—" />
                <x-field name="manager_id" label="Jefe directo" type="select" :options="$managers" :value="$employee->manager_id" empty="—" />
                <x-field name="shift_id" label="Turno por defecto" type="select" :options="$shifts" :value="$employee->shift_id" empty="Sin turno" />
                <x-field name="site_id" label="Sitio por defecto" type="select" :options="$sites" :value="$employee->site_id" empty="—" />
                <x-field name="contractor_id" label="Contratista (personal tercerizado)" type="select" :options="$contractors" :value="$employee->contractor_id" empty="Empleado de planta" help="El personal de contratistas no entra en la nómina: se factura al contratista." />
                <x-field name="hire_date" label="Fecha de ingreso" type="date" :value="$employee->hire_date" />
                <x-field name="termination_date" label="Fecha de retiro" type="date" :value="$employee->termination_date" />
            </div>
        </div>

        <div class="card">
            <div class="card-header"><h2 class="card-title">Pago</h2></div>
            <div class="card-body space-y-4">
                <div class="grid gap-4 md:grid-cols-3">
                    <div>
                        <label class="label" for="pay_type">Tipo de pago</label>
                        <select id="pay_type" name="pay_type" class="input" x-model="payType">
                            @foreach (\App\Models\User::PAY_TYPES as $key => $label)
                                <option value="{{ $key }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div x-show="payType === 'monthly'"><x-field name="monthly_salary" label="Salario mensual" type="number" step="0.01" min="0" :value="$employee->monthly_salary" /></div>
                    <div x-show="payType === 'hourly'"><x-field name="hourly_rate" label="Tarifa por hora" type="number" step="0.01" min="0" :value="$employee->hourly_rate" /></div>
                </div>

                @if ($components->isNotEmpty())
                    <div>
                        <div class="label">Componentes de pago individuales (subsidios y deducciones)</div>
                        <div class="grid gap-2 md:grid-cols-2">
                            @foreach ($components as $item)
                                @php($pivot = $assigned->get($item->id)?->pivot)
                                <div class="flex items-center gap-3 rounded-lg border border-gray-200 px-3 py-2">
                                    <input type="checkbox" class="checkbox" name="components[{{ $item->id }}][enabled]" value="1" @checked(old("components.{$item->id}.enabled", $pivot !== null))>
                                    <div class="flex-1 text-sm">
                                        <div class="font-medium">{{ $item->name }}</div>
                                        <div class="text-xs text-gray-500">{{ \App\Models\PayComponent::TYPES[$item->type] }} · {{ $item->calc_type === 'percent' ? $item->amount.'%' : money($item->amount) }}</div>
                                    </div>
                                    <input type="number" step="0.01" min="0" name="components[{{ $item->id }}][amount]" value="{{ old("components.{$item->id}.amount", $pivot?->amount) }}" class="input w-28 py-1 text-xs" placeholder="Valor propio">
                                </div>
                            @endforeach
                        </div>
                        <p class="help">Los componentes marcados como «aplica a todos» se agregan automáticamente en la nómina.</p>
                    </div>
                @endif
            </div>
        </div>

        <div class="flex justify-end gap-2">
            <a href="{{ $employee->exists ? route('employees.show', $employee) : route('employees.index') }}" class="btn btn-secondary">Cancelar</a>
            <button class="btn btn-primary">Guardar empleado</button>
        </div>
    </form>
</x-app-layout>
