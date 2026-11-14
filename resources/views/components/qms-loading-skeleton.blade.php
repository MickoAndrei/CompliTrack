@props(['workspace' => false])
<div {{ $attributes->class(['qms-workspace' => $workspace, 'qms-loading-skeleton']) }} role="status" aria-live="polite">
    <span class="qms-sr-only">Loading authorized documents…</span>
    @if ($workspace)
        <aside class="qms-sidebar qms-skeleton-sidebar" aria-hidden="true">
            <span class="qms-skeleton qms-skeleton-campus"></span>
            <span class="qms-skeleton qms-skeleton-caption"></span>
            <div class="qms-skeleton-view-nav">
                @foreach (range(1, 5) as $row)
                    <div class="qms-skeleton-nav-item"><span class="qms-skeleton qms-skeleton-nav-icon"></span><span class="qms-skeleton qms-skeleton-nav-label"></span></div>
                @endforeach
            </div>
            <div class="qms-skeleton-tree">
                <span class="qms-skeleton qms-skeleton-caption"></span><span class="qms-skeleton qms-skeleton-college"></span>
                @foreach (range(1, 6) as $row)
                    <div class="qms-skeleton-unit"><span class="qms-skeleton qms-skeleton-nav-icon"></span><span class="qms-skeleton qms-skeleton-nav-label"></span></div>
                @endforeach
            </div>
        </aside>
        <div class="qms-main" aria-hidden="true">
            <div class="qms-browser-heading qms-skeleton-heading"><div><span class="qms-skeleton qms-skeleton-eyebrow"></span><span class="qms-skeleton qms-skeleton-page-title"></span><span class="qms-skeleton qms-skeleton-subtitle"></span></div><span class="qms-skeleton qms-skeleton-layout"></span></div>
            <div class="qms-location-bar qms-skeleton-location"><div class="qms-navigation">@foreach (range(1, 4) as $arrow)<span class="qms-skeleton-arrow"><span class="qms-skeleton"></span></span>@endforeach</div><span class="qms-skeleton qms-skeleton-path"></span></div>
            <div class="qms-toolbar qms-skeleton-controls"><span class="qms-skeleton qms-skeleton-search"></span><span class="qms-skeleton qms-skeleton-filter"></span><span class="qms-skeleton qms-skeleton-sort"></span><span class="qms-skeleton qms-skeleton-count"></span></div>
            <div class="qms-category-header"><span class="qms-skeleton qms-skeleton-section-title"></span><span class="qms-skeleton qms-skeleton-count"></span></div>
            <div class="qms-folders" aria-hidden="true">
                @foreach (range(1, 6) as $folder)
                    <div class="qms-skeleton-folder">
                        <span class="qms-skeleton qms-skeleton-icon"></span>
                        <span class="qms-skeleton qms-skeleton-title"></span>
                        <span class="qms-skeleton qms-skeleton-line"></span>
                    </div>
                @endforeach
            </div>
            <div class="qms-doc-header"><span class="qms-skeleton qms-skeleton-section-title"></span></div>
    @endif
    <div class="qms-skeleton-files" :class="{'is-list': layout === 'list'}" aria-hidden="true">
        <div class="qms-skeleton-list-head"><span>Name</span><span>Owner</span><span>Modified</span><span>Type</span><span></span></div>
        <div class="qms-skeleton-records">
        @foreach (range(1, 3) as $document)
            <div class="qms-skeleton-record">
                <div class="qms-skeleton-file-name"><span class="qms-skeleton qms-skeleton-file-icon"></span><div><span class="qms-skeleton qms-skeleton-file-title"></span><span class="qms-skeleton qms-skeleton-file-code"></span></div></div>
                <div class="qms-skeleton-owner"><span class="qms-skeleton qms-skeleton-line"></span><span class="qms-skeleton qms-skeleton-file-code"></span></div>
                <div class="qms-skeleton-date"><span class="qms-skeleton qms-skeleton-line"></span><span class="qms-skeleton qms-skeleton-file-code"></span></div>
                <span class="qms-skeleton qms-skeleton-file-type"></span><span class="qms-skeleton qms-skeleton-menu"></span>
                <div class="qms-skeleton-card-actions"><span class="qms-skeleton"></span><span class="qms-skeleton"></span></div>
            </div>
        @endforeach
        </div>
    </div>
    @if ($workspace)
            <div class="qms-access-note"><span class="qms-skeleton qms-skeleton-nav-icon"></span><div class="qms-skeleton-access"><span class="qms-skeleton qms-skeleton-section-title"></span><span class="qms-skeleton qms-skeleton-line"></span></div></div>
        </div>
    @endif
</div>
