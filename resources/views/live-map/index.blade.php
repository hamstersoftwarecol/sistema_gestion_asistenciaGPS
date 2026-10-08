<x-app-layout>
    <x-slot name="header">Mapa en vivo</x-slot>

    <div x-data="liveMap({ dataUrl: @js(route('live-map.data')), interval: 30 })" class="grid gap-4 lg:grid-cols-4">
        <div class="space-y-4 lg:col-span-3">
            <div class="grid grid-cols-2 gap-3 md:grid-cols-4">
                <div class="kpi"><div class="kpi-label">En sitio</div><div class="kpi-value text-emerald-600" x-text="stats.on_site"></div></div>
                <div class="kpi"><div class="kpi-label">Ya salieron</div><div class="kpi-value text-gray-600" x-text="stats.checked_out"></div></div>
                <div class="kpi"><div class="kpi-label">Fuera de zona</div><div class="kpi-value text-red-600" x-text="stats.out_of_zone"></div></div>
                <div class="kpi"><div class="kpi-label">Llegaron tarde</div><div class="kpi-value text-amber-600" x-text="stats.late"></div></div>
            </div>
            <div class="card overflow-hidden">
                <div class="card-header">
                    <div class="flex flex-wrap items-center gap-3 text-xs text-gray-600">
                        <span class="flex items-center gap-1"><span class="h-3 w-3 rounded-full bg-emerald-600"></span>En sitio</span>
                        <span class="flex items-center gap-1"><span class="h-3 w-3 rounded-full bg-gray-500"></span>Salida registrada</span>
                        <span class="flex items-center gap-1"><span class="h-3 w-3 rounded-full bg-red-600"></span>Fuera de zona</span>
                        <span class="flex items-center gap-1"><span class="h-3 w-3 rounded-full border-2 border-indigo-600"></span>Geocerca</span>
                    </div>
                    <div class="flex items-center gap-2 text-xs text-gray-500">
                        <span x-show="generatedAt">Actualizado <span x-text="generatedAt"></span> · próxima en <span x-text="countdown"></span> s</span>
                        <button type="button" class="btn btn-secondary btn-sm" @click="load(false)" :disabled="loading"><x-icon name="refresh" class="h-3.5 w-3.5" /> Actualizar</button>
                    </div>
                </div>
                <div x-show="error" x-cloak class="bg-amber-50 px-4 py-2 text-sm text-amber-800" x-text="error"></div>
                <div x-ref="map" class="h-[60vh] min-h-[380px] w-full"></div>
            </div>
        </div>

        <div class="card flex max-h-[80vh] flex-col">
            <div class="card-header flex-col items-stretch">
                <h2 class="card-title">Marcaciones de hoy</h2>
                <input type="search" x-model="search" placeholder="Buscar empleado…" class="input mt-2">
                <div class="mt-2 flex flex-wrap gap-1 text-xs">
                    @foreach (['all' => 'Todos', 'on_site' => 'En sitio', 'out_of_zone' => 'Fuera de zona', 'late' => 'Tarde'] as $key => $label)
                        <button type="button" class="rounded-full px-2.5 py-1" :class="filter === '{{ $key }}' ? 'bg-indigo-600 text-white' : 'bg-gray-100 text-gray-700'" @click="filter = '{{ $key }}'">{{ $label }}</button>
                    @endforeach
                </div>
            </div>
            <ul class="flex-1 divide-y divide-gray-100 overflow-y-auto text-sm">
                <template x-for="p in visible" :key="p.id">
                    <li class="flex cursor-pointer items-center gap-3 px-4 py-2 hover:bg-gray-50" @click="focus(p)">
                        <template x-if="p.selfie"><img :src="p.selfie" class="h-9 w-9 rounded-full object-cover" alt=""></template>
                        <template x-if="!p.selfie"><span class="flex h-9 w-9 items-center justify-center rounded-full bg-indigo-100 text-xs font-semibold text-indigo-700" x-text="p.initials"></span></template>
                        <div class="min-w-0 flex-1">
                            <div class="truncate font-medium" x-text="p.name"></div>
                            <div class="truncate text-xs text-gray-500">
                                <span x-text="p.check_in"></span><span x-show="p.check_out"> – <span x-text="p.check_out"></span></span>
                                · <span x-text="p.site || 'sin sitio'"></span>
                            </div>
                        </div>
                        <span class="h-2.5 w-2.5 shrink-0 rounded-full" :class="p.out_of_zone ? 'bg-red-600' : (p.on_site ? 'bg-emerald-600' : 'bg-gray-400')"></span>
                    </li>
                </template>
                <li x-show="!visible.length" class="px-4 py-8 text-center text-gray-500">Sin marcaciones que coincidan.</li>
            </ul>
        </div>
    </div>
</x-app-layout>
