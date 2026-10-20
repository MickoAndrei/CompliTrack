@props(['roleId'])
@php
    $roles = [
        0 => ['Super Admin', 'bg-ustp-gold text-ustp-navy'],
        1 => ['Admin', 'bg-info/10 text-info'],
        2 => ['Auditor', 'bg-ustp-purple/10 text-ustp-purple'],
        3 => ['Auditee', 'bg-ustp-gray/10 text-ustp-gray'],
    ];
    [$label, $classes] = $roles[(int) $roleId] ?? ['Unknown', 'bg-slate-100 text-ustp-muted'];
@endphp
<span {{ $attributes->class(['inline-flex items-center justify-center rounded-full px-3 py-2 text-xs font-semibold leading-5', $classes]) }} style="min-width: 6.25rem;">{{ $label }}</span>
