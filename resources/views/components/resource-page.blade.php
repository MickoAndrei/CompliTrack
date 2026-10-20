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
])
@php($roleId = (int) auth()->user()->roleId)
@php($searchLabel = match ($title) { 'User Management' => 'User', 'Archive Repository' => 'Archive', 'Audit Logs' => 'Audit Logs', default => strtolower($title) })
@php($panelClass = match ($roleId) { 0 => 'app-view-panel active', 2, 3 => 'view-panel active', default => '' })
<section class="{{ $panelClass }}" x-data="resourcePage({ endpoint: @js($endpoint), columns: @js($columns), perPage: {{ (int) $perPage }}, detailBase: @js($detailBase), allowStatusUpdates: {{ $allowStatusUpdates ? 'true' : 'false' }}, allowArchiveActions: {{ $allowArchiveActions ? 'true' : 'false' }}, allowNotificationActions: {{ $allowNotificationActions ? 'true' : 'false' }}, allowUserCreate: {{ $allowUserCreate ? 'true' : 'false' }}, allowUserActions: {{ $allowUserActions ? 'true' : 'false' }}, actorRoleId: {{ $actorRoleId === null ? 'null' : (int) $actorRoleId }}, allowDepartmentCreate: {{ $allowDepartmentCreate ? 'true' : 'false' }}, allowDepartmentActions: {{ $allowDepartmentActions ? 'true' : 'false' }}, allowBroadcast: {{ $allowBroadcast ? 'true' : 'false' }} })" x-init="load()">
    @php($toolbarClass = match ($roleId) { 0, 2 => 'table-filter-bar', 1 => 'toolbar', 3 => 'toolbar-row', default => 'toolbar' })
    @php($controlClass = match ($roleId) { 0, 2 => 'filter-left-controls', 1 => 'toolbar-left', 3 => '', default => 'toolbar-left' })
    <div class="{{ $toolbarClass }}">
        <div class="{{ $controlClass }}">
            <label class="{{ match ($roleId) { 0, 2 => 'search-input-wrapper', 1 => 'search-box', 3 => 'search-input-box', default => 'search-box' } }}">
                <i data-lucide="search" aria-hidden="true"></i>
                <span class="sr-only">Search {{ $searchLabel }}</span>
                <input type="search" x-model="search" @input.debounce.350ms="load(1)" placeholder="Search {{ $searchLabel }}" aria-label="Search {{ $searchLabel }}" class="{{ $roleId === 0 ? 'input-search-field' : '' }}">
            </label>
        </div>
        <button x-show="allowUserCreate" x-cloak type="button" @click="startUserCreate()" class="{{ $roleId === 1 ? 'btn-gold user-create-trigger' : 'btn btn-gold user-create-trigger' }}"><i data-lucide="user-round-plus" aria-hidden="true"></i><span>Create User</span></button>
        <button x-show="allowDepartmentCreate" x-cloak type="button" @click="startDepartmentCreate()" class="{{ $roleId === 1 ? 'btn-gold department-create-trigger' : 'btn btn-gold department-create-trigger' }}"><i data-lucide="building-2" aria-hidden="true"></i><span>Add Department</span></button>
        <button x-show="allowBroadcast" x-cloak type="button" @click="startBroadcast()" class="btn-gold"><i data-lucide="send" aria-hidden="true"></i><span>Broadcast</span></button>
    </div>
    <template x-if="loading">
        <div class="space-y-3 rounded-2xl bg-white p-5 shadow-sm">
            <div class="h-10 animate-pulse rounded-lg bg-slate-100"></div><div class="h-12 animate-pulse rounded-lg bg-slate-100"></div><div class="h-12 animate-pulse rounded-lg bg-slate-100"></div>
        </div>
    </template>
    <template x-if="error">
        <div class="rounded-2xl bg-white p-6 shadow-sm">
            <x-alert-banner type="error"><span x-text="error"></span></x-alert-banner>
            <button type="button" @click="load()" class="mt-4 rounded-xl bg-ustp-navy px-4 py-2 text-sm font-semibold text-white hover:bg-ustp-gold hover:text-ustp-navy">Retry</button>
        </div>
    </template>
    <template x-if="!loading && !error && rows.length === 0">
        <div class="{{ in_array($endpoint, ['archives', 'logs'], true) ? 'resource-empty-state resource-empty-state-centered' : 'resource-empty-state' }} rounded-2xl bg-white px-6 py-12 text-center shadow-sm">
            <span class="archive-empty-icon"><i data-lucide="inbox" aria-hidden="true"></i></span>
            <p class="archive-empty-title">No records yet</p>
            <p class="archive-empty-description">Records will appear here when available.</p>
        </div>
    </template>
    <div x-show="!loading && !error && rows.length > 0" x-cloak class="{{ in_array($roleId, [0, 3], true) ? 'content-card' : ($roleId === 1 ? 'table-card' : ($roleId === 2 ? 'card' : 'overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-slate-200/70')) }} {{ $roleId === 0 ? 'table-card-bordered' : '' }}">
        <div class="{{ match ($roleId) { 0 => 'responsive-table-wrapper', 2 => 'table-container', default => 'overflow-x-auto' } }}">
            <table class="min-w-full divide-y divide-slate-100 text-left text-sm {{ match ($roleId) { 0, 3 => 'data-table', 2 => 'compliance-table', default => '' } }}">
                <thead class="bg-slate-50 text-xs uppercase tracking-wide text-ustp-muted">
                    <tr><template x-for="column in columns" :key="column.key"><th scope="col" class="whitespace-nowrap px-5 py-3 font-semibold" x-text="column.label"></th></template><th x-show="allowArchiveActions || allowNotificationActions || allowUserActions || allowDepartmentActions" scope="col" class="px-5 py-3 font-semibold">Actions</th></tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <template x-for="row in rows" :key="row._id || row.id || JSON.stringify(row)">
                        <tr class="hover:bg-slate-50/70">
                            <template x-for="column in columns" :key="column.key">
                                <td class="whitespace-nowrap px-5 py-4 text-ustp-dark">
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
                                    <template x-if="column.key !== 'checkCode' && column.key !== 'roleId' && !['status', 'result', 'correctiveStatus'].includes(column.key)">
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
                            <td x-show="allowUserActions" class="whitespace-nowrap px-5 py-4">
                                <button type="button" @click="startUserEdit(row)" class="mr-3 text-xs font-semibold text-ustp-navy hover:underline">Edit</button>
                                <button x-show="row.isActive" type="button" @click="sendReset(row)" class="mr-3 text-xs font-semibold text-ustp-navy hover:underline">Reset password</button>
                                <button x-show="row.isActive" type="button" @click="deactivateUser(row)" class="text-xs font-semibold text-error hover:underline">Deactivate</button>
                            </td>
                            <td x-show="allowDepartmentActions" class="whitespace-nowrap px-5 py-4">
                                <button type="button" @click="startDepartmentEdit(row)" class="mr-3 text-xs font-semibold text-ustp-navy hover:underline">Edit</button>
                                <button x-show="row.isActive" type="button" @click="deactivateDepartment(row)" class="text-xs font-semibold text-error hover:underline">Deactivate</button>
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>
        <div class="flex flex-col gap-3 border-t border-slate-100 px-5 py-4 text-sm sm:flex-row sm:items-center sm:justify-between">
            <p class="text-ustp-muted"><span x-text="meta.total || 0"></span> records</p>
            <div class="flex items-center gap-2">
                <button type="button" @click="load(page - 1)" :disabled="page <= 1 || loading" class="rounded-lg border border-slate-200 px-3 py-2 disabled:opacity-40">Previous</button>
                <span class="text-ustp-muted">Page <span x-text="page"></span> of <span x-text="meta.lastPage || 1"></span></span>
                <button type="button" @click="load(page + 1)" :disabled="page >= (meta.lastPage || 1) || loading" class="rounded-lg border border-slate-200 px-3 py-2 disabled:opacity-40">Next</button>
            </div>
        </div>
    </div>
    <template x-if="message"><div class="mt-4"><x-alert-banner type="success"><span x-text="message"></span></x-alert-banner></div></template>
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
                    <label>College<input x-model="departmentForm.collegeName" :readonly="actorRoleId === 1" required maxlength="100" placeholder="Enter college name"></label>
                    <label>Campus<input x-model="departmentForm.campusName" required maxlength="100" placeholder="Enter campus name"></label>
                </div>
            </div>
            <footer class="user-modal-footer"><span>Changes apply to this department record.</span><div><button type="button" @click="departmentModalOpen = false" class="user-modal-cancel">Cancel</button><button type="submit" :disabled="saving" class="user-modal-submit"><i data-lucide="building-2" aria-hidden="true"></i><span x-text="saving ? 'Saving...' : (departmentForm._id ? 'Save changes' : 'Add Department')"></span></button></div></footer>
        </form>
    </div>
    <div x-cloak x-show="broadcastModalOpen" class="fixed inset-0 z-50 flex items-center justify-center overflow-y-auto bg-ustp-dark/50 p-4" @keydown.escape.window="broadcastModalOpen = false">
        <form @submit.prevent="sendBroadcast()" class="my-auto w-full max-w-lg space-y-4 rounded-2xl bg-white p-6 shadow-xl">
            <div class="flex items-start justify-between"><div><h3 class="font-heading text-xl font-bold text-ustp-navy">Broadcast notification</h3><p class="mt-1 text-sm text-ustp-muted">This message will be sent to auditees in the chosen department.</p></div><button type="button" @click="broadcastModalOpen = false" aria-label="Close" class="rounded-lg p-2 hover:bg-ustp-light"><i data-lucide="x" class="size-5"></i></button></div>
            <template x-if="error"><x-alert-banner type="error"><span x-text="error"></span></x-alert-banner></template>
            <label class="block text-sm font-medium">Department<select x-model="broadcastForm.departmentId" required class="mt-1.5 w-full rounded-xl border border-slate-200 px-3 py-2.5"><option value="">Choose a department</option><template x-for="department in departments" :key="department._id"><option :value="department._id" x-text="department.departmentName"></option></template></select></label>
            <label class="block text-sm font-medium">Message<textarea x-model="broadcastForm.message" required maxlength="2000" rows="5" class="mt-1.5 w-full rounded-xl border border-slate-200 px-3 py-2.5"></textarea></label>
            <div class="flex justify-end gap-3"><button type="button" @click="broadcastModalOpen = false" class="rounded-xl border border-slate-200 px-4 py-2.5 text-sm font-semibold">Cancel</button><button :disabled="saving" class="rounded-xl bg-ustp-navy px-4 py-2.5 text-sm font-semibold text-white disabled:opacity-60" x-text="saving ? 'Sending…' : 'Send broadcast'"></button></div>
        </form>
    </div>
</section>
