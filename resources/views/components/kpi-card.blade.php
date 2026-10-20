@props(['icon', 'label', 'value' => '—'])
<article {{ $attributes->merge(['class' => 'rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200/70']) }}>
    <div class="flex items-start justify-between gap-4">
        <div>
            <p class="text-sm text-ustp-muted">{{ $label }}</p>
            <p class="mt-2 font-heading text-3xl font-bold text-ustp-dark">{{ $value }}</p>
        </div>
        <span class="rounded-xl bg-ustp-navy/5 p-3 text-ustp-navy"><i data-lucide="{{ $icon }}" class="size-5"></i></span>
    </div>
</article>