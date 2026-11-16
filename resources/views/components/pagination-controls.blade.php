@props(['label' => 'Record pages', 'previousAction' => 'load(page - 1)', 'nextAction' => 'load(page + 1)', 'pageExpression' => 'page', 'lastPageExpression' => 'meta.lastPage || 1'])
@once
    @push('styles')
        <link rel="stylesheet" href="{{ asset('css/pagination.css') }}?v={{ filemtime(public_path('css/pagination.css')) }}">
    @endpush
@endonce
<nav {{ $attributes->class(['app-pagination']) }} aria-label="{{ $label }}">
    <button type="button" class="app-pagination-button" @click="{{ $previousAction }}" :disabled="({{ $pageExpression }}) <= 1 || loading">Previous</button>
    <span class="app-pagination-status" aria-live="polite" x-text="'Page ' + ({{ $pageExpression }}) + ' of ' + ({{ $lastPageExpression }})"></span>
    <button type="button" class="app-pagination-button" @click="{{ $nextAction }}" :disabled="({{ $pageExpression }}) >= ({{ $lastPageExpression }}) || loading">Next</button>
</nav>
