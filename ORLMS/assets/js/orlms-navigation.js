'use strict';
(function(){
  const isCollapsed = localStorage.getItem('orlms_sidebar_collapsed') === '1';
  if (isCollapsed && window.innerWidth > 1050) {
    document.body.classList.add('sidebar-collapsed');
  }
})();

document.addEventListener('DOMContentLoaded', function(){
  const sidebar = document.getElementById('orlmsSidebar');
  const toggle = document.getElementById('orlmsSidebarToggle');
  const collapseToggle = document.getElementById('orlmsCollapseToggle');
  const backdrop = document.getElementById('orlmsSidebarBackdrop');

  function closeMobile(){
    sidebar?.classList.remove('open');
    backdrop?.classList.remove('show');
  }

  function handleToggle(e){
    if (e) { e.preventDefault(); e.stopPropagation(); }
    if (window.innerWidth <= 1050) {
      sidebar?.classList.toggle('open');
      backdrop?.classList.toggle('show');
    } else {
      document.body.classList.toggle('sidebar-collapsed');
      const active = document.body.classList.contains('sidebar-collapsed');
      localStorage.setItem('orlms_sidebar_collapsed', active ? '1' : '0');
    }
  }

  document.addEventListener('click', function(e){
    const btn = e.target.closest('#orlmsCollapseToggle, #orlmsSidebarToggle, .orlms-collapse-toggle, .orlms-menu-button');
    if (btn) {
      handleToggle(e);
    }
  });

  backdrop?.addEventListener('click', closeMobile);

  window.addEventListener('resize', function(){
    if (window.innerWidth > 1050) {
      closeMobile();
    }
  });

  document.querySelectorAll('[data-ui-preview]').forEach(function(button){
    button.addEventListener('click', function(){
      const title = button.querySelector('strong')?.textContent?.trim() || button.textContent.trim() || 'Module action';
      if (typeof Swal !== 'undefined') {
        Swal.fire({
          icon: 'info',
          title: title,
          html: '<div class="text-start small"><p>This page and navigation are complete for the current client-demo phase.</p><p class="text-muted mb-0">Database CRUD, AJAX processing, workflow automation, uploads, and Ollama AI integration will be implemented in a later phase.</p></div>',
          confirmButtonText: 'Okay'
        });
      }
    });
  });
});
