<x-app-layout>
    <x-slot name="header">Solicitar permiso</x-slot>

    <form method="POST" action="{{ route('leave-requests.store') }}" enctype="multipart/form-data" class="card mx-auto max-w-2xl">
        @csrf
        <div class="card-body grid gap-4 sm:grid-cols-2">
            <div class="sm:col-span-2">
                <label class="label" for="leave_type_id">Tipo de permiso <span class="text-red-500">*</span></label>
                <select id="leave_type_id" name="leave_type_id" class="input" required>
                    <option value="">Seleccione…</option>
                    @foreach ($types as $type)
                        <option value="{{ $type->id }}" @selected(old('leave_type_id') == $type->id)>
                            {{ $type->name }} — {{ $type->paid ? 'remunerado' : 'no remunerado' }}{{ $type->hasQuota() ? ', '.$type->annual_quota.' días/año' : '' }}{{ $type->requires_attachment ? ', requiere soporte' : '' }}
                        </option>
                    @endforeach
                </select>
                @error('leave_type_id')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
            </div>
            <x-field name="start_date" label="Desde" type="date" required />
            <x-field name="end_date" label="Hasta" type="date" required />
            <x-field name="reason" label="Motivo" type="textarea" class="sm:col-span-2" />
            <div class="sm:col-span-2">
                <label class="label" for="attachment">Soporte (PDF o imagen, máx. 5 MB)</label>
                <input id="attachment" type="file" name="attachment" accept=".pdf,image/*" class="block w-full text-sm text-gray-600 file:mr-3 file:rounded-md file:border-0 file:bg-indigo-50 file:px-3 file:py-2 file:text-indigo-700">
                @error('attachment')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
            </div>
            <p class="text-xs text-gray-500 sm:col-span-2">Solo se descuentan días hábiles (sin fines de semana ni festivos). Al aprobarse, los días quedan marcados como «Permiso» en tu asistencia.</p>
        </div>
        <div class="flex justify-end gap-2 border-t border-gray-100 px-5 py-3">
            <a href="{{ route('leave-requests.index') }}" class="btn btn-secondary">Cancelar</a>
            <button class="btn btn-primary">Enviar solicitud</button>
        </div>
    </form>
</x-app-layout>
