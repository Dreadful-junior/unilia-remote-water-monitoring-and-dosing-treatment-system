<?php
session_start();
include 'includes/session_sync.php';
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Access Control: Managers only
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'manager') {
    header("Location: dashboard.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Settings | UniLi Remote Water Monitoring System</title>
    <link rel="stylesheet" href="assets/css/style.css?v=2.1">
    <link rel="stylesheet" href="assets/css/dashboard_new.css">
    <script src="assets/js/common.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .settings-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 2rem;
            margin-top: 2rem;
        }

        .settings-card {
            background: rgba(255, 255, 255, 0.65);
            backdrop-filter: blur(15px);
            border-radius: var(--radius-xl);
            border: 1px solid rgba(255, 255, 255, 0.5);
            box-shadow: 0 8px 32px 0 rgba(31, 38, 135, 0.07);
            padding: 3rem 2rem;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            cursor: pointer;
            text-decoration: none;
            color: inherit;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            text-align: center;
        }

        .settings-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
            border-color: var(--primary);
            background: rgba(255, 255, 255, 0.9);
        }

        .card-icon-wrapper {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, rgba(14, 165, 233, 0.1), white);
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 1.5rem;
            box-shadow: 0 4px 12px rgba(14, 165, 233, 0.05);
            transition: all 0.3s ease;
        }

        .card-icon {
            font-size: 1.75rem;
            color: var(--primary-600);
        }

        .card-title {
            font-size: 1.1rem;
            font-weight: 800;
            color: var(--gray-900);
            margin-bottom: 0;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .card-desc {
            font-size: 0.95rem;
            color: var(--gray-600);
            margin-bottom: 1.5rem;
            flex-grow: 1;
        }

        .card-link {
            font-size: 0.9rem;
            font-weight: 600;
            color: var(--primary-600);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .section-badge {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            background: var(--primary-50);
            color: var(--primary-700);
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            margin-bottom: 1rem;
        }
    </style>
</head>

<body class="web-dashboard-body">
    <div class="dashboard-container">
        <!-- Sidebar -->
        <?php include 'includes/sidebar.php'; ?>
        <div class="sidebar-overlay" id="sidebarOverlay" onclick="toggleSidebar()"></div>

        <main class="main-content">
            <header class="dashboard-header-wide">
                <div class="main-header-welcome">
                    <div style="display: flex; align-items: center; gap: 1rem; margin-bottom: 0.5rem;">
                        <button class="mobile-toggle" onclick="toggleSidebar()">
                            <i class="fas fa-bars"></i>
                        </button>
                        <h1 class="welcome-title" style="margin-bottom: 0;">System Settings</h1>
                    </div>
                    <p class="welcome-subtitle">Configure and manage your water system parameters</p>
                </div>
                <?php include 'includes/header_user.php'; ?>
            </header>

            <div class="settings-grid">
                <!-- User Management -->
                <a href="settings_users.php" class="settings-card">
                    <div class="card-icon-wrapper">
                        <i class="fas fa-users card-icon"></i>
                    </div>
                    <h3 class="card-title">Manage Users</h3>
                </a>

                <!-- Monitoring Configuration -->
                <a href="settings_monitoring.php" class="settings-card">
                    <div class="card-icon-wrapper">
                        <i class="fas fa-sliders-h card-icon"></i>
                    </div>
                    <h3 class="card-title">Configure Sensors</h3>
                </a>

                <!-- Treatment Configuration -->
                <a href="settings_treatment.php" class="settings-card">
                    <div class="card-icon-wrapper">
                        <i class="fas fa-flask card-icon"></i>
                    </div>
                    <h3 class="card-title">Treatment Logic</h3>
                </a>

                <!-- Hardware Settings -->
                <a href="settings_hardware.php" class="settings-card">
                    <div class="card-icon-wrapper">
                        <i class="fas fa-microchip card-icon"></i>
                    </div>
                    <h3 class="card-title">Hardware Setup</h3>
                </a>

                <!-- Alerts & Notifications -->
                <a href="settings_alerts.php" class="settings-card">
                    <div class="card-icon-wrapper">
                        <i class="fas fa-bell card-icon"></i>
                    </div>
                    <h3 class="card-title">Alert Settings</h3>
                </a>

                <!-- System Interface -->
                <a href="settings_system.php" class="settings-card">
                    <div class="card-icon-wrapper">
                        <i class="fas fa-server card-icon"></i>
                    </div>
                    <h3 class="card-title">System Health</h3>
                </a>
            </div>
        </main>
    </div>
    <script>
    </script>
</body>

</html>
