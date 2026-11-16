@props(['workspace' => false])
<div {{ $attributes->class(['qms-workspace' => $workspace, 'qms-loading-skeleton']) }} role="status" aria-live="polite">
    <span class="qms-sr-only">Loading authorized content&hellip;</span>
    @if ($workspace)
        <aside class="qms-sidebar qms-skeleton-sidebar" aria-hidden="true">
            <span class="qms-skeleton qms-skeleton-mobile-toggle"></span>
            <span class="qms-skeleton qms-skeleton-caption"></span>
            <div class="qms-skeleton-view-nav">
                @foreach (range(1, 7) as $row)
                    <div class="qms-skeleton-nav-item"><span class="qms-skeleton qms-skeleton-nav-icon"></span><span class="qms-skeleton qms-skeleton-nav-label"></span></div>
                @endforeach
            </div>
            <div class="qms-skeleton-tree" x-show="(tree.repositoryFolders || []).length">
                <span class="qms-skeleton qms-skeleton-caption"></span>
                @foreach (range(1, 3) as $row)
                    <div class="qms-skeleton-unit"><span class="qms-skeleton qms-skeleton-nav-icon"></span><span class="qms-skeleton qms-skeleton-nav-label"></span></div>
                @endforeach
            </div>
            <div class="qms-sidebar-footer"><span class="qms-skeleton qms-skeleton-summary-line"></span><span class="qms-skeleton qms-skeleton-summary-line"></span></div>
        </aside>
        <div class="qms-main" aria-hidden="true">
            <div class="qms-location-bar qms-skeleton-location"><div class="qms-navigation">@foreach (range(1, 4) as $arrow)<span class="qms-skeleton-arrow"><span class="qms-skeleton"></span></span>@endforeach</div><span class="qms-skeleton qms-skeleton-path"></span><span class="qms-skeleton qms-location-search qms-skeleton-address-search"></span></div>
            <div class="qms-browser-controls qms-skeleton-browser-controls"><div class="qms-skeleton-create-actions"><span class="qms-skeleton qms-skeleton-new-folder"></span><span class="qms-skeleton qms-skeleton-upload-button"></span></div><span class="qms-skeleton qms-skeleton-layout"></span></div>
            <div class="qms-skeleton-folder-browser" x-show="!['shared','recent'].includes(currentView)">
                <div class="qms-category-header"><span class="qms-skeleton qms-skeleton-section-title"></span></div>
                <div class="qms-folders" :class="{'qms-folder-grid':layout === 'grid'}">
                    <div class="qms-folder-row-heading qms-skeleton-folder-head" x-show="layout === 'list'"><span>Name</span><span>Owner</span><span>Date modified</span><span>File size</span><span></span></div>
                    @foreach (range(1, 6) as $folder)
                        <div class="qms-skeleton-folder"><span class="qms-skeleton qms-skeleton-icon"></span><span class="qms-skeleton-folder-name"><span class="qms-skeleton qms-skeleton-title"></span></span><span class="qms-skeleton qms-skeleton-folder-owner"></span><span class="qms-skeleton qms-skeleton-folder-date"></span><span class="qms-skeleton qms-skeleton-folder-size"></span><span class="qms-skeleton qms-skeleton-folder-menu"></span></div>
                    @endforeach
                </div>
            </div>
    @endif
    <div class="qms-skeleton-files" :class="{'is-list': layout === 'list'}" @if ($workspace) x-show="['shared','recent'].includes(currentView)" @endif aria-hidden="true">
        @if ($workspace)<div class="qms-filter-row"><span class="qms-skeleton qms-skeleton-filter"></span><span class="qms-skeleton qms-skeleton-sort"></span></div>@endif
        <div class="qms-skeleton-list-head" x-show="!hasVisibleFolders"><span>Name</span><span>Owner</span><span>Date modified</span><span>File size</span><span></span></div>
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
    @if ($workspace)</div>@endif
</div>
