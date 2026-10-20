/**
 * assets/js/reports.js
 * ------------------------------------------------------------------
 * Charts, filters, and export UI for the Reports Center.
 * ------------------------------------------------------------------
 */

'use strict';

document.addEventListener('DOMContentLoaded', () => {
    const reportData =
        window.ORLMS_REPORTS_DATA || null;

    if (
        reportData &&
        typeof Chart !== 'undefined'
    ) {
        renderMonthlyChart(reportData);
        renderTypeChart(reportData);
        renderStatusChart(reportData);
    }

    document.getElementById(
        'btnExportReportData'
    )?.addEventListener('click', () => {
        Swal.fire({
            icon: 'info',
            title: 'Report Export',
            text:
                'CSV, Excel, and PDF export endpoints will be connected during backend implementation.',
        });
    });

    document.getElementById(
        'btnExportRegistry'
    )?.addEventListener('click', () => {
        exportVisibleRegistryToCsv();
    });

    const searchInput =
        document.getElementById('registrySearch');

    const typeFilter =
        document.getElementById('registryTypeFilter');

    const statusFilter =
        document.getElementById('registryStatusFilter');

    const priorityFilter =
        document.getElementById('registryPriorityFilter');

    const resetButton =
        document.getElementById('btnResetRegistryFilters');

    const rows = Array.from(
        document.querySelectorAll('.registry-data-row')
    );

    const noResultRow =
        document.getElementById('registryNoFilterResult');

    const countLabel =
        document.getElementById('registryRecordCount');

    function applyRegistryFilters() {
        const search =
            (searchInput?.value || '')
                .trim()
                .toLowerCase();

        const type = typeFilter?.value || '';
        const status = statusFilter?.value || '';
        const priority = priorityFilter?.value || '';

        let visible = 0;

        rows.forEach((row) => {
            const matchesSearch =
                !search ||
                (row.dataset.search || '').includes(search);

            const matchesType =
                !type ||
                row.dataset.type === type;

            const matchesStatus =
                !status ||
                row.dataset.status === status;

            const matchesPriority =
                !priority ||
                row.dataset.priority === priority;

            const show =
                matchesSearch &&
                matchesType &&
                matchesStatus &&
                matchesPriority;

            row.classList.toggle('d-none', !show);

            if (show) {
                visible++;
            }
        });

        noResultRow?.classList.toggle(
            'd-none',
            visible > 0 || rows.length === 0
        );

        if (countLabel) {
            countLabel.textContent =
                `Showing ${visible} record(s)`;
        }
    }

    searchInput?.addEventListener(
        'input',
        applyRegistryFilters
    );

    typeFilter?.addEventListener(
        'change',
        applyRegistryFilters
    );

    statusFilter?.addEventListener(
        'change',
        applyRegistryFilters
    );

    priorityFilter?.addEventListener(
        'change',
        applyRegistryFilters
    );

    resetButton?.addEventListener('click', () => {
        if (searchInput) {
            searchInput.value = '';
        }

        if (typeFilter) {
            typeFilter.value = '';
        }

        if (statusFilter) {
            statusFilter.value = '';
        }

        if (priorityFilter) {
            priorityFilter.value = '';
        }

        applyRegistryFilters();
    });

    function renderMonthlyChart(data) {
        const canvas =
            document.getElementById('reportsMonthlyChart');

        if (!canvas) {
            return;
        }

        new Chart(canvas, {
            type: 'line',
            data: {
                labels: data.monthlyLabels || [],
                datasets: [{
                    label: 'Records Created',
                    data: data.monthlyValues || [],
                    borderColor: '#b8860b',
                    backgroundColor: 'rgba(184, 134, 11, 0.15)',
                    fill: true,
                    tension: 0.35,
                    pointRadius: 4,
                    pointHoverRadius: 6
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            precision: 0
                        }
                    }
                }
            }
        });
    }

    function renderTypeChart(data) {
        const canvas =
            document.getElementById('reportsTypeChart');

        if (!canvas) {
            return;
        }

        new Chart(canvas, {
            type: 'doughnut',
            data: {
                labels: data.typeLabels || [],
                datasets: [{
                    data: data.typeValues || [],
                    backgroundColor: [
                        '#b8860b',
                        '#071426'
                    ],
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '68%',
                plugins: {
                    legend: {
                        display: false
                    }
                }
            }
        });
    }

    function renderStatusChart(data) {
        const canvas =
            document.getElementById('reportsStatusChart');

        if (!canvas) {
            return;
        }

        new Chart(canvas, {
            type: 'bar',
            data: {
                labels: data.statusLabels || [],
                datasets: [{
                    label: 'Records',
                    data: data.statusValues || [],
                    backgroundColor: '#b8860b',
                    borderRadius: 6
                }]
            },
            options: {
                indexAxis: 'y',
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    x: {
                        beginAtZero: true,
                        ticks: {
                            precision: 0
                        }
                    }
                }
            }
        });
    }

    function exportVisibleRegistryToCsv() {
        const table =
            document.getElementById(
                'legislativeRegistryTable'
            );

        if (!table) {
            return;
        }

        const csvRows = [];
        const headerCells = Array.from(
            table.querySelectorAll('thead th')
        );

        csvRows.push(
            headerCells
                .map((cell) => csvEscape(cell.textContent.trim()))
                .join(',')
        );

        Array.from(
            table.querySelectorAll(
                'tbody tr.registry-data-row:not(.d-none)'
            )
        ).forEach((row) => {
            const values = Array.from(
                row.querySelectorAll('td')
            ).map((cell) => {
                return csvEscape(
                    cell.textContent
                        .replace(/\s+/g, ' ')
                        .trim()
                );
            });

            csvRows.push(values.join(','));
        });

        const blob = new Blob(
            [csvRows.join('\n')],
            {
                type: 'text/csv;charset=utf-8;'
            }
        );

        const url = URL.createObjectURL(blob);
        const link = document.createElement('a');

        link.href = url;
        link.download =
            `orlms-legislative-registry-${new Date()
                .toISOString()
                .slice(0, 10)}.csv`;

        document.body.appendChild(link);
        link.click();
        link.remove();
        URL.revokeObjectURL(url);
    }

    function csvEscape(value) {
        const text = String(value ?? '');

        if (
            text.includes(',') ||
            text.includes('"') ||
            text.includes('\n')
        ) {
            return `"${text.replace(/"/g, '""')}"`;
        }

        return text;
    }
});
