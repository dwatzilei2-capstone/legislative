/**
 * LACMS reusable layout interactions.
 */

'use strict';

document.addEventListener('DOMContentLoaded', () => {
    const body = document.body;

    const sidebarToggle =
        document.getElementById('sidebarToggle');

    const sidebarClose =
        document.getElementById('sidebarClose');

    const sidebarOverlay =
        document.getElementById('sidebarOverlay');

    function openSidebar() {
        body.classList.add('sidebar-open');
    }

    function closeSidebar() {
        body.classList.remove('sidebar-open');
    }

    sidebarToggle?.addEventListener('click', openSidebar);
    sidebarClose?.addEventListener('click', closeSidebar);
    sidebarOverlay?.addEventListener('click', closeSidebar);

    window.addEventListener('resize', () => {
        if (window.innerWidth > 1100) {
            closeSidebar();
        }
    });

    document.getElementById(
        'notificationButton'
    )?.addEventListener('click', () => {
        if (typeof Swal === 'undefined') {
            return;
        }

        Swal.fire({
            icon: 'info',
            title: 'Notifications',
            text:
                'Meeting, deadline, and calendar notifications will appear here after the modules are connected.',
            confirmButtonText: 'Close',
        });
    });

    const flashElement =
        document.querySelector('.lacms-flash-message');

    if (
        flashElement &&
        typeof Swal !== 'undefined'
    ) {
        const type =
            flashElement.dataset.flashType || 'info';

        const message =
            flashElement.dataset.flashMessage || '';

        const iconMap = {
            success: 'success',
            danger: 'error',
            warning: 'warning',
            info: 'info',
        };

        Swal.fire({
            icon: iconMap[type] || 'info',
            title:
                type === 'success'
                    ? 'Success'
                    : type === 'danger'
                        ? 'Action Failed'
                        : 'Notice',
            text: message,
            timer: type === 'success' ? 2400 : undefined,
            showConfirmButton: type !== 'success',
        });
    }
});
