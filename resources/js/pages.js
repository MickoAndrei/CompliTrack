const apiPath = (endpoint) => `/api/v1/${endpoint.replace(/^\/+/, '')}`;

function responseData(response) {
    return response.data?.data ?? response.data ?? [];
}

function responseMessage(error, fallback) {
    return error.response?.data?.message || fallback;
}

document.addEventListener('alpine:init', () => {
    Alpine.data('auditeeNotifications', () => ({
        open: false,
        loading: false,
        markingRead: false,
        error: null,
        notifications: [],
        refreshTimer: null,
        get unreadCount() {
            return this.notifications.filter((notification) => !notification.isRead).length;
        },
        async toggle() {
            this.open = !this.open;
            if (this.open) {
                await this.refresh();
                this.refreshTimer = window.setInterval(() => this.refresh(), 20000);
            } else {
                this.stopRefreshing();
            }
        },
        close() {
            this.open = false;
            this.stopRefreshing();
        },
        stopRefreshing() {
            if (this.refreshTimer) window.clearInterval(this.refreshTimer);
            this.refreshTimer = null;
        },
        async refresh() {
            this.loading = true;
            this.error = null;
            try {
                const { data } = await axios.get(apiPath('notifications'), { params: { perPage: 8 } });
                this.notifications = data.data || [];
                this.$nextTick(() => window.renderIcons?.());
            } catch (error) {
                this.error = responseMessage(error, 'Unable to load notifications.');
            } finally {
                this.loading = false;
            }
        },
        async markAllRead() {
            if (this.unreadCount === 0 || this.markingRead) return;
            this.markingRead = true;
            this.error = null;
            try {
                await axios.patch(apiPath('notifications/read-all'));
                this.notifications = this.notifications.map((notification) => ({ ...notification, isRead: true }));
            } catch (error) {
                this.error = responseMessage(error, 'Unable to mark notifications as read.');
            } finally {
                this.markingRead = false;
            }
        },
        async markNotificationRead(notification) {
            if (notification.isRead) return;
            try {
                await axios.patch(apiPath(`notifications/${encodeURIComponent(notification._id)}/read`));
                notification.isRead = true;
            } catch (error) {
                this.error = responseMessage(error, 'Unable to mark this notification as read.');
            }
        },
        async clearNotifications() {
            if (this.loading || this.markingRead) return;
            this.markingRead = true;
            this.error = null;
            try {
                if (this.unreadCount > 0) await axios.patch(apiPath('notifications/read-all'));
                this.notifications = [];
            } catch (error) {
                this.error = responseMessage(error, 'Unable to clear notifications.');
            } finally {
                this.markingRead = false;
            }
        },
        formatActivityTime(value) {
            const date = value ? new Date(value) : null;
            if (!date || Number.isNaN(date.getTime())) return 'Time unavailable';
            const seconds = Math.round((date.getTime() - Date.now()) / 1000);
            const absSeconds = Math.abs(seconds);
            const [amount, unit] = absSeconds < 60 ? [seconds, 'second']
                : absSeconds < 3600 ? [Math.round(seconds / 60), 'minute']
                    : absSeconds < 86400 ? [Math.round(seconds / 3600), 'hour']
                        : [Math.round(seconds / 86400), 'day'];
            return new Intl.RelativeTimeFormat(undefined, { numeric: 'auto' }).format(amount, unit);
        },
        destroy() {
            this.stopRefreshing();
        },
    }));

    Alpine.data('activityNotifications', () => ({
        open: false,
        loading: false,
        markingRead: false,
        error: null,
        activities: [],
        notifications: [],
        dismissedActivityIds: (() => {
            try {
                return JSON.parse(window.localStorage.getItem('complitrack.dismissedActivities') || '[]');
            } catch {
                return [];
            }
        })(),
        readActivityIds: (() => {
            try {
                return JSON.parse(window.localStorage.getItem('complitrack.readActivities') || '[]');
            } catch {
                return [];
            }
        })(),
        refreshTimer: null,
        get unreadCount() {
            return this.notifications.filter((notification) => !notification.isRead).length;
        },
        async toggle() {
            this.open = !this.open;
            if (this.open) {
                this.refreshTimer = window.setInterval(() => this.refresh(), 20000);
                await this.refresh();
            } else {
                this.stopRefreshing();
            }
        },
        close() {
            this.open = false;
            this.stopRefreshing();
        },
        stopRefreshing() {
            if (this.refreshTimer) window.clearInterval(this.refreshTimer);
            this.refreshTimer = null;
        },
        async refresh() {
            this.loading = true;
            this.error = null;
            try {
                const [logsResponse, notificationsResponse] = await Promise.all([
                    axios.get(apiPath('logs'), { params: { perPage: 8 } }),
                    axios.get(apiPath('notifications'), { params: { perPage: 8 } }),
                ]);
                this.activities = (logsResponse.data.data || []).filter((activity) => !this.dismissedActivityIds.includes(String(activity._id)));
                this.notifications = (notificationsResponse.data.data || []).filter((notification) => !notification.isRead);
                this.$nextTick(() => window.renderIcons?.());
            } catch (error) {
                this.error = responseMessage(error, 'Unable to load recent activity.');
            } finally {
                this.loading = false;
            }
        },
        async markAllRead() {
            if (this.unreadCount === 0 || this.markingRead) return;
            this.markingRead = true;
            try {
                await axios.patch(apiPath('notifications/read-all'));
                this.notifications = this.notifications.map((notification) => ({ ...notification, isRead: true }));
            } catch (error) {
                this.error = responseMessage(error, 'Unable to mark notifications as read.');
            } finally {
                this.markingRead = false;
            }
        },
        async markNotificationRead(notification) {
            if (notification.isRead) return;
            try {
                await axios.patch(apiPath(`notifications/${encodeURIComponent(notification._id)}/read`));
                notification.isRead = true;
            } catch (error) {
                this.error = responseMessage(error, 'Unable to mark this notification as read.');
            }
        },
        async clearNotifications() {
            if (this.loading || this.markingRead) return;
            this.markingRead = true;
            this.error = null;
            try {
                if (this.unreadCount > 0) await axios.patch(apiPath('notifications/read-all'));
                this.dismissedActivityIds = [...new Set([
                    ...this.dismissedActivityIds,
                    ...this.activities.map((activity) => String(activity._id)),
                ])];
                window.localStorage.setItem('complitrack.dismissedActivities', JSON.stringify(this.dismissedActivityIds));
                this.activities = [];
                this.notifications = [];
            } catch (error) {
                this.error = responseMessage(error, 'Unable to clear notifications.');
            } finally {
                this.markingRead = false;
            }
        },
        isActivityRead(activity) {
            return this.readActivityIds.includes(String(activity._id));
        },
        async openActivity(activity) {
            const id = String(activity._id);
            if (!this.readActivityIds.includes(id)) {
                this.readActivityIds = [...this.readActivityIds, id];
                window.localStorage.setItem('complitrack.readActivities', JSON.stringify(this.readActivityIds));
            }
            await new Promise((resolve) => window.setTimeout(resolve, 220));
            window.location.assign(`/super-admin/logs?highlight=${encodeURIComponent(id)}`);
        },
        formatActivityTime(value) {
            const date = value ? new Date(value) : null;
            if (!date || Number.isNaN(date.getTime())) return 'Time unavailable';
            const seconds = Math.round((date.getTime() - Date.now()) / 1000);
            const absSeconds = Math.abs(seconds);
            const [amount, unit] = absSeconds < 60 ? [seconds, 'second']
                : absSeconds < 3600 ? [Math.round(seconds / 60), 'minute']
                    : absSeconds < 86400 ? [Math.round(seconds / 3600), 'hour']
                        : [Math.round(seconds / 86400), 'day'];
            return new Intl.RelativeTimeFormat(undefined, { numeric: 'auto' }).format(amount, unit);
        },
        destroy() {
            this.stopRefreshing();
        },
    }));

    Alpine.data('profilePage', ({ avatarUrl }) => ({
        avatarUrl: avatarUrl || '',
        uploading: false,
        error: null,
        message: null,
        popupTimeout: null,
        showPopup(type, text) {
            window.clearTimeout(this.popupTimeout);
            this.error = type === 'error' ? text : null;
            this.message = type === 'success' ? text : null;
            this.popupTimeout = window.setTimeout(() => {
                this.error = null;
                this.message = null;
            }, 3000);
        },
        async uploadAvatar(event) {
            const file = event.target.files?.[0];
            if (!file) return;
            this.error = null;
            this.message = null;
            this.uploading = true;
            const form = new FormData();
            form.append('avatar', file);
            try {
                const { data } = await axios.post(apiPath('auth/avatar'), form);
                this.avatarUrl = `${data.avatarUrl}${data.avatarUrl.includes('?') ? '&' : '?'}v=${Date.now()}`;
                document.querySelectorAll('[data-user-avatar]').forEach((image) => {
                    image.src = this.avatarUrl;
                    image.classList.remove('hidden');
                });
                document.querySelectorAll('[data-user-avatar-fallback]').forEach((fallback) => fallback.classList.add('hidden'));
                this.showPopup('success', 'Profile photo updated.');
            } catch (error) {
                this.showPopup('error', responseMessage(error, 'Profile photo could not be uploaded. Choose a JPG, PNG, or WebP image up to 2 MB.'));
            } finally {
                this.uploading = false;
                event.target.value = '';
            }
        },
        destroy() {
            window.clearTimeout(this.popupTimeout);
        },
    }));

    Alpine.data('loginPage', () => ({
        email: '',
        password: '',
        remember: false,
        loading: false,
        error: null,
        async submit() {
            this.loading = true;
            this.error = null;
            try {
                await axios.get('/sanctum/csrf-cookie');
                const { data } = await axios.post(apiPath('auth/login'), {
                    email: this.email,
                    password: this.password,
                    remember: this.remember,
                });
                const roleId = Number(data.user?.roleId);
                const destinations = ['/super-admin/dashboard', '/admin/dashboard', '/auditor/dashboard', '/auditee/dashboard'];
                if (!Number.isInteger(roleId) || !destinations[roleId]) {
                    throw new Error('This account does not have a valid CompliTrack role.');
                }
                window.location.assign(destinations[roleId]);
            } catch (error) {
                this.error = responseMessage(error, 'Unable to sign in. Check your credentials and try again.');
            } finally {
                this.loading = false;
            }
        },
    }));

    Alpine.data('resourcePage', (config) => ({
        endpoint: config.endpoint,
        columns: config.columns,
        perPage: config.perPage || 10,
        detailBase: config.detailBase,
        allowStatusUpdates: config.allowStatusUpdates,
        allowArchiveActions: config.allowArchiveActions,
        allowNotificationActions: config.allowNotificationActions,
        allowUserCreate: config.allowUserCreate,
        allowUserActions: config.allowUserActions,
        actorRoleId: config.actorRoleId,
        allowDepartmentCreate: config.allowDepartmentCreate,
        allowDepartmentActions: config.allowDepartmentActions,
        allowBroadcast: config.allowBroadcast,
        rows: [],
        meta: { total: 0, lastPage: 1 },
        page: 1,
        search: '',
        sortBy: 'createdAt:desc',
        loading: true,
        error: null,
        message: null,
        successPopupMessage: '',
        successPopupType: 'success',
        successPopupVisible: false,
        successPopupTimeout: null,
        logHighlightId: new URLSearchParams(window.location.search).get('highlight'),
        logHighlightTimeout: null,
        saving: false,
        userModalOpen: false,
        departmentModalOpen: false,
        broadcastModalOpen: false,
        resetConfirmOpen: false,
        resetTarget: null,
        resetSending: false,
        deactivateConfirmOpen: false,
        deactivateTarget: null,
        deactivateType: null,
        deactivateSending: false,
        reactivateConfirmOpen: false,
        reactivateTarget: null,
        reactivateType: null,
        reactivateSending: false,
        reactivateError: null,
        reactivateErrorOpen: false,
        departments: [],
        userForm: { _id: null, fullName: '', email: '', roleId: 2, departmentId: '', officeName: '' },
        departmentForm: { _id: null, departmentCode: '', departmentName: '', collegeName: 'CET', campusName: 'Claveria' },
        broadcastForm: { departmentId: '', message: '' },

        init() {
            this.refreshOnFocus = () => {
                if (!document.hidden && !this.loading) this.load();
            };
            window.addEventListener('focus', this.refreshOnFocus);
            document.addEventListener('visibilitychange', this.refreshOnFocus);
        },

        destroy() {
            window.removeEventListener('focus', this.refreshOnFocus);
            document.removeEventListener('visibilitychange', this.refreshOnFocus);
            window.clearTimeout(this.successPopupTimeout);
            window.clearTimeout(this.logHighlightTimeout);
        },

        highlightLogRow() {
            if (this.endpoint !== 'logs' || !this.logHighlightId) return;
            const row = this.rows.find((item) => String(item._id) === String(this.logHighlightId));
            if (!row) return;
            const id = String(this.logHighlightId);
            this.$nextTick(() => {
                const target = [...document.querySelectorAll('[data-log-id]')].find((element) => element.dataset.logId === id);
                target?.scrollIntoView({ behavior: 'smooth', block: 'center' });
            });
            window.clearTimeout(this.logHighlightTimeout);
            this.logHighlightTimeout = window.setTimeout(() => {
                this.logHighlightId = null;
            }, 2000);
        },

        showSuccessPopup(message, type = 'success') {
            this.successPopupMessage = message;
            this.successPopupType = type;
            this.successPopupVisible = true;
            window.clearTimeout(this.successPopupTimeout);
            this.successPopupTimeout = window.setTimeout(() => {
                this.successPopupVisible = false;
            }, 3000);
        },

        async load(page = this.page) {
            this.loading = true;
            this.error = null;
            try {
                const { data } = await axios.get(apiPath(this.endpoint), {
                    params: {
                        page,
                        perPage: this.perPage,
                        ...(this.search ? { search: this.search } : {}),
                        ...(this.endpoint === 'logs' ? {
                            sortBy: this.sortBy.split(':')[0],
                            sortDirection: this.sortBy.split(':')[1],
                        } : {}),
                    },
                });
                this.rows = Array.isArray(data.data) ? data.data : [];
                this.meta = data.meta || { total: this.rows.length, lastPage: 1 };
                this.page = this.meta.currentPage || page;
            } catch (error) {
                this.error = responseMessage(error, 'Unable to load these records. Please try again.');
            } finally {
                this.loading = false;
                this.$nextTick(() => {
                    window.renderIcons?.();
                    this.highlightLogRow();
                });
            }
        },

        formatValue(value) {
            if (value === null || value === undefined || value === '') return '—';
            if (typeof value === 'boolean') return value ? 'Active' : 'Inactive';
            if (typeof value === 'object') return Array.isArray(value) ? value.join(', ') : JSON.stringify(value);
            if (typeof value === 'string' && /^\d{4}-\d{2}-\d{2}T/.test(value)) return new Date(value).toLocaleString();
            return String(value).replaceAll('_', ' ');
        },

        isRowActive(row) {
            return row?.isActive === true || row?.isActive === 1
                || ['1', 'true', 'yes', 'on'].includes(String(row?.isActive).toLowerCase());
        },

        statusClass(value) {
            const status = String(value || '').toLowerCase().replaceAll('_', ' ');
            if (['open', 'non compliant'].includes(status)) return 'bg-error/10 text-error';
            if (status === 'in progress') return 'bg-ustp-gold/40 text-ustp-dark';
            if (['closed', 'compliant'].includes(status)) return 'bg-success/10 text-success';
            return 'bg-slate-100 text-ustp-muted';
        },

        roleName(roleId) {
            return ['Super Admin', 'Admin', 'Auditor', 'Auditee'][Number(roleId)] || 'Unknown';
        },

        roleClass(roleId) {
            return ['bg-ustp-gold text-ustp-navy', 'bg-info/10 text-info', 'bg-ustp-purple/10 text-ustp-purple', 'bg-ustp-gray/10 text-ustp-gray'][Number(roleId)] || 'bg-slate-100 text-ustp-muted';
        },

        async startUserCreate() {
            this.error = null;
            this.userForm = { _id: null, fullName: '', email: '', roleId: 2, departmentId: '', officeName: '' };
            this.userModalOpen = true;
            try {
                const { data } = await axios.get(apiPath('departments'), { params: { perPage: 100 } });
                this.departments = data.data || [];
            } catch (error) {
                this.error = responseMessage(error, 'Unable to load departments for the new user.');
            }
        },

        async startUserEdit(row) {
            this.error = null;
            this.userForm = {
                _id: row._id,
                fullName: row.fullName,
                email: row.email,
                roleId: Number(row.roleId),
                departmentId: row.departmentId || '',
                officeName: row.officeName || '',
            };
            this.userModalOpen = true;
            try {
                const { data } = await axios.get(apiPath('departments'), { params: { perPage: 100 } });
                this.departments = data.data || [];
            } catch (error) {
                this.error = responseMessage(error, 'Unable to load departments for the user.');
            }
        },

        async saveUser() {
            this.saving = true;
            this.error = null;
            this.message = null;
            try {
                const { _id, ...payload } = this.userForm;
                payload.roleId = Number(payload.roleId);
                if (_id) {
                    await axios.patch(apiPath(`users/${encodeURIComponent(_id)}`), payload);
                } else {
                    await axios.post(apiPath('users'), payload);
                }
                this.userModalOpen = false;
                if (_id) {
                    this.showSuccessPopup('User details updated.');
                } else {
                    this.message = 'User created. An account setup email has been sent.';
                }
                await this.load(1);
            } catch (error) {
                this.error = responseMessage(error, 'Unable to create this user.');
            } finally {
                this.saving = false;
            }
        },

        async deactivateUser(row) {
            this.error = null;
            this.message = null;
            this.deactivateTarget = row;
            this.deactivateType = 'user';
            this.deactivateConfirmOpen = true;
            this.$nextTick(() => window.renderIcons?.());
        },

        reactivateUser(row) {
            this.error = null;
            this.message = null;
            this.reactivateError = null;
            this.reactivateErrorOpen = false;
            this.reactivateTarget = row;
            this.reactivateType = 'user';
            this.reactivateConfirmOpen = true;
            this.$nextTick(() => window.renderIcons?.());
        },

        reactivateDepartment(row) {
            this.error = null;
            this.message = null;
            this.reactivateError = null;
            this.reactivateErrorOpen = false;
            this.reactivateTarget = row;
            this.reactivateType = 'department';
            this.reactivateConfirmOpen = true;
            this.$nextTick(() => window.renderIcons?.());
        },

        async confirmReactivate() {
            if (!this.reactivateTarget) return;
            this.reactivateSending = true;
            this.error = null;
            this.reactivateError = null;
            try {
                const resource = this.reactivateType === 'department' ? 'departments' : 'users';
                await axios.post(apiPath(`${resource}/${encodeURIComponent(this.reactivateTarget._id)}/reactivate`));
                const successMessage = `${this.reactivateType === 'department' ? 'Department' : 'User'} reactivated.`;
                this.reactivateTarget.isActive = true;
                this.closeReactivateModals();
                this.showSuccessPopup(successMessage);
            } catch (error) {
                this.reactivateError = responseMessage(error, `Unable to reactivate this ${this.reactivateType || 'user'}.`);
                this.reactivateConfirmOpen = false;
                this.reactivateErrorOpen = true;
            } finally {
                this.reactivateSending = false;
            }
        },

        closeReactivateModals() {
            this.reactivateConfirmOpen = false;
            this.reactivateErrorOpen = false;
            this.reactivateTarget = null;
            this.reactivateType = null;
            this.reactivateSending = false;
            this.reactivateError = null;
        },

        async sendReset(row) {
            this.error = null;
            this.message = null;
            this.resetTarget = row;
            this.resetConfirmOpen = true;
            this.$nextTick(() => window.renderIcons?.());
        },

        async confirmReset() {
            if (!this.resetTarget) return;
            this.resetSending = true;
            this.error = null;
            try {
                await axios.post(apiPath(`users/${encodeURIComponent(this.resetTarget._id)}/reset-password`));
                const successMessage = `A password reset link was sent to ${this.resetTarget.email}.`;
                this.closeResetModals();
                this.showSuccessPopup(successMessage);
            } catch (error) {
                this.error = responseMessage(error, 'Unable to send a password reset link.');
                this.resetConfirmOpen = false;
            } finally {
                this.resetSending = false;
            }
        },

        closeResetModals() {
            this.resetConfirmOpen = false;
            this.resetTarget = null;
            this.resetSending = false;
        },

        async confirmDeactivate() {
            if (!this.deactivateTarget) return;
            this.deactivateSending = true;
            this.error = null;
            try {
                const wasUser = this.deactivateType === 'user';
                if (this.deactivateType === 'user') {
                    await axios.delete(apiPath(`users/${encodeURIComponent(this.deactivateTarget._id)}`));
                    this.deactivateTarget.isActive = false;
                } else {
                    await axios.delete(apiPath(`departments/${encodeURIComponent(this.deactivateTarget._id)}`));
                    this.deactivateTarget.isActive = false;
                }
                this.closeDeactivateModals();
                this.showSuccessPopup(wasUser ? 'User deactivated.' : 'Department deactivated.');
            } catch (error) {
                this.error = responseMessage(error, this.deactivateType === 'user' ? 'Unable to deactivate this user.' : 'Unable to deactivate this department.');
                this.deactivateConfirmOpen = false;
            } finally {
                this.deactivateSending = false;
            }
        },

        closeDeactivateModals() {
            this.deactivateConfirmOpen = false;
            this.deactivateTarget = null;
            this.deactivateType = null;
            this.deactivateSending = false;
        },

        startDepartmentCreate() {
            this.error = null;
            this.departmentForm = { _id: null, departmentCode: '', departmentName: '', collegeName: 'CET', campusName: 'Claveria' };
            this.departmentModalOpen = true;
        },

        startDepartmentEdit(row) {
            this.error = null;
            this.departmentForm = {
                _id: row._id,
                departmentCode: row.departmentCode,
                departmentName: row.departmentName,
                collegeName: row.collegeName,
                campusName: row.campusName,
            };
            this.departmentModalOpen = true;
        },

        async saveDepartment() {
            this.saving = true;
            this.error = null;
            try {
                const { _id, departmentCode, ...payload } = this.departmentForm;
                if (_id) {
                    await axios.patch(apiPath(`departments/${encodeURIComponent(_id)}`), payload);
                } else {
                    await axios.post(apiPath('departments'), { departmentCode, ...payload });
                }
                this.departmentModalOpen = false;
                this.showSuccessPopup(_id ? 'Department updated.' : 'Department created.');
                await this.load(1);
            } catch (error) {
                this.error = responseMessage(error, 'Unable to create this department.');
            } finally {
                this.saving = false;
            }
        },

        async deactivateDepartment(row) {
            this.error = null;
            this.message = null;
            this.deactivateTarget = row;
            this.deactivateType = 'department';
            this.deactivateConfirmOpen = true;
            this.$nextTick(() => window.renderIcons?.());
        },

        async startBroadcast() {
            this.error = null;
            this.broadcastForm = { departmentId: '', message: '' };
            this.broadcastModalOpen = true;
            try {
                const { data } = await axios.get(apiPath('departments'), { params: { perPage: 100 } });
                this.departments = data.data || [];
            } catch (error) {
                this.error = responseMessage(error, 'Unable to load departments for the broadcast.');
            }
        },

        async sendBroadcast() {
            this.saving = true;
            this.error = null;
            try {
                const { data } = await axios.post(apiPath('notifications/broadcast'), this.broadcastForm);
                this.broadcastModalOpen = false;
                this.message = `Broadcast sent to ${data.recipientCount} auditee(s) in ${data.departmentName}.`;
                await this.load(1);
            } catch (error) {
                this.error = responseMessage(error, 'Unable to send the broadcast.');
            } finally {
                this.saving = false;
            }
        },

        statusOptions(row) {
            const labels = { open: 'Open', in_progress: 'In Progress', closed: 'Closed' };
            const next = { open: 'in_progress', in_progress: 'closed', closed: 'open' }[row.correctiveStatus];
            return [
                { value: row.correctiveStatus, label: labels[row.correctiveStatus], disabled: true },
                { value: next, label: labels[next], disabled: false },
            ];
        },

        async updateStatus(row, select) {
            const correctiveStatus = select.value;
            let remarks;
            if (row.correctiveStatus === 'closed' && correctiveStatus === 'open') {
                remarks = window.prompt('Add a remark explaining why this action is being reopened:');
                if (!remarks?.trim()) {
                    select.value = row.correctiveStatus;
                    return;
                }
            }
            this.error = null;
            try {
                await axios.patch(apiPath(`compliance-checks/${encodeURIComponent(row._id)}/status`), { correctiveStatus, remarks });
                this.message = 'Corrective action status updated.';
                await this.load(this.page);
            } catch (error) {
                select.value = row.correctiveStatus;
                this.error = responseMessage(error, 'Unable to update this corrective action.');
            }
        },

        async verifyArchive(row) {
            this.error = null;
            try {
                await axios.get(apiPath(`archives/${encodeURIComponent(row._id)}/verify-checksum`));
                this.showSuccessPopup(`Archive verification succeeded: ${row.originalName} passed its integrity check.`);
            } catch (error) {
                this.showSuccessPopup(`Archive verification failed: ${responseMessage(error, 'The archive could not be verified.')}`, 'error');
            }
        },

        async downloadArchive(row) {
            this.error = null;
            try {
                const { data } = await axios.get(apiPath(`archives/${encodeURIComponent(row._id)}/download`), { responseType: 'blob' });
                const url = URL.createObjectURL(data);
                const anchor = document.createElement('a');
                anchor.href = url;
                anchor.download = row.originalName || row.fileCode;
                anchor.click();
                URL.revokeObjectURL(url);
                this.showSuccessPopup(`Download started: ${row.originalName || row.fileCode}.`);
            } catch (error) {
                this.showSuccessPopup(`Download failed: ${responseMessage(error, 'The archive could not be downloaded.')}`, 'error');
            }
        },

        async markRead(row) {
            this.error = null;
            try {
                await axios.patch(apiPath(`notifications/${encodeURIComponent(row._id)}/read`));
                row.isRead = true;
            } catch (error) {
                this.showSuccessPopup(responseMessage(error, 'Unable to mark this notification as read.'), 'error');
            }
        },
    }));

    Alpine.data('dashboardPage', ({ roleId, heading, subheading }) => ({
        roleId,
        title: '',
        subtitle: '',
        loading: true,
        error: null,
        stats: {},
        reportData: null,
        departmentComplianceView: 'all',
        recentChecks: [],
        cards: [],
        actions: [],
        checksUrl: '',

        userTrendMax(months) {
            const max = Math.max(0, ...(Array.isArray(months) ? months.map(month => Number(month.count) || 0) : []));
            return Math.max(1, Math.ceil(max / 4) * 4);
        },

        userTrendTicks(months) {
            const max = this.userTrendMax(months);
            return [0, 1, 2, 3, 4].map(step => max * step / 4);
        },

        trendX(index, length) {
            return length < 2 ? 210 : 20 + index * (380 / (length - 1));
        },

        trendLinePoints(months) {
            const items = Array.isArray(months) ? months : [];
            return items.map((month, index) => {
                const x = this.trendX(index, items.length);
                const count = Math.max(0, Number(month.count) || 0);
                const y = 160 - (count / this.userTrendMax(items)) * 135;
                return `${x},${y}`;
            }).join(' ');
        },

        trendY(count, months) {
            return 160 - (Math.max(0, Number(count) || 0) / this.userTrendMax(months)) * 135;
        },

        departmentComplianceItems() {
            const items = [...(this.reportData?.deptCompliance || [])];
            if (this.departmentComplianceView === 'most') {
                return items.sort((a, b) => Number(b.compliancePercentage) - Number(a.compliancePercentage));
            }
            if (this.departmentComplianceView === 'least') {
                return items.sort((a, b) => Number(a.compliancePercentage) - Number(b.compliancePercentage));
            }
            return items;
        },

        async load() {
            this.loading = true;
            this.error = null;
            const roleConfig = {
                0: {
                    title: 'System Overview',
                    subtitle: 'System-wide compliance, departments, and storage activity.',
                    checksUrl: '/super-admin/logs',
                    cards: [
                        ['totalUsers', 'Total Users', 'users'],
                        ['totalDepts', 'Departments', 'building-2'],
                        ['storageUsedGb', 'Storage Used (GB)', 'hard-drive'],
                        ['activeAudits', 'Active Audits', 'clipboard-check'],
                    ],
                    actions: [
                        ['Manage Users', '/super-admin/users', 'users'],
                        ['Departments', '/super-admin/departments', 'building-2'],
                        ['Audit Logs', '/super-admin/logs', 'scroll-text'],
                        ['System Settings', '/super-admin/settings', 'settings-2'],
                    ],
                },
                1: {
                    title: 'Admin Dashboard',
                    subtitle: 'CET departments, audit activity, and corrective actions.',
                    checksUrl: '/admin/reports',
                    cards: [
                        ['totalAuditors', 'Total Auditors', 'user-check'],
                        ['totalAuditees', 'Total Auditees', 'users'],
                        ['complianceRate', 'Compliance Rate (%)', 'chart-no-axes-combined'],
                        ['openCars', 'Open CARs', 'circle-alert'],
                    ],
                    actions: [
                        ['Manage Users', '/admin/users', 'users'],
                        ['Departments', '/admin/departments', 'building-2'],
                        ['Archives', '/admin/archives', 'archive'],
                        ['Reports', '/admin/reports', 'chart-no-axes-combined'],
                    ],
                },
                2: {
                    title: 'Auditor Dashboard',
                    subtitle: 'Your compliance checks, corrective actions, and evidence.',
                    checksUrl: '/auditor/checks',
                    cards: [
                        ['totalChecks', 'Total Checks', 'clipboard-check'],
                        ['compliantChecks', 'Compliant', 'circle-check'],
                        ['openCars', 'Open CARs', 'circle-alert'],
                        ['closedCars', 'Closed', 'archive-check'],
                    ],
                    actions: [
                        ['View Checks', '/auditor/checks', 'clipboard-check'],
                        ['Create Check', '/auditor/check-create', 'circle-plus'],
                        ['Corrective Actions', '/auditor/corrective-actions', 'kanban'],
                        ['Archive Review', '/auditor/archives', 'archive'],
                    ],
                },
                3: {
                    title: 'My Dashboard',
                    subtitle: 'Your compliance findings, evidence, and recent updates.',
                    checksUrl: '/auditee/my-findings',
                    cards: [
                        ['totalChecks', 'My Checks', 'clipboard-check'],
                        ['openCars', 'Open Issues', 'circle-alert'],
                        ['inProgressCars', 'In Progress', 'clock-3'],
                        ['closedCars', 'Closed', 'circle-check'],
                    ],
                    actions: [
                        ['My Findings', '/auditee/my-findings', 'clipboard-list'],
                        ['Upload Evidence', '/auditee/upload', 'upload-cloud'],
                        ['My Archives', '/auditee/archives', 'archive'],
                        ['Notifications', '/auditee/notifications', 'bell'],
                    ],
                },
            };
            const config = roleConfig[this.roleId];
            this.title = heading || config.title;
            this.subtitle = subheading || config.subtitle;
            this.checksUrl = config.checksUrl;
            this.cards = config.cards.map(([key, label, icon]) => ({ key, label, icon }));
            this.actions = config.actions.map(([label, href, icon]) => ({ label, href, icon }));

            try {
                const checksRequest = axios.get(apiPath('compliance-checks'), {
                    params: this.roleId === 0 ? { result: 'non_compliant', perPage: 5 } : { perPage: 5 },
                });
                if (this.roleId <= 1) {
                    const { data } = await axios.get(apiPath('reports'));
                    this.stats = data.data || {};
                    this.reportData = data.data || null;
                    const checks = await checksRequest;
                    this.recentChecks = responseData(checks);
                } else {
                    const checkFilters = [
                        {},
                        { result: 'compliant' },
                        { correctiveStatus: 'open' },
                        { correctiveStatus: 'in_progress' },
                        { correctiveStatus: 'closed' },
                        { overdue: true },
                    ];
                    const [checksResponse, ...counts] = await Promise.all([
                        checksRequest,
                        ...checkFilters.map((params) => axios.get(apiPath('compliance-checks'), { params: { ...params, perPage: 1 } })),
                    ]);
                    const keys = ['totalChecks', 'compliantChecks', 'openCars', 'inProgressCars', 'closedCars', 'openOverSevenDays'];
                    this.stats = Object.fromEntries(keys.map((key, index) => [key, counts[index].data.meta?.total || 0]));
                    this.stats.complianceRate = this.stats.totalChecks === 0
                        ? 0
                        : Math.round((this.stats.compliantChecks / this.stats.totalChecks) * 1000) / 10;
                    this.recentChecks = responseData(checksResponse);
                }
            } catch (error) {
                this.error = responseMessage(error, 'Unable to load dashboard data. Please try again.');
            } finally {
                this.loading = false;
                this.$nextTick(() => window.renderIcons?.());
            }
        },
    }));

    Alpine.data('forgotPasswordPage', () => ({
        email: '',
        loading: false,
        submitted: false,
        error: null,
        async submit() {
            this.loading = true;
            this.error = null;
            try {
                await axios.get('/sanctum/csrf-cookie');
                await axios.post(apiPath('auth/forgot-password'), { email: this.email });
                this.submitted = true;
            } catch (error) {
                this.error = responseMessage(error, 'We could not submit your request. Please try again.');
            } finally {
                this.loading = false;
            }
        },
    }));

    Alpine.data('checkCreatePage', () => ({
        departments: [],
        auditees: [],
        departmentId: '',
        auditeeId: '',
        referenceStandard: 'ARTA-3days',
        result: 'compliant',
        remarks: '',
        loading: true,
        saving: false,
        error: null,
        async init() {
            try {
                const [departments, auditees] = await Promise.all([
                    axios.get(apiPath('directory/departments'), { params: { perPage: 100 } }),
                    axios.get(apiPath('directory/auditees'), { params: { perPage: 100 } }),
                ]);
                this.departments = responseData(departments);
                this.auditees = responseData(auditees);
            } catch (error) {
                this.error = responseMessage(error, 'Unable to load departments or auditees.');
            } finally {
                this.loading = false;
            }
        },
        async submit() {
            this.saving = true;
            this.error = null;
            try {
                const { data } = await axios.post(apiPath('compliance-checks'), {
                    departmentId: this.departmentId,
                    auditeeId: this.auditeeId,
                    referenceStandard: this.referenceStandard,
                    result: this.result,
                    remarks: this.remarks,
                });
                window.location.assign(`/auditor/checks/${data.data._id}`);
            } catch (error) {
                this.error = responseMessage(error, 'Could not create the compliance check.');
            } finally {
                this.saving = false;
            }
        },
    }));

    Alpine.data('checkDetailsPage', ({ id }) => ({
        id,
        check: null,
        loading: true,
        error: null,
        fields: [
            ['departmentName', 'Department'],
            ['auditeeName', 'Auditee'],
            ['auditorName', 'Auditor'],
            ['referenceStandard', 'Reference standard'],
            ['result', 'Result'],
            ['correctiveStatus', 'Corrective status'],
            ['createdAt', 'Created'],
        ].map(([key, label]) => ({ key, label })),
        async load() {
            this.loading = true;
            this.error = null;
            try {
                const { data } = await axios.get(apiPath(`compliance-checks/${encodeURIComponent(this.id)}`));
                this.check = data.data;
            } catch (error) {
                this.error = responseMessage(error, 'Unable to load this compliance check.');
            } finally {
                this.loading = false;
            }
        },
    }));

    Alpine.data('evidenceUploadPage', () => ({
        checks: [],
        selectedCheckId: '',
        file: null,
        loading: true,
        saving: false,
        message: null,
        error: null,
        popupTimeout: null,
        showPopup(type, text) {
            window.clearTimeout(this.popupTimeout);
            this.error = type === 'error' ? text : null;
            this.message = type === 'success' ? text : null;
            this.popupTimeout = window.setTimeout(() => {
                this.error = null;
                this.message = null;
            }, 3000);
        },
        async init() {
            try {
                const { data } = await axios.get(apiPath('compliance-checks'), { params: { result: 'non_compliant', perPage: 100 } });
                this.checks = (data.data || []).filter((check) => check.auditeeId === document.body.dataset.userId);
            } catch (error) {
                this.error = responseMessage(error, 'Unable to load checks that can receive evidence.');
            } finally {
                this.loading = false;
            }
        },
        async submit() {
            if (!this.file || !this.selectedCheckId) {
                this.showPopup('error', 'Choose a compliance check and a file before uploading.');
                return;
            }
            const check = this.checks.find((item) => item._id === this.selectedCheckId);
            if (!check) {
                this.showPopup('error', 'The selected check is no longer available.');
                return;
            }
            this.saving = true;
            window.clearTimeout(this.popupTimeout);
            this.error = null;
            this.message = null;
            const form = new FormData();
            form.append('file', this.file);
            form.append('checkId', check._id);
            form.append('referenceStandard', check.referenceStandard);
            try {
                await axios.post(apiPath('archives/upload'), form);
                this.showPopup('success', 'Evidence uploaded and encrypted successfully.');
                this.file = null;
                this.$refs.fileInput.value = '';
            } catch (error) {
                this.showPopup('error', responseMessage(error, 'Evidence could not be uploaded.'));
            } finally {
                this.saving = false;
            }
        },
        destroy() {
            window.clearTimeout(this.popupTimeout);
        },
    }));
});
