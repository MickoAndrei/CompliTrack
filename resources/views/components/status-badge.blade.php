@props(['status'])
@php
    $normalized = strtolower(str_replace('_', ' ', (string) $status));
    $classes = match ($normalized) {
        'open', 'non compliant' => 'bg-error/10 text-error',
        'in progress' => 'bg-ustp-gold/40 text-ustp-dark',
        'closed', 'compliant' => 'bg-success/10 text-success',
        default => 'bg-slate-100 text-ustp-muted',
    };
@endphp
<span {{ $attributes->class(['inline-flex rounded-full px-2.5 py-1 text-xs font-semibold capitalize', $classes]) }}>{{ $normalized ?: 'Unknown' }}</span>