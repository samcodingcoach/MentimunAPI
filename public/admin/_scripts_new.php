<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<script>
    // Initialize Select2 for searchable dropdowns
    $(document).ready(function () {
        // Single Select with Search
        $('.select2-search').select2({
            theme: 'bootstrap-5',
            width: '100%',
            placeholder: '-- Pilih --',
            allowClear: true
        });

        // Multiple Select with Search
        $('.select2-multiple').select2({
            theme: 'bootstrap-5',
            width: '100%',
            placeholder: '-- Pilih --',
            allowClear: true
        });
    });

    // Sidebar Toggle
    const sidebar = document.getElementById('sidebar');
    const mainContent = document.getElementById('mainContent');
    const toggleBtn = document.getElementById('toggleSidebar');
    const sidebarOverlay = document.getElementById('sidebarOverlay');

    toggleBtn.addEventListener('click', function () {
        if (window.innerWidth <= 768) {
            // Mobile: Show/hide sidebar
            sidebar.classList.toggle('show-mobile');
            sidebarOverlay.classList.toggle('show');
        } else {
            // Desktop: Collapse/expand sidebar
            sidebar.classList.toggle('collapsed');
            mainContent.classList.toggle('expanded');
            localStorage.setItem('sidebarCollapsed', sidebar.classList.contains('collapsed'));
        }
    });

    // Close sidebar when clicking overlay (mobile)
    sidebarOverlay.addEventListener('click', function () {
        sidebar.classList.remove('show-mobile');
        sidebarOverlay.classList.remove('show');
    });

    // Rotate arrow on menu collapse
    document.querySelectorAll('[data-bs-toggle="collapse"]').forEach(function (element) {
        element.addEventListener('click', function () {
            const arrow = this.querySelector('.menu-arrow');
            if (arrow) {
                arrow.classList.toggle('rotated');
            }
        });
    });

    // Dark Mode Toggle
    const darkModeToggle = document.getElementById('darkModeToggle');
    const darkModeIcon = document.getElementById('darkModeIcon');
    const htmlElement = document.documentElement;

    // Load saved theme
    const currentTheme = localStorage.getItem('bsTheme') || 'light';
    htmlElement.setAttribute('data-bs-theme', currentTheme);

    if (currentTheme === 'dark') {
        darkModeIcon.classList.remove('bi-moon-stars-fill');
        darkModeIcon.classList.add('bi-sun-fill');
    }

    darkModeToggle.addEventListener('click', function () {
        const currentTheme = htmlElement.getAttribute('data-bs-theme');

        if (currentTheme === 'light') {
            htmlElement.setAttribute('data-bs-theme', 'dark');
            localStorage.setItem('bsTheme', 'dark');
            darkModeIcon.classList.remove('bi-moon-stars-fill');
            darkModeIcon.classList.add('bi-sun-fill');
        } else {
            htmlElement.setAttribute('data-bs-theme', 'light');
            localStorage.setItem('bsTheme', 'light');
            darkModeIcon.classList.remove('bi-sun-fill');
            darkModeIcon.classList.add('bi-moon-stars-fill');
        }
    });

    // Load sidebar state on desktop
    if (localStorage.getItem('sidebarCollapsed') === 'true' && window.innerWidth > 768) {
        sidebar.classList.add('collapsed');
        mainContent.classList.add('expanded');
    }

    // Handle window resize
    window.addEventListener('resize', function () {
        if (window.innerWidth > 768) {
            sidebar.classList.remove('show-mobile');
            sidebarOverlay.classList.remove('show');
        }
    });

    // Auto-dismiss alerts after 5 seconds
    setTimeout(function() {
        const alerts = document.querySelectorAll('.alert');
        alerts.forEach(function(alert) {
            const bsAlert = new bootstrap.Alert(alert);
            bsAlert.close();
        });
    }, 5000);
</script>
