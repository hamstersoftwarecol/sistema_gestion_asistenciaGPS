@props(['status', 'map' => null])
@php
    $defaults = [
        'pending' => ['Pendiente', 'yellow'],
        'approved' => ['Aprobada', 'green'],
        'rejected' => ['Rechazada', 'red'],
        'cancelled' => ['Cancelada', 'gray'],
        'none' => ['—', 'gray'],
        'draft' => ['Borrador', 'gray'],
        'processed' => ['Generada', 'blue'],
        'locked' => ['Bloqueada', 'indigo'],
        'issued' => ['Emitida', 'blue'],
        'paid' => ['Pagada', 'green'],
        'active' => ['Activo', 'green'],
        'inactive' => ['Inactivo', 'gray'],
        'terminated' => ['Retirado', 'red'],
        'closed' => ['Pagado', 'gray'],
    ];
    foreach (\App\Models\Attendance::STATUSES as $key => [$label, $code, $color]) {
        $defaults[$key] = [$label, $color];
    }
    [$label, $color] = ($map[$status] ?? null) ?? $defaults[$status] ?? [$status, 'gray'];
@endphp
<x-badge :color="$color" {{ $attributes }}>{{ $label }}</x-badge>
