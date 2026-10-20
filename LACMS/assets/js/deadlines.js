'use strict';

document.addEventListener('DOMContentLoaded', () => {
    const modalElement = document.getElementById('deadlineModal');
    const modal = modalElement
        ? new bootstrap.Modal(modalElement)
        : null;

    const form = document.getElementById('deadlineForm');
    const stepButtons = Array.from(
        document.querySelectorAll('[data-deadline-step]')
    );
    const sections = Array.from(
        document.querySelectorAll('[data-deadline-form-section]')
    );

    const previousButton =
        document.getElementById('btnPreviousDeadlineStep');
    const nextButton =
        document.getElementById('btnNextDeadlineStep');
    const submitButton =
        document.getElementById('btnSubmitDeadline');

    let currentStep = 1;
    const maximumStep = 4;
    const previewDeadlines = [];

    function showStep(step) {
        currentStep = step;

        stepButtons.forEach((button) => {
            button.classList.toggle(
                'active',
                Number(button.dataset.deadlineStep) === step
            );
        });

        sections.forEach((section) => {
            section.classList.toggle(
                'active',
                Number(section.dataset.deadlineFormSection) === step
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

    function openDeadline(options = {}) {
        if (!modal || !form) {
            return;
        }

        form.reset();

        setText(
            'deadlineModalTitle',
            options.reference
                ? `Create Deadline: ${options.reference}`
                : 'Create Legislative Deadline'
        );

        setValue('deadlineTitle', options.title || '');
        setValue(
            'deadlineDueDate',
            toDateKey(addDays(new Date(), 7))
        );
        setValue('deadlineDueTime', '17:00');

        const select =
            document.getElementById('deadlineMeasureSelect');

        if (select) {
            select.value = options.candidateId || '';
            select.dispatchEvent(new Event('change'));
        }

        resetMilestoneBuilder();
        showStep(1);
        modal.show();
    }

    document.getElementById('btnCreateDeadline')
        ?.addEventListener('click', () => openDeadline());

    document.getElementById('btnQuickDeadline')
        ?.addEventListener('click', () => openDeadline());

    document.querySelectorAll('[data-create-deadline]')
        .forEach((button) => {
            button.addEventListener('click', () => {
                openDeadline({
                    candidateId:
                        button.dataset.createDeadline || '',
                    reference:
                        button.dataset.reference || '',
                    title:
                        button.dataset.title || ''
                });
            });
        });

    const candidateFromUrl =
        new URLSearchParams(window.location.search)
            .get('create');

    if (candidateFromUrl) {
        const button = document.querySelector(
            `[data-create-deadline="${CSS.escape(
                candidateFromUrl
            )}"]`
        );

        button?.click();
    }

    stepButtons.forEach((button) => {
        button.addEventListener('click', () => {
            showStep(Number(button.dataset.deadlineStep));
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
        document.getElementById('deadlineMeasureSelect');

    const measurePreview =
        document.getElementById('deadlineMeasurePreview');

    measureSelect?.addEventListener('change', () => {
        const option =
            measureSelect.options[measureSelect.selectedIndex];

        if (!option || !option.value) {
            if (measurePreview) {
                measurePreview.innerHTML = `
                    <i class="bi bi-file-earmark-text"></i>
                    <div>
                        <strong>No legislative measure linked</strong>
                        <span>
                            The deadline can remain independent or be
                            linked to an ordinance or resolution.
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
                        ${escapeHtml(option.dataset.reference || '')}
                    </strong>

                    <span>
                        ${escapeHtml(option.dataset.type || '')}
                        · ${escapeHtml(option.dataset.status || '')}
                        · ${escapeHtml(option.dataset.priority || '')}
                    </span>

                    <small>
                        ${escapeHtml(option.dataset.title || '')}
                    </small>
                </div>
            `;
        }
    });

    document.getElementById('btnRunDeadlineRiskCheck')
        ?.addEventListener('click', () => {
            Swal.fire({
                icon: 'success',
                title: 'Deadline Risk Check',
                text:
                    'No saved deadline risks are available yet. The final checker will evaluate due dates, progress, dependencies, reminders, and escalation status.',
            });
        });

    document.getElementById('btnSaveDeadlineDraft')
        ?.addEventListener('click', () => {
            Swal.fire({
                icon: 'info',
                title: 'Deadline Draft UI Ready',
                text:
                    'Draft saving will be connected when the dedicated deadline tables and AJAX endpoints are implemented.',
            });
        });

    form?.addEventListener('submit', (event) => {
        event.preventDefault();

        const title =
            document.getElementById('deadlineTitle')
                ?.value.trim() || '';

        const dueDate =
            document.getElementById('deadlineDueDate')
                ?.value || '';

        const dueTime =
            document.getElementById('deadlineDueTime')
                ?.value || '';

        const priority =
            document.getElementById('deadlinePriority')
                ?.value || 'Normal';

        const status =
            document.getElementById('deadlineStatus')
                ?.value || 'Open';

        if (!title || !dueDate) {
            Swal.fire({
                icon: 'warning',
                title: 'Missing deadline information',
                text:
                    'Enter a deadline title and due date before continuing.',
            });

            showStep(1);
            return;
        }

        previewDeadlines.push({
            title,
            dueDate,
            dueTime,
            priority,
            status
        });

        renderPreview();

        Swal.fire({
            icon: 'success',
            title: 'Deadline Preview Added',
            text:
                'The deadline was added to this browser preview. It is not yet stored in MySQL.',
        }).then(() => modal?.hide());
    });

    function renderPreview() {
        const container =
            document.getElementById('deadlinePreviewList');

        if (!container || !previewDeadlines.length) {
            return;
        }

        const today = new Date();
        today.setHours(0, 0, 0, 0);

        container.innerHTML = [...previewDeadlines]
            .sort((a, b) => a.dueDate.localeCompare(b.dueDate))
            .slice(0, 10)
            .map((deadline) => {
                const dueDate =
                    new Date(`${deadline.dueDate}T00:00:00`);

                const dayDifference =
                    Math.ceil(
                        (dueDate - today) /
                        (1000 * 60 * 60 * 24)
                    );

                let timingLabel = 'Due today';
                let timingClass = 'today';

                if (dayDifference > 0) {
                    timingLabel =
                        `${dayDifference} day${dayDifference === 1 ? '' : 's'} remaining`;

                    timingClass =
                        dayDifference <= 3
                            ? 'due-soon'
                            : 'normal';
                } else if (dayDifference < 0) {
                    timingLabel =
                        `${Math.abs(dayDifference)} day${Math.abs(dayDifference) === 1 ? '' : 's'} overdue`;

                    timingClass = 'overdue';
                }

                return `
                    <div class="deadline-preview-item">
                        <span class="deadline-date-box ${timingClass}">
                            <strong>${dueDate.getDate()}</strong>
                            <small>
                                ${dueDate.toLocaleDateString(
                                    undefined,
                                    { month: 'short' }
                                )}
                            </small>
                        </span>

                        <div class="deadline-preview-copy">
                            <strong>${escapeHtml(deadline.title)}</strong>

                            <span>
                                ${escapeHtml(
                                    deadline.dueTime || 'Time pending'
                                )}
                                · ${escapeHtml(timingLabel)}
                            </span>
                        </div>

                        <span class="deadline-preview-priority
                                     ${escapeHtml(
                                         deadline.priority.toLowerCase()
                                     )}">
                            ${escapeHtml(deadline.priority)}
                        </span>

                        <span class="deadline-preview-status">
                            ${escapeHtml(deadline.status)}
                        </span>
                    </div>
                `;
            })
            .join('');
    }

    const milestoneBuilder =
        document.getElementById('deadlineMilestoneBuilder');

    document.getElementById('btnAddDeadlineMilestone')
        ?.addEventListener('click', () => {
            if (!milestoneBuilder) {
                return;
            }

            const item = document.createElement('div');
            item.className = 'deadline-milestone-item';
            item.dataset.deadlineMilestone = '';

            item.innerHTML = `
                <span class="milestone-order">1</span>

                <div class="milestone-fields">
                    <input
                        type="text"
                        class="form-control"
                        name="milestone_title[]"
                        placeholder="Milestone title"
                    >

                    <input
                        type="date"
                        class="form-control"
                        name="milestone_date[]"
                    >
                </div>

                <button
                    type="button"
                    class="milestone-remove-button"
                    data-remove-deadline-milestone
                >
                    <i class="bi bi-trash"></i>
                </button>
            `;

            milestoneBuilder.appendChild(item);
            updateMilestoneOrder();
        });

    milestoneBuilder?.addEventListener('click', (event) => {
        const removeButton =
            event.target.closest(
                '[data-remove-deadline-milestone]'
            );

        if (!removeButton) {
            return;
        }

        const items =
            milestoneBuilder.querySelectorAll(
                '[data-deadline-milestone]'
            );

        if (items.length <= 1) {
            Swal.fire({
                icon: 'info',
                title: 'Milestone Row Required',
                text: 'Keep at least one milestone row.',
            });

            return;
        }

        removeButton
            .closest('[data-deadline-milestone]')
            ?.remove();

        updateMilestoneOrder();
    });

    function updateMilestoneOrder() {
        milestoneBuilder
            ?.querySelectorAll('[data-deadline-milestone]')
            .forEach((item, index) => {
                const order =
                    item.querySelector('.milestone-order');

                if (order) {
                    order.textContent = String(index + 1);
                }
            });
    }

    function resetMilestoneBuilder() {
        if (!milestoneBuilder) {
            return;
        }

        const items = Array.from(
            milestoneBuilder.querySelectorAll(
                '[data-deadline-milestone]'
            )
        );

        items.slice(1).forEach((item) => item.remove());

        items[0]
            ?.querySelectorAll('input')
            .forEach((field) => {
                field.value = '';
            });

        updateMilestoneOrder();
    }

    const documentInput =
        document.getElementById('deadlineDocuments');

    const selectedFiles =
        document.getElementById('deadlineSelectedFiles');

    documentInput?.addEventListener('change', () => {
        if (!selectedFiles) {
            return;
        }

        selectedFiles.innerHTML = '';

        Array.from(documentInput.files).forEach((file) => {
            const item = document.createElement('div');
            item.className = 'deadline-file-item';

            item.innerHTML = `
                <i class="bi bi-file-earmark"></i>
                <span>${escapeHtml(file.name)}</span>
                <small>${formatFileSize(file.size)}</small>
            `;

            selectedFiles.appendChild(item);
        });
    });

    document.querySelectorAll('[data-deadline-history]')
        .forEach((button) => {
            button.addEventListener('click', () => {
                Swal.fire({
                    icon: 'info',
                    title: 'Deadline History',
                    text:
                        'Reminder, escalation, due-date change, completion, and evidence history will be connected later.',
                });
            });
        });

    const searchInput =
        document.getElementById('deadlineSearch');
    const typeFilter =
        document.getElementById('deadlineTypeFilter');
    const priorityFilter =
        document.getElementById('deadlinePriorityFilter');
    const statusFilter =
        document.getElementById('deadlineStatusFilter');
    const rows = Array.from(
        document.querySelectorAll('.deadline-data-row')
    );
    const noResult =
        document.getElementById('deadlineNoFilterResult');
    const countLabel =
        document.getElementById('deadlineRecordCount');

    function applyFilters() {
        const search =
            (searchInput?.value || '').trim().toLowerCase();
        const type = typeFilter?.value || '';
        const priority = priorityFilter?.value || '';
        const status = statusFilter?.value || '';

        let visible = 0;

        rows.forEach((row) => {
            const show =
                (!search ||
                    (row.dataset.search || '').includes(search)) &&
                (!type || row.dataset.type === type) &&
                (!priority || row.dataset.priority === priority) &&
                (!status || row.dataset.status === status);

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

    searchInput?.addEventListener('input', applyFilters);
    typeFilter?.addEventListener('change', applyFilters);
    priorityFilter?.addEventListener('change', applyFilters);
    statusFilter?.addEventListener('change', applyFilters);

    document.getElementById('btnResetDeadlineFilters')
        ?.addEventListener('click', () => {
            if (searchInput) searchInput.value = '';
            if (typeFilter) typeFilter.value = '';
            if (priorityFilter) priorityFilter.value = '';
            if (statusFilter) statusFilter.value = '';
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
            String(date.getMonth() + 1).padStart(2, '0');
        const day =
            String(date.getDate()).padStart(2, '0');

        return `${year}-${month}-${day}`;
    }

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

    function formatFileSize(bytes) {
        if (bytes < 1024) {
            return `${bytes} B`;
        }

        if (bytes < 1024 * 1024) {
            return `${(bytes / 1024).toFixed(1)} KB`;
        }

        return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
    }
});
