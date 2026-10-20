/**
 * assets/js/activity-logs.js
 * ------------------------------------------------------------------
 * Filters, details modal, and CSV export for the activity log.
 * ------------------------------------------------------------------
 */

'use strict';

document.addEventListener('DOMContentLoaded', () => {
    const detailModalElement =
        document.getElementById('auditDetailModal');

    const detailModal = detailModalElement
        ? new bootstrap.Modal(detailModalElement)
        : null;

    document.querySelectorAll(
        '[data-audit-detail]'
    ).forEach((button) => {
        button.addEventListener('click', () => {
            let payload = {};

            try {
                payload = JSON.parse(
                    button.dataset.auditDetail || '{}'
                );
            } catch (error) {
                payload = {};
            }

            setText(
                'auditDetailEventNumber',
                payload.id
                    ? `Audit event #${payload.id}`
                    : 'Audit event'
            );

            setText(
                'auditDetailAction',
                payload.action || 'Activity'
            );

            setText(
                'auditDetailDescription',
                payload.description || 'No description recorded.'
            );

            setText(
                'auditDetailActor',
                payload.actor_name || 'System'
            );

            setText(
                'auditDetailEmail',
                payload.actor_email || 'Not recorded'
            );

            setText(
                'auditDetailModule',
                payload.module || 'General'
            );

            setText(
                'auditDetailSystem',
                payload.system_name || 'ORLMS'
            );

            setText(
                'auditDetailDate',
                payload.created_at || 'Not recorded'
            );

            setText(
                'auditDetailIp',
                payload.ip_address || 'Not recorded'
            );

            setText(
                'auditDetailMetadata',
                payload.details ||
                    'No additional details recorded.'
            );

            setText(
                'auditDetailUserAgent',
                payload.user_agent ||
                    'Not recorded.'
            );

            const icon =
                document.getElementById('auditDetailIcon');

            if (icon) {
                icon.className =
                    `audit-detail-icon ${
                        payload.severity || 'information'
                    }`;

                icon.innerHTML =
                    getSeverityIcon(
                        payload.severity || 'information'
                    );
            }

            detailModal?.show();
        });
    });

    document.getElementById(
        'btnOpenAuditGuide'
    )?.addEventListener('click', () => {
        Swal.fire({
            icon: 'info',
            title: 'Activity Log Guide',
            html:
                '<div class="text-start small">' +
                '<p><strong>Information:</strong> Normal system or workflow activity.</p>' +
                '<p><strong>Success:</strong> Creation, approval, or completed actions.</p>' +
                '<p><strong>Security:</strong> Login, logout, password, permission, or role events.</p>' +
                '<p><strong>Attention:</strong> Failed, rejected, deleted, denied, or error-related events.</p>' +
                '<p>Use the search, module, action, and date filters to narrow the audit trail.</p>' +
                '</div>',
        });
    });

    const searchInput =
        document.getElementById('auditSearch');

    const moduleFilter =
        document.getElementById('auditModuleFilter');

    const actionFilter =
        document.getElementById('auditActionFilter');

    const dateFrom =
        document.getElementById('auditDateFrom');

    const dateTo =
        document.getElementById('auditDateTo');

    const resetButton =
        document.getElementById('btnResetAuditFilters');

    const rows = Array.from(
        document.querySelectorAll('.audit-data-row')
    );

    const noResultRow =
        document.getElementById('auditNoFilterResult');

    const countLabel =
        document.getElementById('auditRecordCount');

    function applyFilters() {
        const search =
            (searchInput?.value || '')
                .trim()
                .toLowerCase();

        const module =
            moduleFilter?.value || '';

        const action =
            actionFilter?.value || '';

        const from =
            dateFrom?.value || '';

        const to =
            dateTo?.value || '';

        let visible = 0;

        rows.forEach((row) => {
            const rowDate = row.dataset.date || '';

            const matchesSearch =
                !search ||
                (row.dataset.search || '').includes(search);

            const matchesModule =
                !module ||
                row.dataset.module === module;

            const matchesAction =
                !action ||
                row.dataset.action === action;

            const matchesFrom =
                !from ||
                (rowDate !== '' && rowDate >= from);

            const matchesTo =
                !to ||
                (rowDate !== '' && rowDate <= to);

            const show =
                matchesSearch &&
                matchesModule &&
                matchesAction &&
                matchesFrom &&
                matchesTo;

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
                `Showing ${visible} audit event(s)`;
        }
    }

    searchInput?.addEventListener('input', applyFilters);
    moduleFilter?.addEventListener('change', applyFilters);
    actionFilter?.addEventListener('change', applyFilters);
    dateFrom?.addEventListener('change', applyFilters);
    dateTo?.addEventListener('change', applyFilters);

    resetButton?.addEventListener('click', () => {
        if (searchInput) {
            searchInput.value = '';
        }

        if (moduleFilter) {
            moduleFilter.value = '';
        }

        if (actionFilter) {
            actionFilter.value = '';
        }

        if (dateFrom) {
            dateFrom.value = '';
        }

        if (dateTo) {
            dateTo.value = '';
        }

        applyFilters();
    });

    document.getElementById(
        'btnExportAuditLogs'
    )?.addEventListener('click', () => {
        exportVisibleRows();
    });

    function exportVisibleRows() {
        const table =
            document.getElementById('auditLogsTable');

        if (!table) {
            return;
        }

        const headers = Array.from(
            table.querySelectorAll('thead th')
        )
            .slice(0, -1)
            .map((cell) => cell.textContent.trim());

        const csvRows = [
            headers.map(csvEscape).join(',')
        ];

        Array.from(
            table.querySelectorAll(
                'tbody tr.audit-data-row:not(.d-none)'
            )
        ).forEach((row) => {
            const cells = Array.from(
                row.querySelectorAll('td')
            )
                .slice(0, -1)
                .map((cell) => {
                    return cell.textContent
                        .replace(/\s+/g, ' ')
                        .trim();
                });

            csvRows.push(
                cells.map(csvEscape).join(',')
            );
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
            `orlms-activity-logs-${new Date()
                .toISOString()
                .slice(0, 10)}.csv`;

        document.body.appendChild(link);
        link.click();
        link.remove();
        URL.revokeObjectURL(url);
    }

    function setText(id, value) {
        const element = document.getElementById(id);

        if (element) {
            element.textContent =
                value === null || value === undefined
                    ? ''
                    : String(value);
        }
    }

    function getSeverityIcon(severity) {
        const icons = {
            information:
                '<i class="bi bi-info-circle"></i>',
            success:
                '<i class="bi bi-check2-circle"></i>',
            security:
                '<i class="bi bi-shield-lock"></i>',
            attention:
                '<i class="bi bi-exclamation-triangle"></i>'
        };

        return icons[severity] || icons.information;
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
