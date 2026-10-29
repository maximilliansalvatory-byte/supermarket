<?php
/**
 * Dual Supermarket Management System - Customer Base Footer
 */
if (!defined('APP_NAME')) {
    die("Direct access prohibited.");
}
?>

<!-- Footer -->
<footer class="mt-5 border-top bg-white py-5 text-secondary transition-all" style="background-color: var(--card-bg) !important;">
    <div class="container">
        <div class="row g-4 justify-content-between">
            <div class="col-lg-4 col-md-6">
                <div class="d-flex align-items-center gap-2 mb-3">
                    <span class="fs-3">🌱</span>
                    <span class="fs-4 fw-bold text-dark" style="color: var(--text-main) !important;"><?php echo APP_NAME; ?></span>
                </div>
                <p class="small text-muted mb-4">
                    Providing fresh, premium, organic groceries directly from local farms. Experience elite quality products at the best market prices with absolute security.
                </p>
                <div class="d-flex gap-3">
                    <a href="#" class="btn btn-outline-success btn-sm rounded-circle" style="width: 36px; height: 36px; display: flex; align-items: center; justify-content: center;"><i class="bi bi-facebook"></i></a>
                    <a href="#" class="btn btn-outline-success btn-sm rounded-circle" style="width: 36px; height: 36px; display: flex; align-items: center; justify-content: center;"><i class="bi bi-twitter-x"></i></a>
                    <a href="#" class="btn btn-outline-success btn-sm rounded-circle" style="width: 36px; height: 36px; display: flex; align-items: center; justify-content: center;"><i class="bi bi-instagram"></i></a>
                </div>
            </div>
            
            <div class="col-lg-2 col-md-3">
                <h6 class="fw-bold text-dark mb-3" style="color: var(--text-main) !important;">Quick Links</h6>
                <ul class="list-unstyled small d-flex flex-column gap-2">
                    <li><a href="<?php echo BASE_URL; ?>/customer/index.php" class="text-decoration-none text-muted hover-success">Home</a></li>
                    <li><a href="<?php echo BASE_URL; ?>/customer/shop.php" class="text-decoration-none text-muted hover-success">Catalog Shop</a></li>
                    <li><a href="<?php echo BASE_URL; ?>/customer/cart.php" class="text-decoration-none text-muted hover-success">My Cart</a></li>
                    <li><a href="<?php echo BASE_URL; ?>/customer/profile.php" class="text-decoration-none text-muted hover-success">My Profile</a></li>
                </ul>
            </div>
            
            <div class="col-lg-2 col-md-3">
                <h6 class="fw-bold text-dark mb-3" style="color: var(--text-main) !important;">Business Info</h6>
                <ul class="list-unstyled small d-flex flex-column gap-2">
                    <li><a href="#" class="text-decoration-none text-muted">About Us</a></li>
                    <li><a href="#" class="text-decoration-none text-muted">Contact Support</a></li>
                    <li><a href="#" class="text-decoration-none text-muted">Privacy Policy</a></li>
                    <li><a href="#" class="text-decoration-none text-muted">Terms of Service</a></li>
                </ul>
            </div>
            
            <div class="col-lg-3 col-md-6">
                <h6 class="fw-bold text-dark mb-3" style="color: var(--text-main) !important;">Newsletter</h6>
                <p class="small text-muted mb-3">Subscribe to receive exclusive discounts, farm arrivals, and recipes!</p>
                <div class="input-group">
                    <input type="email" placeholder="Enter email..." class="form-control rounded-start-pill py-2 border-secondary-subtle" style="font-size: 0.9rem;">
                    <button class="btn btn-success rounded-end-pill px-3" type="button"><i class="bi bi-send-fill"></i></button>
                </div>
            </div>
        </div>
        
        <hr class="my-4 border-secondary-subtle">
        
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-center gap-3">
            <span class="small text-muted">&copy; <?php echo date('Y'); ?> FreshCart Supermarket. All rights reserved. Built with pure PHP.</span>
            <div class="d-flex gap-3 text-muted small">
                <span>Secure Payments:</span>
                <i class="bi bi-credit-card-2-front fs-5"></i>
                <i class="bi bi-paypal fs-5"></i>
                <i class="bi bi-wallet2 fs-5"></i>
            </div>
        </div>
    </div>
</footer>

<!-- Modern Toast Notification Container -->
<div class="toast-container-custom" id="toastContainer"></div>

<!-- Bootstrap 5 JS Bundle (Includes Popper) -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<!-- Global App scripts -->
<script src="<?php echo BASE_URL; ?>/assets/js/app.js"></script>

<!-- Theme Handler & Toggle Logic -->
<script>
document.addEventListener('DOMContentLoaded', () => {
    const html = document.documentElement;
    const toggleBtn = document.getElementById('themeToggle');
    const toggleIcon = document.getElementById('themeToggleIcon');
    
    // Read saved theme or fallback to system preferences
    const savedTheme = localStorage.getItem('theme') || 'light';
    setTheme(savedTheme);
    
    if (toggleBtn) {
        toggleBtn.addEventListener('click', () => {
            const currentTheme = html.getAttribute('data-theme');
            const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
            setTheme(newTheme);
        });
    }
    
    function setTheme(theme) {
        html.setAttribute('data-theme', theme);
        localStorage.setItem('theme', theme);
        
        if (toggleIcon) {
            if (theme === 'dark') {
                toggleIcon.className = 'bi bi-sun-fill text-warning';
            } else {
                toggleIcon.className = 'bi bi-moon-stars-fill text-success';
            }
        }
    }
});
</script>

</body>
</html>
