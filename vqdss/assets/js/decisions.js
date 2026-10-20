'use strict';

document.addEventListener('DOMContentLoaded', () => {
    const modalElement =
        document.getElementById('decisionModal');

    const modal =
        modalElement &&
        typeof bootstrap !== 'undefined'
            ? new bootstrap.Modal(modalElement)
            : null;

    const form =
        document.getElementById('decisionForm');

    const stepButtons = Array.from(
        document.querySelectorAll(
            '[data-decision-step]'
        )
    );

    const sections = Array.from(
        document.querySelectorAll(
            '[data-decision-form-section]'
        )
    );

    const previousButton =
        document.getElementById(
            'btnPreviousDecisionStep'
        );

    const nextButton =
        document.getElementById(
            'btnNextDecisionStep'
        );

    const submitButton =
        document.getElementById(
            'btnSubmitDecision'
        );

    const actionBuilder =
        document.getElementById(
            'decisionActionBuilder'
        );

    let currentStep = 1;
    const maximumStep = 4;
    const previewDecisions = [];

    function showStep(step) {
        currentStep = step;

        stepButtons.forEach((button) => {
            button.classList.toggle(
                'active',
                Number(
                    button.dataset.decisionStep
                ) === step
            );
        });

        sections.forEach((section) => {
            section.classList.toggle(
                'active',
                Number(
                    section.dataset.decisionFormSection
                ) === step
            );
        });

        if (previousButton) {
            previousButton.disabled =
                step === 1;
        }

        nextButton?.classList.toggle(
            'd-none',
            step === maximumStep
        );

        submitButton?.classList.toggle(
            'd-none',
            step !== maximumStep
        );

        updateDecisionSummary();
    }

    function openDecision(options = {}) {
        if (!modal || !form) {
            return;
        }

        form.reset();

        setText(
            'decisionModalTitle',
            options.reference
                ? `Record Decision: ${options.reference}`
                : 'Create Decision Record'
        );

        setValue(
            'decisionDateTime',
            toDateTimeLocal(new Date())
        );

        setValue(
            'decisionCertifiedAt',
            toDateTimeLocal(new Date())
        );

        setValue(
            'decisionRecordedBy',
            document.getElementById(
                'decisionRecordedBy'
            )?.defaultValue || ''
        );

        const measureSelect =
            document.getElementById(
                'decisionMeasureSelect'
            );

        if (measureSelect) {
            measureSelect.value =
                options.candidateId || '';

            measureSelect.dispatchEvent(
                new Event('change')
            );
        }

        resetActionBuilder();
        updateDecisionSummary();
        showStep(1);
        modal.show();
    }

    document.getElementById(
        'btnCreateDecision'
    )?.addEventListener('click', () => {
        openDecision();
    });

    document.getElementById(
        'btnQuickDecision'
    )?.addEventListener('click', () => {
        openDecision();
    });

    document.querySelectorAll(
        '[data-create-decision]'
    ).forEach((button) => {
        button.addEventListener('click', () => {
            openDecision({
                candidateId:
                    button.dataset.createDecision || '',
                reference:
                    button.dataset.reference || '',
                title:
                    button.dataset.title || ''
            });
        });
    });

    const candidateFromUrl =
        new URLSearchParams(
            window.location.search
        ).get('decision');

    if (candidateFromUrl) {
        const button =
            document.querySelector(
                `[data-create-decision="${CSS.escape(
                    candidateFromUrl
                )}"]`
            );

        button?.click();
    }

    stepButtons.forEach((button) => {
        button.addEventListener('click', () => {
            showStep(
                Number(
                    button.dataset.decisionStep
                )
            );
        });
    });

    previousButton?.addEventListener(
        'click',
        () => {
            if (currentStep > 1) {
                showStep(currentStep - 1);
            }
        }
    );

    nextButton?.addEventListener(
        'click',
        () => {
            if (currentStep < maximumStep) {
                showStep(currentStep + 1);
            }
        }
    );

    const measureSelect =
        document.getElementById(
            'decisionMeasureSelect'
        );

    const measurePreview =
        document.getElementById(
            'decisionMeasurePreview'
        );

    measureSelect?.addEventListener(
        'change',
        () => {
            const option =
                measureSelect.options[
                    measureSelect.selectedIndex
                ];

            if (!option || !option.value) {
                if (measurePreview) {
                    measurePreview.innerHTML = `
                        <i class="bi bi-file-earmark-text"></i>

                        <div>
                            <strong>
                                No legislative measure selected
                            </strong>

                            <span>
                                Select the ordinance or resolution
                                connected to the official decision.
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
                            ${escapeHtml(
                                option.dataset.type || ''
                            )}
                            ·
                            ${escapeHtml(
                                option.dataset.status || ''
                            )}
                            ·
                            ${escapeHtml(
                                option.dataset.priority || ''
                            )}
                        </span>

                        <small>
                            ${escapeHtml(
                                option.dataset.title || ''
                            )}
                        </small>
                    </div>
                `;
            }
        }
    );

    [
        'decisionOutcome',
        'decisionAffirmativeVotes',
        'decisionNegativeVotes',
        'decisionAbstainVotes',
        'decisionRequiredVotes',
        'decisionClassification',
        'decisionEffectivity',
        'decisionRecordStatus'
    ].forEach((id) => {
        const element =
            document.getElementById(id);

        element?.addEventListener(
            'input',
            updateDecisionSummary
        );

        element?.addEventListener(
            'change',
            updateDecisionSummary
        );
    });

    function updateDecisionSummary() {
        const outcome =
            document.getElementById(
                'decisionOutcome'
            )?.value || 'Approved';

        const affirmative =
            Number(
                document.getElementById(
                    'decisionAffirmativeVotes'
                )?.value || 0
            );

        const negative =
            Number(
                document.getElementById(
                    'decisionNegativeVotes'
                )?.value || 0
            );

        const abstain =
            Number(
                document.getElementById(
                    'decisionAbstainVotes'
                )?.value || 0
            );

        const required =
            Number(
                document.getElementById(
                    'decisionRequiredVotes'
                )?.value || 0
            );

        const classification =
            document.getElementById(
                'decisionClassification'
            )?.value || 'Final Decision';

        const effectivity =
            document.getElementById(
                'decisionEffectivity'
            )?.value || 'Immediately';

        const recordStatus =
            document.getElementById(
                'decisionRecordStatus'
            )?.value || 'Certified';

        const actionData =
            updateActionSummary();

        setText(
            'decisionBoardAffirmative',
            affirmative
        );

        setText(
            'decisionBoardNegative',
            negative
        );

        setText(
            'decisionBoardAbstain',
            abstain
        );

        setText(
            'decisionBoardRequired',
            required
        );

        setText(
            'decisionBoardOutcome',
            outcome
        );

        setText(
            'decisionFinalOutcome',
            outcome
        );

        setText(
            'decisionFinalClass',
            classification
        );

        setText(
            'decisionFinalActions',
            actionData.total
        );

        setText(
            'decisionFinalEffectivity',
            effectivity
        );

        setText(
            'decisionFinalStatus',
            recordStatus
        );

        return {
            outcome,
            affirmative,
            negative,
            abstain,
            required,
            classification,
            effectivity,
            recordStatus,
            actions: actionData
        };
    }

    document.getElementById(
        'btnAddDecisionAction'
    )?.addEventListener('click', () => {
        addActionItem();
    });

    document.getElementById(
        'btnApplyStandardActions'
    )?.addEventListener('click', () => {
        if (!actionBuilder) {
            return;
        }

        actionBuilder.innerHTML = '';

        [
            'Prepare and circulate the certified decision record',
            'Transmit the decision to the responsible implementing office',
            'Prepare publication or public information materials'
        ].forEach((title, index) => {
            addActionItem({
                title,
                priority:
                    index === 0
                        ? 'High'
                        : 'Normal'
            });
        });

        updateActionSummary();
    });

    function buildOfficeOptions() {
        const source =
            document.querySelector(
                'select[name="responsible_office_id[]"]'
            );

        if (!source) {
            return `
                <option value="">
                    -- Responsible office --
                </option>
            `;
        }

        return source.innerHTML;
    }

    function addActionItem(config = {}) {
        if (!actionBuilder) {
            return;
        }

        const item =
            document.createElement('div');

        item.className =
            'decision-action-item';

        item.dataset.decisionActionItem = '';

        item.innerHTML = `
            <span class="action-order">1</span>

            <div class="action-fields">
                <input
                    type="text"
                    class="form-control"
                    name="action_title[]"
                    value="${escapeHtml(config.title || '')}"
                    placeholder="Required follow-up action"
                    data-action-title
                >

                <select
                    class="form-select"
                    name="responsible_office_id[]"
                    data-action-office
                >
                    ${buildOfficeOptions()}
                </select>

                <input
                    type="date"
                    class="form-control"
                    name="action_due_date[]"
                    data-action-date
                >

                <select
                    class="form-select"
                    name="action_priority[]"
                >
                    ${[
                        'Normal',
                        'High',
                        'Urgent',
                        'Low'
                    ].map((priority) => `
                        <option
                            ${priority === (config.priority || 'Normal')
                                ? 'selected'
                                : ''
                            }
                        >
                            ${priority}
                        </option>
                    `).join('')}
                </select>
            </div>

            <button
                type="button"
                class="action-remove-button"
                data-remove-decision-action
                title="Remove action"
            >
                <i class="bi bi-trash"></i>
            </button>
        `;

        actionBuilder.appendChild(item);
        updateActionOrder();
        updateActionSummary();
    }

    actionBuilder?.addEventListener(
        'click',
        (event) => {
            const removeButton =
                event.target.closest(
                    '[data-remove-decision-action]'
                );

            if (!removeButton) {
                return;
            }

            const items =
                actionBuilder.querySelectorAll(
                    '[data-decision-action-item]'
                );

            if (items.length <= 1) {
                showDialog({
                    icon: 'info',
                    title: 'Action Row Required',
                    text:
                        'Keep at least one action row.'
                });

                return;
            }

            removeButton
                .closest(
                    '[data-decision-action-item]'
                )
                ?.remove();

            updateActionOrder();
            updateActionSummary();
        }
    );

    actionBuilder?.addEventListener(
        'input',
        updateActionSummary
    );

    actionBuilder?.addEventListener(
        'change',
        updateActionSummary
    );

    document.querySelectorAll(
        '[data-decision-notification]'
    ).forEach((checkbox) => {
        checkbox.addEventListener(
            'change',
            updateActionSummary
        );
    });

    function updateActionOrder() {
        actionBuilder
            ?.querySelectorAll(
                '[data-decision-action-item]'
            )
            .forEach((item, index) => {
                const order =
                    item.querySelector(
                        '.action-order'
                    );

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
                '[data-decision-action-item]'
            )
        );

        items.slice(1).forEach((item) => {
            item.remove();
        });

        const first = items[0];

        first?.querySelectorAll(
            'input'
        ).forEach((input) => {
            input.value = '';
        });

        first?.querySelectorAll(
            'select'
        ).forEach((select) => {
            select.selectedIndex = 0;
        });

        updateActionOrder();
    }

    function updateActionSummary() {
        const rows = Array.from(
            document.querySelectorAll(
                '[data-decision-action-item]'
            )
        );

        let assigned = 0;
        let deadlines = 0;

        rows.forEach((row) => {
            if (
                row.querySelector(
                    '[data-action-office]'
                )?.value
            ) {
                assigned++;
            }

            if (
                row.querySelector(
                    '[data-action-date]'
                )?.value
            ) {
                deadlines++;
            }
        });

        const notifications =
            document.querySelectorAll(
                '[data-decision-notification]:checked'
            ).length;

        setText(
            'decisionActionCount',
            rows.length
        );

        setText(
            'decisionAssignedCount',
            assigned
        );

        setText(
            'decisionDeadlineCount',
            deadlines
        );

        setText(
            'decisionNotificationCount',
            notifications
        );

        setText(
            'decisionFinalActions',
            rows.length
        );

        return {
            total: rows.length,
            assigned,
            deadlines,
            notifications
        };
    }

    document.getElementById(
        'btnRunDecisionAudit'
    )?.addEventListener('click', () => {
        const summary =
            updateDecisionSummary();

        const statement =
            document.getElementById(
                'decisionStatement'
            )?.value.trim() || '';

        const measure =
            document.getElementById(
                'decisionMeasureSelect'
            )?.value || '';

        const ready =
            Boolean(measure) &&
            Boolean(statement) &&
            summary.actions.total > 0;

        showDialog({
            icon: ready ? 'success' : 'warning',
            title: 'Decision Record Audit',
            html: `
                <div class="text-start small">
                    <p><strong>Outcome:</strong> ${escapeHtml(summary.outcome)}</p>
                    <p><strong>Affirmative votes:</strong> ${summary.affirmative}</p>
                    <p><strong>Required votes:</strong> ${summary.required}</p>
                    <p><strong>Decision class:</strong> ${escapeHtml(summary.classification)}</p>
                    <p><strong>Action items:</strong> ${summary.actions.total}</p>
                    <p><strong>Assigned actions:</strong> ${summary.actions.assigned}</p>
                    <p><strong>Notification groups:</strong> ${summary.actions.notifications}</p>
                    <p class="text-muted">
                        The backend audit will also verify the certified tally,
                        source integrity, legal authority, duplicate decisions,
                        signatures, revision history, and release permissions.
                    </p>
                </div>
            `
        });
    });

    document.getElementById(
        'btnSaveDecisionDraft'
    )?.addEventListener('click', () => {
        showDialog({
            icon: 'info',
            title: 'Decision Draft UI Ready',
            text:
                'Draft saving will be connected after the dedicated decision tables and AJAX endpoints are implemented.'
        });
    });

    form?.addEventListener(
        'submit',
        (event) => {
            event.preventDefault();

            const measureOption =
                document.getElementById(
                    'decisionMeasureSelect'
                )?.selectedOptions?.[0];

            const statement =
                document.getElementById(
                    'decisionStatement'
                )?.value.trim() || '';

            const recordNumber =
                document.getElementById(
                    'decisionRecordNumber'
                )?.value.trim() || '';

            const summary =
                updateDecisionSummary();

            if (!measureOption?.value || !statement) {
                showDialog({
                    icon: 'warning',
                    title: 'Incomplete Decision Record',
                    text:
                        'Select a legislative measure and enter the complete official decision statement.'
                });

                showStep(
                    !measureOption?.value
                        ? 1
                        : 2
                );

                return;
            }

            previewDecisions.push({
                reference:
                    measureOption.dataset.reference || '',
                title:
                    measureOption.dataset.title || '',
                statement,
                recordNumber:
                    recordNumber || 'Record number pending',
                ...summary
            });

            renderPreviewBoard();

            showDialog({
                icon: 'success',
                title: 'Decision Preview Certified',
                text:
                    'The decision was added to this browser preview. It is not yet stored in MySQL.'
            }).then(() => {
                modal?.hide();
            });
        }
    );

    function renderPreviewBoard() {
        const board =
            document.getElementById(
                'decisionPreviewBoard'
            );

        if (!board || !previewDecisions.length) {
            return;
        }

        board.innerHTML =
            previewDecisions
                .slice()
                .reverse()
                .slice(0, 5)
                .map((decision) => `
                    <div class="decision-preview-card">
                        <div class="decision-preview-card-top">
                            <span class="decision-preview-icon">
                                <i class="bi bi-journal-check"></i>
                            </span>

                            <div class="decision-preview-copy">
                                <span class="decision-preview-reference">
                                    ${escapeHtml(decision.reference)}
                                </span>

                                <strong>
                                    ${escapeHtml(decision.title)}
                                </strong>

                                <small>
                                    ${escapeHtml(decision.recordNumber)}
                                    ·
                                    ${escapeHtml(decision.classification)}
                                    ·
                                    ${escapeHtml(decision.recordStatus)}
                                </small>
                            </div>

                            <span class="decision-preview-outcome ${outcomeClass(decision.outcome)}">
                                ${escapeHtml(decision.outcome)}
                            </span>
                        </div>

                        <p class="decision-preview-statement">
                            ${escapeHtml(decision.statement)}
                        </p>

                        <div class="decision-preview-stats">
                            <div>
                                <span>Affirmative</span>
                                <strong>${decision.affirmative}</strong>
                            </div>

                            <div>
                                <span>Negative</span>
                                <strong>${decision.negative}</strong>
                            </div>

                            <div>
                                <span>Abstain</span>
                                <strong>${decision.abstain}</strong>
                            </div>

                            <div>
                                <span>Required</span>
                                <strong>${decision.required}</strong>
                            </div>

                            <div>
                                <span>Actions</span>
                                <strong>${decision.actions.total}</strong>
                            </div>

                            <div>
                                <span>Notifications</span>
                                <strong>${decision.actions.notifications}</strong>
                            </div>
                        </div>
                    </div>
                `)
                .join('');
    }

    function outcomeClass(outcome) {
        const normalized =
            String(outcome)
                .toLowerCase();

        if (
            normalized.includes('approved') ||
            normalized.includes('adopted') ||
            normalized.includes('amended')
        ) {
            return 'positive';
        }

        if (
            normalized.includes('rejected') ||
            normalized.includes('not adopted')
        ) {
            return 'negative';
        }

        return 'pending';
    }

    const checklistItems = Array.from(
        document.querySelectorAll(
            '.decision-checklist-item'
        )
    );

    function updateChecklist() {
        if (!checklistItems.length) {
            return;
        }

        const completed =
            checklistItems.filter(
                (item) => item.checked
            ).length;

        const percent =
            Math.round(
                (
                    completed /
                    checklistItems.length
                ) * 100
            );

        setText(
            'decisionChecklistValue',
            `${percent}%`
        );

        const bar =
            document.getElementById(
                'decisionChecklistBar'
            );

        if (bar) {
            bar.style.width =
                `${percent}%`;
        }
    }

    checklistItems.forEach((item) => {
        item.addEventListener(
            'change',
            updateChecklist
        );
    });

    const documentInput =
        document.getElementById(
            'decisionDocuments'
        );

    const selectedFiles =
        document.getElementById(
            'decisionSelectedFiles'
        );

    documentInput?.addEventListener(
        'change',
        () => {
            if (!selectedFiles) {
                return;
            }

            selectedFiles.innerHTML = '';

            Array.from(
                documentInput.files
            ).forEach((file) => {
                const item =
                    document.createElement('div');

                item.className =
                    'decision-file-item';

                item.innerHTML = `
                    <i class="bi bi-file-earmark"></i>
                    <span>${escapeHtml(file.name)}</span>
                    <small>${formatFileSize(file.size)}</small>
                `;

                selectedFiles.appendChild(item);
            });
        }
    );

    document.querySelectorAll(
        '[data-decision-history]'
    ).forEach((button) => {
        button.addEventListener('click', () => {
            showDialog({
                icon: 'info',
                title: 'Decision History',
                text:
                    'Drafts, source links, certifications, revisions, releases, reconsiderations, supersessions, archival actions, and validation handoff will be connected later.'
            });
        });
    });

    const searchInput =
        document.getElementById(
            'decisionSearch'
        );

    const typeFilter =
        document.getElementById(
            'decisionTypeFilter'
        );

    const priorityFilter =
        document.getElementById(
            'decisionPriorityFilter'
        );

    const statusFilter =
        document.getElementById(
            'decisionStatusFilter'
        );

    const rows = Array.from(
        document.querySelectorAll(
            '.decision-data-row'
        )
    );

    const noResult =
        document.getElementById(
            'decisionNoFilterResult'
        );

    const countLabel =
        document.getElementById(
            'decisionRecordCount'
        );

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

            row.classList.toggle(
                'd-none',
                !show
            );

            if (show) {
                visible++;
            }
        });

        noResult?.classList.toggle(
            'd-none',
            visible > 0 ||
            rows.length === 0
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

    document.getElementById(
        'btnResetDecisionFilters'
    )?.addEventListener('click', () => {
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

    function toDateTimeLocal(date) {
        const offset =
            date.getTimezoneOffset() * 60000;

        return new Date(
            date.getTime() - offset
        ).toISOString().slice(0, 16);
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

    function showDialog(options) {
        if (typeof Swal !== 'undefined') {
            return Swal.fire(options);
        }

        const temporary =
            document.createElement('div');

        temporary.innerHTML =
            options.html || '';

        window.alert(
            `${options.title || 'Notice'}\n\n` +
            (
                options.text ||
                temporary.textContent ||
                ''
            )
        );

        return Promise.resolve();
    }

    updateDecisionSummary();
});
