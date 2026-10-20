/**
 * assets/js/reviews.js
 * ------------------------------------------------------------------
 * Client-side behavior for the Review and Committee Endorsement UI.
 * ------------------------------------------------------------------
 */

'use strict';

document.addEventListener('DOMContentLoaded', () => {
    const modalElement =
        document.getElementById('reviewDecisionModal');

    const modal = modalElement
        ? new bootstrap.Modal(modalElement)
        : null;

    const reviewItemId =
        document.getElementById('reviewItemId');

    const reviewReference =
        document.getElementById('reviewRecordReference');

    const reviewTitle =
        document.getElementById('reviewRecordTitle');

    document.querySelectorAll(
        '[data-start-review]'
    ).forEach((button) => {
        button.addEventListener('click', () => {
            if (reviewItemId) {
                reviewItemId.value =
                    button.dataset.startReview || '';
            }

            if (reviewReference) {
                reviewReference.textContent =
                    button.dataset.reference ||
                    'Legislative record';
            }

            if (reviewTitle) {
                reviewTitle.textContent =
                    button.dataset.title ||
                    'Committee review';
            }

            modal?.show();
        });
    });

    document.getElementById(
        'reviewDecisionForm'
    )?.addEventListener('submit', (event) => {
        event.preventDefault();

        const checkedDecision =
            event.currentTarget.querySelector(
                'input[name="decision"]:checked'
            );

        if (!checkedDecision) {
            Swal.fire({
                icon: 'warning',
                title: 'Select a recommendation',
                text:
                    'Choose Endorse, Return for Revision, or Continue Review.',
            });

            return;
        }

        Swal.fire({
            icon: 'success',
            title: 'Review Interface Ready',
            text:
                'The committee review decision form is complete. The AJAX save endpoint will be connected during backend implementation.',
            confirmButtonText: 'Continue',
        }).then(() => {
            modal?.hide();
        });
    });

    document.getElementById(
        'btnOpenReviewGuide'
    )?.addEventListener('click', () => {
        Swal.fire({
            icon: 'info',
            title: 'Committee Review Guide',
            html:
                '<div class="text-start small">' +
                '<p><strong>1.</strong> Verify completeness and legal form.</p>' +
                '<p><strong>2.</strong> Review policy, fiscal, and technical implications.</p>' +
                '<p><strong>3.</strong> Record committee observations.</p>' +
                '<p><strong>4.</strong> Endorse, return for revision, or continue review.</p>' +
                '</div>',
        });
    });

    document.querySelectorAll(
        '[data-add-review-note]'
    ).forEach((button) => {
        button.addEventListener('click', () => {
            Swal.fire({
                title: 'Add Committee Note',
                input: 'textarea',
                inputPlaceholder:
                    'Enter a committee note or observation...',
                showCancelButton: true,
                confirmButtonText: 'Add Note',
            }).then((result) => {
                if (!result.isConfirmed) {
                    return;
                }

                Swal.fire({
                    icon: 'success',
                    title: 'Note UI Ready',
                    text:
                        'The note endpoint will be connected during backend implementation.',
                });
            });
        });
    });

    document.getElementById(
        'btnAddReviewComment'
    )?.addEventListener('click', () => {
        Swal.fire({
            icon: 'success',
            title: 'Comment UI Ready',
            text:
                'Review discussion saving will be connected later.',
        });
    });

    document.getElementById(
        'btnAssignCommittee'
    )?.addEventListener('click', () => {
        Swal.fire({
            icon: 'info',
            title: 'Committee Assignment',
            text:
                'Committee assignment will be connected to the review database tables.',
        });
    });

    document.getElementById(
        'btnUploadReviewDocument'
    )?.addEventListener('click', () => {
        Swal.fire({
            icon: 'info',
            title: 'Document Upload',
            text:
                'Review document upload will be connected during backend implementation.',
        });
    });

    const searchInput =
        document.getElementById('reviewSearch');

    const typeFilter =
        document.getElementById('reviewTypeFilter');

    const statusFilter =
        document.getElementById('reviewStatusFilter');

    const priorityFilter =
        document.getElementById('reviewPriorityFilter');

    const resetButton =
        document.getElementById('btnResetReviewFilters');

    const rows = Array.from(
        document.querySelectorAll('.review-data-row')
    );

    const noResultRow =
        document.getElementById('reviewNoFilterResult');

    const countLabel =
        document.getElementById('reviewRecordCount');

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
                `Showing ${visible} review record(s)`;
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
});
