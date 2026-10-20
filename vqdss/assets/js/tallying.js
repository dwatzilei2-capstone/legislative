'use strict';

document.addEventListener('DOMContentLoaded', () => {
    const modalElement =
        document.getElementById('tallyModal');

    const modal =
        modalElement &&
        typeof bootstrap !== 'undefined'
            ? new bootstrap.Modal(modalElement)
            : null;

    const form =
        document.getElementById('tallyForm');

    const stepButtons = Array.from(
        document.querySelectorAll(
            '[data-tally-step]'
        )
    );

    const sections = Array.from(
        document.querySelectorAll(
            '[data-tally-form-section]'
        )
    );

    const previousButton =
        document.getElementById(
            'btnPreviousTallyStep'
        );

    const nextButton =
        document.getElementById(
            'btnNextTallyStep'
        );

    const submitButton =
        document.getElementById(
            'btnSubmitTally'
        );

    const optionBuilder =
        document.getElementById(
            'tallyOptionBuilder'
        );

    let currentStep = 1;
    const maximumStep = 4;
    const previewTallies = [];

    function showStep(step) {
        currentStep = step;

        stepButtons.forEach((button) => {
            button.classList.toggle(
                'active',
                Number(
                    button.dataset.tallyStep
                ) === step
            );
        });

        sections.forEach((section) => {
            section.classList.toggle(
                'active',
                Number(
                    section.dataset.tallyFormSection
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

        calculateTally();
    }

    function openTally(options = {}) {
        if (!modal || !form) {
            return;
        }

        form.reset();

        setText(
            'tallyModalTitle',
            options.reference
                ? `Create Tally: ${options.reference}`
                : 'Create Vote Tally'
        );

        setValue(
            'tallyDateTime',
            toDateTimeLocal(new Date())
        );

        setValue(
            'tallyEligibleVoters',
            '10'
        );

        setValue(
            'tallyFixedRequirement',
            '6'
        );

        const measureSelect =
            document.getElementById(
                'tallyMeasureSelect'
            );

        if (measureSelect) {
            measureSelect.value =
                options.candidateId || '';

            measureSelect.dispatchEvent(
                new Event('change')
            );
        }

        resetDefaultOptions();
        calculateTally();
        showStep(1);
        modal.show();
    }

    document.getElementById(
        'btnCreateTally'
    )?.addEventListener('click', () => {
        openTally();
    });

    document.getElementById(
        'btnQuickTally'
    )?.addEventListener('click', () => {
        openTally();
    });

    document.querySelectorAll(
        '[data-create-tally]'
    ).forEach((button) => {
        button.addEventListener('click', () => {
            openTally({
                candidateId:
                    button.dataset.createTally || '',
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
        ).get('tally');

    if (candidateFromUrl) {
        const button =
            document.querySelector(
                `[data-create-tally="${CSS.escape(
                    candidateFromUrl
                )}"]`
            );

        button?.click();
    }

    stepButtons.forEach((button) => {
        button.addEventListener('click', () => {
            showStep(
                Number(
                    button.dataset.tallyStep
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
            'tallyMeasureSelect'
        );

    const measurePreview =
        document.getElementById(
            'tallyMeasurePreview'
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
                                Select the measure connected
                                to the closed voting session.
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
        'btnAddTallyOption'
    )?.addEventListener('click', () => {
        addTallyOption({
            label: 'OPTION',
            category: 'Custom',
            count: 0
        });
    });

    document.getElementById(
        'btnResetTallyOptions'
    )?.addEventListener(
        'click',
        resetDefaultOptions
    );

    document.getElementById(
        'btnImportVoteCounts'
    )?.addEventListener('click', () => {
        showDialog({
            icon: 'info',
            title: 'Closed Voting Session Import',
            text:
                'The backend version will import ballot options, vote totals, voter eligibility, timestamps, and source hashes from a closed voting event.'
        });
    });

    optionBuilder?.addEventListener(
        'click',
        (event) => {
            const removeButton =
                event.target.closest(
                    '[data-remove-tally-option]'
                );

            if (!removeButton) {
                return;
            }

            const items =
                optionBuilder.querySelectorAll(
                    '[data-tally-option-item]'
                );

            if (items.length <= 2) {
                showDialog({
                    icon: 'info',
                    title: 'Vote Options Required',
                    text:
                        'Keep at least two vote options.'
                });

                return;
            }

            removeButton
                .closest(
                    '[data-tally-option-item]'
                )
                ?.remove();

            updateOptionOrder();
            calculateTally();
        }
    );

    optionBuilder?.addEventListener(
        'input',
        calculateTally
    );

    optionBuilder?.addEventListener(
        'change',
        calculateTally
    );

    function addTallyOption(config) {
        if (!optionBuilder) {
            return;
        }

        const item =
            document.createElement('div');

        item.className =
            'tally-option-item';

        item.dataset.tallyOptionItem = '';

        item.innerHTML = `
            <span class="option-order">1</span>

            <div class="option-fields">
                <input
                    type="text"
                    class="form-control"
                    name="option_label[]"
                    value="${escapeHtml(config.label || '')}"
                    placeholder="Option label"
                    data-tally-label
                >

                <select
                    class="form-select"
                    name="option_category[]"
                    data-tally-category
                >
                    ${[
                        'Affirmative',
                        'Negative',
                        'Abstention',
                        'Invalid',
                        'Challenged',
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

                <input
                    type="number"
                    class="form-control"
                    name="option_count[]"
                    value="${Number(config.count || 0)}"
                    min="0"
                    data-tally-count
                >

                <input
                    type="text"
                    class="form-control"
                    name="option_note[]"
                    placeholder="Count note or source reference"
                >
            </div>

            <button
                type="button"
                class="option-remove-button"
                data-remove-tally-option
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
                category: 'Affirmative',
                count: 6
            },
            {
                label: 'NO',
                category: 'Negative',
                count: 3
            },
            {
                label: 'ABSTAIN',
                category: 'Abstention',
                count: 1
            }
        ].forEach(addTallyOption);

        calculateTally();
    }

    function updateOptionOrder() {
        optionBuilder
            ?.querySelectorAll(
                '[data-tally-option-item]'
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

    [
        'tallyEligibleVoters',
        'tallyThreshold',
        'tallyFixedRequirement',
        'tallyInvalidBallots',
        'tallyChallengedBallots'
    ].forEach((id) => {
        const element =
            document.getElementById(id);

        element?.addEventListener(
            'input',
            calculateTally
        );

        element?.addEventListener(
            'change',
            calculateTally
        );
    });

    function calculateTally() {
        const eligible =
            Math.max(
                0,
                Number(
                    document.getElementById(
                        'tallyEligibleVoters'
                    )?.value || 0
                )
            );

        const threshold =
            document.getElementById(
                'tallyThreshold'
            )?.value || 'simple_majority';

        const fixedRequirement =
            Math.max(
                1,
                Number(
                    document.getElementById(
                        'tallyFixedRequirement'
                    )?.value || 1
                )
            );

        const rows = Array.from(
            document.querySelectorAll(
                '[data-tally-option-item]'
            )
        );

        let totalCast = 0;
        let affirmative = 0;
        let negative = 0;
        let abstention = 0;
        let invalidCategory = 0;
        let challengedCategory = 0;

        const options = rows.map((row) => {
            const label =
                row.querySelector(
                    '[data-tally-label]'
                )?.value.trim() || 'Option';

            const category =
                row.querySelector(
                    '[data-tally-category]'
                )?.value || 'Custom';

            const count =
                Math.max(
                    0,
                    Number(
                        row.querySelector(
                            '[data-tally-count]'
                        )?.value || 0
                    )
                );

            totalCast += count;

            if (category === 'Affirmative') {
                affirmative += count;
            } else if (category === 'Negative') {
                negative += count;
            } else if (category === 'Abstention') {
                abstention += count;
            } else if (category === 'Invalid') {
                invalidCategory += count;
            } else if (category === 'Challenged') {
                challengedCategory += count;
            }

            return {
                label,
                category,
                count
            };
        });

        const externalInvalid =
            Math.max(
                0,
                Number(
                    document.getElementById(
                        'tallyInvalidBallots'
                    )?.value || 0
                )
            );

        const externalChallenged =
            Math.max(
                0,
                Number(
                    document.getElementById(
                        'tallyChallengedBallots'
                    )?.value || 0
                )
            );

        const totalInvalid =
            invalidCategory + externalInvalid;

        const totalChallenged =
            challengedCategory + externalChallenged;

        const validDecisionVotes =
            Math.max(
                0,
                affirmative + negative
            );

        let required =
            Math.floor(
                validDecisionVotes / 2
            ) + 1;

        if (threshold === 'absolute_majority') {
            required =
                Math.floor(eligible / 2) + 1;
        } else if (threshold === 'two_thirds') {
            required =
                Math.ceil(
                    validDecisionVotes *
                    (2 / 3)
                );
        } else if (
            threshold === 'three_fourths'
        ) {
            required =
                Math.ceil(
                    validDecisionVotes *
                    0.75
                );
        } else if (
            threshold === 'unanimous'
        ) {
            required =
                validDecisionVotes;
        } else if (
            threshold === 'fixed'
        ) {
            required =
                fixedRequirement;
        }

        const passed =
            validDecisionVotes > 0 &&
            affirmative >= required;

        const missing =
            Math.max(
                0,
                eligible - totalCast
            );

        const difference =
            totalCast - eligible;

        const balanced =
            difference <= 0;

        const participation =
            eligible > 0
                ? Math.min(
                    100,
                    Math.round(
                        (
                            totalCast /
                            eligible
                        ) * 100
                    )
                )
                : 0;

        setText(
            'tallyCalcEligible',
            eligible
        );

        setText(
            'tallyCalcCast',
            totalCast
        );

        setText(
            'tallyCalcValid',
            validDecisionVotes
        );

        setText(
            'tallyCalcRequired',
            required
        );

        setText(
            'tallyCalcAffirmative',
            affirmative
        );

        setText(
            'tallyCalcOutcome',
            passed ? 'Passed' : 'Failed'
        );

        const calcOutcome =
            document.getElementById(
                'tallyCalcOutcome'
            );

        calcOutcome?.classList.toggle(
            'is-passed',
            passed
        );

        calcOutcome?.classList.toggle(
            'is-failed',
            !passed
        );

        setValue(
            'tallyMissingVotes',
            missing
        );

        setText(
            'tallyParticipationValue',
            `${participation}%`
        );

        const participationBar =
            document.getElementById(
                'tallyParticipationBar'
            );

        if (participationBar) {
            participationBar.style.width =
                `${participation}%`;
        }

        setText(
            'tallyAuditSource',
            totalCast
        );

        setText(
            'tallyAuditEligible',
            eligible
        );

        setText(
            'tallyAuditDifference',
            difference
        );

        setText(
            'tallyAuditStatus',
            balanced
                ? 'Balanced'
                : 'Mismatch'
        );

        const auditStatus =
            document.getElementById(
                'tallyAuditStatus'
            );

        auditStatus?.classList.toggle(
            'is-balanced',
            balanced
        );

        auditStatus?.classList.toggle(
            'is-mismatch',
            !balanced
        );

        setText(
            'tallyFinalCast',
            totalCast
        );

        setText(
            'tallyFinalValid',
            validDecisionVotes
        );

        setText(
            'tallyFinalRequired',
            required
        );

        setText(
            'tallyFinalAffirmative',
            affirmative
        );

        setText(
            'tallyFinalOutcome',
            passed ? 'Passed' : 'Failed'
        );

        const finalOutcome =
            document.getElementById(
                'tallyFinalOutcome'
            );

        finalOutcome?.classList.toggle(
            'is-passed',
            passed
        );

        finalOutcome?.classList.toggle(
            'is-failed',
            !passed
        );

        return {
            eligible,
            totalCast,
            validDecisionVotes,
            affirmative,
            negative,
            abstention,
            totalInvalid,
            totalChallenged,
            required,
            passed,
            missing,
            difference,
            balanced,
            participation,
            options
        };
    }

    document.getElementById(
        'btnRunTallyAudit'
    )?.addEventListener('click', () => {
        const result =
            calculateTally();

        showDialog({
            icon:
                result.balanced
                    ? 'success'
                    : 'warning',
            title: 'Tally Audit Result',
            html: `
                <div class="text-start small">
                    <p><strong>Eligible voters:</strong> ${result.eligible}</p>
                    <p><strong>Total votes cast:</strong> ${result.totalCast}</p>
                    <p><strong>Valid decision votes:</strong> ${result.validDecisionVotes}</p>
                    <p><strong>Invalid ballots:</strong> ${result.totalInvalid}</p>
                    <p><strong>Challenged ballots:</strong> ${result.totalChallenged}</p>
                    <p><strong>Participation:</strong> ${result.participation}%</p>
                    <p><strong>Audit status:</strong> ${result.balanced ? 'Balanced' : 'Count mismatch detected'}</p>
                    <p class="text-muted">
                        The final audit will also verify source hashes,
                        duplicate ballots, voter eligibility, timestamps,
                        manual corrections, recount authorization, and
                        operator audit logs.
                    </p>
                </div>
            `
        });
    });

    document.getElementById(
        'btnSaveTallyDraft'
    )?.addEventListener('click', () => {
        showDialog({
            icon: 'info',
            title: 'Tally Draft UI Ready',
            text:
                'Draft saving will be connected after the dedicated tally tables and AJAX endpoints are implemented.'
        });
    });

    form?.addEventListener(
        'submit',
        (event) => {
            event.preventDefault();

            const measureOption =
                document.getElementById(
                    'tallyMeasureSelect'
                )?.selectedOptions?.[0];

            const sessionReference =
                document.getElementById(
                    'tallySessionReference'
                )?.value.trim() || '';

            const source =
                document.getElementById(
                    'tallySource'
                )?.value || 'Electronic';

            const status =
                document.getElementById(
                    'tallyStatus'
                )?.value || 'Verified';

            const result =
                calculateTally();

            if (!measureOption?.value) {
                showDialog({
                    icon: 'warning',
                    title: 'Legislative Measure Required',
                    text:
                        'Select the legislative measure connected to the tally.'
                });

                showStep(1);
                return;
            }

            if (
                result.options.length < 2 ||
                result.totalCast < 1
            ) {
                showDialog({
                    icon: 'warning',
                    title: 'Incomplete Vote Counts',
                    text:
                        'Keep at least two vote options and enter at least one vote.'
                });

                showStep(2);
                return;
            }

            previewTallies.push({
                reference:
                    measureOption.dataset.reference || '',
                title:
                    measureOption.dataset.title || '',
                sessionReference:
                    sessionReference || 'Session reference pending',
                source,
                status,
                ...result
            });

            renderPreviewBoard();

            showDialog({
                icon:
                    result.balanced
                        ? 'success'
                        : 'warning',
                title:
                    result.passed
                        ? 'Tally Preview Certified — Passed'
                        : 'Tally Preview Certified — Failed',
                text:
                    'The tally was added to this browser preview. It is not yet stored in MySQL.'
            }).then(() => {
                modal?.hide();
            });
        }
    );

    function renderPreviewBoard() {
        const board =
            document.getElementById(
                'tallyPreviewBoard'
            );

        if (
            !board ||
            !previewTallies.length
        ) {
            return;
        }

        board.innerHTML =
            previewTallies
                .slice()
                .reverse()
                .slice(0, 5)
                .map((tally) => {
                    return `
                        <div class="tally-preview-card">
                            <div class="tally-preview-card-top">
                                <span class="tally-preview-icon">
                                    <i class="bi bi-bar-chart"></i>
                                </span>

                                <div class="tally-preview-copy">
                                    <span class="tally-preview-reference">
                                        ${escapeHtml(tally.reference)}
                                    </span>

                                    <strong>
                                        ${escapeHtml(tally.title)}
                                    </strong>

                                    <small>
                                        ${escapeHtml(tally.sessionReference)}
                                        ·
                                        ${escapeHtml(tally.source)}
                                        ·
                                        ${escapeHtml(tally.status)}
                                    </small>
                                </div>

                                <span class="tally-preview-outcome ${tally.passed ? 'passed' : 'failed'}">
                                    ${tally.passed ? 'Passed' : 'Failed'}
                                </span>
                            </div>

                            <div class="tally-preview-options">
                                ${tally.options.map((option) => `
                                    <div>
                                        <span>
                                            ${escapeHtml(option.label)}
                                        </span>
                                        <strong>
                                            ${option.count}
                                        </strong>
                                    </div>
                                `).join('')}
                            </div>

                            <div class="tally-preview-stats">
                                <div>
                                    <span>Eligible</span>
                                    <strong>${tally.eligible}</strong>
                                </div>

                                <div>
                                    <span>Votes Cast</span>
                                    <strong>${tally.totalCast}</strong>
                                </div>

                                <div>
                                    <span>Required</span>
                                    <strong>${tally.required}</strong>
                                </div>

                                <div>
                                    <span>Affirmative</span>
                                    <strong>${tally.affirmative}</strong>
                                </div>

                                <div>
                                    <span>Participation</span>
                                    <strong>${tally.participation}%</strong>
                                </div>

                                <div>
                                    <span>Audit</span>
                                    <strong class="${tally.balanced ? 'balanced' : 'mismatch'}">
                                        ${tally.balanced ? 'Balanced' : 'Mismatch'}
                                    </strong>
                                </div>
                            </div>

                            <div class="tally-preview-meter">
                                <span style="width:${tally.participation}%"></span>
                            </div>
                        </div>
                    `;
                })
                .join('');
    }

    const checklistItems = Array.from(
        document.querySelectorAll(
            '.tally-checklist-item'
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
            'tallyChecklistValue',
            `${percent}%`
        );

        const bar =
            document.getElementById(
                'tallyChecklistBar'
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
            'tallyDocuments'
        );

    const selectedFiles =
        document.getElementById(
            'tallySelectedFiles'
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
                    'tally-file-item';

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
        '[data-tally-history]'
    ).forEach((button) => {
        button.addEventListener('click', () => {
            showDialog({
                icon: 'info',
                title: 'Vote Tally History',
                text:
                    'Imports, manual entries, recalculations, recounts, corrections, certifications, locking, and Decision Recording handoff will be connected later.'
            });
        });
    });

    const searchInput =
        document.getElementById(
            'tallySearch'
        );

    const typeFilter =
        document.getElementById(
            'tallyTypeFilter'
        );

    const priorityFilter =
        document.getElementById(
            'tallyPriorityFilter'
        );

    const statusFilter =
        document.getElementById(
            'tallyStatusFilter'
        );

    const rows = Array.from(
        document.querySelectorAll(
            '.tally-data-row'
        )
    );

    const noResult =
        document.getElementById(
            'tallyNoFilterResult'
        );

    const countLabel =
        document.getElementById(
            'tallyRecordCount'
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
        'btnResetTallyFilters'
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

    calculateTally();
});
