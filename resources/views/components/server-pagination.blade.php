@props(['paginator', 'showTotal' => true])
<nav class="app-pagination" aria-label="Record pages">
    @if($showTotal)<span class="app-pagination-total">{{ $paginator->total() }} {{ $paginator->total() === 1 ? 'record' : 'records' }}</span>@endif
    <x-server-page-size :paginator="$paginator" />
    @if($paginator->onFirstPage())<span class="app-pagination-button" aria-disabled="true">Previous</span>@else<a class="app-pagination-button" href="{{ $paginator->previousPageUrl() }}" rel="prev">Previous</a>@endif
    <span class="app-pagination-status">Page {{ $paginator->currentPage() }} of {{ $paginator->lastPage() }}</span>
    @if($paginator->hasMorePages())<a class="app-pagination-button" href="{{ $paginator->nextPageUrl() }}" rel="next">Next</a>@else<span class="app-pagination-button" aria-disabled="true">Next</span>@endif
</nav>
