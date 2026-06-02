<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Access Control: Admins only
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['manager', 'admin'])) {
    header("Location: dashboard.php");
    exit();
}

include 'db_connect.php';
include_once 'includes/logger.php';

$msg = "";
$msgType = "success";

/* =====================================================
   CREATE TABLE (AUTO RUN ONCE)
=====================================================*/
$conn->query("
CREATE TABLE IF NOT EXISTS hardware_settings (
    id INT PRIMARY KEY DEFAULT 1,
    wifi_ssid VARCHAR(100) DEFAULT '',
    wifi_password VARCHAR(100) DEFAULT '',
    operation_mode VARCHAR(20) DEFAULT 'auto',
    turbidity_multiplier FLOAT DEFAULT 1.0,
    tds_multiplier FLOAT DEFAULT 1.0,
    config_version INT DEFAULT 1
)");

$conn->query("INSERT IGNORE INTO hardware_settings(id) VALUES(1)");

// Ensure columns exist (Robust check)
$required_settings_cols = [
    'turbidity_multiplier' => 'FLOAT DEFAULT 1.0',
    'tds_multiplier' => 'FLOAT DEFAULT 1.0',
    'config_version' => 'INT DEFAULT 1'
];
foreach ($required_settings_cols as $col => $def) {
    $check = $conn->query("SHOW COLUMNS FROM hardware_settings LIKE '$col'");
    if ($check && $check->num_rows == 0) {
        $conn->query("ALTER TABLE hardware_settings ADD COLUMN $col $def");
    }
}

/* =====================================================
   CREATE HARDWARE RECOGNITION TABLE
 =====================================================*/
$conn->query("
CREATE TABLE IF NOT EXISTS hardware_recognition (
    id INT AUTO_INCREMENT PRIMARY KEY,
    component_name VARCHAR(100) NOT NULL,
    identifier VARCHAR(50) UNIQUE NOT NULL,
    status ENUM('online', 'offline', 'not_detected') DEFAULT 'not_detected',
    is_enabled TINYINT DEFAULT 1,
    last_seen TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

// Initialize default components if missing
$conn->query("INSERT IGNORE INTO hardware_recognition (component_name, identifier) VALUES ('Turbidity Sensor', 'turbidity_sensor')");
$conn->query("INSERT IGNORE INTO hardware_recognition (component_name, identifier) VALUES ('TDS Sensor', 'tds_sensor')");
$conn->query("INSERT IGNORE INTO hardware_recognition (component_name, identifier) VALUES ('Temperature Sensor', 'temp_sensor')");
$conn->query("INSERT IGNORE INTO hardware_recognition (component_name, identifier) VALUES ('Ultrasonic Sensor', 'ultrasonic_sensor')");
$conn->query("INSERT IGNORE INTO hardware_recognition (component_name, identifier) VALUES ('Dosing Pump', 'dosing_pump')");

// Ensure columns exist (Robust check)
$check = $conn->query("SHOW COLUMNS FROM hardware_recognition LIKE 'is_enabled'");
if ($check && $check->num_rows == 0) {
    $conn->query("ALTER TABLE hardware_recognition ADD COLUMN is_enabled TINYINT DEFAULT 1");
}


/* =====================================================
   SAVE CONFIGURATION
=====================================================*/
if (isset($_POST['action']) && $_POST['action'] == "save") {

    $ssid = $conn->real_escape_string($_POST['ssid']);
    $pass = $conn->real_escape_string($_POST['password']);
    $mode = $conn->real_escape_string($_POST['mode']);

    try {
        $conn->query("
            UPDATE hardware_settings
            SET wifi_ssid='$ssid',
                wifi_password='$pass',
                operation_mode='$mode',
                config_version = config_version + 1
            WHERE id=1
        ");
    } catch (Exception $e) {
        header("Location: settings_hardware.php?msg=Error saving: " . urlencode($e->getMessage()) . "&type=error");
        exit();
    }

    if (function_exists('logActivity')) {
        logActivity(
            $conn,
            $_SESSION['user_id'],
            'UPDATE_HARDWARE',
            "ESP32 settings updated"
        );
    }

    header("Location: settings_hardware.php?msg=Configuration saved successfully&type=success");
    exit();
}

/* =====================================================
   PUSH CONFIG (SIMULATION)
=====================================================*/
if (isset($_POST['action']) && $_POST['action'] == "push") {

    // Here later you connect to ESP32 API or MQTT
    // file_get_contents("http://esp32-ip/update")

    if (function_exists('logActivity')) {
        logActivity(
            $conn,
            $_SESSION['user_id'],
            'PUSH_CONFIG',
            "Configuration pushed to ESP32"
        );
    }

    header("Location: settings_hardware.php?msg=Configuration pushed to device&type=success");
    exit();
}

/* =====================================================
   RESTART DEVICE (SIMULATION)
=====================================================*/
if (isset($_POST['action']) && $_POST['action'] == "restart") {

    if (function_exists('logActivity')) {
        logActivity(
            $conn,
            $_SESSION['user_id'],
            'RESTART_DEVICE',
            "ESP32 restart issued"
        );
    }

    header("Location: settings_hardware.php?msg=Device restart command sent&type=success");
    exit();
}

/* =====================================================
   TOGGLE SENSOR ENABLED
=====================================================*/
if (isset($_POST['action']) && $_POST['action'] == "toggle_sensor") {
    $id = $conn->real_escape_string($_POST['sensor_id']);
    $enabled = intval($_POST['enabled']);
    $note = isset($_POST['note']) ? $conn->real_escape_string($_POST['note']) : null;
    
    if ($note !== null) {
        $conn->query("UPDATE hardware_recognition SET is_enabled = $enabled, maintenance_note = '$note' WHERE identifier = '$id'");
    } else {
        $conn->query("UPDATE hardware_recognition SET is_enabled = $enabled WHERE identifier = '$id'");
    }
    echo json_encode(['success' => true]);
    exit();
}

/* =====================================================
   FETCH SETTINGS
=====================================================*/
$settings = $conn->query("SELECT * FROM hardware_settings WHERE id=1")
    ->fetch_assoc();

// Fallback for missing settings
if (!$settings) {
    $settings = [
        'wifi_ssid' => '',
        'wifi_password' => '',
        'operation_mode' => 'auto',
        'turbidity_multiplier' => 1.0,
        'tds_multiplier' => 1.0
    ];
}

// Fetch Hardware Inventory
$hw_inventory = $conn->query("SELECT * FROM hardware_recognition ORDER BY component_name ASC");
$hw_inventory_list = [];

if ($hw_inventory) {
    while ($row = $hw_inventory->fetch_assoc()) {
        // Stale detection: If last_seen > 60 seconds, it's effectively offline
        $is_stale = false;
        if ($row['last_seen']) {
            $last_seen_time = strtotime($row['last_seen']);
            if (time() - $last_seen_time > 60) {
                $is_stale = true;
            }
        } else {
            $is_stale = true; // Never seen
        }

        if ($is_stale && $row['status'] !== 'not_detected') {
            $row['status'] = 'offline';
        }
        
        $hw_inventory_list[] = $row;
    }
}

// Handle GET messages
if (isset($_GET['msg'])) {
    $msg = htmlspecialchars($_GET['msg']);
    if (isset($_GET['type']))
        $msgType = htmlspecialchars($_GET['type']);
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hardware Settings | UniLi Remote Water Monitoring System</title>
    <link rel="stylesheet" href="assets/css/style.css?v=2.0">
    <link rel="stylesheet" href="assets/css/dashboard_new.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="assets/js/common.js"></script>
    <style>
        .settings-container {
            max-width: 900px;
            margin: 0 auto;
        }

        .status-badge-premium { display: inline-flex; align-items: center; gap: 0.5rem; padding: 0.5rem 1.25rem; border-radius: 99px; font-size: 0.85rem; font-weight: 800; }
        .status-badge-premium.online { color: #22c55e; background: rgba(34, 197, 94, 0.1); border: 1px solid rgba(34, 197, 94, 0.2); }
        .status-badge-premium.offline { color: #ef4444; background: rgba(239, 68, 68, 0.1); border: 1px solid rgba(239, 68, 68, 0.2); }
        .status-badge-premium .pulse { width: 10px; height: 10px; background: #22c55e; border-radius: 50%; box-shadow: 0 0 10px #22c55e; animation: pulse 2s infinite; }
        .status-badge-premium .dot { width: 10px; height: 10px; background: #ef4444; border-radius: 50%; }
        
        .status-pill { display: inline-flex; align-items: center; gap: 0.4rem; padding: 0.25rem 0.75rem; border-radius: 99px; font-size: 0.7rem; font-weight: 800; text-transform: uppercase; }
        .status-pill.online { color: #22c55e; background: rgba(34, 197, 94, 0.1); }
        .status-pill.offline { color: #ef4444; background: rgba(239, 68, 68, 0.1); }
        .status-pill.disabled { color: var(--gray-400); background: var(--gray-100); }
        .status-pill.unknown { color: var(--gray-400); background: var(--gray-100); }
        .status-pill .dot { width: 6px; height: 6px; border-radius: 50%; background: currentColor; }

        @keyframes pulse { 0% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(34, 197, 94, 0.7); } 70% { transform: scale(1); box-shadow: 0 0 0 10px rgba(34, 197, 94, 0); } 100% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(34, 197, 94, 0); } }
    </style>
</head>

<body>

    <div class="dashboard-container">

        <?php include 'includes/sidebar.php'; ?>
        <div class="sidebar-overlay" id="sidebarOverlay" onclick="toggleSidebar()"></div>

        <main class="main-content">
            <header class="dashboard-header-wide">
                <div class="main-header-welcome">
                    <div style="display: flex; align-items: center; gap: 1rem; margin-bottom: 0.5rem; flex-wrap: wrap;">
                        <a href="settings.php" class="btn-back" style="text-decoration: none; color: var(--primary); font-size: 1.1rem; display: flex; align-items: center; gap: 0.5rem; background: rgba(14, 165, 233, 0.1); padding: 0.5rem 1rem; border-radius: 12px; transition: all 0.2s; font-weight: 700;">
                            <i class="fas fa-arrow-left"></i>
                            <span>Back</span>
                        </a>
                        <h1 class="welcome-title" style="margin-bottom: 0;">Hardware Configuration</h1>
                    </div>
                    <p class="welcome-subtitle">
                        <a href="settings.php" style="text-decoration: none; color: inherit; opacity: 0.7;">System Settings</a>
                        <i class="fas fa-chevron-right" style="font-size: 0.7rem; margin: 0 0.5rem; opacity: 0.5;"></i>
                        <span style="font-weight: 600;">Hardware</span>
                    </p>
                </div>
                <div style="display: flex; align-items: center; gap: 1rem; flex-wrap: wrap; justify-content: flex-end;">
                    <button class="btn btn-primary btn-save-mobile" onclick="document.querySelector('#configForm').submit()">
                        <i class="fas fa-save"></i> <span class="btn-text">Save Config</span>
                    </button>
                    <?php include 'includes/header_user.php'; ?>
                </div>
            </header>

            <div class="settings-container">

                <?php if ($msg): ?>
                    <div class="<?= $msgType == 'success' ? 'alert-success' : 'alert-error' ?>">
                        <i class="<?= $msgType == 'success' ? 'fas fa-check-circle' : 'fas fa-exclamation-circle' ?>"></i>
                        <?= $msg ?>
                    </div>
                <?php endif; ?>

                <!-- ================= CONFIG FORM ================= -->
                <form method="POST" id="configForm">
                    <input type="hidden" name="action" value="save">

                    <div class="settings-card">
                        <div class="card-header">
                            <h3 class="card-title">WiFi Configuration</h3>
                            <p class="card-desc">Configure ESP32 network connectivity settings.</p>
                        </div>

                        <div class="form-grid">
                            <div class="input-group">
                                <label class="input-label">WiFi SSID</label>
                                <input type="text" name="ssid" class="premium-input"
                                    value="<?= htmlspecialchars($settings['wifi_ssid']) ?>" placeholder="Network Name">
                            </div>

                            <div class="input-group">
                                <label class="input-label">WiFi Password</label>
                                <input type="password" name="password" class="premium-input"
                                    value="<?= htmlspecialchars($settings['wifi_password']) ?>"
                                    placeholder="Network Password">
                            </div>

                            <div class="input-group">
                                <label class="input-label">Operation Mode</label>
                                <select name="mode" class="premium-input premium-select">
                                    <option value="auto" <?= $settings['operation_mode'] == "auto" ? "selected" : "" ?>>
                                        Automatic (Sensor Driven)</option>
                                    <option value="manual" <?= $settings['operation_mode'] == "manual" ? "selected" : "" ?>>
                                        Manual Override</option>
                                    <option value="maintenance" <?= $settings['operation_mode'] == "maintenance" ? "selected" : "" ?>>Maintenance Mode
                                    </option>
                                </select>
                                <div class="helper-text">Determines how the controller responds to inputs.</div>
                            </div>
                        </div>

                        <div class="card-footer" style="margin-top:2rem; text-align:right;">
                            <button class="btn btn-primary">
                                <i class="fas fa-save"></i> Save Configuration
                            </button>
                        </div>
                    </div>
                </form>

                <!-- ================= HARDWARE INVENTORY ================= -->
                <div class="settings-card" style="margin-bottom: 2rem;">
                    <div class="card-header" style="display: flex; justify-content: space-between; align-items: flex-start;">
                        <div>
                            <h3 class="card-title">Hardware Inventory</h3>
                            <p class="card-desc">Recognized physical components and their current connectivity status.</p>
                        </div>
                        <?php 
                        $esp_status = 'offline';
                        foreach($hw_inventory_list as $hw) {
                            if($hw['identifier'] == 'esp32_controller') $esp_status = $hw['status'];
                        }
                        ?>
                        <div style="text-align: right;" id="main-controller-status">
                            <span style="font-size: 0.7rem; color: var(--gray-500); font-weight: 700; text-transform: uppercase; display: block; margin-bottom: 0.25rem;">Controller Status</span>
                            <?php if ($esp_status == 'online'): ?>
                                <span class="status-badge-premium online">
                                    <span class="pulse"></span>
                                    SYSTEM ONLINE
                                </span>
                            <?php else: ?>
                                <span class="status-badge-premium offline">
                                    <span class="dot"></span>
                                    SYSTEM OFFLINE
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="inventory-list" style="padding: 0 1.5rem 1.5rem; overflow-x: auto;">
                        <table style="width: 100%; border-collapse: collapse;">
                            <thead>
                                <tr style="text-align: left; border-bottom: 2px solid var(--gray-100);">
                                    <th style="padding: 1rem 0.5rem; color: var(--gray-500); font-size: 0.85rem; text-transform: uppercase;">Component</th>
                                    <th style="padding: 1rem 0.5rem; color: var(--gray-500); font-size: 0.85rem; text-transform: uppercase;">Status</th>
                                    <th style="padding: 1rem 0.5rem; color: var(--gray-500); font-size: 0.85rem; text-transform: uppercase;">Last Seen</th>
                                    <th style="padding: 1rem 0.5rem; color: var(--gray-500); font-size: 0.85rem; text-transform: uppercase;">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($hw_inventory_list as $hw): ?>
                                    <tr style="border-bottom: 1px solid var(--gray-100); <?= (isset($hw['is_enabled']) && $hw['is_enabled']) ? '' : 'opacity: 0.5;' ?>">
                                        <td style="padding: 1rem 0.5rem;">
                                            <div style="display: flex; align-items: center; gap: 0.75rem;">
                                                <div style="width: 32px; height: 32px; background: var(--gray-100); border-radius: 8px; display: flex; align-items: center; justify-content: center; color: var(--gray-600);">
                                                    <i class="fas <?= 
                                                        $hw['identifier'] == 'dosing_pump' ? 'fa-pump-medical' : 
                                                        ($hw['identifier'] == 'temp_sensor' ? 'fa-thermometer-half' : 
                                                        ($hw['identifier'] == 'ultrasonic_sensor' ? 'fa-ruler-vertical' : 
                                                        ($hw['identifier'] == 'turbidity_sensor' ? 'fa-water' : 
                                                        ($hw['identifier'] == 'tds_sensor' ? 'fa-flask' : 'fa-microchip'))))
                                                    ?>"></i>
                                                </div>
                                                <span style="font-weight: 600; color: var(--gray-900);"><?= $hw['component_name'] ?></span>
                                            </div>
                                        </td>
                                        <td style="padding: 1rem 0.5rem;" id="status-<?= $hw['identifier'] ?>">
                                            <?php if (isset($hw['is_enabled']) && !$hw['is_enabled']): ?>
                                                <span class="status-pill disabled">DISABLED</span>
                                            <?php elseif ($hw['status'] == 'online'): ?>
                                                <span class="status-pill online">
                                                    <span class="dot"></span> ONLINE
                                                </span>
                                            <?php elseif ($hw['status'] == 'offline'): ?>
                                                <span class="status-pill offline">
                                                    <span class="dot"></span> OFFLINE
                                                </span>
                                            <?php else: ?>
                                                <span class="status-pill unknown">NOT DETECTED</span>
                                            <?php endif; ?>
                                        </td>
                                        <td style="padding: 1rem 0.5rem; color: var(--gray-500); font-size: 0.85rem;">
                                            <?= $hw['last_seen'] ? date('M j, H:i:s', strtotime($hw['last_seen'])) : 'Never' ?>
                                        </td>
                                        <td style="padding: 1rem 0.5rem;">
                                            <?php if (isset($hw['is_enabled']) && $hw['is_enabled']): ?>
                                                <button onclick="toggleSensor('<?= $hw['identifier'] ?>', 0)" 
                                                        class="btn" style="padding: 0.6rem 1.2rem; font-size: 0.8rem; background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; font-weight: 700; border-radius: 10px; cursor: pointer; transition: all 0.2s; display: flex; align-items: center; gap: 0.5rem;">
                                                    <i class="fas fa-power-off"></i> <span>Disable</span>
                                                </button>
                                            <?php else: ?>
                                                <button onclick="toggleSensor('<?= $hw['identifier'] ?>', 1)" 
                                                        class="btn" style="padding: 0.6rem 1.2rem; font-size: 0.8rem; background: #dcfce7; color: #166534; border: 1px solid #bbf7d0; font-weight: 700; border-radius: 10px; cursor: pointer; transition: all 0.2s; display: flex; align-items: center; gap: 0.5rem;">
                                                    <i class="fas fa-play-circle"></i> <span>Enable</span>
                                                </button>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 2rem;">

                    <!-- ================= PUSH CONFIG ================= -->
                    <form method="POST">
                        <input type="hidden" name="action" value="push">

                        <div class="settings-card" style="height: 100%;">
                            <div class="card-header">
                                <h3 class="card-title">Deploy Configuration</h3>
                                <p class="card-desc">Send stored settings to the ESP32 controller.</p>
                            </div>

                            <div
                                style="text-align:center; padding:2rem; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 1rem;">
                                <button class="btn btn-success" style="background: var(--success-text); color: white;">
                                    <i class="fas fa-upload"></i> Push to Device
                                </button>
                                <p style="font-size: 0.85rem; color: var(--gray-500);">Requires device to be online.</p>
                            </div>
                        </div>
                    </form>

                    <!-- ================= RESTART ================= -->
                    <form method="POST">
                        <input type="hidden" name="action" value="restart">

                        <div class="settings-card" style="height: 100%;">
                            <div class="card-header">
                                <h3 class="card-title">Device Maintenance</h3>
                                <p class="card-desc">Restart hardware controller remotely.</p>
                            </div>

                            <div
                                style="text-align:center; padding:2rem; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 1rem;">
                                <button class="btn btn-danger"
                                    onclick="return confirm('Are you sure you want to restart the ESP32?')"
                                    style="background: var(--danger-text); color: white;">
                                    <i class="fas fa-power-off"></i> Restart Device
                                </button>
                                <p style="font-size: 0.85rem; color: var(--gray-500);">System will be offline for ~30
                                    seconds.</p>
                            </div>
                        </div>
                    </form>
                </div>

            </div>

        </main>
    </div>

    <!-- MAINTENANCE REASON MODAL -->
    <div id="maintModal" class="modal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); backdrop-filter:blur(5px); z-index:9999; align-items:center; justify-content:center;">
        <div style="background:white; padding:2rem; border-radius:20px; max-width:400px; width:90%; box-shadow:0 10px 40px rgba(0,0,0,0.2);">
            <h3 style="margin-bottom:1rem; color:var(--gray-800);">Disable Controller?</h3>
            <p style="font-size:0.9rem; color:var(--gray-600); margin-bottom:1.5rem;">Please provide a reason for the shutdown. This will be displayed to all users on their dashboard.</p>
            
            <label class="input-label" style="display:block; margin-bottom:0.5rem;">Maintenance Reason</label>
            <textarea id="maintNote" class="premium-input" style="width:100%; height:100px; resize:none; padding:10px;" placeholder="e.g. Tank cleaning, routine inspection, relay replacement..."></textarea>
            
            <div style="display:flex; gap:1rem; margin-top:1.5rem;">
                <button onclick="closeMaintModal()" class="btn" style="flex:1; background:var(--gray-100); color:var(--gray-700);">Cancel</button>
                <button onclick="submitMaintToggle()" class="btn" style="flex:1; background:var(--danger-text); color:white;">Disable System</button>
            </div>
        </div>
    </div>

    <script>

        let pendingToggleId = '';

        async function toggleSensor(id, enabled) {
            if (id === 'esp32_controller' && enabled === 0) {
                pendingToggleId = id;
                document.getElementById('maintModal').style.display = 'flex';
                return;
            }
            
            await performToggle(id, enabled);
        }

        function closeMaintModal() {
            document.getElementById('maintModal').style.display = 'none';
            document.getElementById('maintNote').value = '';
        }

        async function submitMaintToggle() {
            const note = document.getElementById('maintNote').value.trim();
            if (!note) {
                alert('Please enter a reason for maintenance.');
                return;
            }
            await performToggle(pendingToggleId, 0, note);
            closeMaintModal();
        }

        async function performToggle(id, enabled, note = null) {
            const formData = new FormData();
            formData.append('action', 'toggle_sensor');
            formData.append('sensor_id', id);
            formData.append('enabled', enabled);
            if (note) formData.append('note', note);

            try {
                const res = await fetch('settings_hardware.php', {
                    method: 'POST',
                    body: formData
                });
                const data = await res.json();
                if (data.success) {
                    location.reload();
                }
            } catch (e) {
                alert('Failed to toggle component.');
            }
        }

        // --- Real-time Hardware Monitoring ---
        const eventSource = new EventSource('api/sse.php');
        let lastEventTime = Date.now();

        eventSource.onmessage = function(e) {
            const data = JSON.parse(e.data);
            lastEventTime = Date.now();
            
            if (data.success) {
                updateHardwareUI(data);
            }
        };

        function updateHardwareUI(data) {
            // 1. Update Main Controller Badge
            const mainBadge = document.getElementById('main-controller-status');
            if (mainBadge) {
                const isOnline = data.system_status === 'online';
                mainBadge.innerHTML = `
                    <span style="font-size: 0.7rem; color: var(--gray-500); font-weight: 700; text-transform: uppercase; display: block; margin-bottom: 0.25rem;">Controller Status</span>
                    <span class="status-badge-premium ${isOnline ? 'online' : 'offline'}">
                        <span class="${isOnline ? 'pulse' : 'dot'}"></span>
                        SYSTEM ${isOnline ? 'ONLINE' : 'OFFLINE'}
                    </span>
                `;
            }

            // 2. Update Table Rows
            if (data.components) {
                Object.keys(data.components).forEach(id => {
                    const cell = document.getElementById(`status-${id}`);
                    if (cell) {
                        const status = data.components[id];
                        // Don't override if manually disabled (server side also handles this but UI should be responsive)
                        if (cell.querySelector('.disabled')) return;

                        cell.innerHTML = `
                            <span class="status-pill ${status}">
                                <span class="dot"></span> ${status.toUpperCase()}
                            </span>
                        `;
                    }
                });
            }
        }

        // Fast offline detection (if SSE stops for > 30s)
        setInterval(() => {
            if (Date.now() - lastEventTime > 30000) {
                updateHardwareUI({ system_status: 'offline', components: {} });
            }
        }, 10000);
    </script>
</body>

</html>
