@props(['href', 'active' => false, 'icon' => null, 'badge' => null])
<a href="{{ $href }}" @class([
    'group flex items-center gap-3 rounded-md px-3 py-2 text-sm font-medium transition',
    'bg-indigo-800 text-white' => $active,
    'text-indigo-200 hover:bg-indigo-900 hover:text-white' => ! $active,
])>
    @if ($icon)
        <x-icon :name="$icon" class="h-5 w-5 shrink-0 {{ $active ? 'text-white' : 'text-indigo-400 group-hover:text-white' }}" />
    @endif
    <span class="flex-1 truncate">{{ $slot }}</span>
    @if ($badge)
        <span class="rounded-full bg-amber-400 px-2 py-0.5 text-xs font-bold text-indigo-950">{{ $badge }}</span>
    @endif
</a>
