'use strict';

document.addEventListener('DOMContentLoaded', () => {
    const modalElement =
        document.getElementById('synchronizationModal');

    const modal = modalElement
        ? new bootstrap.Modal(modalElement)
        : null;

    const form =
        document.getElementById('synchronizationForm');

    const stepButtons = Array.from(
        document.querySelectorAll('[data-sync-step]')
    );

    const sections = Array.from(
        document.querySelectorAll(
            '[data-sync-form-section]'
        )
    );

    const previousButton =
        document.getElementById('btnPreviousSyncStep');

    const nextButton =
        document.getElementById('btnNextSyncStep');

    const submitButton =
        document.getElementById(
            'btnSubmitSynchronization'
        );

    let currentStep = 1;
    const maximumStep = 4;
    const previewRecords = [];

    function showStep(step) {
        currentStep = step;

        stepButtons.forEach((button) => {
            button.classList.toggle(
                'active',
                Number(button.dataset.syncStep) === step
            );
        });

        sections.forEach((section) => {
            section.classList.toggle(
                'active',
                Number(section.dataset.syncFormSection) === step
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

    function openSynchronization(options = {}) {
        if (!modal || !form) {
            return;
        }

        form.reset();

        setText(
            'synchronizationModalTitle',
            options.reference
                ? `Create Sync Record: ${options.reference}`
                : 'Create Executive-Legislative Sync Record'
        );

        setValue('syncExecutiveProgram', '');
        setValue(
            'syncCoordinationDate',
            toDateKey(new Date())
        );

        setValue(
            'syncTargetDate',
            toDateKey(addDays(new Date(), 30))
        );

        const select =
            document.getElementById('syncMeasureSelect');

        if (select) {
            select.value = options.candidateId || '';
            select.dispatchEvent(new Event('change'));
        }

        resetActionBuilder();
        calculateAlignmentScore();
        showStep(1);
        modal.show();
    }

    document.getElementById('btnCreateSynchronization')
        ?.addEventListener('click', () => {
            openSynchronization();
        });

    document.getElementById('btnQuickSynchronization')
        ?.addEventListener('click', () => {
            openSynchronization();
        });

    document.querySelectorAll('[data-create-sync]')
        .forEach((button) => {
            button.addEventListener('click', () => {
                openSynchronization({
                    candidateId:
                        button.dataset.createSync || '',
                    reference:
                        button.dataset.reference || '',
                    title:
                        button.dataset.title || ''
                });
            });
        });

    const candidateFromUrl =
        new URLSearchParams(window.location.search)
            .get('sync');

    if (candidateFromUrl) {
        const button = document.querySelector(
            `[data-create-sync="${CSS.escape(
                candidateFromUrl
            )}"]`
        );

        button?.click();
    }

    stepButtons.forEach((button) => {
        button.addEventListener('click', () => {
            showStep(
                Number(button.dataset.syncStep)
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
        document.getElementById('syncMeasureSelect');

    const measurePreview =
        document.getElementById('syncMeasurePreview');

    measureSelect?.addEventListener('change', () => {
        const option =
            measureSelect.options[
                measureSelect.selectedIndex
            ];

        if (!option || !option.value) {
            if (measurePreview) {
                measurePreview.innerHTML = `
                    <i class="bi bi-file-earmark-text"></i>

                    <div>
                        <strong>No legislative measure selected</strong>

                        <span>
                            Select an ordinance or resolution
                            to begin synchronization.
                        </span>
                    </div>
                `;
            }

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
                        ·
                        ${escapeHtml(option.dataset.priority || '')}
                    </span>

                    <small>
                        ${escapeHtml(option.dataset.title || '')}
                    </small>
                </div>
            `;
        }
    });

    document.querySelectorAll('[data-sync-score]')
        .forEach((input) => {
            input.addEventListener(
                'change',
                calculateAlignmentScore
            );
        });

    function calculateAlignmentScore() {
        const selected = Array.from(
            document.querySelectorAll(
                '[data-sync-score]:checked'
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

        let result = 'Conflict';

        if (score >= 85) {
            result = 'Aligned';
        } else if (score >= 65) {
            result = 'Partially Aligned';
        } else if (score >= 45) {
            result = 'Requires Discussion';
        }

        setText('syncScoreValue', score);
        setText('syncScoreResult', result);

        const bar =
            document.getElementById('syncScoreBar');

        if (bar) {
            bar.style.width = `${score}%`;
        }

        const statusSelect =
            document.getElementById('syncAlignmentStatus');

        if (statusSelect) {
            const options = Array.from(
                statusSelect.options
            );

            const matchingOption = options.find(
                (option) => option.value === result
            );

            if (matchingOption) {
                statusSelect.value = result;
            }
        }
    }

    calculateAlignmentScore();

    document.getElementById('btnOpenConflictReview')
        ?.addEventListener('click', () => {
            Swal.fire({
                icon: 'info',
                title: 'Alignment Conflict Review',
                text:
                    'The final review will compare saved legal, policy, budget, implementation, scheduling, and responsibility conflicts.',
            });
        });

    document.getElementById('btnSaveSyncDraft')
        ?.addEventListener('click', () => {
            Swal.fire({
                icon: 'info',
                title: 'Synchronization Draft UI Ready',
                text:
                    'Draft saving will be connected after the dedicated synchronization tables and AJAX endpoints are created.',
            });
        });

    form?.addEventListener('submit', (event) => {
        event.preventDefault();

        const measureOption =
            document.getElementById('syncMeasureSelect')
                ?.selectedOptions?.[0];

        const executiveProgram =
            document.getElementById('syncExecutiveProgram')
                ?.value.trim() || '';

        const executiveOffice =
            document.getElementById('syncExecutiveOffice')
                ?.selectedOptions?.[0]
                ?.dataset.name || '';

        const alignmentStatus =
            document.getElementById('syncAlignmentStatus')
                ?.value || 'Partially Aligned';

        const activityStatus =
            document.getElementById('syncActivityStatus')
                ?.value || 'For Coordination';

        if (!measureOption?.value || !executiveProgram) {
            Swal.fire({
                icon: 'warning',
                title: 'Missing synchronization information',
                text:
                    'Select a legislative measure and enter its executive program, policy, or commitment.',
            });

            showStep(1);
            return;
        }

        previewRecords.push({
            reference:
                measureOption.dataset.reference || '',
            legislativeTitle:
                measureOption.dataset.title || '',
            executiveProgram,
            executiveOffice:
                executiveOffice || 'Executive office pending',
            alignmentStatus,
            activityStatus
        });

        renderBoard();

        Swal.fire({
            icon: 'success',
            title: 'Synchronization Preview Added',
            text:
                'The synchronization record was added to this browser preview. It is not yet stored in MySQL.',
        }).then(() => {
            modal?.hide();
        });
    });

    function renderBoard() {
        const legislativeColumn =
            document.getElementById(
                'syncLegislativeColumn'
            );

        const executiveColumn =
            document.getElementById(
                'syncExecutiveColumn'
            );

        const jointColumn =
            document.getElementById(
                'syncJointColumn'
            );

        if (
            !legislativeColumn ||
            !executiveColumn ||
            !jointColumn ||
            !previewRecords.length
        ) {
            return;
        }

        legislativeColumn.innerHTML =
            previewRecords.map((record) => `
                <div class="sync-board-card">
                    <span class="board-card-label">
                        ${escapeHtml(record.reference)}
                    </span>

                    <strong>
                        ${escapeHtml(record.legislativeTitle)}
                    </strong>
                </div>
            `).join('');

        executiveColumn.innerHTML =
            previewRecords.map((record) => `
                <div class="sync-board-card executive">
                    <span class="board-card-label">
                        ${escapeHtml(record.executiveOffice)}
                    </span>

                    <strong>
                        ${escapeHtml(record.executiveProgram)}
                    </strong>
                </div>
            `).join('');

        jointColumn.innerHTML =
            previewRecords.map((record) => `
                <div class="sync-board-card joint">
                    <span class="board-card-label">
                        ${escapeHtml(record.alignmentStatus)}
                    </span>

                    <strong>
                        ${escapeHtml(record.activityStatus)}
                    </strong>
                </div>
            `).join('');
    }

    const actionBuilder =
        document.getElementById('syncActionBuilder');

    document.getElementById('btnAddSyncAction')
        ?.addEventListener('click', () => {
            if (!actionBuilder) {
                return;
            }

            const item = document.createElement('div');

            item.className = 'sync-action-item';
            item.dataset.syncActionItem = '';

            item.innerHTML = `
                <span class="action-order">1</span>

                <div class="action-fields">
                    <input
                        type="text"
                        class="form-control"
                        name="action_title[]"
                        placeholder="Joint action item"
                    >

                    <div class="row g-2">
                        <div class="col-md-6">
                            <input
                                type="text"
                                class="form-control"
                                name="action_owner[]"
                                placeholder="Responsible office or person"
                            >
                        </div>

                        <div class="col-md-4">
                            <input
                                type="date"
                                class="form-control"
                                name="action_due_date[]"
                            >
                        </div>

                        <div class="col-md-2">
                            <select
                                class="form-select"
                                name="action_status[]"
                            >
                                <option>Open</option>
                                <option>In Progress</option>
                                <option>Completed</option>
                            </select>
                        </div>
                    </div>
                </div>

                <button
                    type="button"
                    class="action-remove-button"
                    data-remove-sync-action
                    title="Remove action item"
                >
                    <i class="bi bi-trash"></i>
                </button>
            `;

            actionBuilder.appendChild(item);
            updateActionOrder();
        });

    actionBuilder?.addEventListener('click', (event) => {
        const removeButton =
            event.target.closest(
                '[data-remove-sync-action]'
            );

        if (!removeButton) {
            return;
        }

        const items =
            actionBuilder.querySelectorAll(
                '[data-sync-action-item]'
            );

        if (items.length <= 1) {
            Swal.fire({
                icon: 'info',
                title: 'Joint Action Required',
                text:
                    'Keep at least one joint action row.',
            });

            return;
        }

        removeButton
            .closest('[data-sync-action-item]')
            ?.remove();

        updateActionOrder();
    });

    function updateActionOrder() {
        actionBuilder
            ?.querySelectorAll('[data-sync-action-item]')
            .forEach((item, index) => {
                const order =
                    item.querySelector('.action-order');

                if (order) {
                    order.textContent =
                        String(index + 1);
                }
            });
    }

    function resetActionBuilder() {
        if (!actionBuilder) {
            return;
        }

        const items = Array.from(
            actionBuilder.querySelectorAll(
                '[data-sync-action-item]'
            )
        );

        items.slice(1).forEach((item) => {
            item.remove();
        });

        items[0]
            ?.querySelectorAll('input, textarea')
            .forEach((field) => {
                field.value = '';
            });

        items[0]
            ?.querySelectorAll('select')
            .forEach((field) => {
                field.selectedIndex = 0;
            });

        updateActionOrder();
    }

    const documentInput =
        document.getElementById('syncDocuments');

    const selectedFiles =
        document.getElementById('syncSelectedFiles');

    documentInput?.addEventListener('change', () => {
        if (!selectedFiles) {
            return;
        }

        selectedFiles.innerHTML = '';

        Array.from(documentInput.files).forEach((file) => {
            const item =
                document.createElement('div');

            item.className = 'sync-file-item';

            item.innerHTML = `
                <i class="bi bi-file-earmark"></i>
                <span>${escapeHtml(file.name)}</span>
                <small>${formatFileSize(file.size)}</small>
            `;

            selectedFiles.appendChild(item);
        });
    });

    document.querySelectorAll('[data-sync-history]')
        .forEach((button) => {
            button.addEventListener('click', () => {
                Swal.fire({
                    icon: 'info',
                    title: 'Synchronization History',
                    text:
                        'Alignment reviews, executive responses, conflicts, agreements, joint actions, approvals, and communication history will be connected later.',
                });
            });
        });

    const checks = Array.from(
        document.querySelectorAll('.sync-check')
    );

    function updateChecklist() {
        if (!checks.length) {
            return;
        }

        const completed =
            checks.filter((item) => item.checked).length;

        const percent =
            Math.round(
                (completed / checks.length) * 100
            );

        setText(
            'syncChecklistValue',
            `${percent}%`
        );

        const bar =
            document.getElementById('syncChecklistBar');

        if (bar) {
            bar.style.width = `${percent}%`;
        }
    }

    checks.forEach((item) => {
        item.addEventListener(
            'change',
            updateChecklist
        );
    });

    const searchInput =
        document.getElementById('syncSearch');

    const typeFilter =
        document.getElementById('syncTypeFilter');

    const priorityFilter =
        document.getElementById('syncPriorityFilter');

    const statusFilter =
        document.getElementById('syncStatusFilter');

    const rows = Array.from(
        document.querySelectorAll('.sync-data-row')
    );

    const noResult =
        document.getElementById('syncNoFilterResult');

    const countLabel =
        document.getElementById('syncRecordCount');

    function applyFilters() {
        const search =
            (searchInput?.value || '')
                .trim()
                .toLowerCase();

        const type =
            typeFilter?.value || '';

        const priority =
            priorityFilter?.value || '';

        const status =
            statusFilter?.value || '';

        let visible = 0;

        rows.forEach((row) => {
            const show =
                (
                    !search ||
                    (row.dataset.search || '')
                        .includes(search)
                ) &&
                (
                    !type ||
                    row.dataset.type === type
                ) &&
                (
                    !priority ||
                    row.dataset.priority === priority
                ) &&
                (
                    !status ||
                    row.dataset.status === status
                );

            row.classList.toggle('d-none', !show);

            if (show) {
                visible++;
            }
        });

        noResult?.classList.toggle(
            'd-none',
            visible > 0 || rows.length === 0
        );

        if (countLabel) {
            countLabel.textContent =
                `Showing ${visible} candidate record(s)`;
        }
    }

    searchInput?.addEventListener(
        'input',
        applyFilters
    );

    typeFilter?.addEventListener(
        'change',
        applyFilters
    );

    priorityFilter?.addEventListener(
        'change',
        applyFilters
    );

    statusFilter?.addEventListener(
        'change',
        applyFilters
    );

    document.getElementById('btnResetSyncFilters')
        ?.addEventListener('click', () => {
            if (searchInput) {
                searchInput.value = '';
            }

            if (typeFilter) {
                typeFilter.value = '';
            }

            if (priorityFilter) {
                priorityFilter.value = '';
            }

            if (statusFilter) {
                statusFilter.value = '';
            }

            applyFilters();
        });

    function addDays(date, days) {
        const copy = new Date(date);
        copy.setDate(copy.getDate() + days);
        return copy;
    }

    function toDateKey(date) {
        const year = date.getFullYear();

        const month =
            String(date.getMonth() + 1)
                .padStart(2, '0');

        const day =
            String(date.getDate())
                .padStart(2, '0');

        return `${year}-${month}-${day}`;
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

    function formatFileSize(bytes) {
        if (bytes < 1024) {
            return `${bytes} B`;
        }

        if (bytes < 1024 * 1024) {
            return `${(
                bytes / 1024
            ).toFixed(1)} KB`;
        }

        return `${(
            bytes /
            (1024 * 1024)
        ).toFixed(1)} MB`;
    }
});
