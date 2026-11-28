@props(['status', 'label' => null])
@php
    $normalized = strtolower(str_replace('_', ' ', (string) $status));
    $classes = match ($normalized) {
        'n', 'non compliant', 'nonconformity', 'non-conformance' => 'ct-outcome-n',
        'c', 'compliant' => 'ct-outcome-c',
        'o', 'observation' => 'ct-outcome-o',
        'open' => 'bg-error/10 text-error',
        'in progress' => 'bg-ustp-gold/40 text-ustp-dark',
        'closed' => 'bg-success/10 text-success',
        default => 'bg-slate-100 text-ustp-muted',
    };
@endphp
<span {{ $attributes->class(['inline-flex rounded-full px-2.5 py-1 text-xs font-semibold', $label === null ? 'capitalize' : '', $classes]) }}>{{ $label ?? (match ($normalized) { 'n' => 'N', 'c' => 'C', 'o' => 'O', default => $normalized ?: 'Unknown' }) }}</span>
