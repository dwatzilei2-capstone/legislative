/**
 * assets/js/implementation.js
 * ------------------------------------------------------------------
 * Client-side behavior for Implementation Monitoring.
 * ------------------------------------------------------------------
 */

'use strict';

document.addEventListener('DOMContentLoaded', () => {
    const modalElement =
        document.getElementById('implementationModal');

    const modal = modalElement
        ? new bootstrap.Modal(modalElement)
        : null;

    const itemId =
        document.getElementById('implementationItemId');

    const itemReference =
        document.getElementById('implementationRecordReference');

    const itemTitle =
        document.getElementById('implementationRecordTitle');

    document.querySelectorAll(
        '[data-process-implementation]'
    ).forEach((button) => {
        button.addEventListener('click', () => {
            if (itemId) {
                itemId.value =
                    button.dataset.processImplementation || '';
            }

            if (itemReference) {
                itemReference.textContent =
                    button.dataset.reference ||
                    'Legislative measure';
            }

            if (itemTitle) {
                itemTitle.textContent =
                    button.dataset.title ||
                    'Implementation monitoring';
            }

            modal?.show();
        });
    });

    const tabs = Array.from(
        document.querySelectorAll(
            '[data-implementation-tab]'
        )
    );

    const sections = Array.from(
        document.querySelectorAll(
            '[data-implementation-section]'
        )
    );

    tabs.forEach((tab) => {
        tab.addEventListener('click', () => {
            const target = tab.dataset.implementationTab;

            tabs.forEach((item) => {
                item.classList.toggle(
                    'active',
                    item === tab
                );
            });

            sections.forEach((section) => {
                section.classList.toggle(
                    'active',
                    section.dataset.implementationSection === target
                );
            });
        });
    });

    const progressInput =
        document.getElementById('implementationProgress');

    const progressLabel =
        document.getElementById('implementationProgressLabel');

    const progressBar =
        document.getElementById('implementationProgressBar');

    progressInput?.addEventListener('input', () => {
        let value = Number(progressInput.value || 0);

        value = Math.max(0, Math.min(100, value));

        if (progressLabel) {
            progressLabel.textContent = `${value}%`;
        }

        if (progressBar) {
            progressBar.style.width = `${value}%`;
        }
    });

    const milestoneTitle =
        document.getElementById('milestoneTitle');

    const milestoneTargetDate =
        document.getElementById('milestoneTargetDate');

    const milestoneStatus =
        document.getElementById('milestoneStatus');

    const milestoneList =
        document.getElementById('implementationMilestoneList');

    document.getElementById(
        'btnAddMilestone'
    )?.addEventListener('click', () => {
        const title = (milestoneTitle?.value || '').trim();

        if (!title) {
            Swal.fire({
                icon: 'warning',
                title: 'Enter a milestone',
                text: 'Provide a milestone title before adding it.',
            });

            return;
        }

        if (milestoneList) {
            const empty =
                milestoneList.querySelector(
                    '.implementation-milestone-empty'
                );

            empty?.remove();

            const item = document.createElement('div');
            item.className = 'implementation-milestone-item';

            item.innerHTML = `
                <span class="milestone-icon">
                    <i class="bi bi-flag"></i>
                </span>

                <div>
                    <strong>${escapeHtml(title)}</strong>
                    <small>
                        ${escapeHtml(milestoneTargetDate?.value || 'No target date')}
                    </small>
                </div>

                <span class="milestone-status">
                    ${escapeHtml(milestoneStatus?.value || 'Pending')}
                </span>

                <button
                    type="button"
                    class="milestone-remove"
                    title="Remove milestone"
                >
                    <i class="bi bi-x"></i>
                </button>
            `;

            item.querySelector(
                '.milestone-remove'
            )?.addEventListener('click', () => {
                item.remove();

                if (
                    milestoneList &&
                    milestoneList.children.length === 0
                ) {
                    milestoneList.innerHTML = `
                        <div class="implementation-milestone-empty">
                            <i class="bi bi-list-check"></i>
                            <span>Added milestones will appear here.</span>
                        </div>
                    `;
                }
            });

            milestoneList.appendChild(item);
        }

        if (milestoneTitle) {
            milestoneTitle.value = '';
        }

        if (milestoneTargetDate) {
            milestoneTargetDate.value = '';
        }

        if (milestoneStatus) {
            milestoneStatus.value = 'Pending';
        }
    });

    document.getElementById(
        'implementationForm'
    )?.addEventListener('submit', (event) => {
        event.preventDefault();

        Swal.fire({
            icon: 'success',
            title: 'Implementation Interface Ready',
            text:
                'The implementation plan, progress, milestone, and document interface is complete. The AJAX save endpoints will be connected during backend implementation.',
            confirmButtonText: 'Continue',
        }).then(() => {
            modal?.hide();
        });
    });

    document.getElementById(
        'btnOpenImplementationGuide'
    )?.addEventListener('click', () => {
        Swal.fire({
            icon: 'info',
            title: 'Implementation Monitoring Guide',
            html:
                '<div class="text-start small">' +
                '<p><strong>1.</strong> Assign the responsible implementation office.</p>' +
                '<p><strong>2.</strong> Set planned start and completion dates.</p>' +
                '<p><strong>3.</strong> Define measurable implementation milestones.</p>' +
                '<p><strong>4.</strong> Record periodic progress and accomplishments.</p>' +
                '<p><strong>5.</strong> Escalate risks, delays, and required interventions.</p>' +
                '</div>',
        });
    });

    document.querySelectorAll(
        '[data-view-milestones]'
    ).forEach((button) => {
        button.addEventListener('click', () => {
            Swal.fire({
                icon: 'info',
                title: 'Implementation Milestones',
                text:
                    'The milestone timeline will be connected during backend implementation.',
            });
        });
    });

    document.getElementById(
        'btnAssignImplementationOffice'
    )?.addEventListener('click', () => {
        Swal.fire({
            icon: 'info',
            title: 'Responsible Office',
            text:
                'Responsible-office assignment will be connected to the implementation database tables.',
        });
    });

    document.getElementById(
        'btnUploadImplementationDocument'
    )?.addEventListener('click', () => {
        Swal.fire({
            icon: 'info',
            title: 'Monitoring Document Upload',
            text:
                'Progress report and supporting document upload will be connected during backend implementation.',
        });
    });

    const documentInput =
        document.getElementById('implementationDocuments');

    const selectedFiles =
        document.getElementById('implementationSelectedFiles');

    documentInput?.addEventListener('change', () => {
        if (!selectedFiles) {
            return;
        }

        selectedFiles.innerHTML = '';

        Array.from(documentInput.files).forEach((file) => {
            const item = document.createElement('div');
            item.className = 'implementation-file-item';

            item.innerHTML = `
                <i class="bi bi-file-earmark"></i>
                <span>${escapeHtml(file.name)}</span>
                <small>${formatFileSize(file.size)}</small>
            `;

            selectedFiles.appendChild(item);
        });
    });

    const searchInput =
        document.getElementById('implementationSearch');

    const typeFilter =
        document.getElementById('implementationTypeFilter');

    const statusFilter =
        document.getElementById('implementationStatusFilter');

    const priorityFilter =
        document.getElementById('implementationPriorityFilter');

    const resetButton =
        document.getElementById('btnResetImplementationFilters');

    const rows = Array.from(
        document.querySelectorAll('.implementation-data-row')
    );

    const noResultRow =
        document.getElementById('implementationNoFilterResult');

    const countLabel =
        document.getElementById('implementationRecordCount');

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
                `Showing ${visible} implementation record(s)`;
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
