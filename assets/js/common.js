/**
 * Common JavaScript for UniLi Water Monitoring System
 */

function toggleSidebar() {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebarOverlay');
    
    if (window.innerWidth > 992) {
        sidebar.classList.toggle('collapsed');
        // Save state to localStorage if needed
        localStorage.setItem('sidebarCollapsed', sidebar.classList.contains('collapsed'));
    } else {
        sidebar.classList.toggle('active');
        overlay.classList.toggle('active');
    }
}

// Initialize sidebar state on page load
document.addEventListener('DOMContentLoaded', () => {
    const sidebar = document.getElementById('sidebar');
    if (sidebar && window.innerWidth > 992) {
        const isCollapsed = localStorage.getItem('sidebarCollapsed') === 'true';
        if (isCollapsed) {
            sidebar.classList.add('collapsed');
        }
    }
});
