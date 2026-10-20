'use strict';

document.addEventListener('DOMContentLoaded', () => {
    const modalElement =
        document.getElementById('votingModal');

    const modal =
        modalElement &&
        typeof bootstrap !== 'undefined'
            ? new bootstrap.Modal(modalElement)
            : null;

    const form =
        document.getElementById('votingForm');

    const stepButtons = Array.from(
        document.querySelectorAll(
            '[data-voting-step]'
        )
    );

    const sections = Array.from(
        document.querySelectorAll(
            '[data-voting-form-section]'
        )
    );

    const previousButton =
        document.getElementById(
            'btnPreviousVotingStep'
        );

    const nextButton =
        document.getElementById(
            'btnNextVotingStep'
        );

    const submitButton =
        document.getElementById(
            'btnSubmitVoting'
        );

    const optionBuilder =
        document.getElementById(
            'votingOptionBuilder'
        );

    const rosterBuilder =
        document.getElementById(
            'votingRosterBuilder'
        );

    let currentStep = 1;
    const maximumStep = 4;
    const previewSessions = [];

    function showStep(step) {
        currentStep = step;

        stepButtons.forEach((button) => {
            button.classList.toggle(
                'active',
                Number(
                    button.dataset.votingStep
                ) === step
            );
        });

        sections.forEach((section) => {
            section.classList.toggle(
                'active',
                Number(
                    section.dataset.votingFormSection
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

        updateFinalBoard();
    }

    function openVotingSession(options = {}) {
        if (!modal || !form) {
            return;
        }

        form.reset();

        setText(
            'votingModalTitle',
            options.reference
                ? `Create Vote: ${options.reference}`
                : 'Create Voting Session'
        );

        setValue(
            'votingMotion',
            options.title
                ? `Motion concerning ${options.title}`
                : ''
        );

        setValue(
            'votingSessionDate',
            toDateKey(new Date())
        );

        setValue(
            'votingOpeningTime',
            '09:00'
        );

        setValue(
            'votingDuration',
            '10'
        );

        const measureSelect =
            document.getElementById(
                'votingMeasureSelect'
            );

        if (measureSelect) {
            measureSelect.value =
                options.candidateId || '';

            measureSelect.dispatchEvent(
                new Event('change')
            );
        }

        resetDefaultOptions();
        resetRosterBuilder();
        updateRosterSummary();
        updateFinalBoard();
        showStep(1);
        modal.show();
    }

    document.getElementById(
        'btnCreateVotingSession'
    )?.addEventListener('click', () => {
        openVotingSession();
    });

    document.getElementById(
        'btnQuickVotingSession'
    )?.addEventListener('click', () => {
        openVotingSession();
    });

    document.querySelectorAll(
        '[data-create-voting]'
    ).forEach((button) => {
        button.addEventListener('click', () => {
            openVotingSession({
                candidateId:
                    button.dataset.createVoting || '',
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
        ).get('vote');

    if (candidateFromUrl) {
        const button =
            document.querySelector(
                `[data-create-voting="${CSS.escape(
                    candidateFromUrl
                )}"]`
            );

        button?.click();
    }

    stepButtons.forEach((button) => {
        button.addEventListener('click', () => {
            showStep(
                Number(
                    button.dataset.votingStep
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
            'votingMeasureSelect'
        );

    const measurePreview =
        document.getElementById(
            'votingMeasurePreview'
        );

    measureSelect?.addEventListener(
        'change',
        () => {
            const option =
                measureSelect.options[
                    measureSelect.selectedIndex
                ];

            if (
                !option ||
                !option.value
            ) {
                if (measurePreview) {
                    measurePreview.innerHTML = `
                        <i class="bi bi-file-earmark-text"></i>

                        <div>
                            <strong>
                                No legislative measure selected
                            </strong>

                            <span>
                                Select an ordinance or resolution
                                to configure the voting session.
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

    document.getElementById(
        'btnAddVotingOption'
    )?.addEventListener('click', () => {
        addVotingOption({
            label: 'OPTION',
            description: '',
            category: 'Custom'
        });
    });

    document.getElementById(
        'btnResetDefaultOptions'
    )?.addEventListener(
        'click',
        resetDefaultOptions
    );

    optionBuilder?.addEventListener(
        'click',
        (event) => {
            const removeButton =
                event.target.closest(
                    '[data-remove-voting-option]'
                );

            if (!removeButton) {
                return;
            }

            const items =
                optionBuilder.querySelectorAll(
                    '[data-voting-option-item]'
                );

            if (items.length <= 2) {
                showDialog({
                    icon: 'info',
                    title: 'Ballot Options Required',
                    text:
                        'Keep at least two ballot options.'
                });

                return;
            }

            removeButton
                .closest(
                    '[data-voting-option-item]'
                )
                ?.remove();

            updateOptionOrder();
        }
    );

    function addVotingOption(config) {
        if (!optionBuilder) {
            return;
        }

        const item =
            document.createElement('div');

        item.className =
            'voting-option-item';

        item.dataset.votingOptionItem = '';

        item.innerHTML = `
            <span class="option-order">1</span>

            <div class="option-fields">
                <input
                    type="text"
                    class="form-control"
                    name="option_label[]"
                    value="${escapeHtml(config.label || '')}"
                    placeholder="Option label"
                >

                <input
                    type="text"
                    class="form-control"
                    name="option_description[]"
                    value="${escapeHtml(config.description || '')}"
                    placeholder="Option description"
                >

                <select
                    class="form-select"
                    name="option_category[]"
                >
                    ${[
                        'Affirmative',
                        'Negative',
                        'Abstention',
                        'Custom'
                    ].map((category) => {
                        return `
                            <option
                                ${category === config.category ? 'selected' : ''}
                            >
                                ${category}
                            </option>
                        `;
                    }).join('')}
                </select>
            </div>

            <button
                type="button"
                class="option-remove-button"
                data-remove-voting-option
                title="Remove option"
            >
                <i class="bi bi-trash"></i>
            </button>
        `;

        optionBuilder.appendChild(item);
        updateOptionOrder();
    }

    function resetDefaultOptions() {
        if (!optionBuilder) {
            return;
        }

        optionBuilder.innerHTML = '';

        [
            {
                label: 'YES',
                description:
                    'Approve or support the motion',
                category: 'Affirmative'
            },
            {
                label: 'NO',
                description:
                    'Reject or oppose the motion',
                category: 'Negative'
            },
            {
                label: 'ABSTAIN',
                description:
                    'Record abstention',
                category: 'Abstention'
            }
        ].forEach(addVotingOption);
    }

    function updateOptionOrder() {
        optionBuilder
            ?.querySelectorAll(
                '[data-voting-option-item]'
            )
            .forEach((item, index) => {
                const order =
                    item.querySelector(
                        '.option-order'
                    );

                if (order) {
                    order.textContent =
                        String(index + 1);
                }
            });
    }

    function buildParticipantOptions() {
        const participants =
            window.VQDSS_VOTING_PARTICIPANTS || [];

        return [
            '<option value="">-- Select voter --</option>',
            ...participants.map((participant) => {
                return `
                    <option
                        value="${escapeHtml(participant.id)}"
                        data-name="${escapeHtml(participant.name)}"
                        data-role="${escapeHtml(participant.role)}"
                        data-email="${escapeHtml(participant.email)}"
                    >
                        ${escapeHtml(participant.name)}
                        —
                        ${escapeHtml(participant.role)}
                    </option>
                `;
            })
        ].join('');
    }

    document.getElementById(
        'btnAddVoter'
    )?.addEventListener('click', () => {
        if (!rosterBuilder) {
            return;
        }

        const item =
            document.createElement('div');

        item.className =
            'voting-roster-item';

        item.dataset.votingRosterItem = '';

        item.innerHTML = `
            <span class="roster-order">1</span>

            <div class="roster-fields">
                <select
                    class="form-select"
                    name="voter_id[]"
                    data-voter-member
                >
                    ${buildParticipantOptions()}
                </select>

                <select
                    class="form-select"
                    name="voter_eligibility[]"
                    data-voter-eligibility
                >
                    <option>Eligible</option>
                    <option>Non-Voting</option>
                    <option>Disqualified</option>
                    <option>Conflict</option>
                </select>

                <select
                    class="form-select"
                    name="voting_channel[]"
                    data-voting-channel
                >
                    <option>Manual</option>
                    <option>Electronic</option>
                    <option>Remote Electronic</option>
                    <option>Roll Call</option>
                </select>

                <input
                    type="text"
                    class="form-control"
                    name="voter_note[]"
                    placeholder="Voting right or restriction note"
                >
            </div>

            <button
                type="button"
                class="roster-remove-button"
                data-remove-voter
                title="Remove voter"
            >
                <i class="bi bi-trash"></i>
            </button>
        `;

        rosterBuilder.appendChild(item);
        updateRosterOrder();
        updateRosterSummary();
    });

    rosterBuilder?.addEventListener(
        'change',
        updateRosterSummary
    );

    rosterBuilder?.addEventListener(
        'input',
        updateRosterSummary
    );

    rosterBuilder?.addEventListener(
        'click',
        (event) => {
            const removeButton =
                event.target.closest(
                    '[data-remove-voter]'
                );

            if (!removeButton) {
                return;
            }

            const items =
                rosterBuilder.querySelectorAll(
                    '[data-voting-roster-item]'
                );

            if (items.length <= 1) {
                showDialog({
                    icon: 'info',
                    title: 'Voter Row Required',
                    text:
                        'Keep at least one voter row.'
                });

                return;
            }

            removeButton
                .closest(
                    '[data-voting-roster-item]'
                )
                ?.remove();

            updateRosterOrder();
            updateRosterSummary();
        }
    );

    document.getElementById(
        'btnMarkAllEligible'
    )?.addEventListener('click', () => {
        document.querySelectorAll(
            '[data-voter-eligibility]'
        ).forEach((select) => {
            select.value = 'Eligible';
        });

        updateRosterSummary();
    });

    document.getElementById(
        'btnImportQuorumRoster'
    )?.addEventListener('click', () => {
        showDialog({
            icon: 'info',
            title: 'Certified Quorum Roster Import',
            text:
                'The backend version will import members from the latest certified quorum check. For now, add shared users manually.'
        });
    });

    function updateRosterOrder() {
        rosterBuilder
            ?.querySelectorAll(
                '[data-voting-roster-item]'
            )
            .forEach((item, index) => {
                const order =
                    item.querySelector(
                        '.roster-order'
                    );

                if (order) {
                    order.textContent =
                        String(index + 1);
                }
            });
    }

    function resetRosterBuilder() {
        if (!rosterBuilder) {
            return;
        }

        const items = Array.from(
            rosterBuilder.querySelectorAll(
                '[data-voting-roster-item]'
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

        updateRosterOrder();
    }

    function updateRosterSummary() {
        const rows = Array.from(
            document.querySelectorAll(
                '[data-voting-roster-item]'
            )
        );

        let eligible = 0;
        let nonVoting = 0;
        let restricted = 0;
        let selectedRows = 0;

        rows.forEach((row) => {
            const memberSelected =
                Boolean(
                    row.querySelector(
                        '[data-voter-member]'
                    )?.value
                );

            if (!memberSelected) {
                return;
            }

            selectedRows++;

            const status =
                row.querySelector(
                    '[data-voter-eligibility]'
                )?.value || '';

            if (status === 'Eligible') {
                eligible++;
            } else if (
                status === 'Non-Voting'
            ) {
                nonVoting++;
            } else {
                restricted++;
            }
        });

        setText(
            'votingRosterCount',
            rows.length
        );

        setText(
            'votingEligibleCount',
            eligible
        );

        setText(
            'votingNonVotingCount',
            nonVoting
        );

        setText(
            'votingRestrictedCount',
            restricted
        );

        setText(
            'votingFinalEligible',
            eligible
        );

        return {
            totalRows: rows.length,
            selectedRows,
            eligible,
            nonVoting,
            restricted
        };
    }

    [
        'votingMethod',
        'votingVisibility',
        'votingDuration',
        'votingInitialStatus'
    ].forEach((id) => {
        const element =
            document.getElementById(id);

        element?.addEventListener(
            'change',
            updateFinalBoard
        );

        element?.addEventListener(
            'input',
            updateFinalBoard
        );
    });

    function updateFinalBoard() {
        const method =
            document.getElementById(
                'votingMethod'
            )?.value || 'Manual';

        const visibility =
            document.getElementById(
                'votingVisibility'
            )?.value || 'Open / Recorded';

        const duration =
            Number(
                document.getElementById(
                    'votingDuration'
                )?.value || 10
            );

        const status =
            document.getElementById(
                'votingInitialStatus'
            )?.value || 'Ready to Open';

        const roster =
            updateRosterSummary();

        setText(
            'votingFinalMethod',
            method
        );

        setText(
            'votingFinalVisibility',
            visibility
        );

        setText(
            'votingFinalEligible',
            roster.eligible
        );

        setText(
            'votingFinalDuration',
            `${duration} minute${duration === 1 ? '' : 's'}`
        );

        setText(
            'votingFinalStatus',
            status
        );
    }

    let previewControlStatus =
        'Ready to Open';

    document.getElementById(
        'btnPreviewOpenVote'
    )?.addEventListener('click', () => {
        previewControlStatus = 'Open';
        setValue(
            'votingInitialStatus',
            'Open Immediately'
        );
        setText(
            'votingFinalStatus',
            'Open'
        );

        showDialog({
            icon: 'success',
            title: 'Voting Preview Opened',
            text:
                'The browser-preview ballot is now open. No real votes are being stored.'
        });
    });

    document.getElementById(
        'btnPreviewPauseVote'
    )?.addEventListener('click', () => {
        previewControlStatus = 'Paused';
        setText(
            'votingFinalStatus',
            'Paused'
        );

        showDialog({
            icon: 'info',
            title: 'Voting Preview Paused',
            text:
                'Vote submission is paused in the browser preview.'
        });
    });

    document.getElementById(
        'btnPreviewCloseVote'
    )?.addEventListener('click', () => {
        previewControlStatus = 'Closed';
        setText(
            'votingFinalStatus',
            'Closed'
        );

        showDialog({
            icon: 'success',
            title: 'Voting Preview Closed',
            text:
                'The browser-preview ballot is locked and ready for Vote Tallying.'
        });
    });

    document.getElementById(
        'btnRunVotingAudit'
    )?.addEventListener('click', () => {
        const roster =
            updateRosterSummary();

        const options =
            document.querySelectorAll(
                '[data-voting-option-item]'
            ).length;

        const method =
            document.getElementById(
                'votingMethod'
            )?.value || 'Manual';

        showDialog({
            icon:
                roster.eligible > 0 &&
                options >= 2
                    ? 'success'
                    : 'warning',
            title: 'Voting Audit Result',
            html: `
                <div class="text-start small">
                    <p><strong>Voting method:</strong> ${escapeHtml(method)}</p>
                    <p><strong>Ballot options:</strong> ${options}</p>
                    <p><strong>Roster entries:</strong> ${roster.totalRows}</p>
                    <p><strong>Eligible voters:</strong> ${roster.eligible}</p>
                    <p><strong>Restricted voters:</strong> ${roster.restricted}</p>
                    <p class="text-muted">
                        The final audit will also verify certified quorum,
                        duplicate voters, identity, permissions, encryption,
                        access codes, device restrictions, and ballot integrity.
                    </p>
                </div>
            `
        });
    });

    document.getElementById(
        'btnSaveVotingDraft'
    )?.addEventListener('click', () => {
        showDialog({
            icon: 'info',
            title: 'Voting Draft UI Ready',
            text:
                'Draft saving will be connected after the dedicated voting tables and AJAX endpoints are implemented.'
        });
    });

    form?.addEventListener(
        'submit',
        (event) => {
            event.preventDefault();

            const measureOption =
                document.getElementById(
                    'votingMeasureSelect'
                )?.selectedOptions?.[0];

            const motion =
                document.getElementById(
                    'votingMotion'
                )?.value.trim() || '';

            const method =
                document.getElementById(
                    'votingMethod'
                )?.value || 'Manual';

            const visibility =
                document.getElementById(
                    'votingVisibility'
                )?.value || 'Open / Recorded';

            const duration =
                Number(
                    document.getElementById(
                        'votingDuration'
                    )?.value || 10
                );

            const initialStatus =
                previewControlStatus !==
                    'Ready to Open'
                    ? previewControlStatus
                    : (
                        document.getElementById(
                            'votingInitialStatus'
                        )?.value ||
                        'Ready to Open'
                    );

            const roster =
                updateRosterSummary();

            const options = Array.from(
                document.querySelectorAll(
                    '[data-voting-option-item]'
                )
            ).map((item) => {
                return (
                    item.querySelector(
                        'input[name="option_label[]"]'
                    )?.value.trim() || 'Option'
                );
            });

            if (
                !measureOption?.value ||
                !motion
            ) {
                showDialog({
                    icon: 'warning',
                    title: 'Missing Voting Information',
                    text:
                        'Select a legislative measure and enter the exact motion or voting question.'
                });

                showStep(1);
                return;
            }

            if (
                options.length < 2 ||
                roster.eligible < 1
            ) {
                showDialog({
                    icon: 'warning',
                    title: 'Incomplete Ballot or Voter Roster',
                    text:
                        'Keep at least two ballot options and add at least one eligible voter.'
                });

                return;
            }

            previewSessions.push({
                reference:
                    measureOption.dataset.reference || '',
                title:
                    measureOption.dataset.title || '',
                motion,
                method,
                visibility,
                duration,
                status: initialStatus,
                eligible: roster.eligible,
                submitted: 0,
                options
            });

            renderLiveBoard();

            showDialog({
                icon: 'success',
                title: 'Voting Session Preview Created',
                text:
                    'The session was added to this browser preview. It is not yet stored in MySQL.'
            }).then(() => {
                modal?.hide();
                previewControlStatus =
                    'Ready to Open';
            });
        }
    );

    function renderLiveBoard() {
        const board =
            document.getElementById(
                'votingLiveBoard'
            );

        if (
            !board ||
            !previewSessions.length
        ) {
            return;
        }

        board.innerHTML =
            previewSessions
                .slice()
                .reverse()
                .slice(0, 5)
                .map((session, index) => {
                    const percent =
                        session.eligible > 0
                            ? Math.round(
                                (
                                    session.submitted /
                                    session.eligible
                                ) * 100
                            )
                            : 0;

                    return `
                        <div class="voting-live-card">
                            <div class="voting-live-card-top">
                                <span class="voting-live-icon">
                                    <i class="bi bi-ui-checks"></i>
                                </span>

                                <div class="voting-live-copy">
                                    <span class="voting-live-reference">
                                        ${escapeHtml(session.reference)}
                                    </span>

                                    <strong>
                                        ${escapeHtml(session.motion)}
                                    </strong>

                                    <small>
                                        ${escapeHtml(session.method)}
                                        ·
                                        ${escapeHtml(session.visibility)}
                                        ·
                                        ${session.duration} minutes
                                    </small>
                                </div>

                                <span class="voting-live-status ${statusClass(session.status)}">
                                    ${escapeHtml(session.status)}
                                </span>
                            </div>

                            <div class="voting-live-options">
                                ${session.options.map((option) => `
                                    <span>${escapeHtml(option)}</span>
                                `).join('')}
                            </div>

                            <div class="voting-live-stats">
                                <div>
                                    <span>Eligible Voters</span>
                                    <strong>${session.eligible}</strong>
                                </div>

                                <div>
                                    <span>Votes Submitted</span>
                                    <strong>${session.submitted}</strong>
                                </div>

                                <div>
                                    <span>Participation</span>
                                    <strong>${percent}%</strong>
                                </div>

                                <div>
                                    <span>Time Limit</span>
                                    <strong>${session.duration}m</strong>
                                </div>
                            </div>

                            <div class="voting-live-actions">
                                <button
                                    type="button"
                                    data-live-action="open"
                                    data-session-index="${previewSessions.length - 1 - index}"
                                >
                                    <i class="bi bi-play"></i>
                                    Open
                                </button>

                                <button
                                    type="button"
                                    data-live-action="simulate"
                                    data-session-index="${previewSessions.length - 1 - index}"
                                >
                                    <i class="bi bi-person-check"></i>
                                    Simulate Vote
                                </button>

                                <button
                                    type="button"
                                    data-live-action="close"
                                    data-session-index="${previewSessions.length - 1 - index}"
                                >
                                    <i class="bi bi-stop"></i>
                                    Close
                                </button>
                            </div>

                            <div class="voting-live-meter">
                                <span style="width:${percent}%"></span>
                            </div>
                        </div>
                    `;
                })
                .join('');
    }

    document.getElementById(
        'votingLiveBoard'
    )?.addEventListener('click', (event) => {
        const button =
            event.target.closest(
                '[data-live-action]'
            );

        if (!button) {
            return;
        }

        const index =
            Number(
                button.dataset.sessionIndex
            );

        const session =
            previewSessions[index];

        if (!session) {
            return;
        }

        const action =
            button.dataset.liveAction;

        if (action === 'open') {
            session.status = 'Open';
        } else if (action === 'close') {
            session.status = 'Closed';
        } else if (
            action === 'simulate' &&
            session.status === 'Open'
        ) {
            session.submitted =
                Math.min(
                    session.eligible,
                    session.submitted + 1
                );
        } else if (
            action === 'simulate'
        ) {
            showDialog({
                icon: 'info',
                title: 'Session Not Open',
                text:
                    'Open the browser-preview session before simulating a vote.'
            });

            return;
        }

        renderLiveBoard();
    });

    function statusClass(status) {
        const normalized =
            String(status)
                .toLowerCase()
                .replace(/[^a-z0-9]+/g, '-');

        return normalized;
    }

    const checklistItems = Array.from(
        document.querySelectorAll(
            '.voting-checklist-item'
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
            'votingChecklistValue',
            `${percent}%`
        );

        const bar =
            document.getElementById(
                'votingChecklistBar'
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
            'votingDocuments'
        );

    const selectedFiles =
        document.getElementById(
            'votingSelectedFiles'
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
                    'voting-file-item';

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
        '[data-voting-history]'
    ).forEach((button) => {
        button.addEventListener('click', () => {
            showDialog({
                icon: 'info',
                title: 'Voting Session History',
                text:
                    'Session creation, quorum link, opening, vote submissions, pauses, closures, operator actions, audits, and tallying handoff will be connected later.'
            });
        });
    });

    const searchInput =
        document.getElementById(
            'votingSearch'
        );

    const typeFilter =
        document.getElementById(
            'votingTypeFilter'
        );

    const priorityFilter =
        document.getElementById(
            'votingPriorityFilter'
        );

    const statusFilter =
        document.getElementById(
            'votingStatusFilter'
        );

    const rows = Array.from(
        document.querySelectorAll(
            '.voting-data-row'
        )
    );

    const noResult =
        document.getElementById(
            'votingNoFilterResult'
        );

    const countLabel =
        document.getElementById(
            'votingRecordCount'
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
        'btnResetVotingFilters'
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

    function toDateKey(date) {
        const year =
            date.getFullYear();

        const month =
            String(
                date.getMonth() + 1
            ).padStart(2, '0');

        const day =
            String(
                date.getDate()
            ).padStart(2, '0');

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

    updateRosterSummary();
    updateFinalBoard();
});
