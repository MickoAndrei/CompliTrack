@props(['workspace' => false])
<div {{ $attributes->class(['qms-workspace' => $workspace, 'qms-loading-skeleton']) }} role="status" aria-live="polite">
    <span class="qms-sr-only">Loading authorized archives…</span>
    @if ($workspace)
        <div class="qms-sidebar" aria-hidden="true">
            <span class="qms-skeleton qms-skeleton-title"></span>
            @foreach (range(1, 6) as $row)
                <span class="qms-skeleton qms-skeleton-nav"></span>
            @endforeach
        </div>
        <div class="qms-main">
            <div class="qms-skeleton-toolbar" aria-hidden="true">
                <span class="qms-skeleton"></span><span class="qms-skeleton"></span>
            </div>
            <div class="qms-folders" aria-hidden="true">
                @foreach (range(1, 6) as $folder)
                    <div class="qms-skeleton-folder">
                        <span class="qms-skeleton qms-skeleton-icon"></span>
                        <span class="qms-skeleton qms-skeleton-title"></span>
                        <span class="qms-skeleton qms-skeleton-line"></span>
                    </div>
                @endforeach
            </div>
    @endif
    <div class="qms-documents" aria-hidden="true">
        @foreach (range(1, 3) as $document)
            <div class="qms-skeleton-document">
                <span class="qms-skeleton qms-skeleton-badge"></span>
                <span class="qms-skeleton qms-skeleton-title"></span>
                @foreach (range(1, 4) as $line)
                    <span class="qms-skeleton qms-skeleton-line"></span>
                @endforeach
                <span class="qms-skeleton qms-skeleton-button"></span>
            </div>
        @endforeach
    </div>
    @if ($workspace)
        </div>
    @endif
</div>
