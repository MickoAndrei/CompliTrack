@props(['value' => 0, 'label' => 'Compliance rate'])
@php($percentage = min(100, max(0, (float) $value)))
<div {{ $attributes->merge(['class' => 'flex items-center gap-4']) }}>
    <div class="relative grid size-24 shrink-0 place-items-center rounded-full" style="background: conic-gradient(var(--color-ustp-navy) {{ $percentage }}%, var(--color-slate-200) 0)">
        <div class="grid size-[4.5rem] place-items-center rounded-full bg-white"><span class="font-heading text-xl font-bold text-ustp-navy">{{ number_format($percentage, 0) }}%</span></div>
    </div>
    <div><p class="font-semibold text-ustp-dark">{{ $label }}</p><p class="mt-1 text-sm text-ustp-muted">Compliance performance</p></div>
</div>