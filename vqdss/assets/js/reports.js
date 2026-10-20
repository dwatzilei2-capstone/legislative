'use strict';

document.addEventListener('DOMContentLoaded', () => {
    const data =
        window.VQDSS_REPORTS_DATA || {};

    const monthly =
        Array.isArray(data.monthly)
            ? data.monthly
            : [];

    const types =
        Array.isArray(data.types)
            ? data.types
            : [];

    const priorities =
        Array.isArray(data.priorities)
            ? data.priorities
            : [];

    const statuses =
        Array.isArray(data.statuses)
            ? data.statuses
            : [];

    const chartRegistry = [];

    function createChart(canvasId, config) {
        const canvas =
            document.getElementById(canvasId);

        if (
            !canvas ||
            typeof Chart === 'undefined'
        ) {
            return;
        }

        const chart =
            new Chart(canvas, config);

        chartRegistry.push(chart);
    }

    createChart(
        'reportsMonthlyChart',
        {
            type: 'line',
            data: {
                labels: monthly.map(
                    (row) => row.label
                ),
                datasets: [
                    {
                        label: 'New Legislative Measures',
                        data: monthly.map(
                            (row) => Number(row.total || 0)
                        ),
                        borderColor: '#1d6fb8',
                        backgroundColor: 'rgba(29,111,184,.14)',
                        pointBackgroundColor: '#b8860b',
                        pointBorderColor: '#0a1628',
                        pointRadius: 4,
                        pointHoverRadius: 6,
                        tension: .28,
                        fill: true
                    }
                ]
            },
            options: chartOptions({
                showLegend: false,
                beginAtZero: true
            })
        }
    );

    createChart(
        'reportsTypeChart',
        {
            type: 'doughnut',
            data: {
                labels: types.map(
                    (row) => row.label
                ),
                datasets: [
                    {
                        data: types.map(
                            (row) => Number(row.total || 0)
                        ),
                        backgroundColor: [
                            '#1d6fb8',
                            '#10b981',
                            '#b8860b',
                            '#64748b'
                        ],
                        borderColor: '#ffffff',
                        borderWidth: 3
                    }
                ]
            },
            options: chartOptions({
                position: 'bottom',
                cutout: '62%'
            })
        }
    );

    createChart(
        'reportsPriorityChart',
        {
            type: 'bar',
            data: {
                labels: priorities.map(
                    (row) => row.label
                ),
                datasets: [
                    {
                        label: 'Legislative Measures',
                        data: priorities.map(
                            (row) => Number(row.total || 0)
                        ),
                        backgroundColor: [
                            '#ef4444',
                            '#b8860b',
                            '#1d6fb8',
                            '#64748b',
                            '#94a3b8'
                        ],
                        borderRadius: 8,
                        maxBarThickness: 54
                    }
                ]
            },
            options: chartOptions({
                showLegend: false,
                beginAtZero: true
            })
        }
    );

    createChart(
        'reportsStatusChart',
        {
            type: 'bar',
            data: {
                labels: statuses.map(
                    (row) => row.label
                ),
                datasets: [
                    {
                        label: 'Measures',
                        data: statuses.map(
                            (row) => Number(row.total || 0)
                        ),
                        backgroundColor: '#1a3a5c',
                        borderRadius: 7,
                        maxBarThickness: 38
                    }
                ]
            },
            options: {
                ...chartOptions({
                    showLegend: false,
                    beginAtZero: true
                }),
                indexAxis: 'y'
            }
        }
    );

    function chartOptions(options = {}) {
        return {
            responsive: true,
            maintainAspectRatio: false,
            interaction: {
                intersect: false,
                mode: 'index'
            },
            plugins: {
                legend: {
                    display:
                        options.showLegend !== false,
                    position:
                        options.position || 'top',
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
                options.cutout
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
                            beginAtZero:
                                options.beginAtZero !== false,
                            grid: {
                                color: 'rgba(148,163,184,.18)'
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

    const modalElement =
        document.getElementById(
            'customReportModal'
        );

    const reportModal =
        modalElement &&
        typeof bootstrap !== 'undefined'
            ? new bootstrap.Modal(modalElement)
            : null;

    document.getElementById(
        'btnBuildCustomReport'
    )?.addEventListener('click', () => {
        reportModal?.show();
    });

    document.getElementById(
        'btnPreviewCustomReport'
    )?.addEventListener('click', () => {
        renderCustomReportPreview(false);
    });

    document.getElementById(
        'customReportForm'
    )?.addEventListener(
        'submit',
        (event) => {
            event.preventDefault();
            renderCustomReportPreview(true);
        }
    );

    function renderCustomReportPreview(finalize) {
        const preview =
            document.getElementById(
                'customReportPreview'
            );

        if (!preview) {
            return;
        }

        const title =
            document.getElementById(
                'customReportTitle'
            )?.value.trim() ||
            'VQDSS Operational Summary';

        const dateFrom =
            document.getElementById(
                'customReportDateFrom'
            )?.value || 'Beginning';

        const dateTo =
            document.getElementById(
                'customReportDateTo'
            )?.value || 'Present';

        const type =
            document.getElementById(
                'customReportType'
            )?.value || 'All Types';

        const priority =
            document.getElementById(
                'customReportPriority'
            )?.value || 'All Priorities';

        const status =
            document.getElementById(
                'customReportStatus'
            )?.value || 'All Statuses';

        const format =
            document.getElementById(
                'customReportFormat'
            )?.value || 'PDF and Print';

        const release =
            document.getElementById(
                'customReportRelease'
            )?.value || 'Internal';

        const preparedBy =
            document.getElementById(
                'customReportPreparedBy'
            )?.value.trim() || 'System User';

        const modules = Array.from(
            document.querySelectorAll(
                '[data-custom-report-module]:checked'
            )
        ).map(
            (checkbox) => checkbox.value
        );

        const sections = Array.from(
            document.querySelectorAll(
                '[data-custom-report-section]:checked'
            )
        ).map(
            (checkbox) => checkbox.value
        );

        if (!modules.length || !sections.length) {
            showDialog({
                icon: 'warning',
                title: 'Report Scope Required',
                text:
                    'Select at least one VQDSS module and one report section.'
            });

            return;
        }

        preview.innerHTML = `
            <div class="custom-report-preview-header">
                <span>
                    <i class="bi bi-file-earmark-bar-graph"></i>
                </span>

                <div>
                    <strong>${escapeHtml(title)}</strong>
                    <small>
                        ${escapeHtml(dateFrom)}
                        to
                        ${escapeHtml(dateTo)}
                    </small>
                </div>

                <em>
                    ${finalize
                        ? 'Browser Preview Created'
                        : 'Preview Ready'
                    }
                </em>
            </div>

            <div class="custom-report-preview-grid">
                <div>
                    <span>Legislative Type</span>
                    <strong>${escapeHtml(type)}</strong>
                </div>

                <div>
                    <span>Priority</span>
                    <strong>${escapeHtml(priority)}</strong>
                </div>

                <div>
                    <span>Workflow Status</span>
                    <strong>${escapeHtml(status)}</strong>
                </div>

                <div>
                    <span>Output Format</span>
                    <strong>${escapeHtml(format)}</strong>
                </div>

                <div>
                    <span>Release</span>
                    <strong>${escapeHtml(release)}</strong>
                </div>

                <div>
                    <span>Prepared By</span>
                    <strong>${escapeHtml(preparedBy)}</strong>
                </div>
            </div>

            <div class="custom-report-preview-lists">
                <div>
                    <span>Included Modules</span>
                    <ul>
                        ${modules.map(
                            (module) => `
                                <li>${escapeHtml(module)}</li>
                            `
                        ).join('')}
                    </ul>
                </div>

                <div>
                    <span>Report Sections</span>
                    <ul>
                        ${sections.map(
                            (section) => `
                                <li>${escapeHtml(section)}</li>
                            `
                        ).join('')}
                    </ul>
                </div>
            </div>

            <p>
                This is a client-demo browser preview.
                The backend phase will save report templates,
                apply database filters, generate files, record
                report history, and support scheduled delivery.
            </p>
        `;

        if (finalize) {
            showDialog({
                icon: 'success',
                title: 'Custom Report Preview Created',
                text:
                    'The report configuration is displayed in the modal. It has not been saved to MySQL.'
            });
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

        window.alert(
            `${options.title || 'Notice'}\n\n` +
            (options.text || '')
        );

        return Promise.resolve();
    }

    window.addEventListener(
        'beforeprint',
        () => {
            chartRegistry.forEach(
                (chart) => chart.resize()
            );
        }
    );
});
