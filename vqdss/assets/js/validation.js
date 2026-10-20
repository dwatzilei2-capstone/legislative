'use strict';

document.addEventListener('DOMContentLoaded', () => {
    const modalElement =
        document.getElementById('validationModal');

    const modal =
        modalElement &&
        typeof bootstrap !== 'undefined'
            ? new bootstrap.Modal(modalElement)
            : null;

    const form =
        document.getElementById('validationForm');

    const stepButtons = Array.from(
        document.querySelectorAll(
            '[data-validation-step]'
        )
    );

    const sections = Array.from(
        document.querySelectorAll(
            '[data-validation-form-section]'
        )
    );

    const previousButton =
        document.getElementById(
            'btnPreviousValidationStep'
        );

    const nextButton =
        document.getElementById(
            'btnNextValidationStep'
        );

    const submitButton =
        document.getElementById(
            'btnSubmitValidation'
        );

    const findingBuilder =
        document.getElementById(
            'validationFindingBuilder'
        );

    let currentStep = 1;
    const maximumStep = 4;
    const previewValidations = [];

    function showStep(step) {
        currentStep = step;

        stepButtons.forEach((button) => {
            button.classList.toggle(
                'active',
                Number(
                    button.dataset.validationStep
                ) === step
            );
        });

        sections.forEach((section) => {
            section.classList.toggle(
                'active',
                Number(
                    section.dataset.validationFormSection
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

        updateValidationSummary();
    }

    function openValidation(options = {}) {
        if (!modal || !form) {
            return;
        }

        form.reset();

        setText(
            'validationModalTitle',
            options.reference
                ? `Validate Result: ${options.reference}`
                : 'Create Validation Review'
        );

        setValue(
            'validationDateTime',
            toDateTimeLocal(new Date())
        );

        setValue(
            'validationCertifiedAt',
            toDateTimeLocal(new Date())
        );

        const measureSelect =
            document.getElementById(
                'validationMeasureSelect'
            );

        if (measureSelect) {
            measureSelect.value =
                options.candidateId || '';

            measureSelect.dispatchEvent(
                new Event('change')
            );
        }

        resetIntegrityChecks();
        resetFindingBuilder();
        updateValidationSummary();
        showStep(1);
        modal.show();
    }

    document.getElementById(
        'btnCreateValidation'
    )?.addEventListener('click', () => {
        openValidation();
    });

    document.getElementById(
        'btnQuickValidation'
    )?.addEventListener('click', () => {
        openValidation();
    });

    document.querySelectorAll(
        '[data-create-validation]'
    ).forEach((button) => {
        button.addEventListener('click', () => {
            openValidation({
                candidateId:
                    button.dataset.createValidation || '',
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
        ).get('validate');

    if (candidateFromUrl) {
        const button =
            document.querySelector(
                `[data-create-validation="${CSS.escape(
                    candidateFromUrl
                )}"]`
            );

        button?.click();
    }

    stepButtons.forEach((button) => {
        button.addEventListener('click', () => {
            showStep(
                Number(
                    button.dataset.validationStep
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
            'validationMeasureSelect'
        );

    const measurePreview =
        document.getElementById(
            'validationMeasurePreview'
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
                                Select the ordinance or
                                resolution to validate.
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
        'validationCheckBuilder'
    )?.addEventListener(
        'change',
        updateValidationSummary
    );

    [
        'validationRiskLevel',
        'validationOutcome',
        'validationStatus',
        'validationReportType',
        'validationReleaseClass'
    ].forEach((id) => {
        const element =
            document.getElementById(id);

        element?.addEventListener(
            'change',
            updateValidationSummary
        );

        element?.addEventListener(
            'input',
            updateValidationSummary
        );
    });

    function resetIntegrityChecks() {
        document.querySelectorAll(
            '[data-validation-check-status]'
        ).forEach((select) => {
            select.value = 'Passed';
        });

        document.querySelectorAll(
            '[data-validation-check-item] input'
        ).forEach((input) => {
            input.value = '';
        });
    }

    function calculateIntegrity() {
        const checks = Array.from(
            document.querySelectorAll(
                '[data-validation-check-status]'
            )
        );

        let passed = 0;
        let failed = 0;
        let needsReview = 0;
        let applicable = 0;

        checks.forEach((select) => {
            const value = select.value;

            if (value !== 'Not Applicable') {
                applicable++;
            }

            if (value === 'Passed') {
                passed++;
            } else if (value === 'Failed') {
                failed++;
            } else if (
                value === 'Needs Review'
            ) {
                needsReview++;
            }
        });

        const score =
            applicable > 0
                ? Math.round(
                    (passed / applicable) * 100
                )
                : 100;

        setText(
            'validationTotalChecks',
            checks.length
        );

        setText(
            'validationPassedChecks',
            passed
        );

        setText(
            'validationFailedChecks',
            failed
        );

        setText(
            'validationReviewChecks',
            needsReview
        );

        setText(
            'validationIntegrityScore',
            `${score}%`
        );

        setText(
            'validationFinalScore',
            `${score}%`
        );

        setText(
            'validationFinalFailed',
            failed
        );

        return {
            total: checks.length,
            applicable,
            passed,
            failed,
            needsReview,
            score
        };
    }

    document.getElementById(
        'btnAddValidationFinding'
    )?.addEventListener('click', () => {
        addFindingItem();
    });

    document.getElementById(
        'btnApplyValidationTemplates'
    )?.addEventListener('click', () => {
        if (!findingBuilder) {
            return;
        }

        findingBuilder.innerHTML = '';

        [
            {
                title:
                    'Source-reference documentation requires completion',
                severity: 'Moderate',
                status: 'Open'
            },
            {
                title:
                    'Certification evidence requires independent review',
                severity: 'High',
                status: 'In Progress'
            }
        ].forEach(addFindingItem);

        updateValidationSummary();
    });

    function buildOfficeOptions() {
        const source =
            document.querySelector(
                'select[name="finding_office_id[]"]'
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

    function addFindingItem(config = {}) {
        if (!findingBuilder) {
            return;
        }

        const item =
            document.createElement('div');

        item.className =
            'validation-finding-item';

        item.dataset.validationFindingItem = '';

        item.innerHTML = `
            <span class="finding-order">1</span>

            <div class="finding-fields">
                <input
                    type="text"
                    class="form-control"
                    name="finding_title[]"
                    value="${escapeHtml(config.title || '')}"
                    placeholder="Finding or discrepancy"
                    data-finding-title
                >

                <select
                    class="form-select"
                    name="finding_severity[]"
                    data-finding-severity
                >
                    ${[
                        'Observation',
                        'Low',
                        'Moderate',
                        'High',
                        'Critical'
                    ].map((severity) => `
                        <option
                            ${severity === (config.severity || 'Observation')
                                ? 'selected'
                                : ''
                            }
                        >
                            ${severity}
                        </option>
                    `).join('')}
                </select>

                <select
                    class="form-select"
                    name="finding_office_id[]"
                    data-finding-office
                >
                    ${buildOfficeOptions()}
                </select>

                <input
                    type="date"
                    class="form-control"
                    name="finding_due_date[]"
                    data-finding-date
                >

                <select
                    class="form-select"
                    name="finding_status[]"
                    data-finding-status
                >
                    ${[
                        'Resolved',
                        'Open',
                        'In Progress',
                        'For Legal Review',
                        'For Recount',
                        'Accepted Risk'
                    ].map((status) => `
                        <option
                            ${status === (config.status || 'Resolved')
                                ? 'selected'
                                : ''
                            }
                        >
                            ${status}
                        </option>
                    `).join('')}
                </select>
            </div>

            <button
                type="button"
                class="finding-remove-button"
                data-remove-validation-finding
                title="Remove finding"
            >
                <i class="bi bi-trash"></i>
            </button>
        `;

        findingBuilder.appendChild(item);
        updateFindingOrder();
        updateValidationSummary();
    }

    findingBuilder?.addEventListener(
        'click',
        (event) => {
            const removeButton =
                event.target.closest(
                    '[data-remove-validation-finding]'
                );

            if (!removeButton) {
                return;
            }

            const items =
                findingBuilder.querySelectorAll(
                    '[data-validation-finding-item]'
                );

            if (items.length <= 1) {
                showDialog({
                    icon: 'info',
                    title: 'Finding Row Required',
                    text:
                        'Keep at least one finding row. It may remain blank when no issue is found.'
                });

                return;
            }

            removeButton
                .closest(
                    '[data-validation-finding-item]'
                )
                ?.remove();

            updateFindingOrder();
            updateValidationSummary();
        }
    );

    findingBuilder?.addEventListener(
        'input',
        updateValidationSummary
    );

    findingBuilder?.addEventListener(
        'change',
        updateValidationSummary
    );

    function updateFindingOrder() {
        findingBuilder
            ?.querySelectorAll(
                '[data-validation-finding-item]'
            )
            .forEach((item, index) => {
                const order =
                    item.querySelector(
                        '.finding-order'
                    );

                if (order) {
                    order.textContent =
                        String(index + 1);
                }
            });
    }

    function resetFindingBuilder() {
        if (!findingBuilder) {
            return;
        }

        const items = Array.from(
            findingBuilder.querySelectorAll(
                '[data-validation-finding-item]'
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

        updateFindingOrder();
    }

    function calculateFindings() {
        const rows = Array.from(
            document.querySelectorAll(
                '[data-validation-finding-item]'
            )
        );

        let activeFindings = 0;
        let open = 0;
        let critical = 0;
        let withDueDate = 0;

        rows.forEach((row) => {
            const title =
                row.querySelector(
                    '[data-finding-title]'
                )?.value.trim() || '';

            if (!title) {
                return;
            }

            activeFindings++;

            const status =
                row.querySelector(
                    '[data-finding-status]'
                )?.value || '';

            const severity =
                row.querySelector(
                    '[data-finding-severity]'
                )?.value || '';

            const dueDate =
                row.querySelector(
                    '[data-finding-date]'
                )?.value || '';

            if (
                ![
                    'Resolved',
                    'Accepted Risk'
                ].includes(status)
            ) {
                open++;
            }

            if (severity === 'Critical') {
                critical++;
            }

            if (dueDate) {
                withDueDate++;
            }
        });

        setText(
            'validationFindingCount',
            activeFindings
        );

        setText(
            'validationOpenFindingCount',
            open
        );

        setText(
            'validationCriticalFindingCount',
            critical
        );

        setText(
            'validationDueFindingCount',
            withDueDate
        );

        setText(
            'validationFinalOpen',
            open
        );

        return {
            total: activeFindings,
            open,
            critical,
            withDueDate
        };
    }

    function updateValidationSummary() {
        const integrity =
            calculateIntegrity();

        const findings =
            calculateFindings();

        const risk =
            document.getElementById(
                'validationRiskLevel'
            )?.value || 'Low';

        let outcome =
            document.getElementById(
                'validationOutcome'
            )?.value || 'Validated';

        if (
            integrity.failed > 0 &&
            outcome === 'Validated'
        ) {
            outcome =
                'Correction Required';

            setValue(
                'validationOutcome',
                outcome
            );
        }

        setText(
            'validationFinalRisk',
            risk
        );

        setText(
            'validationFinalOutcome',
            outcome
        );

        return {
            integrity,
            findings,
            risk,
            outcome,
            status:
                document.getElementById(
                    'validationStatus'
                )?.value || 'Certified',
            reportType:
                document.getElementById(
                    'validationReportType'
                )?.value || 'Full Validation Report',
            releaseClass:
                document.getElementById(
                    'validationReleaseClass'
                )?.value || 'Public'
        };
    }

    document.getElementById(
        'btnRunValidationAudit'
    )?.addEventListener('click', () => {
        const summary =
            updateValidationSummary();

        const sourceReferences = [
            'validationQuorumReference',
            'validationVotingReference',
            'validationTallyReference',
            'validationDecisionReference'
        ].filter((id) => {
            return Boolean(
                document.getElementById(id)
                    ?.value.trim()
            );
        }).length;

        showDialog({
            icon:
                summary.integrity.failed === 0
                    ? 'success'
                    : 'warning',
            title: 'Integrity Audit Result',
            html: `
                <div class="text-start small">
                    <p><strong>Source references supplied:</strong> ${sourceReferences}/4</p>
                    <p><strong>Integrity score:</strong> ${summary.integrity.score}%</p>
                    <p><strong>Passed checks:</strong> ${summary.integrity.passed}</p>
                    <p><strong>Failed checks:</strong> ${summary.integrity.failed}</p>
                    <p><strong>Checks needing review:</strong> ${summary.integrity.needsReview}</p>
                    <p><strong>Open findings:</strong> ${summary.findings.open}</p>
                    <p><strong>Risk level:</strong> ${escapeHtml(summary.risk)}</p>
                    <p class="text-muted">
                        The final audit will verify database references,
                        source hashes, signatures, timestamps, permissions,
                        duplicate records, corrections, release status,
                        and complete audit history.
                    </p>
                </div>
            `
        });
    });

    document.getElementById(
        'btnSaveValidationDraft'
    )?.addEventListener('click', () => {
        showDialog({
            icon: 'info',
            title: 'Validation Draft UI Ready',
            text:
                'Draft saving will be connected after the dedicated validation tables and AJAX endpoints are implemented.'
        });
    });

    form?.addEventListener(
        'submit',
        (event) => {
            event.preventDefault();

            const measureOption =
                document.getElementById(
                    'validationMeasureSelect'
                )?.selectedOptions?.[0];

            const reference =
                document.getElementById(
                    'validationReference'
                )?.value.trim() || '';

            const executiveSummary =
                document.getElementById(
                    'validationExecutiveSummary'
                )?.value.trim() || '';

            const summary =
                updateValidationSummary();

            if (!measureOption?.value) {
                showDialog({
                    icon: 'warning',
                    title: 'Legislative Measure Required',
                    text:
                        'Select the legislative measure being validated.'
                });

                showStep(1);
                return;
            }

            if (!executiveSummary) {
                showDialog({
                    icon: 'warning',
                    title: 'Executive Summary Required',
                    text:
                        'Enter the executive summary before certifying the validation report.'
                });

                showStep(4);
                return;
            }

            previewValidations.push({
                reference:
                    measureOption.dataset.reference || '',
                title:
                    measureOption.dataset.title || '',
                validationReference:
                    reference || 'Validation reference pending',
                executiveSummary,
                ...summary
            });

            renderPreviewBoard();

            showDialog({
                icon:
                    summary.integrity.failed === 0
                        ? 'success'
                        : 'warning',
                title: 'Validation Preview Certified',
                text:
                    'The validation was added to this browser preview. It is not yet stored in MySQL.'
            }).then(() => {
                modal?.hide();
            });
        }
    );

    function renderPreviewBoard() {
        const board =
            document.getElementById(
                'validationPreviewBoard'
            );

        if (
            !board ||
            !previewValidations.length
        ) {
            return;
        }

        board.innerHTML =
            previewValidations
                .slice()
                .reverse()
                .slice(0, 5)
                .map((validation) => `
                    <div class="validation-preview-card">
                        <div class="validation-preview-card-top">
                            <span class="validation-preview-icon">
                                <i class="bi bi-patch-check"></i>
                            </span>

                            <div class="validation-preview-copy">
                                <span class="validation-preview-reference">
                                    ${escapeHtml(validation.reference)}
                                </span>

                                <strong>
                                    ${escapeHtml(validation.title)}
                                </strong>

                                <small>
                                    ${escapeHtml(validation.validationReference)}
                                    ·
                                    ${escapeHtml(validation.reportType)}
                                    ·
                                    ${escapeHtml(validation.status)}
                                </small>
                            </div>

                            <span class="validation-preview-outcome ${outcomeClass(validation.outcome)}">
                                ${escapeHtml(validation.outcome)}
                            </span>
                        </div>

                        <p class="validation-preview-summary">
                            ${escapeHtml(validation.executiveSummary)}
                        </p>

                        <div class="validation-preview-stats">
                            <div>
                                <span>Integrity Score</span>
                                <strong>${validation.integrity.score}%</strong>
                            </div>

                            <div>
                                <span>Passed Checks</span>
                                <strong>${validation.integrity.passed}</strong>
                            </div>

                            <div>
                                <span>Failed Checks</span>
                                <strong>${validation.integrity.failed}</strong>
                            </div>

                            <div>
                                <span>Open Findings</span>
                                <strong>${validation.findings.open}</strong>
                            </div>

                            <div>
                                <span>Risk Level</span>
                                <strong>${escapeHtml(validation.risk)}</strong>
                            </div>

                            <div>
                                <span>Release</span>
                                <strong>${escapeHtml(validation.releaseClass)}</strong>
                            </div>
                        </div>

                        <div class="validation-preview-meter">
                            <span style="width:${validation.integrity.score}%"></span>
                        </div>
                    </div>
                `)
                .join('');
    }

    function outcomeClass(outcome) {
        const value =
            String(outcome).toLowerCase();

        if (value === 'validated') {
            return 'validated';
        }

        if (
            value.includes('failed') ||
            value.includes('withheld')
        ) {
            return 'failed';
        }

        return 'conditional';
    }

    const checklistItems = Array.from(
        document.querySelectorAll(
            '.validation-checklist-item'
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
            'validationChecklistValue',
            `${percent}%`
        );

        const bar =
            document.getElementById(
                'validationChecklistBar'
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
            'validationDocuments'
        );

    const selectedFiles =
        document.getElementById(
            'validationSelectedFiles'
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
                    'validation-file-item';

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
        '[data-validation-history]'
    ).forEach((button) => {
        button.addEventListener('click', () => {
            showDialog({
                icon: 'info',
                title: 'Validation History',
                text:
                    'Reviews, check results, findings, responses, corrections, revalidations, certifications, releases, and report versions will be connected later.'
            });
        });
    });

    const searchInput =
        document.getElementById(
            'validationSearch'
        );

    const typeFilter =
        document.getElementById(
            'validationTypeFilter'
        );

    const priorityFilter =
        document.getElementById(
            'validationPriorityFilter'
        );

    const statusFilter =
        document.getElementById(
            'validationStatusFilter'
        );

    const rows = Array.from(
        document.querySelectorAll(
            '.validation-data-row'
        )
    );

    const noResult =
        document.getElementById(
            'validationNoFilterResult'
        );

    const countLabel =
        document.getElementById(
            'validationRecordCount'
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
        'btnResetValidationFilters'
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

    updateValidationSummary();
});
