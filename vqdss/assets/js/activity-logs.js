'use strict';

document.addEventListener('DOMContentLoaded', () => {
    const reportData =
        window.VQDSS_ACTIVITY_DATA || {};

    const daily =
        Array.isArray(reportData.daily)
            ? reportData.daily
            : [];

    const modules =
        Array.isArray(reportData.modules)
            ? reportData.modules
            : [];

    const actions =
        Array.isArray(reportData.actions)
            ? reportData.actions
            : [];

    const severity =
        Array.isArray(reportData.severity)
            ? reportData.severity
            : [];

    function chartOptions(options = {}) {
        return {
            responsive: true,
            maintainAspectRatio: false,
            indexAxis:
                options.indexAxis || 'x',
            plugins: {
                legend: {
                    display:
                        options.legend !== false,
                    position:
                        options.legendPosition ||
                        'bottom',
                    labels: {
                        boxWidth: 12,
                        usePointStyle: true,
                        font: {
                            size: 10
                        }
                    }
                },
                tooltip: {
                    backgroundColor: '#0a1628',
                    padding: 10,
                    titleFont: {
                        size: 11
                    },
                    bodyFont: {
                        size: 10
                    }
                }
            },
            scales:
                options.noScales
                    ? undefined
                    : {
                        x: {
                            grid: {
                                display: false
                            },
                            ticks: {
                                color: '#64748b',
                                font: {
                                    size: 9
                                }
                            }
                        },
                        y: {
                            beginAtZero: true,
                            grid: {
                                color:
                                    'rgba(148,163,184,.18)'
                            },
                            ticks: {
                                color: '#64748b',
                                precision: 0,
                                font: {
                                    size: 9
                                }
                            }
                        }
                    },
            cutout: options.cutout
        };
    }

    function createChart(
        canvasId,
        configuration
    ) {
        const canvas =
            document.getElementById(
                canvasId
            );

        if (
            !canvas ||
            typeof Chart === 'undefined'
        ) {
            return;
        }

        new Chart(
            canvas,
            configuration
        );
    }

    createChart(
        'activityDailyChart',
        {
            type: 'line',
            data: {
                labels: daily.map(
                    (row) => row.label
                ),
                datasets: [
                    {
                        label: 'Audit Events',
                        data: daily.map(
                            (row) =>
                                Number(
                                    row.total || 0
                                )
                        ),
                        borderColor: '#1d6fb8',
                        backgroundColor:
                            'rgba(29,111,184,.14)',
                        pointBackgroundColor:
                            '#b8860b',
                        pointBorderColor:
                            '#0a1628',
                        pointRadius: 4,
                        pointHoverRadius: 6,
                        tension: .28,
                        fill: true
                    }
                ]
            },
            options: chartOptions({
                legend: false
            })
        }
    );

    createChart(
        'activitySeverityChart',
        {
            type: 'doughnut',
            data: {
                labels: severity.map(
                    (row) => row.label
                ),
                datasets: [
                    {
                        data: severity.map(
                            (row) =>
                                Number(
                                    row.total || 0
                                )
                        ),
                        backgroundColor: [
                            '#7f1d1d',
                            '#ef4444',
                            '#b8860b',
                            '#10b981',
                            '#1d6fb8'
                        ],
                        borderColor: '#ffffff',
                        borderWidth: 3
                    }
                ]
            },
            options: chartOptions({
                noScales: true,
                cutout: '62%'
            })
        }
    );

    createChart(
        'activityModuleChart',
        {
            type: 'bar',
            data: {
                labels: modules.map(
                    (row) => row.label
                ),
                datasets: [
                    {
                        label: 'Events',
                        data: modules.map(
                            (row) =>
                                Number(
                                    row.total || 0
                                )
                        ),
                        backgroundColor:
                            '#1a3a5c',
                        borderRadius: 7,
                        maxBarThickness: 38
                    }
                ]
            },
            options: chartOptions({
                legend: false,
                indexAxis: 'y'
            })
        }
    );

    createChart(
        'activityActionChart',
        {
            type: 'bar',
            data: {
                labels: actions.map(
                    (row) => row.label
                ),
                datasets: [
                    {
                        label: 'Events',
                        data: actions.map(
                            (row) =>
                                Number(
                                    row.total || 0
                                )
                        ),
                        backgroundColor: [
                            '#1d6fb8',
                            '#10b981',
                            '#b8860b',
                            '#8b5cf6',
                            '#64748b',
                            '#0f766e',
                            '#c2410c',
                            '#475569'
                        ],
                        borderRadius: 7,
                        maxBarThickness: 42
                    }
                ]
            },
            options: chartOptions({
                legend: false
            })
        }
    );

    const searchInput =
        document.getElementById(
            'activitySearch'
        );

    const moduleFilter =
        document.getElementById(
            'activityModuleFilter'
        );

    const actionFilter =
        document.getElementById(
            'activityActionFilter'
        );

    const severityFilter =
        document.getElementById(
            'activitySeverityFilter'
        );

    const dateFromInput =
        document.getElementById(
            'activityDateFrom'
        );

    const dateToInput =
        document.getElementById(
            'activityDateTo'
        );

    const sourceFilters = Array.from(
        document.querySelectorAll(
            '[data-activity-source]'
        )
    );

    const rows = Array.from(
        document.querySelectorAll(
            '.activity-data-row'
        )
    );

    const noResult =
        document.getElementById(
            'activityNoFilterResult'
        );

    const countLabel =
        document.getElementById(
            'activityRecordCount'
        );

    const exportLink =
        document.getElementById(
            'activityExportLink'
        );

    const printLink =
        document.getElementById(
            'activityPrintLink'
        );

    function currentFilters() {
        const enabledSources =
            sourceFilters
                .filter(
                    (checkbox) =>
                        checkbox.checked
                )
                .map(
                    (checkbox) =>
                        checkbox.value
                );

        return {
            search:
                (
                    searchInput?.value || ''
                )
                    .trim()
                    .toLowerCase(),
            module:
                moduleFilter?.value || '',
            action:
                actionFilter?.value || '',
            severity:
                severityFilter?.value || '',
            dateFrom:
                dateFromInput?.value || '',
            dateTo:
                dateToInput?.value || '',
            enabledSources
        };
    }

    function applyFilters() {
        const filters =
            currentFilters();

        let visible = 0;

        rows.forEach((row) => {
            const rowSearch =
                row.dataset.search || '';

            const rowDate =
                row.dataset.date || '';

            const sourceMatches =
                filters.enabledSources.includes(
                    row.dataset.source || ''
                );

            const show =
                (
                    !filters.search ||
                    rowSearch.includes(
                        filters.search
                    )
                ) &&
                (
                    !filters.module ||
                    row.dataset.module ===
                        filters.module
                ) &&
                (
                    !filters.action ||
                    row.dataset.action ===
                        filters.action
                ) &&
                (
                    !filters.severity ||
                    row.dataset.severity ===
                        filters.severity
                ) &&
                sourceMatches &&
                (
                    !filters.dateFrom ||
                    (
                        rowDate !== '' &&
                        rowDate >=
                            filters.dateFrom
                    )
                ) &&
                (
                    !filters.dateTo ||
                    (
                        rowDate !== '' &&
                        rowDate <=
                            filters.dateTo
                    )
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
                `Showing ${visible} audit event(s)`;
        }

        updateActionLinks(
            filters
        );
    }

    function updateActionLinks(filters) {
        const parameters =
            new URLSearchParams();

        if (filters.search) {
            parameters.set(
                'search',
                filters.search
            );
        }

        if (filters.module) {
            parameters.set(
                'module',
                filters.module
            );
        }

        if (filters.action) {
            parameters.set(
                'action',
                filters.action
            );
        }

        if (filters.severity) {
            parameters.set(
                'severity',
                filters.severity
            );
        }

        if (filters.dateFrom) {
            parameters.set(
                'date_from',
                filters.dateFrom
            );
        }

        if (filters.dateTo) {
            parameters.set(
                'date_to',
                filters.dateTo
            );
        }

        if (
            filters.enabledSources.length === 1
        ) {
            parameters.set(
                'source',
                filters.enabledSources[0]
            );
        }

        const suffix =
            parameters.toString()
                ? `?${parameters.toString()}`
                : '';

        if (exportLink) {
            exportLink.href =
                `activity_logs_export.php${suffix}`;
        }

        if (printLink) {
            printLink.href =
                `activity_logs_print.php${suffix}`;
        }
    }

    [
        searchInput,
        moduleFilter,
        actionFilter,
        severityFilter,
        dateFromInput,
        dateToInput
    ].forEach((element) => {
        element?.addEventListener(
            'input',
            applyFilters
        );

        element?.addEventListener(
            'change',
            applyFilters
        );
    });

    sourceFilters.forEach((checkbox) => {
        checkbox.addEventListener(
            'change',
            applyFilters
        );
    });

    document.getElementById(
        'btnResetActivityFilters'
    )?.addEventListener('click', () => {
        if (searchInput) {
            searchInput.value = '';
        }

        if (moduleFilter) {
            moduleFilter.value = '';
        }

        if (actionFilter) {
            actionFilter.value = '';
        }

        if (severityFilter) {
            severityFilter.value = '';
        }

        if (dateFromInput) {
            dateFromInput.value = '';
        }

        if (dateToInput) {
            dateToInput.value = '';
        }

        sourceFilters.forEach(
            (checkbox) => {
                checkbox.checked = true;
            }
        );

        applyFilters();
    });

    const modalElement =
        document.getElementById(
            'activityDetailModal'
        );

    const detailModal =
        modalElement &&
        typeof bootstrap !== 'undefined'
            ? new bootstrap.Modal(
                modalElement
            )
            : null;

    let currentDetailText = '';

    document.querySelectorAll(
        '[data-activity-detail]'
    ).forEach((button) => {
        button.addEventListener('click', () => {
            let data = {};

            try {
                data = JSON.parse(
                    button.dataset.activityDetail ||
                    '{}'
                );
            } catch (error) {
                showDialog({
                    icon: 'error',
                    title: 'Unable to Read Log Details',
                    text:
                        'The selected audit record could not be opened.'
                });

                return;
            }

            populateDetailModal(data);
            detailModal?.show();
        });
    });

    function populateDetailModal(data) {
        const fallback =
            'Not recorded';

        setText(
            'activityDetailKey',
            data.key ||
            'Normalized activity record'
        );

        setText(
            'activityDetailDate',
            data.date || fallback
        );

        setText(
            'activityDetailModule',
            data.module || 'General'
        );

        setText(
            'activityDetailAction',
            data.action || 'Other'
        );

        setText(
            'activityDetailSeverity',
            data.severity || 'Info'
        );

        setText(
            'activityDetailStatus',
            data.status || 'Recorded'
        );

        setText(
            'activityDetailSource',
            data.source || fallback
        );

        setText(
            'activityDetailDescription',
            data.description ||
            'No description available.'
        );

        setText(
            'activityDetailOldValues',
            data.oldValues ||
            'No previous values recorded.'
        );

        setText(
            'activityDetailNewValues',
            data.newValues ||
            'No new values recorded.'
        );

        setText(
            'activityDetailMetadata',
            data.metadata ||
            'No metadata recorded.'
        );

        setText(
            'activityDetailActor',
            data.actorName || 'System'
        );

        setText(
            'activityDetailEmail',
            data.actorEmail || fallback
        );

        setText(
            'activityDetailRole',
            data.actorRole || fallback
        );

        setText(
            'activityDetailEntityType',
            data.entityType || fallback
        );

        setText(
            'activityDetailEntityId',
            data.entityId || fallback
        );

        setText(
            'activityDetailSourceTable',
            data.sourceTable || fallback
        );

        setText(
            'activityDetailIp',
            data.ipAddress || fallback
        );

        setText(
            'activityDetailMethod',
            data.requestMethod || fallback
        );

        setText(
            'activityDetailUrl',
            data.requestUrl || fallback
        );

        setText(
            'activityDetailAgent',
            data.userAgent || fallback
        );

        currentDetailText = [
            `Audit record: ${data.key || fallback}`,
            `Date: ${data.date || fallback}`,
            `Actor: ${data.actorName || 'System'}`,
            `Email: ${data.actorEmail || fallback}`,
            `Role: ${data.actorRole || fallback}`,
            `Module: ${data.module || 'General'}`,
            `Action: ${data.action || 'Other'}`,
            `Description: ${data.description || fallback}`,
            `Entity: ${data.entityType || fallback} ${data.entityId || ''}`.trim(),
            `Severity: ${data.severity || 'Info'}`,
            `Status: ${data.status || 'Recorded'}`,
            `IP address: ${data.ipAddress || fallback}`,
            `Request: ${data.requestMethod || ''} ${data.requestUrl || ''}`.trim(),
            `Source: ${data.source || fallback} (${data.sourceTable || fallback})`,
            '',
            'Previous values:',
            data.oldValues || fallback,
            '',
            'New values:',
            data.newValues || fallback,
            '',
            'Metadata:',
            data.metadata || fallback
        ].join('\n');
    }

    document.getElementById(
        'btnCopyActivityDetails'
    )?.addEventListener('click', async () => {
        if (!currentDetailText) {
            return;
        }

        try {
            await navigator.clipboard.writeText(
                currentDetailText
            );

            showDialog({
                icon: 'success',
                title: 'Audit Details Copied',
                text:
                    'The normalized activity details were copied to the clipboard.'
            });
        } catch (error) {
            showDialog({
                icon: 'info',
                title: 'Copy Not Available',
                text:
                    'Select the text manually from the details window.'
            });
        }
    });

    document.getElementById(
        'btnAuditOverview'
    )?.addEventListener('click', () => {
        const summary =
            reportData.summary || {};

        const sourceText =
            Array.isArray(summary.sources) &&
            summary.sources.length
                ? summary.sources.join(', ')
                : 'No source connected';

        showDialog({
            icon: 'info',
            title: 'VQDSS Audit Overview',
            html: `
                <div class="text-start small">
                    <p><strong>Loaded events:</strong> ${Number(summary.total || 0)}</p>
                    <p><strong>Events today:</strong> ${Number(summary.today || 0)}</p>
                    <p><strong>Unique actors:</strong> ${Number(summary.actors || 0)}</p>
                    <p><strong>Security-related events:</strong> ${Number(summary.security || 0)}</p>
                    <p><strong>Events needing attention:</strong> ${Number(summary.attention || 0)}</p>
                    <p><strong>Connected sources:</strong> ${escapeHtml(sourceText)}</p>
                    <p class="text-muted">
                        The page normalizes different shared activity-log schemas.
                        The backend phase will write VQDSS actions automatically,
                        enforce retention policies, and protect immutable audit data.
                    </p>
                </div>
            `
        });
    });

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

    applyFilters();
});
