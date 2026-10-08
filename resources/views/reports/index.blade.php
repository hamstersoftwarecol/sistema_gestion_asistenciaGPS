<x-app-layout>
    <x-slot name="header">Informes de RR. HH.</x-slot>

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
        @foreach ($reports as $key => [$title, $description])
            <a href="{{ route('reports.show', $key) }}" class="card group block p-5 transition hover:border-indigo-300 hover:shadow">
                <div class="flex items-center gap-3">
                    <span class="flex h-10 w-10 items-center justify-center rounded-lg bg-indigo-50 text-indigo-600 group-hover:bg-indigo-600 group-hover:text-white"><x-icon name="chart" /></span>
                    <h2 class="font-semibold text-gray-900">{{ $title }}</h2>
                </div>
                <p class="mt-2 text-sm text-gray-500">{{ $description }}</p>
                <p class="mt-3 text-xs font-medium text-indigo-600">Ver y exportar CSV →</p>
            </a>
        @endforeach
    </div>
</x-app-layout>
