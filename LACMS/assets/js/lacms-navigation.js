'use strict';
(function() {
    function initNavigation() {
        const sidebar = document.getElementById('orlmsSidebar') || document.getElementById('lacmsSidebar') || document.querySelector('.sidebar');
        const backdrop = document.getElementById('orlmsSidebarBackdrop') || document.getElementById('lacmsSidebarBackdrop') || document.querySelector('.sidebar-backdrop');

        // Restore saved collapsed sidebar state across all modules
        if (localStorage.getItem('lacms_sidebar_collapsed') === 'true') {
            document.body.classList.add('sidebar-collapsed');
        }

        function toggleSidebarState() {
            if (window.innerWidth <= 1050) {
                sidebar?.classList.toggle('open');
                backdrop?.classList.toggle('show');
                document.body.classList.toggle('sidebar-open');
            } else {
                const isCollapsed = document.body.classList.toggle('sidebar-collapsed');
                localStorage.setItem('lacms_sidebar_collapsed', isCollapsed ? 'true' : 'false');
            }
        }

        function closeSidebarMobile() {
            sidebar?.classList.remove('open');
            backdrop?.classList.remove('show');
            document.body.classList.remove('sidebar-open');
        }

        // Global Event Delegation for any toggle button click
        document.addEventListener('click', function(e) {
            const btn = e.target.closest('#lacmsSidebarToggle, #orlmsSidebarToggle, .lacms-menu-button, .orlms-menu-button, .topbar-menu-button, [data-sidebar-toggle]');
            if (btn) {
                e.preventDefault();
                e.stopPropagation();
                toggleSidebarState();
            }
        });

        backdrop?.addEventListener('click', closeSidebarMobile);

        window.addEventListener('resize', function() {
            if (window.innerWidth > 1050) closeSidebarMobile();
        });

        // Admin View Toggle functionality (if present)
        const adminToggleBtn = document.getElementById('lacmsAdminToggle');
        const adminBadge = document.getElementById('lacmsAdminBadge');
        
        let isAdminActive = localStorage.getItem('lacms_admin_mode') !== 'off';

        function updateAdminState(active, notify) {
            isAdminActive = active;
            localStorage.setItem('lacms_admin_mode', active ? 'on' : 'off');

            if (adminToggleBtn && adminBadge) {
                if (active) {
                    adminToggleBtn.classList.remove('admin-off');
                    adminBadge.textContent = 'ON';
                    adminToggleBtn.setAttribute('title', 'Admin View Mode Enabled (Click to switch to Standard View)');
                } else {
                    adminToggleBtn.classList.add('admin-off');
                    adminBadge.textContent = 'OFF';
                    adminToggleBtn.setAttribute('title', 'Standard View (Click to enable Admin View)');
                }
            }

            if (notify && typeof Swal !== 'undefined') {
                Swal.fire({
                    toast: true,
                    position: 'top-end',
                    icon: active ? 'success' : 'info',
                    title: active ? 'Admin View Mode Enabled' : 'Switched to Standard User View',
                    showConfirmButton: false,
                    timer: 2200,
                    timerProgressBar: true
                });
            }
        }

        if (adminToggleBtn) {
            updateAdminState(isAdminActive, false);
            adminToggleBtn.addEventListener('click', function() {
                updateAdminState(!isAdminActive, true);
            });
        }

        document.querySelectorAll('[data-ui-preview]').forEach(function(button){
            button.addEventListener('click',function(){
                const title=button.querySelector('strong')?.textContent?.trim()||button.textContent.trim()||'LACMS action';
                if(typeof Swal!=='undefined'){
                    Swal.fire({
                        icon:'info',
                        title:title,
                        html:'<div class="text-start small"><p>This navigation and page are ready for the current client-demo phase.</p><p class="text-muted mb-0">Database CRUD, automated reminders, email delivery, schedule conflict checking, and live reports will be connected later.</p></div>',
                        confirmButtonText:'Okay'
                    });
                }
            });
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initNavigation);
    } else {
        initNavigation();
    }
})();
