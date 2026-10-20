'use strict';

document.addEventListener('DOMContentLoaded', () => {
    const reportData =
        window.VQDSS_USER_DATA || {};

    const roleData =
        Array.isArray(reportData.roles)
            ? reportData.roles
            : [];

    const roleChartCanvas =
        document.getElementById(
            'usersRoleChart'
        );

    if (
        roleChartCanvas &&
        typeof Chart !== 'undefined'
    ) {
        new Chart(
            roleChartCanvas,
            {
                type: 'doughnut',
                data: {
                    labels: roleData.map(
                        (row) => row.label
                    ),
                    datasets: [
                        {
                            data: roleData.map(
                                (row) =>
                                    Number(
                                        row.total || 0
                                    )
                            ),
                            backgroundColor: [
                                '#1d6fb8',
                                '#10b981',
                                '#b8860b',
                                '#8b5cf6',
                                '#64748b',
                                '#0f766e',
                                '#c2410c'
                            ],
                            borderColor: '#ffffff',
                            borderWidth: 3
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    cutout: '62%',
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: {
                                boxWidth: 12,
                                usePointStyle: true,
                                font: {
                                    size: 10
                                }
                            }
                        },
                        tooltip: {
                            backgroundColor: '#0a1628',
                            padding: 10
                        }
                    }
                }
            }
        );
    }

    const searchInput =
        document.getElementById(
            'usersSearch'
        );

    const roleFilter =
        document.getElementById(
            'usersRoleFilter'
        );

    const officeFilter =
        document.getElementById(
            'usersOfficeFilter'
        );

    const statusFilter =
        document.getElementById(
            'usersStatusFilter'
        );

    const rows = Array.from(
        document.querySelectorAll(
            '.users-data-row'
        )
    );

    const noResult =
        document.getElementById(
            'usersNoFilterResult'
        );

    const countLabel =
        document.getElementById(
            'usersRecordCount'
        );

    const exportLink =
        document.getElementById(
            'usersExportLink'
        );

    function applyFilters() {
        const search =
            (searchInput?.value || '')
                .trim()
                .toLowerCase();

        const role =
            roleFilter?.value || '';

        const office =
            officeFilter?.value || '';

        const status =
            statusFilter?.value || '';

        let visible = 0;

        rows.forEach((row) => {
            const show =
                (
                    !search ||
                    (
                        row.dataset.search || ''
                    ).includes(search)
                ) &&
                (
                    !role ||
                    row.dataset.role === role
                ) &&
                (
                    !office ||
                    row.dataset.office === office
                ) &&
                (
                    !status ||
                    row.dataset.status === status
                );

            row.classList.toggle(
                'd-none',
                !show
            );

            if (show) {
                visible++;
            }
        });

        noResult?.classList.toggle(
            'd-none',
            visible > 0 ||
            rows.length === 0
        );

        if (countLabel) {
            countLabel.textContent =
                `Showing ${visible} account(s)`;
        }

        updateExportLink({
            search,
            role,
            office,
            status
        });
    }

    function updateExportLink(filters) {
        if (!exportLink) {
            return;
        }

        const parameters =
            new URLSearchParams();

        Object.entries(filters).forEach(
            ([key, value]) => {
                if (value) {
                    parameters.set(
                        key,
                        value
                    );
                }
            }
        );

        exportLink.href =
            'user_management_export.php' +
            (
                parameters.toString()
                    ? `?${parameters.toString()}`
                    : ''
            );
    }

    [
        searchInput,
        roleFilter,
        officeFilter,
        statusFilter
    ].forEach((element) => {
        element?.addEventListener(
            'input',
            applyFilters
        );

        element?.addEventListener(
            'change',
            applyFilters
        );
    });

    document.getElementById(
        'btnResetUserFilters'
    )?.addEventListener('click', () => {
        if (searchInput) {
            searchInput.value = '';
        }

        if (roleFilter) {
            roleFilter.value = '';
        }

        if (officeFilter) {
            officeFilter.value = '';
        }

        if (statusFilter) {
            statusFilter.value = '';
        }

        applyFilters();
    });

    const editorElement =
        document.getElementById(
            'userEditorModal'
        );

    const editorModal =
        editorElement &&
        typeof bootstrap !== 'undefined'
            ? new bootstrap.Modal(
                editorElement
            )
            : null;

    const viewElement =
        document.getElementById(
            'userViewModal'
        );

    const viewModal =
        viewElement &&
        typeof bootstrap !== 'undefined'
            ? new bootstrap.Modal(
                viewElement
            )
            : null;

    const matrixElement =
        document.getElementById(
            'permissionMatrixModal'
        );

    const matrixModal =
        matrixElement &&
        typeof bootstrap !== 'undefined'
            ? new bootstrap.Modal(
                matrixElement
            )
            : null;

    const editorForm =
        document.getElementById(
            'userEditorForm'
        );

    const stepButtons = Array.from(
        document.querySelectorAll(
            '[data-user-step]'
        )
    );

    const formSections = Array.from(
        document.querySelectorAll(
            '[data-user-form-section]'
        )
    );

    const previousButton =
        document.getElementById(
            'btnPreviousUserStep'
        );

    const nextButton =
        document.getElementById(
            'btnNextUserStep'
        );

    const saveButton =
        document.getElementById(
            'btnSaveUserPreview'
        );

    let currentStep = 1;
    const maximumStep = 3;
    let editorMode = 'create';

    function showEditorStep(step) {
        currentStep = step;

        stepButtons.forEach((button) => {
            button.classList.toggle(
                'active',
                Number(
                    button.dataset.userStep
                ) === step
            );
        });

        formSections.forEach((section) => {
            section.classList.toggle(
                'active',
                Number(
                    section.dataset.userFormSection
                ) === step
            );
        });

        if (previousButton) {
            previousButton.disabled =
                step === 1;
        }

        nextButton?.classList.toggle(
            'd-none',
            step === maximumStep
        );

        saveButton?.classList.toggle(
            'd-none',
            step !== maximumStep
        );

        updatePermissionSummary();
    }

    stepButtons.forEach((button) => {
        button.addEventListener('click', () => {
            showEditorStep(
                Number(
                    button.dataset.userStep
                )
            );
        });
    });

    previousButton?.addEventListener(
        'click',
        () => {
            if (currentStep > 1) {
                showEditorStep(
                    currentStep - 1
                );
            }
        }
    );

    nextButton?.addEventListener(
        'click',
        () => {
            if (currentStep < maximumStep) {
                showEditorStep(
                    currentStep + 1
                );
            }
        }
    );

    function openUserEditor(
        mode,
        data = {}
    ) {
        if (!editorForm || !editorModal) {
            return;
        }

        editorMode = mode;
        editorForm.reset();

        setValue(
            'userEditorId',
            data.id || ''
        );

        setText(
            'userEditorTitle',
            mode === 'edit'
                ? `Edit User: ${data.fullName || 'Account'}`
                : 'Create User Account'
        );

        setValue(
            'userFullName',
            data.fullName || ''
        );

        setValue(
            'userEmail',
            data.email || ''
        );

        setValue(
            'userUsername',
            data.username || ''
        );

        setValue(
            'userPhone',
            data.phone || ''
        );

        setValue(
            'userRole',
            data.roleId || ''
        );

        setValue(
            'userOffice',
            data.officeId || ''
        );

        setValue(
            'userStatus',
            data.status || 'Active'
        );

        setValue(
            'userPassword',
            ''
        );

        setValue(
            'userPasswordConfirm',
            ''
        );

        const forceChange =
            document.getElementById(
                'userForcePasswordChange'
            );

        const sendWelcome =
            document.getElementById(
                'userSendWelcome'
            );

        if (forceChange) {
            forceChange.checked =
                mode === 'create';
        }

        if (sendWelcome) {
            sendWelcome.checked =
                mode === 'create';
        }

        applyRolePreset(
            data.roleLabel || ''
        );

        updateRoleSummary();
        showEditorStep(1);
        editorModal.show();
    }

    document.getElementById(
        'btnCreateUser'
    )?.addEventListener('click', () => {
        openUserEditor('create');
    });

    document.querySelectorAll(
        '[data-edit-user]'
    ).forEach((button) => {
        button.addEventListener('click', () => {
            const data =
                parsePayload(
                    button.dataset.editUser
                );

            openUserEditor(
                'edit',
                data
            );
        });
    });

    document.querySelectorAll(
        '[data-user-permissions]'
    ).forEach((button) => {
        button.addEventListener('click', () => {
            const data =
                parsePayload(
                    button.dataset.userPermissions
                );

            openUserEditor(
                'edit',
                data
            );

            setTimeout(
                () => showEditorStep(3),
                120
            );
        });
    });

    const roleSelect =
        document.getElementById(
            'userRole'
        );

    const statusSelect =
        document.getElementById(
            'userStatus'
        );

    roleSelect?.addEventListener(
        'change',
        () => {
            const option =
                roleSelect.selectedOptions[0];

            applyRolePreset(
                option?.dataset.roleLabel || ''
            );

            updateRoleSummary();
            updatePermissionSummary();
        }
    );

    statusSelect?.addEventListener(
        'change',
        updatePermissionSummary
    );

    function updateRoleSummary() {
        const option =
            roleSelect?.selectedOptions[0];

        const label =
            option?.dataset.roleLabel ||
            'No role selected';

        const descriptions = {
            'Administrator':
                'Full VQDSS administration, configuration, certification, reporting, and user-management access.',
            'Legislative Staff':
                'Operational access for quorum, voting, tallying, decision preparation, and reports.',
            'Committee Member':
                'Meeting, attendance, voting, decision review, and limited certification access.',
            'Validator':
                'Result-validation, findings, evidence review, certification, and reporting access.',
            'Presiding Officer':
                'Quorum certification, voting control, decision confirmation, and official result access.',
            'Read-Only User':
                'View-only access to authorized VQDSS records and reports.',
            'No role selected':
                'Select a role to preview the recommended VQDSS access level.'
        };

        setText(
            'userRoleSummaryTitle',
            label
        );

        setText(
            'userRoleSummaryText',
            descriptions[label] ||
            'Custom shared role. Configure VQDSS permissions manually.'
        );
    }

    function applyRolePreset(roleLabel) {
        const modules = Array.from(
            document.querySelectorAll(
                '[data-permission-module]'
            )
        );

        modules.forEach(
            (moduleCard, moduleIndex) => {
                const moduleToggle =
                    moduleCard.querySelector(
                        '[data-module-toggle]'
                    );

                const permissions =
                    Array.from(
                        moduleCard.querySelectorAll(
                            '[data-permission-checkbox]'
                        )
                    );

                let enabled = true;

                permissions.forEach(
                    (permission, permissionIndex) => {
                        let checked = false;

                        if (
                            roleLabel === 'Administrator'
                        ) {
                            checked = true;
                        } else if (
                            roleLabel === 'Legislative Staff'
                        ) {
                            checked =
                                permissionIndex < 3;
                        } else if (
                            roleLabel === 'Committee Member'
                        ) {
                            checked =
                                permissionIndex < 2;
                        } else if (
                            roleLabel === 'Validator'
                        ) {
                            checked =
                                moduleIndex === 4 ||
                                permissionIndex === 0;
                        } else if (
                            roleLabel === 'Read-Only User'
                        ) {
                            checked =
                                permissionIndex === 0;
                        } else if (
                            roleLabel === 'Presiding Officer'
                        ) {
                            checked =
                                permissionIndex < 3;
                        } else {
                            checked =
                                permissionIndex === 0;
                        }

                        permission.checked =
                            checked;
                    }
                );

                enabled =
                    permissions.some(
                        (permission) =>
                            permission.checked
                    );

                if (moduleToggle) {
                    moduleToggle.checked =
                        enabled;
                }

                setModuleState(
                    moduleCard,
                    enabled
                );
            }
        );

        updatePermissionSummary();
    }

    document.querySelectorAll(
        '[data-module-toggle]'
    ).forEach((toggle) => {
        toggle.addEventListener(
            'change',
            () => {
                const moduleCard =
                    toggle.closest(
                        '[data-permission-module]'
                    );

                if (!moduleCard) {
                    return;
                }

                setModuleState(
                    moduleCard,
                    toggle.checked
                );

                if (toggle.checked) {
                    const permissions =
                        moduleCard.querySelectorAll(
                            '[data-permission-checkbox]'
                        );

                    if (
                        !Array.from(permissions).some(
                            (permission) =>
                                permission.checked
                        )
                    ) {
                        permissions[0].checked =
                            true;
                    }
                }

                updatePermissionSummary();
            }
        );
    });

    document.querySelectorAll(
        '[data-permission-checkbox]'
    ).forEach((checkbox) => {
        checkbox.addEventListener(
            'change',
            () => {
                const moduleCard =
                    checkbox.closest(
                        '[data-permission-module]'
                    );

                if (!moduleCard) {
                    return;
                }

                const moduleToggle =
                    moduleCard.querySelector(
                        '[data-module-toggle]'
                    );

                const anyChecked =
                    Array.from(
                        moduleCard.querySelectorAll(
                            '[data-permission-checkbox]'
                        )
                    ).some(
                        (permission) =>
                            permission.checked
                    );

                if (moduleToggle) {
                    moduleToggle.checked =
                        anyChecked;
                }

                updatePermissionSummary();
            }
        );
    });

    function setModuleState(
        moduleCard,
        enabled
    ) {
        moduleCard.classList.toggle(
            'disabled',
            !enabled
        );

        moduleCard.querySelectorAll(
            '[data-permission-checkbox]'
        ).forEach((checkbox) => {
            checkbox.disabled =
                !enabled;
        });
    }

    function updatePermissionSummary() {
        const enabledModules =
            document.querySelectorAll(
                '[data-module-toggle]:checked'
            ).length;

        const selectedPermissions =
            document.querySelectorAll(
                '[data-permission-checkbox]:checked'
            ).length;

        const roleOption =
            roleSelect?.selectedOptions[0];

        setText(
            'userEnabledModuleCount',
            enabledModules
        );

        setText(
            'userPermissionCount',
            selectedPermissions
        );

        setText(
            'userPermissionStatus',
            statusSelect?.value ||
            'Active'
        );

        setText(
            'userPermissionRole',
            roleOption?.dataset.roleLabel ||
            'Unassigned'
        );
    }

    editorForm?.addEventListener(
        'submit',
        (event) => {
            event.preventDefault();

            const fullName =
                document.getElementById(
                    'userFullName'
                )?.value.trim() || '';

            const email =
                document.getElementById(
                    'userEmail'
                )?.value.trim() || '';

            const username =
                document.getElementById(
                    'userUsername'
                )?.value.trim() || '';

            const password =
                document.getElementById(
                    'userPassword'
                )?.value || '';

            const passwordConfirmation =
                document.getElementById(
                    'userPasswordConfirm'
                )?.value || '';

            const roleOption =
                roleSelect?.selectedOptions[0];

            if (!fullName) {
                showDialog({
                    icon: 'warning',
                    title: 'Full Name Required',
                    text:
                        'Enter the user’s complete name.'
                });

                showEditorStep(1);
                return;
            }

            if (!email && !username) {
                showDialog({
                    icon: 'warning',
                    title: 'Login Identity Required',
                    text:
                        'Provide an email address or username.'
                });

                showEditorStep(1);
                return;
            }

            if (
                password ||
                passwordConfirmation
            ) {
                if (
                    password !==
                    passwordConfirmation
                ) {
                    showDialog({
                        icon: 'warning',
                        title: 'Passwords Do Not Match',
                        text:
                            'The temporary password and confirmation must match.'
                    });

                    showEditorStep(1);
                    return;
                }

                if (password.length < 8) {
                    showDialog({
                        icon: 'warning',
                        title: 'Password Too Short',
                        text:
                            'Use at least eight characters for the temporary password.'
                    });

                    showEditorStep(1);
                    return;
                }
            }

            if (!roleOption?.value) {
                showDialog({
                    icon: 'warning',
                    title: 'Shared Role Required',
                    text:
                        'Select a role before saving the account preview.'
                });

                showEditorStep(2);
                return;
            }

            const permissionCount =
                document.querySelectorAll(
                    '[data-permission-checkbox]:checked'
                ).length;

            showDialog({
                icon: 'success',
                title:
                    editorMode === 'edit'
                        ? 'Account Update Preview Ready'
                        : 'User Account Preview Ready',
                html: `
                    <div class="text-start small">
                        <p><strong>Name:</strong> ${escapeHtml(fullName)}</p>
                        <p><strong>Login:</strong> ${escapeHtml(email || username)}</p>
                        <p><strong>Role:</strong> ${escapeHtml(roleOption.dataset.roleLabel || roleOption.textContent)}</p>
                        <p><strong>Status:</strong> ${escapeHtml(statusSelect?.value || 'Active')}</p>
                        <p><strong>Selected permissions:</strong> ${permissionCount}</p>
                        <p class="text-muted">
                            This is a browser-only preview. No shared user,
                            role, password, status, office, or permission
                            record was changed in MySQL.
                        </p>
                    </div>
                `
            }).then(() => {
                editorModal?.hide();
            });
        }
    );

    document.querySelectorAll(
        '[data-view-user]'
    ).forEach((button) => {
        button.addEventListener('click', () => {
            const data =
                parsePayload(
                    button.dataset.viewUser
                );

            populateViewModal(data);
            viewModal?.show();
        });
    });

    function populateViewModal(data) {
        const fallback =
            'Not recorded';

        setText(
            'userViewId',
            `Shared user ID: ${data.id || fallback}`
        );

        setText(
            'userViewAvatar',
            String(
                data.fullName || 'U'
            )
                .charAt(0)
                .toUpperCase()
        );

        setText(
            'userViewName',
            data.fullName || 'Unnamed User'
        );

        setText(
            'userViewEmail',
            data.email ||
            data.username ||
            'No email recorded'
        );

        setText(
            'userViewRole',
            data.roleLabel ||
            data.roleName ||
            'Unassigned'
        );

        setText(
            'userViewStatus',
            data.status || 'Active'
        );

        const statusBadge =
            document.getElementById(
                'userViewStatus'
            );

        if (statusBadge) {
            statusBadge.className =
                `users-status-badge ${
                    String(
                        data.status || 'Active'
                    ).toLowerCase()
                }`;
        }

        setText(
            'userViewUsername',
            data.username || fallback
        );

        setText(
            'userViewPhone',
            data.phone || fallback
        );

        setText(
            'userViewOffice',
            data.officeName ||
            'Not assigned'
        );

        setText(
            'userViewCreated',
            data.createdAt || fallback
        );

        setText(
            'userViewUpdated',
            data.updatedAt || fallback
        );

        setText(
            'userViewLastLogin',
            data.lastLoginAt ||
            'No login recorded'
        );
    }

    document.getElementById(
        'btnPermissionMatrix'
    )?.addEventListener('click', () => {
        matrixModal?.show();
    });

    document.querySelectorAll(
        '[data-user-status-toggle]'
    ).forEach((button) => {
        button.addEventListener('click', () => {
            const data =
                parsePayload(
                    button.dataset.userStatusToggle
                );

            const currentStatus =
                data.status || 'Active';

            const nextStatus =
                currentStatus === 'Active'
                    ? 'Suspended'
                    : 'Active';

            showDialog({
                icon: 'warning',
                title: 'Account Status Preview',
                html: `
                    <div class="text-start small">
                        <p>
                            Change <strong>${escapeHtml(data.fullName || 'this user')}</strong>
                            from <strong>${escapeHtml(currentStatus)}</strong>
                            to <strong>${escapeHtml(nextStatus)}</strong>?
                        </p>

                        <p class="text-muted">
                            The backend version will require a reason,
                            authorization, and an immutable activity-log entry.
                            No database status will change in this demo.
                        </p>
                    </div>
                `,
                showCancelButton: true,
                confirmButtonText:
                    `Preview ${nextStatus}`
            });
        });
    });

    function parsePayload(payload) {
        try {
            return JSON.parse(
                payload || '{}'
            );
        } catch (error) {
            showDialog({
                icon: 'error',
                title: 'Unable to Read User Record',
                text:
                    'The selected account details could not be opened.'
            });

            return {};
        }
    }

    function setValue(id, value) {
        const element =
            document.getElementById(id);

        if (element) {
            element.value =
                String(value ?? '');
        }
    }

    function setText(id, value) {
        const element =
            document.getElementById(id);

        if (element) {
            element.textContent =
                String(value ?? '');
        }
    }

    function escapeHtml(value) {
        const element =
            document.createElement('div');

        element.textContent =
            String(value ?? '');

        return element.innerHTML;
    }

    function showDialog(options) {
        if (typeof Swal !== 'undefined') {
            return Swal.fire(options);
        }

        const temporary =
            document.createElement('div');

        temporary.innerHTML =
            options.html || '';

        window.alert(
            `${options.title || 'Notice'}\n\n` +
            (
                options.text ||
                temporary.textContent ||
                ''
            )
        );

        return Promise.resolve();
    }

    updateRoleSummary();
    updatePermissionSummary();
    applyFilters();
});
