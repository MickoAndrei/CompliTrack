@props(['type' => 'error'])
@php
    $styles = [
        'error' => 'border-error/20 bg-error/5 text-error',
        'warning' => 'border-warning/30 bg-warning/10 text-ustp-dark',
        'success' => 'border-success/20 bg-success/5 text-success',
        'info' => 'border-info/20 bg-info/5 text-info',
    ];
@endphp
<div {{ $attributes->merge(['class' => 'rounded-xl border px-4 py-3 text-sm '.$styles[$type]]) }} role="alert">
    {{ $slot }}
</div>