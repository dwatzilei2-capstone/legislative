'use strict';

document.addEventListener('DOMContentLoaded', () => {
    const monthGrid = document.getElementById('calendarMonthGrid');
    const monthTitle = document.getElementById('calendarCurrentTitle');
    const previewEvents = [];
    let currentDate = new Date();
    currentDate.setDate(1);

    const toDateKey = (date) => {
        const year = date.getFullYear();
        const month = String(date.getMonth() + 1).padStart(2, '0');
        const day = String(date.getDate()).padStart(2, '0');
        return `${year}-${month}-${day}`;
    };

    const escapeHtml = (value) => {
        const element = document.createElement('div');
        element.textContent = String(value ?? '');
        return element.innerHTML;
    };

    function renderCalendar() {
        if (!monthGrid || !monthTitle) {
            return;
        }

        monthGrid.innerHTML = '';

        const year = currentDate.getFullYear();
        const month = currentDate.getMonth();
        const firstDay = new Date(year, month, 1).getDay();
        const daysInMonth = new Date(year, month + 1, 0).getDate();
        const previousMonthDays = new Date(year, month, 0).getDate();
        const today = new Date();

        monthTitle.textContent = currentDate.toLocaleDateString(
            undefined,
            { month: 'long', year: 'numeric' }
        );

        for (let index = 0; index < 42; index++) {
            let cellDate;
            let number;
            let outside = false;

            if (index < firstDay) {
                number = previousMonthDays - firstDay + index + 1;
                cellDate = new Date(year, month - 1, number);
                outside = true;
            } else if (index >= firstDay + daysInMonth) {
                number = index - firstDay - daysInMonth + 1;
                cellDate = new Date(year, month + 1, number);
                outside = true;
            } else {
                number = index - firstDay + 1;
                cellDate = new Date(year, month, number);
            }

            const key = toDateKey(cellDate);
            const cell = document.createElement('button');
            cell.type = 'button';
            cell.className = 'calendar-day-cell';

            if (outside) {
                cell.classList.add('outside-month');
            }

            if (
                cellDate.getFullYear() === today.getFullYear() &&
                cellDate.getMonth() === today.getMonth() &&
                cellDate.getDate() === today.getDate()
            ) {
                cell.classList.add('today');
            }

            cell.innerHTML = `
                <span class="calendar-day-number">${number}</span>
                <span class="calendar-day-events"></span>
            `;

            const eventContainer = cell.querySelector(
                '.calendar-day-events'
            );

            previewEvents
                .filter((event) => event.date === key)
                .slice(0, 3)
                .forEach((event) => {
                    const badge = document.createElement('span');
                    badge.className = 'calendar-preview-event';
                    badge.textContent = event.allDay
                        ? event.title
                        : `${event.time} ${event.title}`;
                    eventContainer.appendChild(badge);
                });

            cell.addEventListener('click', () => {
                openScheduleModal({ date: key });
            });

            monthGrid.appendChild(cell);
        }
    }

    renderCalendar();

    document.getElementById('calendarPrevious')?.addEventListener(
        'click',
        () => {
            currentDate.setMonth(currentDate.getMonth() - 1);
            renderCalendar();
        }
    );

    document.getElementById('calendarNext')?.addEventListener(
        'click',
        () => {
            currentDate.setMonth(currentDate.getMonth() + 1);
            renderCalendar();
        }
    );

    document.getElementById('calendarToday')?.addEventListener(
        'click',
        () => {
            currentDate = new Date();
            currentDate.setDate(1);
            renderCalendar();
        }
    );

    document.querySelectorAll('[data-calendar-view]').forEach((button) => {
        button.addEventListener('click', () => {
            const view = button.dataset.calendarView || 'month';

            document.querySelectorAll('[data-calendar-view]').forEach(
                (item) => {
                    item.classList.toggle('active', item === button);
                }
            );

            document.querySelectorAll('[data-calendar-section]').forEach(
                (section) => {
                    section.classList.toggle(
                        'active',
                        section.dataset.calendarSection === view
                    );
                }
            );
        });
    });

    const modalElement = document.getElementById('calendarEventModal');
    const modal = modalElement
        ? new bootstrap.Modal(modalElement)
        : null;
    const form = document.getElementById('calendarEventForm');

    const stepButtons = Array.from(
        document.querySelectorAll('[data-calendar-step]')
    );

    const sections = Array.from(
        document.querySelectorAll('[data-calendar-form-section]')
    );

    const previousButton = document.getElementById(
        'btnPreviousCalendarStep'
    );

    const nextButton = document.getElementById('btnNextCalendarStep');
    const submitButton = document.getElementById(
        'btnSubmitCalendarEvent'
    );

    let currentStep = 1;

    function showStep(step) {
        currentStep = step;

        stepButtons.forEach((button) => {
            button.classList.toggle(
                'active',
                Number(button.dataset.calendarStep) === step
            );
        });

        sections.forEach((section) => {
            section.classList.toggle(
                'active',
                Number(section.dataset.calendarFormSection) === step
            );
        });

        if (previousButton) {
            previousButton.disabled = step === 1;
        }

        nextButton?.classList.toggle('d-none', step === 4);
        submitButton?.classList.toggle('d-none', step !== 4);
    }

    function resetMeasurePreview() {
        const preview = document.getElementById('calendarMeasurePreview');

        if (!preview) {
            return;
        }

        preview.innerHTML = `
            <i class="bi bi-file-earmark-text"></i>
            <div>
                <strong>No legislative measure linked</strong>
                <span>
                    The activity may remain independent or be connected
                    to a legislative record.
                </span>
            </div>
        `;
    }

    function resetAgendaBuilder() {
        const builder = document.getElementById('calendarAgendaBuilder');

        if (!builder) {
            return;
        }

        const items = Array.from(
            builder.querySelectorAll('[data-agenda-item]')
        );

        items.slice(1).forEach((item) => item.remove());

        items[0]?.querySelectorAll('input, textarea').forEach((field) => {
            field.value = '';
        });

        updateAgendaOrder();
    }

    function openScheduleModal(options = {}) {
        if (!modal || !form) {
            return;
        }

        form.reset();

        const title = document.getElementById('calendarActivityTitle');
        const date = document.getElementById('calendarActivityDate');
        const select = document.getElementById('calendarMeasureSelect');
        const modalTitle = document.getElementById(
            'calendarEventModalTitle'
        );

        if (title) {
            title.value = options.title || '';
        }

        if (date) {
            date.value = options.date || toDateKey(new Date());
        }

        if (select) {
            select.value = options.candidateId || '';
            select.dispatchEvent(new Event('change'));
        }

        if (modalTitle) {
            modalTitle.textContent = options.reference
                ? `Schedule: ${options.reference}`
                : 'Schedule Legislative Activity';
        }

        resetAgendaBuilder();
        showStep(1);
        modal.show();
    }

    document.getElementById('btnScheduleEvent')?.addEventListener(
        'click',
        () => openScheduleModal()
    );

    document.querySelectorAll('[data-schedule-candidate]').forEach(
        (button) => {
            button.addEventListener('click', () => {
                openScheduleModal({
                    candidateId: button.dataset.scheduleCandidate || '',
                    reference: button.dataset.reference || '',
                    title: button.dataset.title || ''
                });
            });
        }
    );

    const scheduleId = new URLSearchParams(window.location.search).get(
        'schedule'
    );

    if (scheduleId) {
        document.querySelector(
            `[data-schedule-candidate="${CSS.escape(scheduleId)}"]`
        )?.click();
    }

    stepButtons.forEach((button) => {
        button.addEventListener('click', () => {
            showStep(Number(button.dataset.calendarStep));
        });
    });

    previousButton?.addEventListener('click', () => {
        if (currentStep > 1) {
            showStep(currentStep - 1);
        }
    });

    nextButton?.addEventListener('click', () => {
        if (currentStep < 4) {
            showStep(currentStep + 1);
        }
    });

    const measureSelect = document.getElementById(
        'calendarMeasureSelect'
    );

    measureSelect?.addEventListener('change', () => {
        const preview = document.getElementById(
            'calendarMeasurePreview'
        );

        const option = measureSelect.options[
            measureSelect.selectedIndex
        ];

        if (!preview || !option || !option.value) {
            resetMeasurePreview();
            return;
        }

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
                <small>${escapeHtml(option.dataset.title || '')}</small>
            </div>
        `;
    });

    const allDay = document.getElementById('calendarAllDay');

    allDay?.addEventListener('change', () => {
        const start = document.getElementById('calendarStartTime');
        const end = document.getElementById('calendarEndTime');

        if (start) {
            start.disabled = allDay.checked;
        }

        if (end) {
            end.disabled = allDay.checked;
        }
    });

    document.getElementById('btnCheckEventConflict')?.addEventListener(
        'click',
        () => {
            Swal.fire({
                icon: 'success',
                title: 'Conflict Check Preview',
                text:
                    'No conflicts are available in the current preview. The final checker will compare saved venues, participants, committees, and time ranges.'
            });
        }
    );

    document.getElementById('btnOpenConflictChecker')?.addEventListener(
        'click',
        () => {
            Swal.fire({
                icon: 'info',
                title: 'Calendar Conflict Checker',
                text:
                    'The final checker will compare saved legislative activities, venues, participants, committees, and executive schedules.'
            });
        }
    );

    document.getElementById('btnSaveCalendarDraft')?.addEventListener(
        'click',
        () => {
            Swal.fire({
                icon: 'info',
                title: 'Calendar Draft UI Ready',
                text:
                    'Draft saving will be connected when the LACMS calendar tables and AJAX endpoints are implemented.'
            });
        }
    );

    function renderUpcoming() {
        const container = document.getElementById(
            'calendarUpcomingList'
        );

        if (!container || !previewEvents.length) {
            return;
        }

        container.innerHTML = [...previewEvents]
            .sort((a, b) => a.date.localeCompare(b.date))
            .slice(0, 6)
            .map((event) => {
                const date = new Date(`${event.date}T00:00:00`);

                return `
                    <div class="calendar-upcoming-item">
                        <span class="upcoming-date">
                            <strong>${date.getDate()}</strong>
                            <small>
                                ${date.toLocaleDateString(
                                    undefined,
                                    { month: 'short' }
                                )}
                            </small>
                        </span>

                        <div>
                            <strong>${escapeHtml(event.title)}</strong>
                            <small>
                                ${
                                    event.allDay
                                        ? 'All day'
                                        : escapeHtml(
                                            event.time || 'Time pending'
                                        )
                                }
                                · Preview only
                            </small>
                        </div>
                    </div>
                `;
            })
            .join('');
    }

    form?.addEventListener('submit', (event) => {
        event.preventDefault();

        const title = document.getElementById(
            'calendarActivityTitle'
        )?.value.trim();

        const date = document.getElementById(
            'calendarActivityDate'
        )?.value;

        const time = document.getElementById(
            'calendarStartTime'
        )?.value;

        const isAllDay = Boolean(
            document.getElementById('calendarAllDay')?.checked
        );

        if (!title || !date) {
            Swal.fire({
                icon: 'warning',
                title: 'Missing activity information',
                text: 'Enter an activity title and date first.'
            });

            showStep(1);
            return;
        }

        previewEvents.push({
            title,
            date,
            time,
            allDay: isAllDay
        });

        const eventDate = new Date(`${date}T00:00:00`);
        currentDate = new Date(
            eventDate.getFullYear(),
            eventDate.getMonth(),
            1
        );

        renderCalendar();
        renderUpcoming();

        Swal.fire({
            icon: 'success',
            title: 'Calendar Preview Added',
            text:
                'The activity was added to this browser preview. It is not yet stored in the database.'
        }).then(() => modal?.hide());
    });

    const agendaBuilder = document.getElementById(
        'calendarAgendaBuilder'
    );

    function updateAgendaOrder() {
        agendaBuilder?.querySelectorAll('[data-agenda-item]').forEach(
            (item, index) => {
                const order = item.querySelector('.agenda-order');

                if (order) {
                    order.textContent = String(index + 1);
                }
            }
        );
    }

    document.getElementById('btnAddAgendaItem')?.addEventListener(
        'click',
        () => {
            if (!agendaBuilder) {
                return;
            }

            const item = document.createElement('div');
            item.className = 'calendar-agenda-item';
            item.dataset.agendaItem = '';

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
                        placeholder="Agenda description or expected action"
                    ></textarea>
                </div>

                <button
                    type="button"
                    class="agenda-remove-button"
                    data-remove-agenda
                >
                    <i class="bi bi-trash"></i>
                </button>
            `;

            agendaBuilder.appendChild(item);
            updateAgendaOrder();
        }
    );

    agendaBuilder?.addEventListener('click', (event) => {
        const button = event.target.closest('[data-remove-agenda]');

        if (!button) {
            return;
        }

        const items = agendaBuilder.querySelectorAll(
            '[data-agenda-item]'
        );

        if (items.length <= 1) {
            Swal.fire({
                icon: 'info',
                title: 'Agenda Item Required',
                text: 'Keep at least one agenda row.'
            });

            return;
        }

        button.closest('[data-agenda-item]')?.remove();
        updateAgendaOrder();
    });

    const fileInput = document.getElementById('calendarDocuments');

    fileInput?.addEventListener('change', () => {
        const container = document.getElementById(
            'calendarSelectedFiles'
        );

        if (!container) {
            return;
        }

        container.innerHTML = Array.from(fileInput.files)
            .map((file) => {
                const size = file.size < 1024 * 1024
                    ? `${(file.size / 1024).toFixed(1)} KB`
                    : `${(file.size / (1024 * 1024)).toFixed(1)} MB`;

                return `
                    <div class="calendar-file-item">
                        <i class="bi bi-file-earmark"></i>
                        <span>${escapeHtml(file.name)}</span>
                        <small>${size}</small>
                    </div>
                `;
            })
            .join('');
    });

    document.querySelectorAll('[data-calendar-history]').forEach(
        (button) => {
            button.addEventListener('click', () => {
                Swal.fire({
                    icon: 'info',
                    title: 'Scheduling History',
                    text:
                        'Rescheduling, postponement, cancellation, and confirmation history will be connected later.'
                });
            });
        }
    );

    const search = document.getElementById('calendarQueueSearch');
    const type = document.getElementById('calendarQueueType');
    const priority = document.getElementById(
        'calendarQueuePriority'
    );
    const status = document.getElementById('calendarQueueStatus');
    const rows = Array.from(
        document.querySelectorAll('.calendar-queue-row')
    );
    const noResult = document.getElementById(
        'calendarQueueNoResult'
    );
    const count = document.getElementById('calendarQueueCount');

    function applyFilters() {
        const query = (search?.value || '').trim().toLowerCase();
        let visible = 0;

        rows.forEach((row) => {
            const show =
                (!query ||
                    (row.dataset.search || '').includes(query)) &&
                (!type?.value ||
                    row.dataset.type === type.value) &&
                (!priority?.value ||
                    row.dataset.priority === priority.value) &&
                (!status?.value ||
                    row.dataset.status === status.value);

            row.classList.toggle('d-none', !show);

            if (show) {
                visible++;
            }
        });

        noResult?.classList.toggle(
            'd-none',
            visible > 0 || rows.length === 0
        );

        if (count) {
            count.textContent =
                `Showing ${visible} candidate record(s)`;
        }
    }

    search?.addEventListener('input', applyFilters);
    type?.addEventListener('change', applyFilters);
    priority?.addEventListener('change', applyFilters);
    status?.addEventListener('change', applyFilters);

    document.getElementById('btnResetCalendarQueue')?.addEventListener(
        'click',
        () => {
            if (search) search.value = '';
            if (type) type.value = '';
            if (priority) priority.value = '';
            if (status) status.value = '';
            applyFilters();
        }
    );
});
