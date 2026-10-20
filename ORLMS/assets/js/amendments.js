/**
 * assets/js/amendments.js
 * ------------------------------------------------------------------
 * Client-side behavior for Amendment and Revision Tracking.
 * ------------------------------------------------------------------
 */

'use strict';

document.addEventListener('DOMContentLoaded', () => {
    const modalElement =
        document.getElementById('amendmentModal');

    const modal = modalElement
        ? new bootstrap.Modal(modalElement)
        : null;

    const form =
        document.getElementById('amendmentForm');

    const createButtons = document.querySelectorAll(
        '#btnCreateAmendment, [data-create-amendment]'
    );

    const stepButtons = Array.from(
        document.querySelectorAll(
            '[data-amendment-step]'
        )
    );

    const sections = Array.from(
        document.querySelectorAll(
            '[data-amendment-section]'
        )
    );

    const previousButton =
        document.getElementById('btnPreviousAmendmentStep');

    const nextButton =
        document.getElementById('btnNextAmendmentStep');

    const submitButton =
        document.getElementById('btnSubmitAmendment');

    let currentStep = 1;
    const maximumStep = 4;

    function showStep(step) {
        currentStep = step;

        stepButtons.forEach((button) => {
            button.classList.toggle(
                'active',
                Number(button.dataset.amendmentStep) === step
            );
        });

        sections.forEach((section) => {
            section.classList.toggle(
                'active',
                Number(section.dataset.amendmentSection) === step
            );
        });

        if (previousButton) {
            previousButton.disabled = step === 1;
        }

        nextButton?.classList.toggle(
            'd-none',
            step === maximumStep
        );

        submitButton?.classList.toggle(
            'd-none',
            step !== maximumStep
        );
    }

    function openCreateModal() {
        if (!modal || !form) {
            return;
        }

        form.reset();

        const amendmentId =
            document.getElementById('amendmentId');

        const modalTitle =
            document.getElementById('amendmentModalTitle');

        if (amendmentId) {
            amendmentId.value = '';
        }

        if (modalTitle) {
            modalTitle.textContent =
                'Create Amendment Proposal';
        }

        resetParentPreview();
        showStep(1);
        modal.show();
    }

    createButtons.forEach((button) => {
        button.addEventListener(
            'click',
            openCreateModal
        );
    });

    stepButtons.forEach((button) => {
        button.addEventListener('click', () => {
            showStep(
                Number(button.dataset.amendmentStep)
            );
        });
    });

    previousButton?.addEventListener('click', () => {
        if (currentStep > 1) {
            showStep(currentStep - 1);
        }
    });

    nextButton?.addEventListener('click', () => {
        if (currentStep < maximumStep) {
            showStep(currentStep + 1);
        }
    });

    const parentMeasure =
        document.getElementById('parentMeasure');

    const parentPreview =
        document.getElementById('parentMeasurePreview');

    parentMeasure?.addEventListener('change', () => {
        const selected =
            parentMeasure.options[
                parentMeasure.selectedIndex
            ];

        if (!selected || !selected.value) {
            resetParentPreview();
            return;
        }

        if (parentPreview) {
            parentPreview.innerHTML = `
                <i class="bi bi-file-earmark-check"></i>

                <div>
                    <strong>
                        ${escapeHtml(
                            selected.dataset.reference || ''
                        )}
                    </strong>

                    <span>
                        ${escapeHtml(
                            selected.dataset.type || ''
                        )} ·
                        ${escapeHtml(
                            selected.dataset.status || ''
                        )}
                    </span>

                    <small>
                        ${escapeHtml(
                            selected.dataset.title || ''
                        )}
                    </small>
                </div>
            `;
        }
    });

    function resetParentPreview() {
        if (!parentPreview) {
            return;
        }

        parentPreview.innerHTML = `
            <i class="bi bi-file-earmark-text"></i>

            <div>
                <strong>No parent measure selected</strong>

                <span>
                    Select a legislative record to display
                    its information.
                </span>
            </div>
        `;
    }

    document.getElementById(
        'btnPreviewComparison'
    )?.addEventListener('click', () => {
        const currentText =
            document.getElementById(
                'currentLegislativeText'
            )?.value || '';

        const proposedText =
            document.getElementById(
                'proposedLegislativeText'
            )?.value || '';

        if (!currentText.trim() && !proposedText.trim()) {
            Swal.fire({
                icon: 'warning',
                title: 'No text to compare',
                text:
                    'Enter the current and proposed legislative text first.',
            });

            return;
        }

        Swal.fire({
            title: 'Text Comparison Preview',
            width: 1000,
            html: `
                <div class="amendment-swal-comparison">
                    <div>
                        <strong>Current Text</strong>
                        <pre>${escapeHtml(
                            currentText || 'No current text entered.'
                        )}</pre>
                    </div>

                    <div>
                        <strong>Proposed Text</strong>
                        <pre>${escapeHtml(
                            proposedText || 'No proposed text entered.'
                        )}</pre>
                    </div>
                </div>
            `,
        });
    });

    document.getElementById(
        'btnClearComparison'
    )?.addEventListener('click', () => {
        const currentText =
            document.getElementById(
                'currentLegislativeText'
            );

        const proposedText =
            document.getElementById(
                'proposedLegislativeText'
            );

        if (currentText) {
            currentText.value = '';
        }

        if (proposedText) {
            proposedText.value = '';
        }
    });

    document.getElementById(
        'btnSaveAmendmentDraft'
    )?.addEventListener('click', () => {
        Swal.fire({
            icon: 'info',
            title: 'Amendment Draft UI Ready',
            text:
                'Draft saving will be connected during backend implementation.',
        });
    });

    form?.addEventListener('submit', (event) => {
        event.preventDefault();

        Swal.fire({
            icon: 'success',
            title: 'Amendment Interface Ready',
            text:
                'The amendment, comparison, impact, and document interface is complete. The AJAX save endpoints will be connected during backend implementation.',
            confirmButtonText: 'Continue',
        }).then(() => {
            modal?.hide();
        });
    });

    document.querySelectorAll(
        '[data-edit-amendment]'
    ).forEach((button) => {
        button.addEventListener('click', () => {
            if (modal && form) {
                const modalTitle =
                    document.getElementById(
                        'amendmentModalTitle'
                    );

                const amendmentId =
                    document.getElementById(
                        'amendmentId'
                    );

                if (modalTitle) {
                    modalTitle.textContent =
                        'Edit Amendment Proposal';
                }

                if (amendmentId) {
                    amendmentId.value =
                        button.dataset.editAmendment || '';
                }

                showStep(1);
                modal.show();
                return;
            }

            Swal.fire({
                icon: 'info',
                title: 'Edit Amendment',
                text:
                    'The amendment edit endpoint will load the saved version during backend implementation.',
            });
        });
    });

    document.querySelectorAll(
        '[data-compare-versions]'
    ).forEach((button) => {
        button.addEventListener('click', () => {
            Swal.fire({
                icon: 'info',
                title: 'Version Comparison',
                text:
                    'The saved current-versus-proposed comparison will be connected to the amendment version tables.',
            });
        });
    });

    document.querySelectorAll(
        '[data-view-history]'
    ).forEach((button) => {
        button.addEventListener('click', () => {
            Swal.fire({
                icon: 'info',
                title: 'Version History',
                text:
                    'The complete legislative version timeline will be connected during backend implementation.',
            });
        });
    });

    document.getElementById(
        'btnAssignAmendmentSponsor'
    )?.addEventListener('click', () => {
        Swal.fire({
            icon: 'info',
            title: 'Amendment Sponsor',
            text:
                'Sponsor assignment will be connected to the amendment database tables.',
        });
    });

    document.getElementById(
        'btnUploadAmendmentDocument'
    )?.addEventListener('click', () => {
        Swal.fire({
            icon: 'info',
            title: 'Amendment Document Upload',
            text:
                'Document upload will be connected during backend implementation.',
        });
    });

    const documentInput =
        document.getElementById('amendmentDocuments');

    const selectedFiles =
        document.getElementById('amendmentSelectedFiles');

    documentInput?.addEventListener('change', () => {
        if (!selectedFiles) {
            return;
        }

        selectedFiles.innerHTML = '';

        Array.from(documentInput.files).forEach((file) => {
            const item = document.createElement('div');
            item.className = 'amendment-file-item';

            item.innerHTML = `
                <i class="bi bi-file-earmark"></i>
                <span>${escapeHtml(file.name)}</span>
                <small>${formatFileSize(file.size)}</small>
            `;

            selectedFiles.appendChild(item);
        });
    });

    const searchInput =
        document.getElementById('amendmentSearch');

    const typeFilter =
        document.getElementById('amendmentTypeFilter');

    const statusFilter =
        document.getElementById('amendmentStatusFilter');

    const priorityFilter =
        document.getElementById('amendmentPriorityFilter');

    const resetButton =
        document.getElementById('btnResetAmendmentFilters');

    const rows = Array.from(
        document.querySelectorAll('.amendment-data-row')
    );

    const noResultRow =
        document.getElementById('amendmentNoFilterResult');

    const countLabel =
        document.getElementById('amendmentRecordCount');

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
                `Showing ${visible} amendment record(s)`;
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
