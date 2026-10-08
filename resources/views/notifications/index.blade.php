<x-app-layout>
    <x-slot name="header">Notificaciones</x-slot>

    <div class="mb-4 flex justify-end">
        <form method="POST" action="{{ route('notifications.read-all') }}">
            @csrf
            <button class="btn btn-secondary">Marcar todas como leídas</button>
        </form>
    </div>

    <div class="card divide-y divide-gray-100">
        @forelse ($notifications as $n)
            @php($color = ['warning' => 'bg-amber-400', 'danger' => 'bg-red-500', 'success' => 'bg-emerald-500'][$n->data['level'] ?? 'info'] ?? 'bg-sky-500')
            <a href="{{ route('notifications.open', $n->id) }}" class="flex gap-3 px-5 py-3 hover:bg-gray-50 {{ $n->read_at ? 'opacity-70' : '' }}">
                <span class="mt-1.5 h-2.5 w-2.5 shrink-0 rounded-full {{ $color }}"></span>
                <div class="flex-1">
                    <div class="flex items-center justify-between gap-2">
                        <span class="font-medium {{ $n->read_at ? 'text-gray-700' : 'text-gray-900' }}">{{ $n->data['title'] ?? 'Notificación' }}</span>
                        <span class="text-xs text-gray-400">{{ $n->created_at->locale('es')->diffForHumans() }}</span>
                    </div>
                    <p class="text-sm text-gray-600">{{ $n->data['message'] ?? '' }}</p>
                </div>
            </a>
        @empty
            <p class="px-5 py-10 text-center text-sm text-gray-500">No tienes notificaciones.</p>
        @endforelse
    </div>
    <div class="mt-4">{{ $notifications->links() }}</div>
</x-app-layout>
