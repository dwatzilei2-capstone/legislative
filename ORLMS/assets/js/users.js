/**
 * assets/js/users.js
 * ------------------------------------------------------------------
 * Client-side behavior for ORLMS User Management.
 * ------------------------------------------------------------------
 */

'use strict';

document.addEventListener('DOMContentLoaded', () => {
    const userFormModalElement =
        document.getElementById('userFormModal');

    const userFormModal = userFormModalElement
        ? new bootstrap.Modal(userFormModalElement)
        : null;

    const userDetailsModalElement =
        document.getElementById('userDetailsModal');

    const userDetailsModal = userDetailsModalElement
        ? new bootstrap.Modal(userDetailsModalElement)
        : null;

    const userForm =
        document.getElementById('userForm');

    const addButtons = document.querySelectorAll(
        '#btnAddUser, [data-add-user]'
    );

    function openAddUser() {
        if (!userForm || !userFormModal) {
            return;
        }

        userForm.reset();

        setValue('userId', '');
        setText('userFormModalTitle', 'Add User Account');

        document.querySelectorAll(
            '[data-system-checkbox]'
        ).forEach((checkbox) => {
            checkbox.checked = false;
        });

        resetRolePreview();
        showUserTab('account');
        userFormModal.show();
    }

    addButtons.forEach((button) => {
        button.addEventListener('click', openAddUser);
    });

    document.querySelectorAll(
        '[data-user-tab]'
    ).forEach((tab) => {
        tab.addEventListener('click', () => {
            showUserTab(tab.dataset.userTab || 'account');
        });
    });

    function showUserTab(target) {
        document.querySelectorAll(
            '[data-user-tab]'
        ).forEach((tab) => {
            tab.classList.toggle(
                'active',
                tab.dataset.userTab === target
            );
        });

        document.querySelectorAll(
            '[data-user-section]'
        ).forEach((section) => {
            section.classList.toggle(
                'active',
                section.dataset.userSection === target
            );
        });
    }

    const roleSelect =
        document.getElementById('userRole');

    roleSelect?.addEventListener('change', () => {
        const option =
            roleSelect.options[roleSelect.selectedIndex];

        const preview =
            document.getElementById('userRolePreview');

        if (!preview) {
            return;
        }

        if (!option || !option.value) {
            resetRolePreview();
            return;
        }

        const roleName =
            option.textContent.trim();

        const description =
            option.dataset.roleDescription ||
            'This role will determine the user’s administrative and workflow capabilities.';

        preview.innerHTML = `
            <i class="bi bi-shield-check"></i>

            <div>
                <strong>${escapeHtml(roleName)}</strong>
                <span>${escapeHtml(description)}</span>
            </div>
        `;
    });

    function resetRolePreview() {
        const preview =
            document.getElementById('userRolePreview');

        if (!preview) {
            return;
        }

        preview.innerHTML = `
            <i class="bi bi-shield"></i>

            <div>
                <strong>No role selected</strong>
                <span>
                    Select a role to preview its description.
                </span>
            </div>
        `;
    }

    document.getElementById(
        'btnSelectAllSystems'
    )?.addEventListener('click', () => {
        document.querySelectorAll(
            '[data-system-checkbox]'
        ).forEach((checkbox) => {
            checkbox.checked = true;
        });
    });

    document.getElementById(
        'btnClearAllSystems'
    )?.addEventListener('click', () => {
        document.querySelectorAll(
            '[data-system-checkbox]'
        ).forEach((checkbox) => {
            checkbox.checked = false;
        });
    });

    document.querySelectorAll(
        '[data-toggle-password]'
    ).forEach((button) => {
        button.addEventListener('click', () => {
            const input =
                document.getElementById(
                    button.dataset.togglePassword || ''
                );

            if (!input) {
                return;
            }

            const shouldShow =
                input.type === 'password';

            input.type =
                shouldShow ? 'text' : 'password';

            button.innerHTML =
                shouldShow
                    ? '<i class="bi bi-eye-slash"></i>'
                    : '<i class="bi bi-eye"></i>';
        });
    });

    userForm?.addEventListener('submit', (event) => {
        event.preventDefault();

        const password =
            document.getElementById('userPassword')?.value || '';

        const confirmPassword =
            document.getElementById(
                'userConfirmPassword'
            )?.value || '';

        if (
            password !== '' &&
            password !== confirmPassword
        ) {
            Swal.fire({
                icon: 'warning',
                title: 'Passwords do not match',
                text:
                    'The password and confirmation password must be the same.',
            });

            showUserTab('security');
            return;
        }

        Swal.fire({
            icon: 'success',
            title: 'User Management Interface Ready',
            text:
                'The account, role, system-access, and security interface is complete. The AJAX create/update endpoints will be connected during backend implementation.',
            confirmButtonText: 'Continue',
        }).then(() => {
            userFormModal?.hide();
        });
    });

    document.querySelectorAll(
        '[data-view-user]'
    ).forEach((button) => {
        button.addEventListener('click', () => {
            const user =
                parsePayload(button.dataset.viewUser);

            setText(
                'userDetailId',
                user.id
                    ? `User account #${user.id}`
                    : 'User account'
            );

            setText(
                'userDetailName',
                user.full_name || 'User Account'
            );

            setText(
                'userDetailEmail',
                user.email || 'No email address'
            );

            setText(
                'userDetailUsername',
                user.username || 'Not recorded'
            );

            setText(
                'userDetailRole',
                user.role_display || 'Unassigned'
            );

            setText(
                'userDetailOffice',
                user.office || 'Not assigned'
            );

            setText(
                'userDetailPosition',
                user.position || 'Not recorded'
            );

            setText(
                'userDetailPhone',
                user.phone || 'Not recorded'
            );

            setText(
                'userDetailLastLogin',
                user.last_login || 'Never'
            );

            setText(
                'userDetailCreatedAt',
                user.created_at || 'Unknown'
            );

            setText(
                'userDetailStatusText',
                user.status || 'Unknown'
            );

            const avatar =
                document.getElementById('userDetailAvatar');

            if (avatar) {
                avatar.textContent =
                    (user.full_name || 'U')
                        .charAt(0)
                        .toUpperCase();
            }

            const statusBadge =
                document.getElementById('userDetailStatus');

            if (statusBadge) {
                const active = isActiveStatus(
                    user.status || ''
                );

                statusBadge.className =
                    `users-status-badge ${
                        active ? 'active' : 'inactive'
                    }`;

                statusBadge.innerHTML = `
                    <i class="bi bi-circle-fill"></i>
                    ${escapeHtml(user.status || 'Unknown')}
                `;
            }

            renderUserSystems(
                Array.isArray(user.assigned_systems)
                    ? user.assigned_systems
                    : []
            );

            userDetailsModal?.show();
        });
    });

    document.querySelectorAll(
        '[data-edit-user]'
    ).forEach((button) => {
        button.addEventListener('click', () => {
            const user =
                parsePayload(button.dataset.editUser);

            if (!userForm || !userFormModal) {
                return;
            }

            userForm.reset();

            setValue('userId', user.id || '');
            setValue('userFullName', user.full_name || '');
            setValue('userEmail', user.email || '');
            setValue('userUsername', user.username || '');
            setValue('userOffice', user.office || '');
            setValue('userPosition', user.position || '');
            setValue('userPhone', user.phone || '');
            setValue('userStatus', user.status || 'Active');

            const roleSelect =
                document.getElementById('userRole');

            if (roleSelect) {
                roleSelect.value =
                    String(user.role_id || '');

                roleSelect.dispatchEvent(
                    new Event('change')
                );
            }

            document.querySelectorAll(
                '[data-system-checkbox]'
            ).forEach((checkbox) => {
                const label =
                    checkbox.closest(
                        '.users-system-card'
                    );

                const systemName =
                    label?.querySelector('strong')
                        ?.textContent
                        ?.trim() || '';

                checkbox.checked =
                    Array.isArray(user.assigned_systems) &&
                    user.assigned_systems.includes(systemName);
            });

            setText(
                'userFormModalTitle',
                `Edit User: ${user.full_name || 'Account'}`
            );

            showUserTab('account');
            userFormModal.show();
        });
    });

    document.querySelectorAll(
        '[data-reset-password]'
    ).forEach((button) => {
        button.addEventListener('click', () => {
            const name =
                button.dataset.userName ||
                'this user';

            Swal.fire({
                title: 'Reset User Password',
                html:
                    `<p class="small text-muted">Set a temporary password for <strong>${escapeHtml(name)}</strong>.</p>`,
                input: 'password',
                inputPlaceholder:
                    'Enter temporary password',
                showCancelButton: true,
                confirmButtonText: 'Reset Password',
                inputValidator: (value) => {
                    if (!value || value.length < 8) {
                        return 'Use at least 8 characters.';
                    }

                    return null;
                },
            }).then((result) => {
                if (!result.isConfirmed) {
                    return;
                }

                Swal.fire({
                    icon: 'success',
                    title: 'Password Reset UI Ready',
                    text:
                        'The password reset endpoint will be connected during backend implementation.',
                });
            });
        });
    });

    document.querySelectorAll(
        '[data-toggle-user]'
    ).forEach((button) => {
        button.addEventListener('click', () => {
            const currentStatus =
                button.dataset.currentStatus || '';

            const currentlyActive =
                isActiveStatus(currentStatus);

            const action =
                currentlyActive
                    ? 'deactivate'
                    : 'activate';

            const name =
                button.dataset.userName ||
                'this user';

            Swal.fire({
                icon: currentlyActive
                    ? 'warning'
                    : 'question',
                title: `${capitalize(action)} User Account`,
                text:
                    `Are you sure you want to ${action} ${name}?`,
                showCancelButton: true,
                confirmButtonText:
                    capitalize(action),
                confirmButtonColor:
                    currentlyActive
                        ? '#b91c1c'
                        : undefined,
            }).then((result) => {
                if (!result.isConfirmed) {
                    return;
                }

                Swal.fire({
                    icon: 'success',
                    title: 'Account Status UI Ready',
                    text:
                        `The ${action} endpoint will be connected during backend implementation.`,
                });
            });
        });
    });

    const searchInput =
        document.getElementById('usersSearch');

    const roleFilter =
        document.getElementById('usersRoleFilter');

    const statusFilter =
        document.getElementById('usersStatusFilter');

    const resetButton =
        document.getElementById('btnResetUserFilters');

    const rows = Array.from(
        document.querySelectorAll('.users-data-row')
    );

    const noResultRow =
        document.getElementById('usersNoFilterResult');

    const countLabel =
        document.getElementById('usersRecordCount');

    function applyFilters() {
        const search =
            (searchInput?.value || '')
                .trim()
                .toLowerCase();

        const role =
            roleFilter?.value || '';

        const status =
            statusFilter?.value || '';

        let visible = 0;

        rows.forEach((row) => {
            const matchesSearch =
                !search ||
                (row.dataset.search || '').includes(search);

            const matchesRole =
                !role ||
                row.dataset.role === role;

            const matchesStatus =
                !status ||
                row.dataset.status === status;

            const show =
                matchesSearch &&
                matchesRole &&
                matchesStatus;

            row.classList.toggle('d-none', !show);

            if (show) {
                visible++;
            }
        });

        noResultRow?.classList.toggle(
            'd-none',
            visible > 0 || rows.length === 0
        );

        if (countLabel) {
            countLabel.textContent =
                `Showing ${visible} user account(s)`;
        }
    }

    searchInput?.addEventListener('input', applyFilters);
    roleFilter?.addEventListener('change', applyFilters);
    statusFilter?.addEventListener('change', applyFilters);

    resetButton?.addEventListener('click', () => {
        if (searchInput) {
            searchInput.value = '';
        }

        if (roleFilter) {
            roleFilter.value = '';
        }

        if (statusFilter) {
            statusFilter.value = '';
        }

        applyFilters();
    });

    document.getElementById(
        'btnExportUsers'
    )?.addEventListener('click', () => {
        exportVisibleUsers();
    });

    function exportVisibleUsers() {
        const table =
            document.getElementById('usersTable');

        if (!table) {
            return;
        }

        const headers = Array.from(
            table.querySelectorAll('thead th')
        )
            .slice(0, -1)
            .map((cell) => cell.textContent.trim());

        const csvRows = [
            headers.map(csvEscape).join(',')
        ];

        Array.from(
            table.querySelectorAll(
                'tbody tr.users-data-row:not(.d-none)'
            )
        ).forEach((row) => {
            const values = Array.from(
                row.querySelectorAll('td')
            )
                .slice(0, -1)
                .map((cell) => {
                    return cell.textContent
                        .replace(/\s+/g, ' ')
                        .trim();
                });

            csvRows.push(
                values.map(csvEscape).join(',')
            );
        });

        const blob = new Blob(
            [csvRows.join('\n')],
            {
                type: 'text/csv;charset=utf-8;'
            }
        );

        const url = URL.createObjectURL(blob);
        const link = document.createElement('a');

        link.href = url;
        link.download =
            `orlms-users-${new Date()
                .toISOString()
                .slice(0, 10)}.csv`;

        document.body.appendChild(link);
        link.click();
        link.remove();
        URL.revokeObjectURL(url);
    }

    function renderUserSystems(systems) {
        const container =
            document.getElementById('userDetailSystems');

        if (!container) {
            return;
        }

        if (!systems.length) {
            container.innerHTML =
                '<span>No specific system access assigned.</span>';

            return;
        }

        container.innerHTML = systems
            .map((system) => {
                return `
                    <span>
                        <i class="bi bi-window-stack"></i>
                        ${escapeHtml(system)}
                    </span>
                `;
            })
            .join('');
    }

    function parsePayload(value) {
        try {
            return JSON.parse(value || '{}');
        } catch (error) {
            return {};
        }
    }

    function isActiveStatus(status) {
        return [
            'active',
            'enabled',
            'approved',
            '1'
        ].includes(
            String(status || '')
                .trim()
                .toLowerCase()
        );
    }

    function setValue(id, value) {
        const element = document.getElementById(id);

        if (element) {
            element.value =
                value === null || value === undefined
                    ? ''
                    : String(value);
        }
    }

    function setText(id, value) {
        const element = document.getElementById(id);

        if (element) {
            element.textContent =
                value === null || value === undefined
                    ? ''
                    : String(value);
        }
    }

    function capitalize(value) {
        const text = String(value || '');

        return text.charAt(0).toUpperCase() +
            text.slice(1);
    }

    function escapeHtml(value) {
        const element = document.createElement('div');
        element.textContent =
            value === null || value === undefined
                ? ''
                : String(value);

        return element.innerHTML;
    }

    function csvEscape(value) {
        const text = String(value ?? '');

        if (
            text.includes(',') ||
            text.includes('"') ||
            text.includes('\n')
        ) {
            return `"${text.replace(/"/g, '""')}"`;
        }

        return text;
    }
});
