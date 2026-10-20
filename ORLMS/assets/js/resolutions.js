'use strict';

document.addEventListener('DOMContentLoaded', () => {
    const modalElement =
        document.getElementById('resolutionModal');

    const form =
        document.getElementById('resolutionForm');

    let modal = null;

    if (modalElement) {
        modal = new bootstrap.Modal(modalElement);
    }

    const createButtons = document.querySelectorAll(
        '#btnCreateResolution, [data-create-resolution]'
    );

    const stepButtons = Array.from(
        document.querySelectorAll('.resolution-step')
    );

    const formSections = Array.from(
        document.querySelectorAll(
            '.resolution-form-section'
        )
    );

    const previousButton =
        document.getElementById(
            'btnPreviousResolutionStep'
        );

    const nextButton =
        document.getElementById(
            'btnNextResolutionStep'
        );

    const submitButton =
        document.getElementById(
            'btnSubmitResolutionForm'
        );

    const draftButton =
        document.getElementById(
            'btnSaveResolutionDraft'
        );

    const documentInput =
        document.getElementById(
            'resolutionDocuments'
        );

    const selectedFiles =
        document.getElementById(
            'resolutionSelectedFiles'
        );

    let currentStep = 1;
    const maximumStep = 4;

    function showStep(step) {
        currentStep = step;

        stepButtons.forEach((button) => {
            button.classList.toggle(
                'active',
                Number(button.dataset.step) === step
            );
        });

        formSections.forEach((section) => {
            section.classList.toggle(
                'active',
                Number(section.dataset.section) === step
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

        const idField =
            document.getElementById('resolutionId');

        const referenceField =
            document.getElementById(
                'resolutionReference'
            );

        const draftedDate =
            document.getElementById(
                'resolutionDateDrafted'
            );

        const modalTitle =
            document.getElementById(
                'resolutionModalTitle'
            );

        if (idField) {
            idField.value = '';
        }

        if (referenceField) {
            referenceField.value = '';
        }

        if (draftedDate) {
            draftedDate.value =
                new Date().toISOString().slice(0, 10);
        }

        if (modalTitle) {
            modalTitle.textContent =
                'Create Resolution Draft';
        }

        if (selectedFiles) {
            selectedFiles.innerHTML = '';
        }

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
            showStep(Number(button.dataset.step));
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

    documentInput?.addEventListener('change', () => {
        if (!selectedFiles) {
            return;
        }

        selectedFiles.innerHTML = '';

        Array.from(documentInput.files).forEach(
            (file) => {
                const item =
                    document.createElement('div');

                item.className =
                    'resolution-file-item';

                item.innerHTML = `
                    <i class="bi bi-file-earmark"></i>
                    <span>${escapeHtml(file.name)}</span>
                    <small>${formatFileSize(file.size)}</small>
                `;

                selectedFiles.appendChild(item);
            }
        );
    });

    draftButton?.addEventListener('click', () => {
        Swal.fire({
            icon: 'info',
            title: 'Resolution Draft',
            text:
                'The draft interface is ready. Database saving will be connected during the implementation phase.',
            confirmButtonText: 'OK',
        });
    });

    form?.addEventListener('submit', (event) => {
        event.preventDefault();

        Swal.fire({
            icon: 'success',
            title: 'Resolution Form Completed',
            text:
                'The complete submission interface is ready. The database endpoint will be connected next.',
            confirmButtonText: 'Continue',
        }).then(() => {
            modal?.hide();
        });
    });

    document.querySelectorAll(
        '[data-edit-resolution]'
    ).forEach((button) => {
        button.addEventListener('click', () => {
            Swal.fire({
                icon: 'info',
                title: 'Edit Resolution',
                text:
                    'Resolution data will be loaded after ajax_get.php is connected.',
            });
        });
    });

    document.querySelectorAll(
        '[data-submit-resolution]'
    ).forEach((button) => {
        button.addEventListener('click', () => {
            Swal.fire({
                icon: 'question',
                title: 'Submit Resolution?',
                text:
                    'This will send the resolution to the Review and Committee Endorsement module.',
                showCancelButton: true,
                confirmButtonText: 'Submit',
            });
        });
    });

    document.querySelectorAll(
        '[data-archive-resolution]'
    ).forEach((button) => {
        button.addEventListener('click', () => {
            Swal.fire({
                icon: 'warning',
                title: 'Archive Resolution?',
                text:
                    'The resolution will remain available in the audit and revision history.',
                showCancelButton: true,
                confirmButtonText: 'Archive',
                confirmButtonColor: '#b91c1c',
            });
        });
    });

    /*
     * Client-side search and filters.
     */
    const searchInput =
        document.getElementById('resolutionSearch');

    const statusFilter =
        document.getElementById(
            'resolutionStatusFilter'
        );

    const priorityFilter =
        document.getElementById(
            'resolutionPriorityFilter'
        );

    const officeFilter =
        document.getElementById(
            'resolutionOfficeFilter'
        );

    const resetButton =
        document.getElementById(
            'btnResetResolutionFilters'
        );

    const rows = Array.from(
        document.querySelectorAll(
            '.resolution-data-row'
        )
    );

    const noResultRow =
        document.getElementById(
            'resolutionNoFilterResult'
        );

    const countLabel =
        document.getElementById(
            'resolutionRecordCount'
        );

    function applyFilters() {
        const searchValue =
            (searchInput?.value || '')
                .trim()
                .toLowerCase();

        const statusValue =
            statusFilter?.value || '';

        const priorityValue =
            priorityFilter?.value || '';

        const officeValue =
            officeFilter?.value || '';

        let visibleCount = 0;

        rows.forEach((row) => {
            const matchesSearch =
                !searchValue ||
                row.dataset.search.includes(searchValue);

            const matchesStatus =
                !statusValue ||
                row.dataset.status === statusValue;

            const matchesPriority =
                !priorityValue ||
                row.dataset.priority === priorityValue;

            const matchesOffice =
                !officeValue ||
                row.dataset.office === officeValue;

            const visible =
                matchesSearch &&
                matchesStatus &&
                matchesPriority &&
                matchesOffice;

            row.classList.toggle('d-none', !visible);

            if (visible) {
                visibleCount++;
            }
        });

        noResultRow?.classList.toggle(
            'd-none',
            visibleCount > 0 || rows.length === 0
        );

        if (countLabel) {
            countLabel.textContent =
                `Showing ${visibleCount} resolution record(s)`;
        }
    }

    searchInput?.addEventListener(
        'input',
        applyFilters
    );

    statusFilter?.addEventListener(
        'change',
        applyFilters
    );

    priorityFilter?.addEventListener(
        'change',
        applyFilters
    );

    officeFilter?.addEventListener(
        'change',
        applyFilters
    );

    resetButton?.addEventListener('click', () => {
        if (searchInput) {
            searchInput.value = '';
        }

        if (statusFilter) {
            statusFilter.value = '';
        }

        if (priorityFilter) {
            priorityFilter.value = '';
        }

        if (officeFilter) {
            officeFilter.value = '';
        }

        applyFilters();
    });

    function escapeHtml(value) {
        const element =
            document.createElement('div');

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