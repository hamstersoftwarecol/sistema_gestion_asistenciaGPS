@props([
    'name',
    'label' => null,
    'type' => 'text',
    'value' => null,
    'options' => [],
    'placeholder' => null,
    'help' => null,
    'required' => false,
    'empty' => null,
])
@php
    $id = $attributes->get('id', str_replace(['[', ']', '.'], ['_', '', '_'], $name));
    $dotName = trim(str_replace(['[', ']'], ['.', ''], $name), '.');
    $current = old($dotName, $value);
    if ($current instanceof \Carbon\CarbonInterface) {
        $current = $type === 'datetime-local' ? $current->format('Y-m-d\TH:i') : $current->format('Y-m-d');
    }
@endphp
<div {{ $attributes->only('class') }}>
    @if ($type === 'checkbox')
        <label for="{{ $id }}" class="inline-flex items-center gap-2 text-sm text-gray-700">
            <input type="hidden" name="{{ $name }}" value="0">
            <input id="{{ $id }}" type="checkbox" name="{{ $name }}" value="1" class="checkbox" @checked($current) {{ $attributes->except(['class', 'id']) }}>
            <span>{{ $label }}</span>
        </label>
    @else
        @if ($label)
            <label for="{{ $id }}" class="label">{{ $label }} @if ($required)<span class="text-red-500">*</span>@endif</label>
        @endif

        @if ($type === 'select')
            <select id="{{ $id }}" name="{{ $name }}" class="input" @required($required) {{ $attributes->except(['class', 'id']) }}>
                @if ($empty !== null)
                    <option value="">{{ $empty }}</option>
                @endif
                @foreach ($options as $optValue => $optLabel)
                    <option value="{{ $optValue }}" @selected((string) $current === (string) $optValue)>{{ $optLabel }}</option>
                @endforeach
            </select>
        @elseif ($type === 'textarea')
            <textarea id="{{ $id }}" name="{{ $name }}" rows="3" class="input" placeholder="{{ $placeholder }}" @required($required) {{ $attributes->except(['class', 'id']) }}>{{ $current }}</textarea>
        @else
            <input id="{{ $id }}" type="{{ $type }}" name="{{ $name }}" value="{{ $current }}" class="input" placeholder="{{ $placeholder }}" @required($required) {{ $attributes->except(['class', 'id']) }}>
        @endif
    @endif

    @if ($help)
        <p class="help">{{ $help }}</p>
    @endif
    @error($dotName)
        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
    @enderror
</div>
