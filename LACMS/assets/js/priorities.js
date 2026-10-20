/**
 * assets/js/priorities.js
 * ------------------------------------------------------------------
 * Legislative Priority Setting interactions.
 * ------------------------------------------------------------------
 */

'use strict';

document.addEventListener('DOMContentLoaded', () => {
    const modalElement =
        document.getElementById('priorityModal');

    const modal = modalElement
        ? new bootstrap.Modal(modalElement)
        : null;

    const form =
        document.getElementById('priorityForm');

    const openButtons = document.querySelectorAll(
        '#btnOpenPriorityModal, [data-set-priority]'
    );

    const steps = Array.from(
        document.querySelectorAll('[data-priority-step]')
    );

    const sections = Array.from(
        document.querySelectorAll(
            '[data-priority-section]'
        )
    );

    const previousButton =
        document.getElementById('btnPreviousPriorityStep');

    const nextButton =
        document.getElementById('btnNextPriorityStep');

    const submitButton =
        document.getElementById('btnSubmitPriority');

    let currentStep = 1;
    const maximumStep = 4;

    function showStep(step) {
        currentStep = step;

        steps.forEach((button) => {
            button.classList.toggle(
                'active',
                Number(button.dataset.priorityStep) === step
            );
        });

        sections.forEach((section) => {
            section.classList.toggle(
                'active',
                Number(section.dataset.prioritySection) === step
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

    openButtons.forEach((button) => {
        button.addEventListener('click', () => {
            if (!modal || !form) {
                return;
            }

            const recordId =
                button.dataset.setPriority || '';

            const reference =
                button.dataset.reference || '';

            const title =
                button.dataset.title || '';

            setValue('priorityRecordId', recordId);

            const select =
                document.getElementById(
                    'priorityMeasureSelect'
                );

            if (select && recordId) {
                select.value = recordId;
                select.dispatchEvent(new Event('change'));
            } else if (select && !recordId) {
                select.value = '';
                resetMeasurePreview();
            }

            setText(
                'priorityModalTitle',
                reference
                    ? `Evaluate Priority: ${reference}`
                    : 'Set Legislative Priority'
            );

            if (
                reference &&
                title &&
                !select
            ) {
                setText(
                    'priorityMeasurePreview',
                    `${reference} — ${title}`
                );
            }

            showStep(1);
            modal.show();
        });
    });

    steps.forEach((button) => {
        button.addEventListener('click', () => {
            showStep(
                Number(button.dataset.priorityStep)
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

    const measureSelect =
        document.getElementById('priorityMeasureSelect');

    const measurePreview =
        document.getElementById('priorityMeasurePreview');

    measureSelect?.addEventListener('change', () => {
        const option =
            measureSelect.options[
                measureSelect.selectedIndex
            ];

        if (!option || !option.value) {
            resetMeasurePreview();
            return;
        }

        if (measurePreview) {
            measurePreview.innerHTML = `
                <i class="bi bi-file-earmark-check"></i>

                <div>
                    <strong>
                        ${escapeHtml(
                            option.dataset.reference || ''
                        )}
                    </strong>

                    <span>
                        ${escapeHtml(option.dataset.type || '')}
                        ·
                        ${escapeHtml(option.dataset.status || '')}
                        · Current Priority:
                        ${escapeHtml(
                            option.dataset.currentPriority || ''
                        )}
                    </span>

                    <small>
                        ${escapeHtml(option.dataset.title || '')}
                    </small>
                </div>
            `;
        }
    });

    function resetMeasurePreview() {
        if (!measurePreview) {
            return;
        }

        measurePreview.innerHTML = `
            <i class="bi bi-file-earmark-text"></i>

            <div>
                <strong>No measure selected</strong>

                <span>
                    Select a legislative record to review
                    its current information.
                </span>
            </div>
        `;
    }

    document.querySelectorAll(
        '[data-score-field]'
    ).forEach((input) => {
        input.addEventListener(
            'change',
            calculatePriorityScore
        );
    });

    function calculatePriorityScore() {
        const selected = Array.from(
            document.querySelectorAll(
                '[data-score-field]:checked'
            )
        );

        let weightedTotal = 0;

        selected.forEach((input) => {
            const rating =
                Number(input.value || 0);

            const weight =
                Number(input.dataset.scoreWeight || 0);

            weightedTotal +=
                (rating / 5) * weight;
        });

        const score =
            Math.round(weightedTotal);

        const scoreValue =
            document.getElementById('priorityScoreValue');

        const scoreBar =
            document.getElementById('priorityScoreBar');

        const recommendation =
            document.getElementById(
                'priorityScoreRecommendation'
            );

        const recommendedSelect =
            document.getElementById(
                'recommendedPriority'
            );

        let level = 'Low';

        if (score >= 85) {
            level = 'Urgent';
        } else if (score >= 70) {
            level = 'High';
        } else if (score >= 50) {
            level = 'Normal';
        }

        if (scoreValue) {
            scoreValue.textContent = String(score);
        }

        if (scoreBar) {
            scoreBar.style.width = `${score}%`;
        }

        if (recommendation) {
            recommendation.textContent =
                `Recommended: ${level} Priority`;
        }

        if (recommendedSelect) {
            recommendedSelect.value = level;
        }
    }

    calculatePriorityScore();

    document.getElementById(
        'btnSavePriorityDraft'
    )?.addEventListener('click', () => {
        Swal.fire({
            icon: 'info',
            title: 'Priority Draft UI Ready',
            text:
                'Draft saving will be connected when the LACMS priority tables and AJAX endpoints are implemented.',
        });
    });

    form?.addEventListener('submit', (event) => {
        event.preventDefault();

        Swal.fire({
            icon: 'success',
            title: 'Priority Setting Interface Ready',
            text:
                'The evaluation, scoring, ranking, recommendation, and document interface is complete. Database write endpoints will be connected later.',
            confirmButtonText: 'Continue',
        }).then(() => {
            modal?.hide();
        });
    });

    document.querySelectorAll(
        '[data-view-history]'
    ).forEach((button) => {
        button.addEventListener('click', () => {
            Swal.fire({
                icon: 'info',
                title: 'Priority History',
                text:
                    'The complete priority-change history will be connected to the dedicated LACMS priority tables.',
            });
        });
    });

    const documentInput =
        document.getElementById('priorityDocuments');

    const selectedFiles =
        document.getElementById('prioritySelectedFiles');

    documentInput?.addEventListener('change', () => {
        if (!selectedFiles) {
            return;
        }

        selectedFiles.innerHTML = '';

        Array.from(documentInput.files).forEach((file) => {
            const item = document.createElement('div');
            item.className = 'priority-file-item';

            item.innerHTML = `
                <i class="bi bi-file-earmark"></i>
                <span>${escapeHtml(file.name)}</span>
                <small>${formatFileSize(file.size)}</small>
            `;

            selectedFiles.appendChild(item);
        });
    });

    const searchInput =
        document.getElementById('prioritySearch');

    const typeFilter =
        document.getElementById('priorityTypeFilter');

    const levelFilter =
        document.getElementById('priorityLevelFilter');

    const statusFilter =
        document.getElementById('priorityStatusFilter');

    const resetButton =
        document.getElementById('btnResetPriorityFilters');

    const rows = Array.from(
        document.querySelectorAll('.priority-data-row')
    );

    const noResultRow =
        document.getElementById('priorityNoFilterResult');

    const countLabel =
        document.getElementById('priorityRecordCount');

    function applyFilters() {
        const search =
            (searchInput?.value || '')
                .trim()
                .toLowerCase();

        const type =
            typeFilter?.value || '';

        const level =
            levelFilter?.value || '';

        const status =
            statusFilter?.value || '';

        let visible = 0;

        rows.forEach((row) => {
            const matchesSearch =
                !search ||
                (row.dataset.search || '').includes(search);

            const matchesType =
                !type ||
                row.dataset.type === type;

            const matchesLevel =
                !level ||
                row.dataset.level === level;

            const matchesStatus =
                !status ||
                row.dataset.status === status;

            const show =
                matchesSearch &&
                matchesType &&
                matchesLevel &&
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
                `Showing ${visible} legislative record(s)`;
        }
    }

    searchInput?.addEventListener('input', applyFilters);
    typeFilter?.addEventListener('change', applyFilters);
    levelFilter?.addEventListener('change', applyFilters);
    statusFilter?.addEventListener('change', applyFilters);

    resetButton?.addEventListener('click', () => {
        if (searchInput) {
            searchInput.value = '';
        }

        if (typeFilter) {
            typeFilter.value = '';
        }

        if (levelFilter) {
            levelFilter.value = '';
        }

        if (statusFilter) {
            statusFilter.value = '';
        }

        applyFilters();
    });

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

    function escapeHtml(value) {
        const element = document.createElement('div');
        element.textContent =
            value === null || value === undefined
                ? ''
                : String(value);

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
