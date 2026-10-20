@props(['headers' => []])
<div {{ $attributes->merge(['class' => 'overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-slate-200/70']) }}>
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-slate-100 text-left text-sm">
            <thead class="bg-slate-50 text-xs uppercase tracking-wide text-ustp-muted">
                <tr>@foreach ($headers as $header)<th scope="col" class="whitespace-nowrap px-5 py-3 font-semibold">{{ $header }}</th>@endforeach</tr>
            </thead>
            <tbody class="divide-y divide-slate-100">{{ $slot }}</tbody>
        </table>
    </div>
</div>