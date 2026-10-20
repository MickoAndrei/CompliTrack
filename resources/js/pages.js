const apiPath = (endpoint) => `/api/v1/${endpoint.replace(/^\/+/, '')}`;

function responseData(response) {
    return response.data?.data ?? response.data ?? [];
}

function responseMessage(error, fallback) {
    return error.response?.data?.message || fallback;
}

document.addEventListener('alpine:init', () => {
    Alpine.data('profilePage', ({ avatarUrl }) => ({
        avatarUrl: avatarUrl || '',
        uploading: false,
        error: null,
        message: null,
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
                this.message = 'Profile photo updated.';
            } catch (error) {
                this.error = responseMessage(error, 'Profile photo could not be uploaded. Choose a JPG, PNG, or WebP image up to 2 MB.');
            } finally {
                this.uploading = false;
                event.target.value = '';
            }
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
        loading: true,
        error: null,
        message: null,
        saving: false,
        userModalOpen: false,
        departmentModalOpen: false,
        broadcastModalOpen: false,
        departments: [],
        userForm: { _id: null, fullName: '', email: '', roleId: 2, departmentId: '', officeName: '' },
        departmentForm: { _id: null, departmentCode: '', departmentName: '', collegeName: 'CET', campusName: 'Claveria' },
        broadcastForm: { departmentId: '', message: '' },

        async load(page = this.page) {
            this.loading = true;
            this.error = null;
            try {
                const { data } = await axios.get(apiPath(this.endpoint), {
                    params: {
                        page,
                        perPage: this.perPage,
                        ...(this.search ? { search: this.search } : {}),
                    },
                });
                this.rows = Array.isArray(data.data) ? data.data : [];
                this.meta = data.meta || { total: this.rows.length, lastPage: 1 };
                this.page = this.meta.currentPage || page;
            } catch (error) {
                this.error = responseMessage(error, 'Unable to load these records. Please try again.');
            } finally {
                this.loading = false;
                this.$nextTick(() => window.renderIcons?.());
            }
        },

        formatValue(value) {
            if (value === null || value === undefined || value === '') return '—';
            if (typeof value === 'boolean') return value ? 'Active' : 'Inactive';
            if (typeof value === 'object') return Array.isArray(value) ? value.join(', ') : JSON.stringify(value);
            if (typeof value === 'string' && /^\d{4}-\d{2}-\d{2}T/.test(value)) return new Date(value).toLocaleString();
            return String(value).replaceAll('_', ' ');
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
            try {
                const { _id, ...payload } = this.userForm;
                payload.roleId = Number(payload.roleId);
                if (_id) {
                    await axios.patch(apiPath(`users/${encodeURIComponent(_id)}`), payload);
                } else {
                    await axios.post(apiPath('users'), payload);
                }
                this.userModalOpen = false;
                this.message = _id ? 'User details updated.' : 'User created. An account setup email has been sent.';
                await this.load(1);
            } catch (error) {
                this.error = responseMessage(error, 'Unable to create this user.');
            } finally {
                this.saving = false;
            }
        },

        async deactivateUser(row) {
            if (!window.confirm(`Deactivate ${row.fullName}?`)) return;
            this.error = null;
            try {
                await axios.delete(apiPath(`users/${encodeURIComponent(row._id)}`));
                row.isActive = false;
                this.message = 'User deactivated.';
            } catch (error) {
                this.error = responseMessage(error, 'Unable to deactivate this user.');
            }
        },

        async sendReset(row) {
            this.error = null;
            try {
                await axios.post(apiPath(`users/${encodeURIComponent(row._id)}/reset-password`));
                this.message = `A password reset link was sent to ${row.email}.`;
            } catch (error) {
                this.error = responseMessage(error, 'Unable to send a password reset link.');
            }
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
                this.message = _id ? 'Department updated.' : 'Department created.';
                await this.load(1);
            } catch (error) {
                this.error = responseMessage(error, 'Unable to create this department.');
            } finally {
                this.saving = false;
            }
        },

        async deactivateDepartment(row) {
            if (!window.confirm(`Deactivate ${row.departmentName}?`)) return;
            this.error = null;
            try {
                await axios.delete(apiPath(`departments/${encodeURIComponent(row._id)}`));
                row.isActive = false;
                this.message = 'Department deactivated.';
            } catch (error) {
                this.error = responseMessage(error, 'Unable to deactivate this department.');
            }
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
                this.message = `${row.originalName} passed its integrity check.`;
            } catch (error) {
                this.error = responseMessage(error, 'The archive could not be verified.');
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
            } catch (error) {
                this.error = responseMessage(error, 'The archive could not be downloaded.');
            }
        },

        async markRead(row) {
            this.error = null;
            try {
                await axios.patch(apiPath(`notifications/${encodeURIComponent(row._id)}/read`));
                row.isRead = true;
            } catch (error) {
                this.error = responseMessage(error, 'Unable to mark this notification as read.');
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
        recentChecks: [],
        cards: [],
        actions: [],
        checksUrl: '',

        trendLinePoints(months) {
            const items = Array.isArray(months) ? months : [];
            return items.map((month, index) => {
                const x = items.length < 2 ? 210 : 20 + index * (380 / (items.length - 1));
                const percent = Math.max(0, Math.min(100, Number(month.compliancePercentage) || 0));
                const y = 160 - percent * 1.35;
                return `${x},${y}`;
            }).join(' ');
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
                this.error = 'Choose a compliance check and a file before uploading.';
                return;
            }
            const check = this.checks.find((item) => item._id === this.selectedCheckId);
            if (!check) {
                this.error = 'The selected check is no longer available.';
                return;
            }
            this.saving = true;
            this.error = null;
            this.message = null;
            const form = new FormData();
            form.append('file', this.file);
            form.append('checkId', check._id);
            form.append('referenceStandard', check.referenceStandard);
            try {
                await axios.post(apiPath('archives/upload'), form);
                this.message = 'Evidence uploaded and encrypted successfully.';
                this.file = null;
                this.$refs.fileInput.value = '';
            } catch (error) {
                this.error = responseMessage(error, 'Evidence could not be uploaded.');
            } finally {
                this.saving = false;
            }
        },
    }));
});
