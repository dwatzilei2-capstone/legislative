/**
 * assets/js/publications.js
 * ------------------------------------------------------------------
 * Client-side behavior for Publication and Dissemination.
 * ------------------------------------------------------------------
 */

'use strict';

document.addEventListener('DOMContentLoaded', () => {
    const modalElement =
        document.getElementById('publicationModal');

    const modal = modalElement
        ? new bootstrap.Modal(modalElement)
        : null;

    const publicationItemId =
        document.getElementById('publicationItemId');

    const publicationReference =
        document.getElementById('publicationRecordReference');

    const publicationTitle =
        document.getElementById('publicationRecordTitle');

    document.querySelectorAll(
        '[data-process-publication]'
    ).forEach((button) => {
        button.addEventListener('click', () => {
            if (publicationItemId) {
                publicationItemId.value =
                    button.dataset.processPublication || '';
            }

            if (publicationReference) {
                publicationReference.textContent =
                    button.dataset.reference ||
                    'Legislative measure';
            }

            if (publicationTitle) {
                publicationTitle.textContent =
                    button.dataset.title ||
                    'Publication record';
            }

            modal?.show();
        });
    });

    const tabs = Array.from(
        document.querySelectorAll(
            '[data-publication-tab]'
        )
    );

    const sections = Array.from(
        document.querySelectorAll(
            '[data-publication-section]'
        )
    );

    tabs.forEach((tab) => {
        tab.addEventListener('click', () => {
            const target = tab.dataset.publicationTab;

            tabs.forEach((item) => {
                item.classList.toggle(
                    'active',
                    item === tab
                );
            });

            sections.forEach((section) => {
                section.classList.toggle(
                    'active',
                    section.dataset.publicationSection === target
                );
            });
        });
    });

    document.getElementById(
        'publicationForm'
    )?.addEventListener('submit', (event) => {
        event.preventDefault();

        Swal.fire({
            icon: 'success',
            title: 'Publication Interface Ready',
            text:
                'The publication and dissemination form is complete. The AJAX save endpoint will be connected during backend implementation.',
            confirmButtonText: 'Continue',
        }).then(() => {
            modal?.hide();
        });
    });

    document.getElementById(
        'btnOpenPublicationGuide'
    )?.addEventListener('click', () => {
        Swal.fire({
            icon: 'info',
            title: 'Publication and Dissemination Guide',
            html:
                '<div class="text-start small">' +
                '<p><strong>1.</strong> Verify the enacted and certified final copy.</p>' +
                '<p><strong>2.</strong> Record the official publication source and date.</p>' +
                '<p><strong>3.</strong> Publish the public-facing copy and URL.</p>' +
                '<p><strong>4.</strong> Select all dissemination channels used.</p>' +
                '<p><strong>5.</strong> Record proof of publication and release.</p>' +
                '</div>',
        });
    });

    document.querySelectorAll(
        '[data-view-dissemination]'
    ).forEach((button) => {
        button.addEventListener('click', () => {
            Swal.fire({
                icon: 'info',
                title: 'Dissemination Records',
                text:
                    'The dissemination channel history will be connected during backend implementation.',
            });
        });
    });

    document.getElementById(
        'btnAssignPublicationOffice'
    )?.addEventListener('click', () => {
        Swal.fire({
            icon: 'info',
            title: 'Responsible Office',
            text:
                'Publication office assignment will be connected to the publication database tables.',
        });
    });

    document.getElementById(
        'btnUploadPublicationDocument'
    )?.addEventListener('click', () => {
        Swal.fire({
            icon: 'info',
            title: 'Publication Document Upload',
            text:
                'Publication proof and document upload will be connected during backend implementation.',
        });
    });

    const documentInput =
        document.getElementById('publicationDocuments');

    const selectedFiles =
        document.getElementById('publicationSelectedFiles');

    documentInput?.addEventListener('change', () => {
        if (!selectedFiles) {
            return;
        }

        selectedFiles.innerHTML = '';

        Array.from(documentInput.files).forEach((file) => {
            const item = document.createElement('div');
            item.className = 'publication-file-item';

            item.innerHTML = `
                <i class="bi bi-file-earmark"></i>
                <span>${escapeHtml(file.name)}</span>
                <small>${formatFileSize(file.size)}</small>
            `;

            selectedFiles.appendChild(item);
        });
    });

    const searchInput =
        document.getElementById('publicationSearch');

    const typeFilter =
        document.getElementById('publicationTypeFilter');

    const statusFilter =
        document.getElementById('publicationStatusFilter');

    const priorityFilter =
        document.getElementById('publicationPriorityFilter');

    const resetButton =
        document.getElementById('btnResetPublicationFilters');

    const rows = Array.from(
        document.querySelectorAll('.publication-data-row')
    );

    const noResultRow =
        document.getElementById('publicationNoFilterResult');

    const countLabel =
        document.getElementById('publicationRecordCount');

    function applyFilters() {
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
                `Showing ${visible} publication record(s)`;
        }
    }

    searchInput?.addEventListener('input', applyFilters);
    typeFilter?.addEventListener('change', applyFilters);
    statusFilter?.addEventListener('change', applyFilters);
    priorityFilter?.addEventListener('change', applyFilters);

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

        applyFilters();
    });

    function escapeHtml(value) {
        const element = document.createElement('div');
        element.textContent = value;
        return element.innerHTML;
    }

    function formatFileSize(bytes) {
        if (bytes < 1024) {
            return `${bytes} B`;
        }

        if (bytes < 1024 * 1024) {
            return `${(bytes / 1024).toFixed(1)} KB`;
        }

        return `${(
            bytes /
            (1024 * 1024)
        ).toFixed(1)} MB`;
    }
});
