/**
 * assets/js/approvals.js
 * ------------------------------------------------------------------
 * Client-side behavior for the Approval and Enactment UI.
 * ------------------------------------------------------------------
 */

'use strict';

document.addEventListener('DOMContentLoaded', () => {
    const modalElement =
        document.getElementById('approvalDecisionModal');

    const modal = modalElement
        ? new bootstrap.Modal(modalElement)
        : null;

    const approvalItemId =
        document.getElementById('approvalItemId');

    const approvalReference =
        document.getElementById('approvalRecordReference');

    const approvalTitle =
        document.getElementById('approvalRecordTitle');

    document.querySelectorAll(
        '[data-process-approval]'
    ).forEach((button) => {
        button.addEventListener('click', () => {
            if (approvalItemId) {
                approvalItemId.value =
                    button.dataset.processApproval || '';
            }

            if (approvalReference) {
                approvalReference.textContent =
                    button.dataset.reference ||
                    'Legislative record';
            }

            if (approvalTitle) {
                approvalTitle.textContent =
                    button.dataset.title ||
                    'Approval decision';
            }

            modal?.show();
        });
    });

    const approvalTabs = Array.from(
        document.querySelectorAll(
            '[data-approval-tab]'
        )
    );

    const approvalSections = Array.from(
        document.querySelectorAll(
            '[data-approval-section]'
        )
    );

    approvalTabs.forEach((tab) => {
        tab.addEventListener('click', () => {
            const target = tab.dataset.approvalTab;

            approvalTabs.forEach((item) => {
                item.classList.toggle(
                    'active',
                    item === tab
                );
            });

            approvalSections.forEach((section) => {
                section.classList.toggle(
                    'active',
                    section.dataset.approvalSection === target
                );
            });
        });
    });

    document.getElementById(
        'approvalDecisionForm'
    )?.addEventListener('submit', (event) => {
        event.preventDefault();

        const decision =
            event.currentTarget.querySelector(
                'input[name="decision"]:checked'
            );

        if (!decision) {
            Swal.fire({
                icon: 'warning',
                title: 'Select a decision',
                text:
                    'Choose Approve, Reject, Return, or Enact.',
            });

            return;
        }

        Swal.fire({
            icon: 'success',
            title: 'Approval Interface Ready',
            text:
                'The approval and enactment decision form is complete. The AJAX save endpoint will be connected during backend implementation.',
            confirmButtonText: 'Continue',
        }).then(() => {
            modal?.hide();
        });
    });

    document.getElementById(
        'btnOpenApprovalGuide'
    )?.addEventListener('click', () => {
        Swal.fire({
            icon: 'info',
            title: 'Approval and Enactment Guide',
            html:
                '<div class="text-start small">' +
                '<p><strong>1.</strong> Confirm committee endorsement and final text.</p>' +
                '<p><strong>2.</strong> Record the approval authority and decision.</p>' +
                '<p><strong>3.</strong> Assign the official enactment number and date.</p>' +
                '<p><strong>4.</strong> Attach signed and certified documents.</p>' +
                '<p><strong>5.</strong> Forward enacted measures for publication.</p>' +
                '</div>',
        });
    });

    document.querySelectorAll(
        '[data-view-approval-history]'
    ).forEach((button) => {
        button.addEventListener('click', () => {
            Swal.fire({
                icon: 'info',
                title: 'Approval History',
                text:
                    'The approval and enactment history timeline will be connected during backend implementation.',
            });
        });
    });

    document.getElementById(
        'btnAssignApprovingAuthority'
    )?.addEventListener('click', () => {
        Swal.fire({
            icon: 'info',
            title: 'Approving Authority',
            text:
                'Approving authority assignment will be connected to the approval database tables.',
        });
    });

    document.getElementById(
        'btnUploadApprovalDocument'
    )?.addEventListener('click', () => {
        Swal.fire({
            icon: 'info',
            title: 'Approval Document Upload',
            text:
                'Document upload will be connected during backend implementation.',
        });
    });

    const documentInput =
        document.getElementById('approvalDocuments');

    const selectedFiles =
        document.getElementById('approvalSelectedFiles');

    documentInput?.addEventListener('change', () => {
        if (!selectedFiles) {
            return;
        }

        selectedFiles.innerHTML = '';

        Array.from(documentInput.files).forEach((file) => {
            const item = document.createElement('div');
            item.className = 'approval-file-item';

            item.innerHTML = `
                <i class="bi bi-file-earmark"></i>
                <span>${escapeHtml(file.name)}</span>
                <small>${formatFileSize(file.size)}</small>
            `;

            selectedFiles.appendChild(item);
        });
    });

    const searchInput =
        document.getElementById('approvalSearch');

    const typeFilter =
        document.getElementById('approvalTypeFilter');

    const statusFilter =
        document.getElementById('approvalStatusFilter');

    const priorityFilter =
        document.getElementById('approvalPriorityFilter');

    const resetButton =
        document.getElementById('btnResetApprovalFilters');

    const rows = Array.from(
        document.querySelectorAll('.approval-data-row')
    );

    const noResultRow =
        document.getElementById('approvalNoFilterResult');

    const countLabel =
        document.getElementById('approvalRecordCount');

    function applyFilters() {
        const search =
            (searchInput?.value || '')
                .trim()
                .toLowerCase();

        const type = typeFilter?.value || '';
        const status = statusFilter?.value || '';
        const priority = priorityFilter?.value || '';

        let visible = 0;

        rows.forEach((row) => {
            const matchesSearch =
                !search ||
                (row.dataset.search || '').includes(search);

            const matchesType =
                !type ||
                row.dataset.type === type;

            const matchesStatus =
                !status ||
                row.dataset.status === status;

            const matchesPriority =
                !priority ||
                row.dataset.priority === priority;

            const show =
                matchesSearch &&
                matchesType &&
                matchesStatus &&
                matchesPriority;

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
                `Showing ${visible} approval record(s)`;
        }
    }

    searchInput?.addEventListener('input', applyFilters);
    typeFilter?.addEventListener('change', applyFilters);
    statusFilter?.addEventListener('change', applyFilters);
    priorityFilter?.addEventListener('change', applyFilters);

    resetButton?.addEventListener('click', () => {
        if (searchInput) {
            searchInput.value = '';
        }

        if (typeFilter) {
            typeFilter.value = '';
        }

        if (statusFilter) {
            statusFilter.value = '';
        }

        if (priorityFilter) {
            priorityFilter.value = '';
        }

        applyFilters();
    });

    function escapeHtml(value) {
        const element = document.createElement('div');
        element.textContent = value;
        return element.innerHTML;
    }

    function formatFileSize(bytes) {
        if (bytes < 1024) {
            return `${bytes} B`;
        }

        if (bytes < 1024 * 1024) {
            return `${(bytes / 1024).toFixed(1)} KB`;
        }

        return `${(
            bytes /
            (1024 * 1024)
        ).toFixed(1)} MB`;
    }
});
