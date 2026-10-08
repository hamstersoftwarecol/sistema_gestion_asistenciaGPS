@props(['color' => 'gray'])
@php
    $colors = [
        'gray' => 'bg-gray-100 text-gray-700',
        'green' => 'bg-emerald-100 text-emerald-800',
        'yellow' => 'bg-amber-100 text-amber-800',
        'orange' => 'bg-orange-100 text-orange-800',
        'red' => 'bg-red-100 text-red-800',
        'blue' => 'bg-sky-100 text-sky-800',
        'purple' => 'bg-purple-100 text-purple-800',
        'indigo' => 'bg-indigo-100 text-indigo-800',
    ];
@endphp
<span {{ $attributes->merge(['class' => 'badge '.($colors[$color] ?? $colors['gray'])]) }}>{{ $slot }}</span>
