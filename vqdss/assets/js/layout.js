'use strict';

document.addEventListener('DOMContentLoaded', () => {
    const sidebar =
        document.getElementById('orlmsSidebar') ||
        document.getElementById('sidebar');

    const backdrop =
        document.getElementById('orlmsSidebarBackdrop') ||
        document.getElementById('sidebarBackdrop');

    const collapseBtn =
        document.getElementById('sidebarCollapseToggle') ||
        document.getElementById('sidebarToggle');

    // Restore collapsed state from localStorage
    if (localStorage.getItem('vqdss_sidebar_collapsed') === '1') {
        document.documentElement.classList.add('sidebar-collapsed');
        if (document.body) document.body.classList.add('sidebar-collapsed');
    }

    const closeSidebar = () => {
        sidebar?.classList.remove('open');
        backdrop?.classList.remove('show');
    };

    backdrop?.addEventListener(
        'click',
        closeSidebar
    );

    window.addEventListener('resize', () => {
        if (window.innerWidth > 991) {
            closeSidebar();
        }
    });
});
