<span class="qms-folder-row-owner" tabindex="0" :title="folder.ownerName || folder.createdByName || 'Not recorded'" x-text="folder.ownerName || folder.createdByName || '—'"></span>
<span class="qms-folder-row-date" x-text="folder.updatedAt || folder.createdAt ? date(folder.updatedAt || folder.createdAt) : '—'"></span>
<span class="qms-folder-row-size" aria-label="Folder size not calculated">—</span>
