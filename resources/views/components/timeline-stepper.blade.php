@props(['history' => []])
<ol {{ $attributes->merge(['class' => 'space-y-5']) }}>
    @forelse ($history as $item)
        @php
            $status = str_replace('_', ' ', $item['status'] ?? 'update');
            $isClosed = ($item['status'] ?? null) === 'closed';
        @endphp
        <li class="relative flex gap-4">
            @if (!$loop->last)<span class="absolute bottom-0 left-3 top-7 w-px bg-slate-200"></span>@endif
            <span class="relative z-10 flex size-6 shrink-0 items-center justify-center rounded-full {{ $isClosed ? 'bg-success text-white' : 'bg-ustp-gold text-ustp-navy' }}"><i data-lucide="{{ $isClosed ? 'check' : 'clock-3' }}" class="size-3.5"></i></span>
            <div class="min-w-0 pb-4"><p class="font-semibold capitalize text-ustp-dark">{{ $status }}</p><p class="mt-1 text-sm text-ustp-muted">{{ $item['remarks'] ?? '' }}</p><time class="mt-1 block text-xs text-ustp-muted">{{ isset($item['timestamp']) ? \Illuminate\Support\Carbon::parse($item['timestamp'])->format('M j, Y g:i A') : '' }}</time></div>
        </li>
    @empty
        <li class="text-sm text-ustp-muted">No status history yet.</li>
    @endforelse
</ol>