<x-app-layout>
    <x-slot name="header">{{ $site->exists ? 'Editar sitio' : 'Nuevo sitio' }}</x-slot>

    <form method="POST" action="{{ $site->exists ? route('sites.update', $site) : route('sites.store') }}"
          x-data="sitePicker({ lat: @js(old('latitude', $site->latitude)), lng: @js(old('longitude', $site->longitude)), radius: @js(old('radius_m', $site->radius_m)), others: @js($others->map->only(['name', 'latitude', 'longitude', 'radius_m'])) })"
          class="grid gap-6 lg:grid-cols-5">
        @csrf
        @if ($site->exists) @method('PUT') @endif

        <div class="card lg:col-span-2">
            <div class="card-body space-y-4">
                <x-field name="name" label="Nombre del sitio" :value="$site->name" required />
                <div class="grid grid-cols-2 gap-4">
                    <x-field name="code" label="Código" :value="$site->code" />
                    <x-field name="client" label="Cliente / proyecto" :value="$site->client" />
                </div>
                <x-field name="address" label="Dirección" :value="$site->address" />
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="label" for="latitude">Latitud <span class="text-red-500">*</span></label>
                        <input id="latitude" name="latitude" x-model="lat" class="input font-mono" required>
                    </div>
                    <div>
                        <label class="label" for="longitude">Longitud <span class="text-red-500">*</span></label>
                        <input id="longitude" name="longitude" x-model="lng" class="input font-mono" required>
                    </div>
                </div>
                <div>
                    <label class="label" for="radius_m">Radio de la geocerca: <span x-text="radius"></span> m</label>
                    <input type="range" min="25" max="2000" step="5" x-model="radius" class="w-full accent-indigo-600">
                    <input id="radius_m" type="number" name="radius_m" x-model="radius" min="10" max="20000" class="input mt-2 w-32">
                    <p class="help">Las marcaciones a mayor distancia del sitio más cercano se marcan «fuera de zona».</p>
                </div>
                <x-field name="active" label="Sitio activo (participa en el cálculo del sitio más cercano)" type="checkbox" :value="$site->active" />
            </div>
        </div>

        <div class="card overflow-hidden lg:col-span-3">
            <div class="card-header">
                <span class="text-sm text-gray-500">Haz clic en el mapa o arrastra el marcador para fijar el centro.</span>
                <button type="button" class="btn btn-secondary btn-sm" @click="locate()" :disabled="locating"><x-icon name="pin" class="h-3.5 w-3.5" /> Usar mi ubicación</button>
            </div>
            <div x-ref="map" class="h-[420px] w-full"></div>
        </div>

        <div class="flex justify-end gap-2 lg:col-span-5">
            <a href="{{ route('sites.index') }}" class="btn btn-secondary">Cancelar</a>
            <button class="btn btn-primary">Guardar sitio</button>
        </div>
    </form>
</x-app-layout>
