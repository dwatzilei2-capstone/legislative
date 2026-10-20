'use strict';

document.addEventListener('DOMContentLoaded', () => {
    const reportData = window.LACMS_REPORT_DATA || null;

    if (reportData && typeof Chart !== 'undefined') {
        makeChart(
            'lacmsMonthlyChart',
            'line',
            reportData.monthly,
            'Legislative Records'
        );

        makeChart(
            'lacmsTypeChart',
            'doughnut',
            reportData.types,
            'Record Types'
        );

        makeChart(
            'lacmsPriorityChart',
            'bar',
            reportData.priorities,
            'Priority Distribution'
        );
    }

    function makeChart(id, type, data, label) {
        const canvas = document.getElementById(id);

        if (!canvas || !data) {
            return;
        }

        new Chart(canvas, {
            type,
            data: {
                labels: data.labels || [],
                datasets: [{
                    label,
                    data: data.values || [],
                    backgroundColor: [
                        '#1d6fb8',
                        '#f5c842',
                        '#10b981',
                        '#7c3aed',
                        '#ef4444',
                        '#64748b'
                    ],
                    borderColor:
                        type === 'line'
                            ? '#1d6fb8'
                            : undefined,
                    borderWidth:
                        type === 'line'
                            ? 2
                            : 0,
                    tension: 0.35,
                    fill: false,
                    borderRadius:
                        type === 'bar'
                            ? 7
                            : 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: type === 'doughnut'
                    }
                },
                scales:
                    type === 'doughnut'
                        ? {}
                        : {
                            y: {
                                beginAtZero: true,
                                ticks: {
                                    precision: 0
                                }
                            }
                        }
            }
        });
    }

    bindFilters({
        rowSelector: '.admin-registry-row',
        searchId: 'adminRegistrySearch',
        filters: [
            ['adminRegistryType', 'type'],
            ['adminRegistryPriority', 'priority'],
            ['adminRegistryStatus', 'status']
        ],
        resetId: 'btnResetAdminRegistry',
        noResultId: 'adminRegistryNoResult',
        countId: 'adminRegistryCount',
        countLabel: 'record(s)'
    });

    bindFilters({
        rowSelector: '.activity-log-row',
        searchId: 'activityLogSearch',
        filters: [
            ['activityLogModule', 'module'],
            ['activityLogAction', 'action'],
            ['activityLogDate', 'date']
        ],
        resetId: 'btnResetActivityLogs',
        noResultId: 'activityLogNoResult',
        countId: 'activityLogCount',
        countLabel: 'log record(s)'
    });

    bindFilters({
        rowSelector: '.user-management-row',
        searchId: 'userManagementSearch',
        filters: [
            ['userManagementRole', 'role'],
            ['userManagementStatus', 'status']
        ],
        resetId: 'btnResetUserManagement',
        noResultId: 'userManagementNoResult',
        countId: 'userManagementCount',
        countLabel: 'user(s)'
    });

    function bindFilters(config) {
        const rows = Array.from(
            document.querySelectorAll(config.rowSelector)
        );

        if (!rows.length) {
            return;
        }

        const search = document.getElementById(config.searchId);
        const noResult = document.getElementById(config.noResultId);
        const count = document.getElementById(config.countId);

        const filterElements = config.filters.map(
            ([id, key]) => ({
                element: document.getElementById(id),
                key
            })
        );

        const apply = () => {
            const searchValue =
                (search?.value || '').trim().toLowerCase();

            let visible = 0;

            rows.forEach((row) => {
                let show =
                    !searchValue ||
                    (row.dataset.search || '')
                        .includes(searchValue);

                filterElements.forEach(({ element, key }) => {
                    const value = element?.value || '';

                    if (
                        value &&
                        row.dataset[key] !== value
                    ) {
                        show = false;
                    }
                });

                row.classList.toggle('d-none', !show);

                if (show) {
                    visible++;
                }
            });

            noResult?.classList.toggle(
                'd-none',
                visible > 0
            );

            if (count) {
                count.textContent =
                    `Showing ${visible} ${config.countLabel}`;
            }
        };

        search?.addEventListener('input', apply);

        filterElements.forEach(({ element }) => {
            element?.addEventListener('change', apply);
        });

        document.getElementById(config.resetId)
            ?.addEventListener('click', () => {
                if (search) {
                    search.value = '';
                }

                filterElements.forEach(({ element }) => {
                    if (element) {
                        element.value = '';
                    }
                });

                apply();
            });
    }

    document.querySelectorAll('[data-log-details]')
        .forEach((button) => {
            button.addEventListener('click', () => {
                showDialog({
                    icon: 'info',
                    title: 'Activity Log Details',
                    html: `
                        <div class="text-start small">
                            <p><strong>Actor:</strong> ${escapeHtml(button.dataset.logActor || 'System')}</p>
                            <p><strong>Module:</strong> ${escapeHtml(button.dataset.logModule || 'LACMS')}</p>
                            <p><strong>Action:</strong> ${escapeHtml(button.dataset.logAction || 'Activity')}</p>
                            <p><strong>Date:</strong> ${escapeHtml(button.dataset.logDate || 'Not recorded')}</p>
                            <p><strong>IP Address:</strong> ${escapeHtml(button.dataset.logIp || 'Not recorded')}</p>
                            <p><strong>Description:</strong><br>${escapeHtml(button.dataset.logDescription || '')}</p>
                        </div>
                    `
                });
            });
        });

    const userModalElement =
        document.getElementById('lacmsUserModal');

    const userModal =
        userModalElement &&
        typeof bootstrap !== 'undefined'
            ? new bootstrap.Modal(userModalElement)
            : null;

    const userForm =
        document.getElementById('lacmsUserForm');

    function openUserModal(mode, data = {}) {
        if (!userModal || !userForm) {
            return;
        }

        userForm.reset();

        setText(
            'lacmsUserModalTitle',
            mode === 'edit'
                ? 'Edit Shared User'
                : 'Add Shared User'
        );

        setValue('lacmsUserId', data.id || '');
        setValue('lacmsUserName', data.name || '');
        setValue('lacmsUserEmail', data.email || '');
        setValue('lacmsUserStatus', data.status || 'Active');

        const roleSelect =
            document.getElementById('lacmsUserRole');

        if (roleSelect && data.role) {
            const option = Array.from(roleSelect.options)
                .find((item) => {
                    return item.textContent.trim() === data.role;
                });

            if (option) {
                roleSelect.value = option.value;
            }
        }

        userModal.show();
    }

    document.getElementById('btnAddLacmsUser')
        ?.addEventListener('click', () => {
            openUserModal('add');
        });

    document.querySelectorAll('[data-edit-user]')
        .forEach((button) => {
            button.addEventListener('click', () => {
                openUserModal('edit', {
                    id: button.dataset.userId,
                    name: button.dataset.userName,
                    email: button.dataset.userEmail,
                    role: button.dataset.userRole,
                    status: button.dataset.userStatus
                });
            });
        });

    document.querySelectorAll('[data-user-details]')
        .forEach((button) => {
            button.addEventListener('click', () => {
                showDialog({
                    icon: 'info',
                    title: 'User Account Details',
                    html: `
                        <div class="text-start small">
                            <p><strong>User ID:</strong> ${escapeHtml(button.dataset.userId || 'N/A')}</p>
                            <p><strong>Name:</strong> ${escapeHtml(button.dataset.userName || '')}</p>
                            <p><strong>Email:</strong> ${escapeHtml(button.dataset.userEmail || '')}</p>
                            <p><strong>Role:</strong> ${escapeHtml(button.dataset.userRole || 'Unassigned')}</p>
                            <p><strong>Status:</strong> ${escapeHtml(button.dataset.userStatus || '')}</p>
                            <p><strong>System Access:</strong> Shared Legislative Platform</p>
                        </div>
                    `
                });
            });
        });

    document.querySelectorAll('[data-reset-password]')
        .forEach((button) => {
            button.addEventListener('click', () => {
                showDialog({
                    icon: 'info',
                    title: 'Password Reset UI Ready',
                    text:
                        `Password reset for ${button.dataset.userName || 'this user'} will be connected during backend implementation.`
                });
            });
        });

    document.querySelectorAll('[data-toggle-account]')
        .forEach((button) => {
            button.addEventListener('click', () => {
                const current =
                    button.dataset.currentStatus || 'Active';

                const next =
                    current === 'Active'
                        ? 'Inactive'
                        : 'Active';

                showDialog({
                    icon: 'info',
                    title: 'Account Status UI Ready',
                    text:
                        `${button.dataset.userName || 'This user'} would be changed from ${current} to ${next}. The database action will be connected later.`
                });
            });
        });

    userForm?.addEventListener('submit', (event) => {
        event.preventDefault();

        showDialog({
            icon: 'success',
            title: 'User Form Completed',
            text:
                'Saving, editing, role assignment, account access, and email delivery will be connected during backend implementation.'
        }).then(() => {
            userModal?.hide();
        });
    });

    function setValue(id, value) {
        const element = document.getElementById(id);

        if (element) {
            element.value = String(value ?? '');
        }
    }

    function setText(id, value) {
        const element = document.getElementById(id);

        if (element) {
            element.textContent = String(value ?? '');
        }
    }

    function escapeHtml(value) {
        const element = document.createElement('div');
        element.textContent = String(value ?? '');
        return element.innerHTML;
    }

    function showDialog(options) {
        if (typeof Swal !== 'undefined') {
            return Swal.fire(options);
        }

        const temporary = document.createElement('div');
        temporary.innerHTML = options.html || '';

        window.alert(
            `${options.title || 'Notice'}\n\n` +
            (options.text || temporary.textContent || '')
        );

        return Promise.resolve();
    }
});
