<?php
// Global Header Actions Component (System Status, Notifications, User Profile)
if (isset($_SESSION['user_id'])) {
    require_once 'db_connect.php';
    $user_id = $_SESSION['user_id'];
    $stmt = $conn->prepare("SELECT fullname, role, avatar FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $user_header_data = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $initials = 'U';
    if ($user_header_data['fullname']) {
        $parts = explode(' ', $user_header_data['fullname']);
        $initials = strtoupper(substr($parts[0], 0, 1));
        if (isset($parts[1]))
            $initials .= strtoupper(substr($parts[1], 0, 1));
    }

    $username = htmlspecialchars($user_header_data['fullname']);
    $role = htmlspecialchars($user_header_data['role']);
    $avatar_path = (!empty($user_header_data['avatar']) && file_exists(__DIR__ . '/../' . $user_header_data['avatar'])) ? $user_header_data['avatar'] : null;
    ?>
    
    <style>
        .user-menu-container {
            position: relative;
            display: flex;
            align-items: center;
        }

        .user-menu-trigger {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.5rem 0.75rem;
            background: none;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.2s ease;
            color: inherit;
            font-family: inherit;
        }

        .user-menu-trigger:hover, .user-menu-trigger.active {
            background: rgba(255, 255, 255, 0.1);
        }

        .user-avatar {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background: var(--primary-500);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            font-size: 0.875rem;
            overflow: hidden;
        }

        .user-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .user-info {
            display: flex;
            flex-direction: column;
            align-items: flex-start;
            min-width: 0;
        }

        .user-name {
            font-size: 0.875rem;
            font-weight: 600;
            color: var(--gray-900);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            max-width: 130px;
        }

        .user-role {
            font-size: 0.75rem;
            color: var(--gray-500);
            text-transform: capitalize;
        }

        .menu-arrow {
            font-size: 0.75rem;
            color: var(--gray-500);
            transition: transform 0.2s ease;
        }

        .user-menu-trigger.active .menu-arrow {
            transform: rotate(180deg);
        }

        .user-dropdown {
            position: absolute;
            top: 100%;
            right: 0;
            min-width: 200px;
            background: white;
            border: 1px solid var(--gray-200);
            border-radius: 8px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
            opacity: 0;
            visibility: hidden;
            transform: translateY(-10px);
            transition: all 0.2s ease;
            z-index: 1000;
            margin-top: 0.5rem;
        }

        .user-dropdown.show {
            opacity: 1;
            visibility: visible;
            transform: translateY(0);
        }

        .dropdown-item {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.75rem 1rem;
            color: var(--gray-700);
            text-decoration: none;
            transition: all 0.2s ease;
            font-size: 0.875rem;
        }

        .dropdown-item:hover {
            background: var(--gray-50);
            color: var(--gray-900);
        }

        .dropdown-item i {
            width: 16px;
            text-align: center;
        }

        .logout-item {
            color: #dc2626;
        }

        .logout-item:hover {
            background: #fef2f2;
            color: #dc2626;
        }

        .dropdown-divider {
            height: 1px;
            background: var(--gray-200);
            margin: 0.25rem 0;
        }

        .notification-item {
            cursor: pointer;
            transition: all 0.2s ease;
            position: relative;
            padding: 1.25rem 1.5rem;
            border-bottom: 1px solid rgba(0,0,0,0.05);
            display: flex;
            gap: 1.25rem;
            background: white;
        }

        .notification-item:hover {
            background: var(--gray-50);
        }

        .notification-item.unread {
            background: rgba(14, 165, 233, 0.06);
            border-left: 4px solid var(--primary-500);
        }

        .notification-item.unread::after {
            content: '';
            position: absolute;
            top: 1.25rem;
            right: 1.5rem;
            width: 8px;
            height: 8px;
            background: var(--primary-500);
            border-radius: 50%;
            box-shadow: 0 0 10px rgba(14, 165, 233, 0.4);
        }

        .notification-item.read {
            opacity: 0.6;
        }

        .notification-item.read .notification-icon {
            filter: grayscale(1);
            opacity: 0.5;
        }

        .notification-item.read .notification-message {
            color: var(--text-muted);
        }

        .pulse-badge {
            animation: pulse-red 2s infinite;
        }

        @keyframes pulse-red {
            0% { transform: scale(1); box-shadow: 0 0 0 0 rgba(239, 68, 68, 0.7); }
            70% { transform: scale(1.1); box-shadow: 0 0 0 10px rgba(239, 68, 68, 0); }
            100% { transform: scale(1); box-shadow: 0 0 0 0 rgba(239, 68, 68, 0); }
        }

        @media (max-width: 768px) {
            .user-info { display: none; }
            .user-menu-trigger { padding: 0.5rem; }
            .header-actions { gap: 0.5rem !important; }
            #global-connection-status { display: none; } /* Hide text on very small screens, rely on status dot elsewhere */
        }
    </style>
    
    <div class="header-actions" style="display: flex; align-items: center; gap: 1rem; flex-wrap: wrap; justify-content: flex-end;">
        
        <!-- Connection Status -->
        <div class="glass" style="padding: 0.5rem 1rem; border-radius: 99px; display: flex; align-items: center; gap: 0.6rem;">
            <span id="global-connection-status" style="font-weight: 600; font-size: 0.82rem; color: #22c55e;">System Online</span>
        </div>

        <!-- Notification Bell -->
        <div id="alert-bell" class="glass" style="width: 40px; height: 40px; border-radius: 50%; display: flex; align-items: center; justify-content: center; cursor: pointer; position: relative;">
            <i class="fas fa-bell" style="color: var(--text-muted); font-size: 0.9rem;" id="globalNotifTrigger"></i>
            <span id="global-alert-count" style="display: none; position: absolute; top: -4px; right: -4px; background: var(--danger); color: white; border-radius: 50%; width: 18px; height: 18px; font-size: 0.65rem; font-weight: 700; align-items: center; justify-content: center;">0</span>

            <!-- Notification Popover -->
            <div id="global-notification-popover" class="notification-popover">
                <div class="notification-header">
                    <h3>Notifications</h3>
                    <button class="mark-all-read" id="globalMarkAllRead">Mark all as read</button>
                </div>
                <div id="global-notification-list" class="notification-list">
                    <div style="padding: 3rem; text-align: center; color: var(--text-muted);">
                        <i class="fas fa-bell-slash" style="font-size: 1.5rem; opacity: 0.3; margin-bottom: 1rem; display: block;"></i>
                        No new notifications
                    </div>
                </div>
                <div class="notification-footer">
                    <a href="analytics.php" class="view-all-alerts">View Analytics & History</a>
                </div>
            </div>
        </div>

        <!-- User Dropdown Menu -->
        <div class="user-menu-container">
            <button class="user-menu-trigger" id="userMenuTrigger" aria-label="User menu">
                <div class="user-avatar">
                    <?php if ($avatar_path): ?>
                        <img src="<?php echo $avatar_path; ?>" alt="Avatar">
                    <?php else: ?>
                        <?php echo $initials; ?>
                    <?php endif; ?>
                </div>
                <div class="user-info">
                    <span class="user-name"><?php echo $username; ?></span>
                    <span class="user-role"><?php echo $role; ?></span>
                </div>
                <i class="fas fa-chevron-down menu-arrow"></i>
            </button>

            <div class="user-dropdown" id="userDropdown">
                <a href="profile.php" class="dropdown-item">
                    <i class="fas fa-user"></i>
                    <span>Profile</span>
                </a>
                <div class="dropdown-divider"></div>
                <a href="logout.php" class="dropdown-item logout-item">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>Sign Out</span>
                </a>
            </div>
        </div>

    </div>

    <!-- Global Header JS -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const trigger = document.getElementById('userMenuTrigger');
            const dropdown = document.getElementById('userDropdown');
            const notifTrigger = document.getElementById('globalNotifTrigger');
            const notifPopover = document.getElementById('global-notification-popover');
            const markAllBtn = document.getElementById('globalMarkAllRead');

            if (trigger && dropdown) {
                trigger.addEventListener('click', function(e) {
                    e.stopPropagation();
                    dropdown.classList.toggle('show');
                    trigger.classList.toggle('active');
                    if(notifPopover) notifPopover.classList.remove('active');
                });
            }

            if (notifTrigger && notifPopover) {
                notifTrigger.addEventListener('click', function(e) {
                    e.stopPropagation();
                    notifPopover.classList.toggle('active');
                    if(dropdown) {
                        dropdown.classList.remove('show');
                        trigger.classList.remove('active');
                    }
                });
            }

            document.addEventListener('click', function(e) {
                if (trigger && dropdown && !trigger.contains(e.target) && !dropdown.contains(e.target)) {
                    dropdown.classList.remove('show');
                    trigger.classList.remove('active');
                }
                if (notifTrigger && notifPopover && !notifTrigger.contains(e.target) && !notifPopover.contains(e.target)) {
                    notifPopover.classList.remove('active');
                }
            });

            if (markAllBtn) {
                markAllBtn.addEventListener('click', async function(e) {
                    e.stopPropagation();
                    try {
                        const res = await fetch('api/alerts.php', { 
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({ action: 'mark_all_read' })
                        });
                        const resData = await res.json();
                        if (resData.success) {
                            const badge = document.getElementById('global-alert-count');
                            if (badge) {
                                badge.style.display = 'none';
                                badge.textContent = '0';
                            }
                            fetchGlobalHeaderData(); 
                        }
                    } catch(err) {}
                });
            }

            // Start Polling immediately
            fetchGlobalHeaderData();
            setInterval(fetchGlobalHeaderData, 5000);
        });

        async function fetchGlobalHeaderData() {
            try {
                // 1. Connection Status - Using the correct institutional endpoint
                const resData = await fetch('api/latest.php');
                const data = await resData.json();
                
                // api/latest.php returns success: true and system_status: 'online' if ESP32 is active
                let isOffline = true;
                if (data && data.success && data.system_status === 'online') {
                    isOffline = false;
                }

                const connStatus = document.getElementById("global-connection-status");
                if (connStatus) {
                    if (isOffline) {
                        connStatus.textContent = "System Offline";
                        connStatus.style.color = "var(--danger)";
                    } else {
                        connStatus.textContent = "System Online";
                        connStatus.style.color = "#22c55e";
                    }
                }

                const sysStatus = document.getElementById("global-sys-status");
                if (sysStatus) {
                    sysStatus.className = isOffline ? "status-indicator status-inactive" : "status-indicator status-active";
                }

                // 2. Notifications
                const resAlerts = await fetch('api/alerts.php?t=' + Date.now());
                const alertData = await resAlerts.json();

                if (alertData.success) {
                    const count = alertData.count || 0;
                    const badge = document.getElementById('global-alert-count');
                    if (badge) {
                        if (count > 0) {
                            badge.textContent = count; 
                            badge.style.display = 'flex';
                            badge.classList.add('pulse-badge');
                        } else {
                            badge.style.display = 'none';
                            badge.classList.remove('pulse-badge');
                        }
                    }

                    const popList = document.getElementById('global-notification-list');
                    if (popList) {
                        if (alertData.alerts && alertData.alerts.length > 0) {
                            popList.innerHTML = '';
                            alertData.alerts.forEach(alert => {
                                const item = document.createElement('div');
                                item.className = 'notification-item' + (alert.is_read ? ' read' : ' unread');
                                
                                let icon = 'fa-info-circle';
                                let iconBg = 'rgba(14, 165, 233, 0.1)';
                                let iconColor = '#0ea5e9';

                                if (alert.severity === 'critical') {
                                    icon = 'fa-exclamation-triangle';
                                    iconBg = 'rgba(239, 68, 68, 0.1)';
                                    iconColor = '#ef4444';
                                } else if (alert.severity === 'warning') {
                                    icon = 'fa-exclamation-circle';
                                    iconBg = 'rgba(245, 158, 11, 0.1)';
                                    iconColor = '#f59e0b';
                                }

                                const time = new Date(alert.created_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });

                                item.innerHTML = `
                                    <div class="notification-icon" style="background: ${iconBg}; color: ${iconColor}">
                                        <i class="fas ${icon}"></i>
                                    </div>
                                    <div class="notification-content">
                                        <div class="notification-message">${alert.message}</div>
                                        <div class="notification-time">${time}</div>
                                    </div>
                                `;
                                
                                item.addEventListener('click', async (e) => {
                                    e.stopPropagation();
                                    try {
                                        const res = await fetch('api/alerts.php', {
                                            method: 'POST',
                                            headers: { 'Content-Type': 'application/json' },
                                            body: JSON.stringify({ action: 'mark_read', id: alert.id })
                                        });
                                        const resData = await res.json();
                                        if (resData.success) {
                                            const badge = document.getElementById('global-alert-count');
                                            if (badge) {
                                                if (resData.count > 0) {
                                                    badge.textContent = resData.count;
                                                    badge.style.display = 'flex';
                                                } else {
                                                    badge.style.display = 'none';
                                                }
                                            }
                                            fetchGlobalHeaderData();
                                        }
                                    } catch(err) {}
                                });

                                popList.appendChild(item);
                            });
                        } else {
                            popList.innerHTML = '<div style="padding: 3rem; text-align: center; color: var(--text-muted);"><i class="fas fa-bell-slash" style="font-size: 1.5rem; opacity: 0.3; margin-bottom: 1rem; display: block;"></i>No new notifications</div>';
                        }
                    }
                }
            } catch (e) {}
        }
    </script>


    <?php
}
?>