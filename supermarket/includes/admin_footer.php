<?php
/**
 * Dual Supermarket Management System - Administrative Layout Footer
 */
if (!defined('APP_NAME')) {
    die("Direct access prohibited.");
}
?>

</div> <!-- Close admin-workspace -->

<!-- Bootstrap 5 JS Bundle -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<!-- Admin Core script -->
<script src="<?php echo BASE_URL; ?>/assets/js/admin.js"></script>

<!-- Admin Theme handler script -->
<script>
document.addEventListener('DOMContentLoaded', () => {
    const html = document.documentElement;
    const adminToggleBtn = document.getElementById('adminThemeToggle');
    const adminToggleIcon = document.getElementById('adminThemeToggleIcon');
    
    // Read saved theme
    const savedTheme = localStorage.getItem('theme') || 'light';
    setAdminTheme(savedTheme);
    
    if (adminToggleBtn) {
        adminToggleBtn.addEventListener('click', () => {
            const currentTheme = html.getAttribute('data-theme');
            const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
            setAdminTheme(newTheme);
        });
    }
    
    function setAdminTheme(theme) {
        html.setAttribute('data-theme', theme);
        localStorage.setItem('theme', theme);
        
        if (adminToggleIcon) {
            if (theme === 'dark') {
                adminToggleIcon.className = 'bi bi-sun text-warning';
            } else {
                adminToggleIcon.className = 'bi bi-moon-stars text-success';
            }
        }
    }
});
</script>

</body>
</html>
