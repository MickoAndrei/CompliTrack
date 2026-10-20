@props(['accept' => '.pdf,.docx,.png,.jpg,.jpeg'])
<label {{ $attributes->merge(['class' => 'block cursor-pointer rounded-2xl border-2 border-dashed border-slate-300 bg-white p-8 text-center transition hover:border-ustp-navy']) }}>
    <input type="file" accept="{{ $accept }}" class="sr-only" {{ $attributes->whereStartsWith('wire:model') }}>
    <i data-lucide="upload-cloud" class="mx-auto size-8 text-ustp-navy"></i>
    <span class="mt-3 block font-semibold text-ustp-dark">Choose a file or drag it here</span>
    <span class="mt-1 block text-sm text-ustp-muted">PDF, DOCX, PNG, or JPG · maximum 20 MB</span>
    <span class="mt-4 inline-flex items-center gap-2 text-xs text-ustp-muted"><i data-lucide="lock-keyhole" class="size-4"></i>File will be encrypted with AES-256-GCM</span>
    {{ $slot }}
</label>