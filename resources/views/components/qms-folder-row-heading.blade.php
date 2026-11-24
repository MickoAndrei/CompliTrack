@props(['visible' => 'true'])
<div class="qms-folder-row-heading" x-show="hasVisibleFolders && ({{ $visible }}) && layout === 'list'">
    <button type="button" @click="folderSortDescending = !folderSortDescending; folderPage = 1" :aria-label="folderSortDescending ? 'Sort folders by name ascending' : 'Sort folders by name descending'">Name <span aria-hidden="true" x-text="folderSortDescending ? '↓' : '↑'"></span></button>
    <span>Owner</span><span>Date modified</span><span>File size</span><span></span>
</div>
