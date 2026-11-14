<nav class="attention-pagination" aria-label="{{ $label }}" x-cloak x-show="!loading && !error && meta.total > 0">
    <span x-text="`${meta.total} record${meta.total === 1 ? '' : 's'} · Page ${page} of ${meta.lastPage || 1}`"></span><div><button type="button" class="attention-button" @click="load(page - 1)" :disabled="loading || page <= 1">Previous</button><button type="button" class="attention-button" @click="load(page + 1)" :disabled="loading || page >= meta.lastPage">Next</button></div>
</nav>
