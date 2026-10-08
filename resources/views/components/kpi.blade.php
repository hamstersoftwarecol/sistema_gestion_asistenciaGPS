@props(['label', 'value', 'hint' => null, 'tone' => 'gray'])
@php
    $accent = [
        'gray' => 'bg-gray-300', 'green' => 'bg-emerald-500', 'yellow' => 'bg-amber-400',
        'red' => 'bg-red-500', 'blue' => 'bg-sky-500', 'indigo' => 'bg-indigo-500', 'purple' => 'bg-purple-500',
    ][$tone] ?? 'bg-gray-300';
@endphp
<div {{ $attributes->merge(['class' => 'kpi relative overflow-hidden']) }}>
    <span class="absolute inset-y-0 left-0 w-1 {{ $accent }}"></span>
    <div class="kpi-label">{{ $label }}</div>
    <div class="kpi-value">{{ $value }}</div>
    @if ($hint)
        <div class="mt-0.5 text-xs text-gray-500">{{ $hint }}</div>
    @endif
</div>
