<?php
$current_page = basename($_SERVER['PHP_SELF']);
?>
<aside class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <div class="brand">
            <div class="brand-icon">
                <img src="assets/img/logo.png" alt="Unilia Logo">
            </div>
            <div class="brand-content">
                <span class="brand-title">UNILIA</span>
                <span class="brand-subtitle">Remote Water Monitoring</span>
            </div>
        </div>
        <button class="sidebar-close" onclick="toggleSidebar()" style="display: none;">
            <i class="fas fa-times"></i>
        </button>
    </div>

    <nav class="nav-menu">
        <ul>
            <li class="nav-item">
                <a href="dashboard.php" class="nav-link <?php echo ($current_page == 'dashboard.php') ? 'active' : ''; ?>">
                    <i class="fas fa-th-large nav-icon"></i>
                    <span>Dashboard</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="treatment.php"
                    class="nav-link <?php echo ($current_page == 'treatment.php') ? 'active' : ''; ?>">
                    <i class="fas fa-flask nav-icon"></i>
                    <span>Treatment</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="analytics.php" class="nav-link <?php echo ($current_page == 'analytics.php') ? 'active' : ''; ?>">
                    <i class="fas fa-chart-line nav-icon"></i>
                    <span>Analytics</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="historical.php" class="nav-link <?php echo ($current_page == 'historical.php') ? 'active' : ''; ?>">
                    <i class="fas fa-history nav-icon"></i>
                    <span>Historical Data</span>
                </a>
            </li>
            <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'manager'): ?>
                <li class="nav-item">
                    <a href="settings.php"
                        class="nav-link <?php echo (strpos($current_page, 'settings') !== false) ? 'active' : ''; ?>">
                        <i class="fas fa-cogs nav-icon"></i>
                        <span>System Settings</span>
                    </a>
                </li>
                <?php
            endif; ?>
            <li class="nav-item">
                <a href="reports.php" class="nav-link <?php echo ($current_page == 'reports.php') ? 'active' : ''; ?>">
                    <i class="fas fa-file-alt nav-icon"></i>
                    <span>Reports</span>
                </a>
            </li>
        </ul>
    </nav>

</aside>