'use strict';

document.addEventListener('DOMContentLoaded', () => {
    const modalElement = document.getElementById('meetingModal');
    const modal = modalElement
        ? new bootstrap.Modal(modalElement)
        : null;

    const form = document.getElementById('meetingForm');
    const stepButtons = Array.from(
        document.querySelectorAll('[data-meeting-step]')
    );
    const sections = Array.from(
        document.querySelectorAll('[data-meeting-form-section]')
    );

    const previousButton =
        document.getElementById('btnPreviousMeetingStep');
    const nextButton =
        document.getElementById('btnNextMeetingStep');
    const submitButton =
        document.getElementById('btnSubmitMeeting');

    let currentStep = 1;
    const maximumStep = 4;
    const previewMeetings = [];

    function showStep(step) {
        currentStep = step;

        stepButtons.forEach((button) => {
            button.classList.toggle(
                'active',
                Number(button.dataset.meetingStep) === step
            );
        });

        sections.forEach((section) => {
            section.classList.toggle(
                'active',
                Number(section.dataset.meetingFormSection) === step
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

    function openMeeting(options = {}) {
        if (!modal || !form) {
            return;
        }

        form.reset();

        setText(
            'meetingModalTitle',
            options.reference
                ? `Coordinate Meeting: ${options.reference}`
                : 'Coordinate Legislative Meeting'
        );

        setValue('meetingTitle', options.title || '');
        setValue('meetingDate', toDateKey(new Date()));
        setValue('meetingStartTime', '09:00');

        const select =
            document.getElementById('meetingMeasureSelect');

        if (select) {
            select.value = options.candidateId || '';
            select.dispatchEvent(new Event('change'));
        }

        resetAgendaBuilder();
        showStep(1);
        modal.show();
    }

    document.getElementById('btnCreateMeeting')
        ?.addEventListener('click', () => openMeeting());

    document.getElementById('btnQuickMeeting')
        ?.addEventListener('click', () => openMeeting());

    document.querySelectorAll('[data-coordinate-meeting]')
        .forEach((button) => {
            button.addEventListener('click', () => {
                openMeeting({
                    candidateId:
                        button.dataset.coordinateMeeting || '',
                    reference:
                        button.dataset.reference || '',
                    title:
                        button.dataset.title || ''
                });
            });
        });

    const candidateFromUrl =
        new URLSearchParams(window.location.search)
            .get('coordinate');

    if (candidateFromUrl) {
        const button = document.querySelector(
            `[data-coordinate-meeting="${CSS.escape(
                candidateFromUrl
            )}"]`
        );

        button?.click();
    }

    stepButtons.forEach((button) => {
        button.addEventListener('click', () => {
            showStep(Number(button.dataset.meetingStep));
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
        document.getElementById('meetingMeasureSelect');

    const preview =
        document.getElementById('meetingMeasurePreview');

    measureSelect?.addEventListener('change', () => {
        const option =
            measureSelect.options[measureSelect.selectedIndex];

        if (!option || !option.value) {
            if (preview) {
                preview.innerHTML = `
                    <i class="bi bi-file-earmark-text"></i>
                    <div>
                        <strong>No legislative measure linked</strong>
                        <span>
                            The meeting can remain independent or be
                            linked to an ordinance or resolution.
                        </span>
                    </div>
                `;
            }
            return;
        }

        if (preview) {
            preview.innerHTML = `
                <i class="bi bi-file-earmark-check"></i>
                <div>
                    <strong>${escapeHtml(
                        option.dataset.reference || ''
                    )}</strong>
                    <span>
                        ${escapeHtml(option.dataset.type || '')}
                        · ${escapeHtml(option.dataset.status || '')}
                        · ${escapeHtml(option.dataset.priority || '')}
                    </span>
                    <small>${escapeHtml(
                        option.dataset.title || ''
                    )}</small>
                </div>
            `;
        }
    });

    document.getElementById('btnImportCommitteeMembers')
        ?.addEventListener('click', () => {
            Swal.fire({
                icon: 'info',
                title: 'Committee Member Import',
                text:
                    'Committee membership will be imported after the relationship table is connected.',
            });
        });

    document.getElementById('btnCheckParticipantConflicts')
        ?.addEventListener('click', () => {
            Swal.fire({
                icon: 'success',
                title: 'Conflict Check Preview',
                text:
                    'The final checker will compare saved participant, venue, and calendar records.',
            });
        });

    document.getElementById('btnPreviewInvitation')
        ?.addEventListener('click', () => {
            const title =
                document.getElementById('meetingTitle')
                    ?.value.trim() ||
                'Legislative Meeting';

            const date =
                document.getElementById('meetingDate')
                    ?.value ||
                'Date pending';

            Swal.fire({
                icon: 'info',
                title: 'Invitation Preview',
                html: `
                    <div class="text-start small">
                        <p><strong>${escapeHtml(title)}</strong></p>
                        <p>Date: ${escapeHtml(date)}</p>
                        <p>
                            You are invited to attend this legislative
                            coordination meeting. Email delivery will be
                            connected later.
                        </p>
                    </div>
                `,
            });
        });

    document.getElementById('btnSaveMeetingDraft')
        ?.addEventListener('click', () => {
            Swal.fire({
                icon: 'info',
                title: 'Meeting Draft UI Ready',
                text:
                    'Draft saving will be connected during backend implementation.',
            });
        });

    form?.addEventListener('submit', (event) => {
        event.preventDefault();

        const title =
            document.getElementById('meetingTitle')
                ?.value.trim() || '';

        const date =
            document.getElementById('meetingDate')
                ?.value || '';

        const startTime =
            document.getElementById('meetingStartTime')
                ?.value || '';

        const venue =
            document.getElementById('meetingVenue')
                ?.value.trim() || '';

        const status =
            document.getElementById('meetingStatus')
                ?.value || 'Confirmed';

        if (!title || !date) {
            Swal.fire({
                icon: 'warning',
                title: 'Missing meeting information',
                text:
                    'Enter a meeting title and date before continuing.',
            });

            showStep(1);
            return;
        }

        previewMeetings.push({
            title,
            date,
            startTime,
            venue,
            status
        });

        renderPreview();

        Swal.fire({
            icon: 'success',
            title: 'Meeting Preview Added',
            text:
                'The meeting was added to this browser preview. It is not yet stored in MySQL.',
        }).then(() => modal?.hide());
    });

    function renderPreview() {
        const container =
            document.getElementById('meetingUpcomingList');

        if (!container || !previewMeetings.length) {
            return;
        }

        container.innerHTML = [...previewMeetings]
            .sort((a, b) => a.date.localeCompare(b.date))
            .slice(0, 8)
            .map((meeting) => {
                const date =
                    new Date(`${meeting.date}T00:00:00`);

                return `
                    <div class="meeting-upcoming-item">
                        <span class="meeting-date-box">
                            <strong>${date.getDate()}</strong>
                            <small>
                                ${date.toLocaleDateString(
                                    undefined,
                                    { month: 'short' }
                                )}
                            </small>
                        </span>

                        <div class="meeting-upcoming-copy">
                            <strong>${escapeHtml(meeting.title)}</strong>
                            <span>
                                ${escapeHtml(
                                    meeting.startTime || 'Time pending'
                                )}
                                ·
                                ${escapeHtml(
                                    meeting.venue || 'Venue pending'
                                )}
                            </span>
                        </div>

                        <span class="meeting-preview-status">
                            ${escapeHtml(meeting.status)}
                        </span>
                    </div>
                `;
            })
            .join('');
    }

    const agendaBuilder =
        document.getElementById('meetingAgendaBuilder');

    document.getElementById('btnAddMeetingAgendaItem')
        ?.addEventListener('click', () => {
            if (!agendaBuilder) {
                return;
            }

            const item = document.createElement('div');
            item.className = 'meeting-agenda-item';
            item.dataset.meetingAgendaItem = '';

            item.innerHTML = `
                <span class="agenda-order">1</span>

                <div class="agenda-fields">
                    <input
                        type="text"
                        class="form-control"
                        name="agenda_title[]"
                        placeholder="Agenda item title"
                    >

                    <textarea
                        class="form-control"
                        name="agenda_description[]"
                        rows="2"
                        placeholder="Details or expected action"
                    ></textarea>
                </div>

                <button
                    type="button"
                    class="agenda-remove-button"
                    data-remove-meeting-agenda
                >
                    <i class="bi bi-trash"></i>
                </button>
            `;

            agendaBuilder.appendChild(item);
            updateAgendaOrder();
        });

    agendaBuilder?.addEventListener('click', (event) => {
        const removeButton =
            event.target.closest('[data-remove-meeting-agenda]');

        if (!removeButton) {
            return;
        }

        const items =
            agendaBuilder.querySelectorAll(
                '[data-meeting-agenda-item]'
            );

        if (items.length <= 1) {
            Swal.fire({
                icon: 'info',
                title: 'Agenda Item Required',
                text: 'Keep at least one agenda item row.',
            });
            return;
        }

        removeButton
            .closest('[data-meeting-agenda-item]')
            ?.remove();

        updateAgendaOrder();
    });

    function updateAgendaOrder() {
        agendaBuilder
            ?.querySelectorAll('[data-meeting-agenda-item]')
            .forEach((item, index) => {
                const order = item.querySelector('.agenda-order');

                if (order) {
                    order.textContent = String(index + 1);
                }
            });
    }

    function resetAgendaBuilder() {
        if (!agendaBuilder) {
            return;
        }

        const items = Array.from(
            agendaBuilder.querySelectorAll(
                '[data-meeting-agenda-item]'
            )
        );

        items.slice(1).forEach((item) => item.remove());

        items[0]
            ?.querySelectorAll('input, textarea')
            .forEach((field) => {
                field.value = '';
            });

        updateAgendaOrder();
    }

    const documentInput =
        document.getElementById('meetingDocuments');

    const selectedFiles =
        document.getElementById('meetingSelectedFiles');

    documentInput?.addEventListener('change', () => {
        if (!selectedFiles) {
            return;
        }

        selectedFiles.innerHTML = '';

        Array.from(documentInput.files).forEach((file) => {
            const item = document.createElement('div');
            item.className = 'meeting-file-item';

            item.innerHTML = `
                <i class="bi bi-file-earmark"></i>
                <span>${escapeHtml(file.name)}</span>
                <small>${formatFileSize(file.size)}</small>
            `;

            selectedFiles.appendChild(item);
        });
    });

    document.querySelectorAll('[data-meeting-history]')
        .forEach((button) => {
            button.addEventListener('click', () => {
                Swal.fire({
                    icon: 'info',
                    title: 'Meeting History',
                    text:
                        'Invitation, confirmation, attendance, minutes, and action history will be connected later.',
                });
            });
        });

    const checks = Array.from(
        document.querySelectorAll('.coordination-check')
    );

    function updateChecklist() {
        if (!checks.length) {
            return;
        }

        const completed =
            checks.filter((item) => item.checked).length;

        const percent =
            Math.round((completed / checks.length) * 100);

        setText('meetingChecklistValue', `${percent}%`);

        const bar =
            document.getElementById('meetingChecklistBar');

        if (bar) {
            bar.style.width = `${percent}%`;
        }
    }

    checks.forEach((item) => {
        item.addEventListener('change', updateChecklist);
    });

    const searchInput =
        document.getElementById('meetingSearch');
    const typeFilter =
        document.getElementById('meetingTypeFilter');
    const priorityFilter =
        document.getElementById('meetingPriorityFilter');
    const statusFilter =
        document.getElementById('meetingStatusFilter');
    const rows = Array.from(
        document.querySelectorAll('.meeting-data-row')
    );
    const noResult =
        document.getElementById('meetingNoFilterResult');
    const countLabel =
        document.getElementById('meetingRecordCount');

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

    document.getElementById('btnResetMeetingFilters')
        ?.addEventListener('click', () => {
            if (searchInput) searchInput.value = '';
            if (typeFilter) typeFilter.value = '';
            if (priorityFilter) priorityFilter.value = '';
            if (statusFilter) statusFilter.value = '';
            applyFilters();
        });

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
