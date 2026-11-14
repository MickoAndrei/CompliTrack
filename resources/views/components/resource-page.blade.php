@props([
    'title',
    'description',
    'endpoint',
    'columns' => [],
    'perPage' => 10,
    'detailBase' => null,
    'allowStatusUpdates' => false,
    'allowArchiveActions' => false,
    'allowNotificationActions' => false,
    'allowUserCreate' => false,
    'allowUserActions' => false,
    'actorRoleId' => null,
    'allowDepartmentCreate' => false,
    'allowDepartmentActions' => false,
    'allowBroadcast' => false,
    'skeletonLoading' => false,
    'enhancedUsers' => false,
    'showHeading' => true,
])
@php($roleId = (int) auth()->user()->roleId)
@php($adminDirectory = $roleId === 1 && in_array($endpoint, ['users', 'departments'], true))
@php($directoryPage = in_array($roleId, [0, 1], true) && in_array($endpoint, ['users', 'departments'], true))
@php($visualRole = $roleId === 3 ? 2 : $roleId)
@php($searchLabel = match ($title) { 'User Management' => 'User', 'Archive Repository' => 'Archive', 'Audit Logs' => 'Audit Logs', default => strtolower($title) })
@php($panelClass = match ($visualRole) { 0, 2 => 'app-view-panel active', 3 => 'view-panel active', default => '' })
<section class="{{ $panelClass }} {{ $directoryPage ? 'attention-page directory-page' : '' }} {{ $endpoint === 'logs' ? 'audit-logs-page' : '' }} {{ $roleId === 1 ? 'admin-resource-page' : '' }}" x-data="resourcePage({ endpoint: @js($endpoint), directoryPage: @js($directoryPage), enhancedUsers: @js($enhancedUsers), columns: @js($columns), perPage: {{ (int) $perPage }}, detailBase: @js($detailBase), allowStatusUpdates: {{ $allowStatusUpdates ? 'true' : 'false' }}, allowArchiveActions: {{ $allowArchiveActions ? 'true' : 'false' }}, allowNotificationActions: {{ $allowNotificationActions ? 'true' : 'false' }}, allowUserCreate: {{ $allowUserCreate ? 'true' : 'false' }}, allowUserActions: {{ $allowUserActions ? 'true' : 'false' }}, actorRoleId: {{ $actorRoleId === null ? 'null' : (int) $actorRoleId }}, departmentColleges: @js($endpoint === 'departments' ? config('qms.campusColleges') : []), allowDepartmentCreate: {{ $allowDepartmentCreate ? 'true' : 'false' }}, allowDepartmentActions: {{ $allowDepartmentActions ? 'true' : 'false' }}, allowBroadcast: {{ $allowBroadcast ? 'true' : 'false' }} })" x-init="load()">
    @if($roleId === 1 && $showHeading && !$directoryPage)<header class="admin-resource-heading"><h2>{{ $title }}</h2><p>{{ $description }}</p></header>@endif
    @if ($enhancedUsers)
        @include('super-admin.partials.user-management')
    @else
    @if($directoryPage)
    <div class="attention-stats" aria-label="Directory summary for current filters">
        @foreach ([['total', $endpoint === 'users' ? 'Total Users' : 'Total Departments', $endpoint === 'users' ? 'users' : 'building-2', 'purple'], ['active', 'Active', 'circle-check', 'blue'], ['inactive', 'Inactive', 'circle-alert', 'danger']] as [$key, $label, $icon, $color])
            <article class="metric-stat-card"><div class="metric-info"><span class="metric-label">{{ $label }}</span><strong class="metric-number color-dark" x-text="summary?.{{ $key }} ?? '—'"></strong></div><div class="metric-icon-box {{ $color }}"><i data-lucide="{{ $icon }}" aria-hidden="true"></i></div></article>
        @endforeach
    </div>
    @endif
    <div x-page-notice="successPopupType" x-cloak x-show="successPopupVisible" x-transition class="fixed right-6 top-6 z-[100] flex min-h-[52px] w-[calc(100%-3rem)] max-w-sm items-center rounded-lg border-l-4 bg-ustp-navy pr-5 py-4 text-[13px] font-medium leading-5 text-white shadow-[0_10px_25px_rgba(5,27,68,0.18)]" :class="successPopupType === 'error' ? 'border-red-500' : 'border-ustp-gold'" style="padding-left: 24px" :role="successPopupType === 'error' ? 'alert' : 'status'" aria-live="polite">
        <span class="min-w-0 flex-1" x-text="successPopupMessage"></span>
    </div>
    @php($toolbarClass = match ($visualRole) { 0, 2 => 'table-filter-bar', 1 => 'toolbar', 3 => 'toolbar-row', default => 'toolbar' })
    @php($controlClass = match ($visualRole) { 0, 2 => 'filter-left-controls', 1 => 'toolbar-left', 3 => '', default => 'toolbar-left' })
    <div class="{{ $toolbarClass }} {{ $adminDirectory ? 'um-filters' : '' }}">
        <div class="{{ $controlClass }}">
            <label class="{{ match ($visualRole) { 0, 2 => 'search-input-wrapper', 1 => 'search-box', 3 => 'search-input-box', default => 'search-box' } }}">
                <i data-lucide="search" aria-hidden="true"></i>
                <span class="sr-only">Search {{ $searchLabel }}</span>
                <input type="search" x-model="search" @input.debounce.350ms="load(1)" placeholder="Search {{ $searchLabel }}" aria-label="Search {{ $searchLabel }}" class="{{ in_array($roleId, [0, 2], true) ? 'input-search-field' : '' }}">
            </label>
            @if($roleId === 1 && $endpoint === 'users')
            <label class="directory-sort"><span>Sort by</span><select x-model="sortBy" @change="load(1)" aria-label="Sort users by"><option value="fullName:asc">Name: A to Z</option><option value="fullName:desc">Name: Z to A</option><option value="createdAt:desc">Newest first</option><option value="createdAt:asc">Oldest first</option></select></label>
            @endif
            @if($endpoint === 'departments')
            <label class="flex items-center gap-2 text-xs"><span class="whitespace-nowrap">Sort by</span><select x-model="sortBy" @change="load(1)" aria-label="Sort departments by" class="std-select min-w-0 rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs"><option value="departmentName:asc">Department: A to Z</option><option value="departmentName:desc">Department: Z to A</option><option value="departmentCode:asc">Code: A to Z</option><option value="departmentCode:desc">Code: Z to A</option><option value="campusName:asc">Campus: A to Z</option><option value="campusName:desc">Campus: Z to A</option><option value="collegeName:asc">College: A to Z</option><option value="collegeName:desc">College: Z to A</option></select></label>
            @endif
            @if($roleId === 1 && $endpoint === 'notifications')
            <label class="admin-unread-filter"><input type="checkbox" x-model="unreadOnly" @change="load(1)">Unread only</label>
            <label class="admin-type-filter"><span class="sr-only">Notification type</span><select x-model="typeFilter" @change="load(1)" aria-label="Notification type"><option value="">All notification types</option>@foreach(['audit_assigned'=>'Audit assignments','audit_submitted'=>'Submitted audits','audit_returned'=>'Returned audits','audit_accepted'=>'Accepted audits','status_changed'=>'CAR updates','admin_broadcast'=>'Announcements'] as $value=>$label)<option value="{{ $value }}">{{ $label }}</option>@endforeach</select></label>
            @endif
            <label x-show="endpoint === 'logs'" x-cloak class="flex items-center gap-2 text-[12.5px] font-medium text-ustp-muted">
                <span>Sort by</span>
                <select x-model="sortBy" @change="load(1)" aria-label="Sort audit logs by" class="h-9 rounded-lg border border-slate-200 bg-white px-3 text-[12.5px] font-medium text-ustp-dark focus:border-ustp-navy focus:outline-none focus:ring-2 focus:ring-ustp-navy/10">
                    <option value="createdAt:desc">Date: Newest first</option>
                    <option value="createdAt:asc">Date: Oldest first</option>
                    <option value="action:asc">Action: A to Z</option>
                    <option value="action:desc">Action: Z to A</option>
                    <option value="userName:asc">User: A to Z</option>
                    <option value="userName:desc">User: Z to A</option>
                    <option value="entityType:asc">Record type: A to Z</option>
                    <option value="entityType:desc">Record type: Z to A</option>
                </select>
            </label>
        </div>
        @if ($endpoint === 'logs' && $roleId === 0)
        <fieldset class="audit-log-date-range">
            <legend>History range</legend>
            <label>From<input type="date" x-model="draftStartDate" aria-label="History start date"></label>
            <label>Through<input type="date" x-model="draftEndDate" :min="draftStartDate || null" aria-label="History end date"></label>
            <button type="button" @click="applyDateRange()" :disabled="loading">Apply</button>
            <button type="button" @click="clearDateRange()" :disabled="loading || (!startDate && !endDate && !draftStartDate && !draftEndDate)">Clear</button>
            <p x-page-notice x-show="dateRangeError" x-cloak role="alert" x-text="dateRangeError"></p>
        </fieldset>
        <button type="button" data-log-export @click="exportAuditLogs()" :disabled="exporting || loading" :aria-busy="exporting" class="btn btn-gold disabled:opacity-50 disabled:cursor-wait"><i data-lucide="download" aria-hidden="true"></i><span x-text="exporting ? 'Exporting…' : 'Export CSV'">Export CSV</span></button>
        @endif
        @if($allowUserCreate)
        <button x-show="allowUserCreate" x-cloak type="button" @click="startUserCreate()" class="{{ $roleId === 1 ? 'btn-gold user-create-trigger' : 'btn btn-gold user-create-trigger' }}"><i data-lucide="user-round-plus" aria-hidden="true"></i><span>Create User</span></button>
        @endif
        @if($allowDepartmentCreate)
        <button x-show="allowDepartmentCreate" x-cloak type="button" @click="startDepartmentCreate()" class="{{ $roleId === 1 ? 'btn-gold department-create-trigger' : 'btn btn-gold department-create-trigger' }}"><i data-lucide="building-2" aria-hidden="true"></i><span>Add Department</span></button>
        @endif
        <button x-show="allowBroadcast" x-cloak type="button" @click="startBroadcast()" class="btn-gold"><i data-lucide="send" aria-hidden="true"></i><span>Broadcast</span></button>
    </div>
    @if ($endpoint === 'logs' && $roleId === 0)
    <div x-show="exportError" x-cloak class="mb-4"><x-alert-banner type="error"><span x-text="exportError"></span></x-alert-banner></div>
    @endif
    <template x-if="loading">
        @if ($skeletonLoading)
        <div class="content-card table-card-bordered {{ $adminDirectory ? 'admin-directory-loading' : '' }}" aria-busy="true">
            <span class="sr-only" role="status">Loading {{ strtolower($title) }}...</span>
            @if ($endpoint === 'logs')
            <div class="audit-log-skeleton" aria-hidden="true"><div class="audit-log-skeleton-heading"></div><template x-for="row in perPage" :key="row"><div class="audit-log-skeleton-row"><span></span><span></span><span></span><span></span></div></template></div>
            @else
            <x-dashboard-skeleton type="table" :columns="count($columns) + (($allowDepartmentActions || $allowUserActions) ? 1 : 0)" :rows="$adminDirectory ? $perPage : 5" />
            @endif
        </div>
        @else
        <div class="space-y-3 rounded-2xl bg-white p-5 shadow-sm">
            <div class="h-10 animate-pulse rounded-lg bg-slate-100"></div><div class="h-12 animate-pulse rounded-lg bg-slate-100"></div><div class="h-12 animate-pulse rounded-lg bg-slate-100"></div>
        </div>
        @endif
    </template>
    <template x-if="error">
        <div x-page-notice="'error'" class="rounded-2xl bg-white p-6 shadow-sm">
            <x-alert-banner type="error"><span x-text="error"></span></x-alert-banner>
            <button type="button" @click="load()" class="mt-4 rounded-xl bg-ustp-navy px-4 py-2 text-sm font-semibold text-white hover:bg-ustp-gold hover:text-ustp-navy">Retry</button>
        </div>
    </template>
    <template x-if="!loading && !error && rows.length === 0">
        <div class="{{ in_array($endpoint, ['documents', 'logs'], true) ? 'resource-empty-state resource-empty-state-centered' : 'resource-empty-state' }} rounded-2xl bg-white px-6 py-12 text-center shadow-sm">
            <span class="archive-empty-icon"><i data-lucide="inbox" aria-hidden="true"></i></span>
            <p class="archive-empty-title">No records yet</p>
            <p class="archive-empty-description">Records will appear here when available.</p>
        </div>
    </template>
    <div x-show="!loading && !error && rows.length > 0" x-cloak class="{{ in_array($roleId, [0, 2, 3], true) ? 'content-card' : ($roleId === 1 ? 'table-card' : ($roleId === 2 ? 'card' : 'overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-slate-200/70')) }} {{ in_array($roleId, [0, 2], true) ? 'table-card-bordered' : '' }}">
        @if($roleId === 1 && $endpoint === 'notifications')
        <div class="admin-notification-list"><template x-for="row in rows" :key="row._id"><article class="admin-notification-card" :class="{'is-unread': !row.isRead}"><span class="admin-notification-icon" aria-hidden="true"><i data-lucide="bell"></i></span><div class="admin-notification-copy"><div class="atl-section-heading"><h3 x-text="row.title"></h3><span class="atl-status" x-text="row.isRead ? 'Read' : 'Unread'"></span></div><p x-text="row.message"></p><div class="admin-notification-meta"><span x-text="formatValue(row.type)"></span><time :datetime="row.createdAt" x-text="formatValue(row.createdAt)"></time></div><div class="admin-notification-actions"><button type="button" x-show="!row.isRead" @click="markRead(row)">Mark as read</button><a x-show="row.destination && row.destination.startsWith('/') && !row.destination.startsWith('//')" :href="row.destination">View related record</a></div></div></article></template></div>
        @else
        <div class="{{ match ($visualRole) { 0 => 'responsive-table-wrapper', 2 => 'table-container', default => 'overflow-x-auto' } }}">
            <table class="{{ $directoryPage ? 'directory-table' : '' }} {{ $adminDirectory ? 'um-table admin-directory-table' : '' }} min-w-full divide-y divide-slate-100 text-left text-sm {{ match ($visualRole) { 0, 3 => 'data-table', 2 => 'compliance-table', default => '' } }}">
                <thead class="bg-slate-50 text-xs uppercase tracking-wide text-ustp-muted">
                    <tr><template x-for="column in columns" :key="column.key"><th scope="col" class="whitespace-nowrap px-5 py-3 font-semibold" x-text="column.label"></th></template>@if(!$adminDirectory)<th x-show="allowArchiveActions || allowNotificationActions || allowUserActions || allowDepartmentActions" scope="col" class="px-5 py-3 font-semibold">Actions</th>@endif</tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <template x-for="row in rows" :key="row._id || row.id || JSON.stringify(row)">
                        <tr class="hover:bg-slate-50/70" :data-log-id="endpoint === 'logs' ? row._id : null" :style="endpoint === 'logs' && logHighlightId === String(row._id) ? 'background-color: #fef3c7; box-shadow: inset 4px 0 #f59e0b;' : ''" style="transition: background-color .35s ease, box-shadow .35s ease;">
                            <template x-for="column in columns" :key="column.key">
                                <td :data-label="column.label" class="whitespace-nowrap px-5 py-4 text-ustp-dark">
                                    <template x-if="column.key === 'checkCode' && detailBase">
                                        <a :href="`/${detailBase}/${encodeURIComponent(row._id)}`" class="font-semibold text-ustp-navy hover:underline" x-text="formatValue(row[column.key])"></a>
                                    </template>
                                    <template x-if="column.key === 'checkCode' && !detailBase">
                                        <span x-text="formatValue(row[column.key])"></span>
                                    </template>
                                    <template x-if="column.key === 'correctiveStatus' && allowStatusUpdates && row.correctiveStatus">
                                        <select :value="row.correctiveStatus" @change="updateStatus(row, $event.target)" class="rounded-lg border border-slate-200 bg-white px-2 py-1.5 text-xs font-semibold">
                                            <template x-for="option in statusOptions(row)" :key="option.value"><option :value="option.value" :disabled="option.disabled" x-text="option.label"></option></template>
                                        </select>
                                    </template>
                                    <template x-if="column.key === 'roleId'">
                                        <span class="user-role-pill rounded-full text-xs font-semibold" :class="roleClass(row.roleId)" style="min-width: 6.25rem; padding: .5rem .75rem; line-height: 1.25rem; justify-content: center;" x-text="roleName(row.roleId)"></span>
                                    </template>
                                    <template x-if="['status', 'result', 'correctiveStatus'].includes(column.key) && !(column.key === 'correctiveStatus' && allowStatusUpdates && row.correctiveStatus)">
                                        <span class="rounded-full px-2.5 py-1 text-xs font-semibold capitalize" :class="statusClass(row[column.key])" x-text="formatValue(row[column.key])"></span>
                                    </template>
                                    @if($directoryPage)
                                    <template x-if="column.key === 'isActive'"><span class="um-status directory-status" :class="{'is-inactive': !isRowActive(row)}" x-text="isRowActive(row) ? 'Active' : 'Inactive'"></span></template>
                                    @endif
                                    <template x-if="column.key !== 'checkCode' && column.key !== 'roleId' && {{ $directoryPage ? "column.key !== 'isActive' &&" : '' }} !['status', 'result', 'correctiveStatus'].includes(column.key)">
                                        <span x-text="formatValue(row[column.key])"></span>
                                    </template>
                                </td>
                            </template>
                            <td x-show="allowArchiveActions" class="whitespace-nowrap px-5 py-4">
                                <button type="button" @click="verifyArchive(row)" class="mr-3 text-xs font-semibold text-ustp-navy hover:underline">Verify</button>
                                <button type="button" @click="downloadArchive(row)" class="text-xs font-semibold text-ustp-navy hover:underline">Download</button>
                            </td>
                            <td x-show="allowNotificationActions" class="whitespace-nowrap px-5 py-4">
                                <button x-show="!row.isRead" type="button" @click="markRead(row)" class="text-xs font-semibold text-ustp-navy hover:underline">Mark read</button>
                                <span x-show="row.isRead" class="text-xs text-ustp-muted">Read</span>
                            </td>
                            @if($allowUserActions)
                            <td data-label="Actions" x-show="allowUserActions" class="whitespace-nowrap px-5 py-4">
                                <button type="button" @click="startUserEdit(row)" class="mr-3 text-xs font-semibold text-ustp-navy hover:underline">Edit</button>
                                <button x-show="isRowActive(row)" type="button" @click="sendReset(row)" class="mr-3 text-xs font-semibold text-ustp-navy hover:underline">Reset password</button>
                                <button x-show="isRowActive(row) && Number(row.roleId) !== 0" type="button" @click="deactivateUser(row)" class="text-xs font-semibold text-error hover:underline">Deactivate</button>
                                <button x-show="!isRowActive(row) && actorRoleId === 0" type="button" @click="reactivateUser(row)" class="text-xs font-semibold text-emerald-700 hover:underline">Reactivate</button>
                            </td>
                            @endif
                            @if($allowDepartmentActions)
                            <td data-label="Actions" x-show="allowDepartmentActions" class="whitespace-nowrap px-5 py-4">
                                <button type="button" @click="startDepartmentEdit(row)" class="mr-3 text-xs font-semibold text-ustp-navy hover:underline">Edit</button>
                                <button x-show="isRowActive(row)" type="button" @click="deactivateDepartment(row)" class="text-xs font-semibold text-error hover:underline">Deactivate</button>
                                <button x-show="!isRowActive(row) && actorRoleId === 0" type="button" @click="reactivateDepartment(row)" class="text-xs font-semibold text-emerald-700 hover:underline">Reactivate</button>
                            </td>
                            @endif
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>
        @endif
        <div class="flex flex-col gap-3 border-t border-slate-100 px-5 py-4 text-sm sm:flex-row sm:items-center sm:justify-between">
            <p class="text-ustp-muted"><span x-text="meta.total || 0"></span> records</p>
            <x-pagination-controls :label="$title.' pages'" />
        </div>
    </div>
    <template x-if="message"><div class="mt-4"><x-alert-banner type="success"><span x-text="message"></span></x-alert-banner></div></template>
    @if(!$adminDirectory)
    <div x-cloak x-show="userModalOpen" class="user-modal-overlay fixed inset-0 z-50 flex items-center justify-center overflow-y-auto bg-ustp-dark/50 p-4" @keydown.escape.window="userModalOpen = false" @click.self="userModalOpen = false" role="dialog" aria-modal="true" aria-labelledby="userModalTitle">
        <form @submit.prevent="saveUser()" class="user-modal-form my-auto w-full max-w-2xl rounded-2xl bg-white shadow-xl">
            <header class="user-modal-header">
                <span class="user-modal-heading-icon"><i data-lucide="user-round-plus" aria-hidden="true"></i></span>
                <div class="user-modal-heading-copy"><p>USER ACCOUNT</p><h3 id="userModalTitle" class="font-heading" x-text="userForm._id ? 'Edit user' : 'Create user'"></h3><span>Enter the account details and assign an access role.</span></div>
                <button type="button" @click="userModalOpen = false" aria-label="Close create user dialog" class="user-modal-close"><i data-lucide="x" aria-hidden="true"></i></button>
            </header>
            <div class="user-modal-body">
                <template x-if="error"><div class="user-modal-error"><x-alert-banner type="error"><span x-text="error"></span></x-alert-banner></div></template>
                <div class="user-modal-fields">
                    <label>Full name<input x-model="userForm.fullName" required maxlength="255" autocomplete="name" placeholder="Enter full name"></label>
                    <label>Email address<input x-model="userForm.email" type="email" required maxlength="255" autocomplete="email" placeholder="name@example.com"></label>
                    <label>Role
                        <select x-model.number="userForm.roleId" required>
                            <template x-if="actorRoleId === 0"><option value="0">Super Admin</option><option value="1">Admin</option></template>
                            <option value="2">Auditor</option><option value="3">Auditee</option>
                        </select>
                    </label>
                    <label x-show="userForm.roleId !== 0">Department
                        <select x-model="userForm.departmentId" :required="userForm.roleId !== 0">
                            <option value="">Choose a department</option><template x-for="department in departments" :key="department._id"><option :value="department._id" x-text="department.departmentName"></option></template>
                        </select>
                    </label>
                    <label x-show="userForm.roleId === 3" class="user-modal-wide-field">Office name<input x-model="userForm.officeName" :required="userForm.roleId === 3" maxlength="255" placeholder="Enter office name"></label>
                </div>
            </div>
            <footer class="user-modal-footer"><span>New users will receive an account setup email.</span><div><button type="button" @click="userModalOpen = false" class="user-modal-cancel">Cancel</button><button type="submit" :disabled="saving" class="user-modal-submit"><i data-lucide="user-round-plus" aria-hidden="true"></i><span x-text="saving ? 'Saving...' : (userForm._id ? 'Save changes' : 'Create User')"></span></button></div></footer>
        </form>
    </div>
    <div x-cloak x-show="departmentModalOpen" class="user-modal-overlay fixed inset-0 z-50 flex items-center justify-center overflow-y-auto bg-ustp-dark/50 p-4" @keydown.escape.window="departmentModalOpen = false" @click.self="departmentModalOpen = false" role="dialog" aria-modal="true" aria-labelledby="departmentModalTitle">
        <form @submit.prevent="saveDepartment()" class="department-modal-form my-auto w-full max-w-xl rounded-2xl bg-white shadow-xl">
            <header class="user-modal-header">
                <span class="user-modal-heading-icon department-modal-heading-icon"><i data-lucide="building-2" aria-hidden="true"></i></span>
                <div class="user-modal-heading-copy"><p>DEPARTMENT DETAILS</p><h3 id="departmentModalTitle" class="font-heading" x-text="departmentForm._id ? 'Edit department' : 'Add department'"></h3><span>Enter the department information for this campus.</span></div>
                <button type="button" @click="departmentModalOpen = false" aria-label="Close department dialog" class="user-modal-close"><i data-lucide="x" aria-hidden="true"></i></button>
            </header>
            <div class="user-modal-body">
                <template x-if="error"><div class="user-modal-error"><x-alert-banner type="error"><span x-text="error"></span></x-alert-banner></div></template>
                <div class="user-modal-fields">
                    <label>Department code<input x-model="departmentForm.departmentCode" :readonly="departmentForm._id !== null" required maxlength="30" placeholder="e.g. CED"></label>
                    <label>Department name<input x-model="departmentForm.departmentName" required maxlength="255" placeholder="Enter department name"></label>
                    <label>Campus<select x-model="departmentForm.campusName" @change="changeDepartmentCampus()" :disabled="actorRoleId === 1" required><option value="">Select a campus</option>@foreach(config('qms.campuses') as $campus)<option value="{{ $campus }}">USTP {{ $campus }}</option>@endforeach</select></label>
                    <label>College<select x-model="departmentForm.collegeName" :disabled="actorRoleId === 1 || !departmentCollegeOptions().length" :required="departmentCollegeOptions().length > 0" aria-describedby="department-college-note"><option value="" x-text="departmentCollegeOptions().length ? 'Select a college' : 'No published college list'"></option><template x-for="college in departmentCollegeOptions()" :key="college.value"><option :value="college.value" x-text="college.label"></option></template></select><small id="department-college-note" x-show="departmentForm.campusName && !departmentCollegeOptions().length">No named-college list is published on this campus's official page. College will remain unassigned.</small></label>
                </div>
            </div>
            <footer class="user-modal-footer"><span>Changes apply to this department record.</span><div><button type="button" @click="departmentModalOpen = false" class="user-modal-cancel">Cancel</button><button type="submit" :disabled="saving" class="user-modal-submit"><i data-lucide="building-2" aria-hidden="true"></i><span x-text="saving ? 'Saving...' : (departmentForm._id ? 'Save changes' : 'Add Department')"></span></button></div></footer>
        </form>
    </div>
    <div x-cloak x-show="broadcastModalOpen" class="fixed inset-0 z-50 flex items-center justify-center overflow-y-auto bg-ustp-dark/50 p-4" @keydown.escape.window="broadcastModalOpen = false">
        <form @submit.prevent="sendBroadcast()" class="my-auto w-full max-w-lg space-y-4 rounded-2xl bg-white p-6 shadow-xl">
            <div class="flex items-start justify-between"><div><h3 class="font-heading text-xl font-bold text-ustp-navy">Broadcast notification</h3><p class="mt-1 text-sm text-ustp-muted">This message will be sent to active auditors and auditees in the chosen department.</p></div><button type="button" @click="broadcastModalOpen = false" aria-label="Close" class="rounded-lg p-2 hover:bg-ustp-light"><i data-lucide="x" class="size-5"></i></button></div>
            <template x-if="error"><x-alert-banner type="error"><span x-text="error"></span></x-alert-banner></template>
            <label class="block text-sm font-medium">Department<select x-model="broadcastForm.departmentId" required class="mt-1.5 w-full rounded-xl border border-slate-200 px-3 py-2.5"><option value="">Choose a department</option><template x-for="department in departments" :key="department._id"><option :value="department._id" x-text="department.departmentName"></option></template></select></label>
            <label class="block text-sm font-medium">Message<textarea x-model="broadcastForm.message" required maxlength="2000" rows="5" class="mt-1.5 w-full rounded-xl border border-slate-200 px-3 py-2.5"></textarea></label>
            <div class="flex justify-end gap-3"><button type="button" @click="broadcastModalOpen = false" class="rounded-xl border border-slate-200 px-4 py-2.5 text-sm font-semibold">Cancel</button><button :disabled="saving" class="rounded-xl bg-ustp-navy px-4 py-2.5 text-sm font-semibold text-white disabled:opacity-60" x-text="saving ? 'Sending…' : 'Send broadcast'"></button></div>
        </form>
    </div>
    <div x-cloak x-show="resetConfirmOpen" class="user-modal-overlay fixed inset-0 z-50 flex items-center justify-center overflow-y-auto bg-ustp-dark/50 p-4" @keydown.escape.window="closeResetModals()" @click.self="closeResetModals()" role="dialog" aria-modal="true">
        <div class="my-auto w-full max-w-md rounded-2xl bg-white shadow-xl overflow-hidden">
            <header class="user-modal-header">
                <span class="user-modal-heading-icon">
                    <i data-lucide="mail-question" aria-hidden="true"></i>
                </span>
                <div class="user-modal-heading-copy">
                    <p>RESET PASSWORD</p>
                    <h3 class="font-heading">Send reset link?</h3>
                    <span>A reset link will be sent to this user.</span>
                </div>
                <button type="button" @click="closeResetModals()" aria-label="Close dialog" class="user-modal-close">
                    <i data-lucide="x" aria-hidden="true"></i>
                </button>
            </header>
            <div class="user-modal-body">
                <p class="text-sm text-ustp-muted">A password reset link will be sent to <span class="font-semibold text-ustp-dark" x-text="resetTarget?.email"></span> (<span x-text="resetTarget?.fullName"></span>).</p>
            </div>
            <footer class="user-modal-footer">
                <span>{{ $roleId === 1 ? 'The current password remains valid until the reset is completed.' : 'This will invalidate the current password.' }}</span>
                <div>
                    <button type="button" @click="closeResetModals()" class="user-modal-cancel">Cancel</button>
                    <button type="button" @click="confirmReset()" :disabled="resetSending" class="user-modal-submit">
                        <i data-lucide="send" aria-hidden="true"></i>
                        <span x-text="resetSending ? 'Sending...' : 'Yes, send link'"></span>
                    </button>
                </div>
            </footer>
        </div>
    </div>
    <div x-cloak x-show="deactivateConfirmOpen" class="user-modal-overlay fixed inset-0 z-50 flex items-center justify-center overflow-y-auto bg-ustp-dark/50 p-4" @keydown.escape.window="closeDeactivateModals()" @click.self="closeDeactivateModals()" role="dialog" aria-modal="true">
        <div class="my-auto w-full max-w-md rounded-2xl bg-white shadow-xl overflow-hidden">
            <header class="user-modal-header">
                <span class="user-modal-heading-icon" style="background-color: rgb(239 68 68 / 0.1); color: rgb(239 68 68);">
                    <i data-lucide="triangle-alert" aria-hidden="true"></i>
                </span>
                <div class="user-modal-heading-copy">
                    <p x-text="deactivateType === 'user' ? 'DEACTIVATE USER' : 'DEACTIVATE DEPARTMENT'"></p>
                    <h3 class="font-heading" x-text="deactivateType === 'user' ? 'Deactivate user?' : 'Deactivate department?'"></h3>
                    <span>{{ $roleId === 1 ? 'A Super Admin can reactivate the account or department.' : 'This action can be reversed by an admin.' }}</span>
                </div>
                <button type="button" @click="closeDeactivateModals()" aria-label="Close dialog" class="user-modal-close">
                    <i data-lucide="x" aria-hidden="true"></i>
                </button>
            </header>
            <div class="user-modal-body">
                <p class="text-sm text-ustp-muted" x-show="deactivateType === 'user'">Are you sure you want to deactivate <span class="font-semibold text-ustp-dark" x-text="deactivateTarget?.fullName"></span> (<span x-text="deactivateTarget?.email"></span>)? They will lose access.</p>
                <p class="text-sm text-ustp-muted" x-show="deactivateType === 'department'">Are you sure you want to deactivate department <span class="font-semibold text-ustp-dark" x-text="deactivateTarget?.departmentName"></span>? Users in this department will be affected.</p>
            </div>
            <footer class="user-modal-footer">
                <span>This cannot be undone without admin help.</span>
                <div>
                    <button type="button" @click="closeDeactivateModals()" class="user-modal-cancel">Cancel</button>
                    <button type="button" @click="confirmDeactivate()" :disabled="deactivateSending" class="rounded-xl bg-red-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-red-700 disabled:opacity-60" x-text="deactivateSending ? 'Deactivating...' : 'Yes, deactivate'"></button>
                </div>
            </footer>
        </div>
    </div>
    <div x-cloak x-show="reactivateConfirmOpen" class="user-modal-overlay fixed inset-0 z-50 flex items-center justify-center overflow-y-auto bg-ustp-dark/50 p-4" @keydown.escape.window="closeReactivateModals()" @click.self="closeReactivateModals()" role="dialog" aria-modal="true" aria-labelledby="reactivate-user-title">
        <div class="my-auto w-full max-w-md rounded-2xl bg-white shadow-xl overflow-hidden">
            <header class="user-modal-header">
                <span class="user-modal-heading-icon" style="background-color: rgb(34 197 94 / 0.1); color: rgb(5 150 105);"><i :data-lucide="reactivateType === 'department' ? 'building-2' : 'user-round-check'" aria-hidden="true"></i></span>
                <div class="user-modal-heading-copy"><p x-text="reactivateType === 'department' ? 'REACTIVATE DEPARTMENT' : 'REACTIVATE USER'"></p><h3 id="reactivate-user-title" class="font-heading" x-text="reactivateType === 'department' ? 'Reactivate department?' : 'Reactivate user?'"></h3><span x-text="reactivateType === 'department' ? 'The department will become active again.' : 'The user will be able to sign in again.'"></span></div>
                <button type="button" @click="closeReactivateModals()" aria-label="Close dialog" class="user-modal-close"><i data-lucide="x" aria-hidden="true"></i></button>
            </header>
            <div class="user-modal-body">
                <p class="text-sm text-ustp-muted">Are you sure you want to reactivate <span class="font-semibold text-ustp-dark" x-text="reactivateType === 'department' ? reactivateTarget?.departmentName : reactivateTarget?.fullName"></span><span x-show="reactivateType === 'user'"> (<span x-text="reactivateTarget?.email"></span>)</span>?</p>
            </div>
            <footer class="user-modal-footer"><span x-text="reactivateType === 'department' ? 'The department will be available again.' : 'This restores account access.'"></span><div><button type="button" @click="closeReactivateModals()" class="user-modal-cancel">Cancel</button><button type="button" @click="confirmReactivate()" :disabled="reactivateSending" class="rounded-xl bg-emerald-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-emerald-700 disabled:opacity-60" x-text="reactivateSending ? 'Reactivating...' : 'Yes, reactivate'"></button></div></footer>
        </div>
    </div>
    <div x-cloak x-show="reactivateErrorOpen" class="user-modal-overlay fixed inset-0 z-50 flex items-center justify-center overflow-y-auto bg-ustp-dark/50 p-4" @keydown.escape.window="closeReactivateModals()" @click.self="closeReactivateModals()" role="alertdialog" aria-modal="true" aria-labelledby="reactivate-error-title">
        <div class="my-auto w-full max-w-md rounded-2xl bg-white shadow-xl overflow-hidden">
            <header class="user-modal-header">
                <span class="user-modal-heading-icon" style="background-color: rgb(245 158 11 / 0.12); color: rgb(180 83 9);"><i data-lucide="triangle-alert" aria-hidden="true"></i></span>
                <div class="user-modal-heading-copy"><p>REACTIVATION BLOCKED</p><h3 id="reactivate-error-title" class="font-heading">Unable to reactivate yet</h3><span>Resolve this requirement, then try again.</span></div>
                <button type="button" @click="closeReactivateModals()" aria-label="Close dialog" class="user-modal-close"><i data-lucide="x" aria-hidden="true"></i></button>
            </header>
            <div class="user-modal-body"><p class="text-sm text-ustp-muted" x-text="reactivateError"></p></div>
            <footer class="user-modal-footer"><span>Resolve the issue above before trying again.</span><div><button type="button" @click="closeReactivateModals()" class="user-modal-submit">Got it</button></div></footer>
        </div>
    </div>
    @endif
    @endif
</section>
