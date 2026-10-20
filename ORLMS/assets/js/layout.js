'use strict';

document.addEventListener('DOMContentLoaded', () => {
    const sidebar = document.getElementById('sidebar');
    const toggle = document.getElementById('sidebarToggle');
    const overlay = document.getElementById('sidebarOverlay');
    const mainContent = document.querySelector('.main-content');

    if (!sidebar || !toggle || !mainContent) {
        return;
    }

    const toggleIcon = toggle.querySelector('i');

    const setCollapsedState = (collapsed) => {
        sidebar.classList.toggle('collapsed', collapsed);
        mainContent.classList.toggle(
            'sidebar-collapsed',
            collapsed
        );

        if (toggleIcon) {
            toggleIcon.className = collapsed
                ? 'bi bi-chevron-right'
                : 'bi bi-chevron-left';
        }

        localStorage.setItem(
            'orlms_sidebar_collapsed',
            collapsed ? '1' : '0'
        );
    };

    if (window.innerWidth > 992) {
        setCollapsedState(
            localStorage.getItem(
                'orlms_sidebar_collapsed'
            ) === '1'
        );
    }

    toggle.addEventListener('click', () => {
        if (window.innerWidth <= 992) {
            sidebar.classList.toggle('mobile-open');

            overlay?.classList.toggle(
                'active',
                sidebar.classList.contains('mobile-open')
            );

            return;
        }

        setCollapsedState(
            !sidebar.classList.contains('collapsed')
        );
    });

    overlay?.addEventListener('click', () => {
        sidebar.classList.remove('mobile-open');
        overlay.classList.remove('active');
    });

    document.addEventListener('keydown', (event) => {
        if (event.key !== 'Escape') {
            return;
        }

        sidebar.classList.remove('mobile-open');
        overlay?.classList.remove('active');
    });
});