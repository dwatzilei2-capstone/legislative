'use strict';

document.addEventListener('DOMContentLoaded', () => {
    const modalElement =
        document.getElementById('ordinanceModal');

    if (!modalElement) {
        return;
    }

    const modal = new bootstrap.Modal(modalElement);
    const form = document.getElementById('ordinanceForm');

    const createButtons = document.querySelectorAll(
        '#btnCreateOrdinance, [data-create-ordinance]'
    );

    const stepButtons = Array.from(
        document.querySelectorAll('.form-step')
    );

    const sections = Array.from(
        document.querySelectorAll(
            '.ordinance-form-section'
        )
    );

    const previousButton =
        document.getElementById(
            'btnPreviousOrdinanceStep'
        );

    const nextButton =
        document.getElementById(
            'btnNextOrdinanceStep'
        );

    const submitButton =
        document.getElementById(
            'btnSubmitOrdinanceForm'
        );

    const draftButton =
        document.getElementById(
            'btnSaveOrdinanceDraft'
        );

    const filesInput =
        document.getElementById(
            'ordinanceDocuments'
        );

    const fileList =
        document.getElementById(
            'ordinanceSelectedFiles'
        );

    let currentStep = 1;
    const maximumStep = 4;

    const showStep = (step) => {
        currentStep = step;

        stepButtons.forEach((button) => {
            button.classList.toggle(
                'active',
                Number(button.dataset.step) === step
            );
        });

        sections.forEach((section) => {
            section.classList.toggle(
                'active',
                Number(section.dataset.section) === step
            );
        });

        previousButton.disabled = step === 1;

        nextButton.classList.toggle(
            'd-none',
            step === maximumStep
        );

        submitButton.classList.toggle(
            'd-none',
            step !== maximumStep
        );
    };

    const openCreateModal = () => {
        form.reset();

        document.getElementById(
            'ordinanceId'
        ).value = '';

        document.getElementById(
            'ordinanceReference'
        ).value = '';

        document.getElementById(
            'ordinanceDateDrafted'
        ).value =
            new Date().toISOString().slice(0, 10);

        document.getElementById(
            'ordinanceModalTitle'
        ).textContent = 'Create Ordinance Draft';

        fileList.innerHTML = '';

        showStep(1);
        modal.show();
    };

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

    previousButton.addEventListener('click', () => {
        if (currentStep > 1) {
            showStep(currentStep - 1);
        }
    });

    nextButton.addEventListener('click', () => {
        if (currentStep < maximumStep) {
            showStep(currentStep + 1);
        }
    });

    filesInput?.addEventListener('change', () => {
        fileList.innerHTML = '';

        Array.from(filesInput.files).forEach(
            (file) => {
                const item =
                    document.createElement('div');

                item.className = 'selected-file-item';

                item.innerHTML = `
                    <i class="bi bi-file-earmark"></i>
                    <span>${escapeHtml(file.name)}</span>
                    <small>${formatFileSize(file.size)}</small>
                `;

                fileList.appendChild(item);
            }
        );
    });

    draftButton.addEventListener('click', () => {
        Swal.fire({
            icon: 'info',
            title: 'Draft UI Ready',
            text:
                'Database saving will be connected during the Ordinance Drafting implementation phase.',
            confirmButtonText: 'OK',
        });
    });

    form.addEventListener('submit', (event) => {
        event.preventDefault();

        Swal.fire({
            icon: 'success',
            title: 'Ordinance Form Completed',
            text:
                'The interface is ready. The save endpoint will be connected next.',
            confirmButtonText: 'Continue',
        }).then(() => {
            modal.hide();
        });
    });

    document.querySelectorAll(
        '[data-edit-ordinance]'
    ).forEach((button) => {
        button.addEventListener('click', () => {
            Swal.fire({
                icon: 'info',
                title: 'Edit Ordinance',
                text:
                    'The edit interface will load ordinance data after ajax_get.php is connected.',
            });
        });
    });

    document.querySelectorAll(
        '[data-submit-ordinance]'
    ).forEach((button) => {
        button.addEventListener('click', () => {
            Swal.fire({
                icon: 'question',
                title: 'Submit for Review?',
                text:
                    'This will move the ordinance to the Review and Committee Endorsement module.',
                showCancelButton: true,
                confirmButtonText: 'Submit',
            });
        });
    });

    document.querySelectorAll(
        '[data-archive-ordinance]'
    ).forEach((button) => {
        button.addEventListener('click', () => {
            Swal.fire({
                icon: 'warning',
                title: 'Archive Ordinance?',
                text:
                    'Archived records will remain in the audit history.',
                showCancelButton: true,
                confirmButtonText: 'Archive',
                confirmButtonColor: '#b91c1c',
            });
        });
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