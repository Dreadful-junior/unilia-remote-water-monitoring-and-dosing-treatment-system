<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['manager', 'admin'])) {
    header("Location: dashboard.php");
    exit();
}

include 'db_connect.php';
include_once 'includes/logger.php';

$msg = "";
$msgType = "success";

/* =====================================================
   REAL DIAGNOSTIC DATA FETCHING
=====================================================*/

// 1. ESP32 Status
$esp_res = $conn->query("SELECT identifier, status, last_seen FROM hardware_recognition WHERE identifier = 'esp32_controller' LIMIT 1");
$esp_data = $esp_res->fetch_assoc();
$is_online = ($esp_data && (time() - strtotime($esp_data['last_seen'])) < 60);

// 1.5 System Settings (Mode, Chemical)
$settings_res = $conn->query("SELECT operation_mode, active_chemical FROM hardware_settings WHERE id = 1");
$hw_settings = $settings_res->fetch_assoc();
$sys_mode = $hw_settings['operation_mode'] ?? 'unknown';
$active_chem = $hw_settings['active_chemical'] ?? 'none';

// 2. Database Health
$db_size_res = $conn->query("SELECT SUM(data_length + index_length) / 1024 / 1024 AS size FROM information_schema.TABLES WHERE table_schema = 'water_system'");
$db_size = round($db_size_res->fetch_assoc()['size'], 2);
$row_count_res = $conn->query("SELECT COUNT(*) as total FROM sensor_data");
$total_rows = $row_count_res->fetch_assoc()['total'];

// 3. Latest Hardware States
$latest_res = $conn->query("SELECT pump_status, recorded_at, turbidity, tds, temperature, water_level FROM sensor_data ORDER BY recorded_at DESC LIMIT 1");
$latest = $latest_res->fetch_assoc();
$pump_active = ($latest && $latest['pump_status'] == 1);
$stale_data = ($latest && (time() - strtotime($latest['recorded_at'])) > 60);

// 4. Sensor Health (Fetch all from hardware_recognition)
$sensors = [];
$hw_rec_res = $conn->query("SELECT component_name, identifier, status, last_seen FROM hardware_recognition WHERE identifier NOT IN ('esp32_controller', 'dosing_pump')");
while ($row = $hw_rec_res->fetch_assoc()) {
    // A sensor is only "OK" if its own status is online AND the main controller is online
    $is_sensor_stale = (strtotime($row['last_seen']) < time() - 60); 
    $is_sensor_active = $is_online && !$is_sensor_stale;
    
    $status_text = 'Offline';
    if ($is_sensor_active && $row['status'] === 'online') {
        $status_text = 'Connected';
    } elseif ($is_online && $is_sensor_stale) {
        $status_text = 'Sensor Timeout';
    } elseif (!$is_online) {
        $status_text = 'Hub Offline';
    }

    $sensors[] = [
        'name' => $row['component_name'],
        'status' => $status_text,
        'is_ok' => ($status_text === 'Connected')
    ];
}

// Fallback if table is empty
if (empty($sensors)) {
    $fallback_status = $is_online ? 'Check Cable' : 'Hub Offline';
    $sensors = [
        ['name' => 'Turbidity Sensor', 'status' => ($is_online && $latest && $latest['turbidity'] > 0) ? 'Connected' : $fallback_status, 'is_ok' => ($is_online && $latest && $latest['turbidity'] > 0)],
        ['name' => 'TDS Sensor', 'status' => ($is_online && $latest && $latest['tds'] > 0) ? 'Connected' : $fallback_status, 'is_ok' => ($is_online && $latest && $latest['tds'] > 0)],
        ['name' => 'Temperature Probe', 'status' => ($is_online && $latest && $latest['temperature'] > 0) ? 'Connected' : $fallback_status, 'is_ok' => ($is_online && $latest && $latest['temperature'] > 0)],
        ['name' => 'Ultrasonic Level', 'status' => ($is_online && $latest && $latest['water_level'] > 0) ? 'Connected' : $fallback_status, 'is_ok' => ($is_online && $latest && $latest['water_level'] > 0)]
    ];
}

if (isset($_GET['msg'])) {
    $msg = htmlspecialchars($_GET['msg']);
    $msgType = isset($_GET['type']) ? htmlspecialchars($_GET['type']) : "success";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Health | UniLi Water Monitoring</title>
    <link rel="stylesheet" href="assets/css/style.css?v=2.2">
    <link rel="stylesheet" href="assets/css/dashboard_new.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .health-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1.5rem;
            margin-top: 1.5rem;
        }

        .status-card-premium {
            background: rgba(255, 255, 255, 0.7);
            backdrop-filter: blur(12px);
            border-radius: 20px;
            padding: 1.5rem;
            border: 1px solid rgba(255, 255, 255, 0.4);
            box-shadow: 0 8px 32px rgba(0,0,0,0.05);
        }

        .diagnostic-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.75rem 0;
            border-bottom: 1px solid rgba(0,0,0,0.05);
        }

        .diagnostic-row:last-child { border-bottom: none; }

        .indicator {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            display: inline-block;
            margin-right: 0.5rem;
        }

        .ind-online { background: #22c55e; box-shadow: 0 0 8px #22c55e; }
        .ind-offline { background: #ef4444; box-shadow: 0 0 8px #ef4444; }
        .ind-warning { background: #f59e0b; box-shadow: 0 0 8px #f59e0b; }

        .health-label { font-weight: 600; color: var(--gray-600); font-size: 0.9rem; }
        .health-value { font-family: 'Courier New', Courier, monospace; font-weight: 700; color: var(--gray-800); }

        .sensor-tag {
            padding: 4px 10px;
            border-radius: 6px;
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
        }

        .tag-ok { background: #dcfce7; color: #166534; }
        .tag-error { background: #fee2e2; color: #991b1b; }
    </style>
</head>
<body>

    <div class="dashboard-container">
        <?php include 'includes/sidebar.php'; ?>

        <main class="main-content">
            <header class="dashboard-header-wide">
                <div class="main-header-welcome">
                    <div style="display: flex; align-items: center; gap: 1rem; margin-bottom: 0.5rem;">
                        <a href="settings.php" class="btn-back" style="text-decoration: none; color: var(--primary); font-size: 1.1rem; display: flex; align-items: center; gap: 0.5rem; background: rgba(14, 165, 233, 0.1); padding: 0.5rem 1rem; border-radius: 12px; transition: all 0.2s; font-weight: 700;">
                            <i class="fas fa-arrow-left"></i>
                            <span>Back</span>
                        </a>
                        <h1 class="welcome-title" style="margin-bottom: 0;">System Health Diagnostics</h1>
                    </div>
                    <p class="welcome-subtitle">
                        <a href="settings.php" style="text-decoration: none; color: inherit; opacity: 0.7;">System Settings</a>
                        <i class="fas fa-chevron-right" style="font-size: 0.7rem; margin: 0 0.5rem; opacity: 0.5;"></i>
                        <span style="font-weight: 600;">System Health</span>
                    </p>
                </div>
                <div style="display: flex; align-items: center; gap: 1rem;">
                    <button class="btn btn-primary" onclick="window.location.reload()">
                        <i class="fas fa-sync-alt"></i> Refresh Diagnostics
                    </button>
                    <?php include 'includes/header_user.php'; ?>
                </div>
            </header>

            <div class="health-grid">
                
                <!-- HARDWARE HEARTBEAT -->
                <div class="status-card-premium">
                    <div style="display: flex; justify-content: space-between; margin-bottom: 1.5rem;">
                        <h3 style="font-size: 1.1rem; color: var(--primary);"><i class="fas fa-microchip"></i> Hardware Heartbeat</h3>
                        <span class="sensor-tag <?= $is_online ? 'tag-ok' : 'tag-error' ?>">
                            <?= $is_online ? 'Online' : 'Offline' ?>
                        </span>
                    </div>
                    
                    <div class="diagnostic-row">
                        <span class="health-label">Controller ID</span>
                        <span class="health-value" id="diag-controller-id"><?= $esp_data['identifier'] ?? 'ESP32_WF_01' ?></span>
                    </div>
                    <div class="diagnostic-row">
                        <span class="health-label">Last Communication</span>
                        <span class="health-value" id="diag-last-seen"><?= $esp_data ? date('H:i:s', strtotime($esp_data['last_seen'])) : 'Never' ?></span>
                    </div>
                    <div class="diagnostic-row">
                        <span class="health-label">Connection Pulse</span>
                        <span class="health-value" id="diag-pulse" style="color: <?= $is_online ? '#22c55e' : '#ef4444' ?>">
                            <i class="fas fa-signal"></i> <?= $is_online ? 'Stable' : 'Lost' ?>
                        </span>
                    </div>
                </div>

                <!-- OPERATIONAL STATUS -->
                <div class="status-card-premium">
                    <div style="display: flex; justify-content: space-between; margin-bottom: 1.5rem;">
                        <h3 style="font-size: 1.1rem; color: var(--primary);"><i class="fas fa-cog"></i> Operation Status</h3>
                    </div>
                    
                    <div class="diagnostic-row">
                        <span class="health-label">Dosing Pump</span>
                        <span class="health-value" id="diag-pump-status">
                            <span class="indicator <?= $pump_active ? 'ind-online' : 'ind-offline' ?>"></span>
                            <?= $pump_active ? 'ACTIVE' : 'IDLE' ?>
                        </span>
                    </div>
                    <div class="diagnostic-row">
                        <span class="health-label">Data Stream</span>
                        <span class="health-value" id="diag-stream-status" style="color: <?= $stale_data ? '#ef4444' : '#22c55e' ?>">
                            <?= $stale_data ? 'STALE' : 'LIVE' ?>
                        </span>
                    </div>
                    <div class="diagnostic-row">
                        <span class="health-label">System Mode</span>
                        <span class="health-value" id="diag-sys-mode"><?= strtoupper($sys_mode) ?></span>
                    </div>
                    <div class="diagnostic-row">
                        <span class="health-label">Active Chemical</span>
                        <span class="health-value"><?= strtoupper($active_chem) ?></span>
                    </div>
                </div>

                <!-- DATABASE HEALTH -->
                <div class="status-card-premium">
                    <div style="display: flex; justify-content: space-between; margin-bottom: 1.5rem;">
                        <h3 style="font-size: 1.1rem; color: var(--primary);"><i class="fas fa-database"></i> Storage Health</h3>
                        <span class="sensor-tag tag-ok">Healthy</span>
                    </div>
                    
                    <div class="diagnostic-row">
                        <span class="health-label">Database Size</span>
                        <span class="health-value"><?= $db_size ?> MB</span>
                    </div>
                    <div class="diagnostic-row">
                        <span class="health-label">Total Logs</span>
                        <span class="health-value"><?= number_format($total_rows) ?> Rows</span>
                    </div>
                    <div class="diagnostic-row">
                        <span class="health-label">DB Engine</span>
                        <span class="health-value">MySQL / InnoDB</span>
                    </div>
                </div>

            </div>

            <!-- SENSOR CONNECTIVITY GRID -->
            <div class="status-card-premium" style="margin-top: 2rem;">
                <h3 style="font-size: 1.1rem; color: var(--primary); margin-bottom: 1.5rem;"><i class="fas fa-project-diagram"></i> Sensor Connectivity Grid</h3>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem;" id="sensor-grid">
                    <?php foreach ($sensors as $s): ?>
                        <div style="background: white; padding: 1rem; border-radius: 12px; border: 1px solid var(--gray-100);" data-sensor="<?= $s['id'] ?? '' ?>">
                            <div style="font-size: 0.8rem; color: var(--gray-500); margin-bottom: 0.5rem; font-weight: 700;"><?= $s['name'] ?></div>
                            <div style="display: flex; align-items: center; gap: 0.5rem;">
                                <span class="indicator <?= $s['is_ok'] ? 'ind-online' : 'ind-warning' ?>"></span>
                                <span class="status-text" style="font-weight: 800; font-size: 0.9rem;"><?= $s['status'] ?></span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- EMERGENCY CONTROLS -->
            <div style="margin-top: 2rem; display: flex; gap: 1rem;">
                <a href="export_db.php" class="btn btn-secondary" style="background: white; border: 1px solid var(--gray-200); flex: 1; justify-content: center;">
                    <i class="fas fa-download"></i> Download System Backup
                </a>
                <button class="btn btn-danger" style="flex: 1; justify-content: center; background: #fee2e2; color: #991b1b;" onclick="handleEmergencyReset()">
                    <i class="fas fa-power-off"></i> Hardware Emergency Reset
                </button>
            </div>

            <script>
                async function handleEmergencyReset() {
                    if (!confirm('EMERGENCY RESET: This will force the system into AUTO mode, stop all active pumps, and signal the hardware to reboot. Proceed?')) {
                        return;
                    }
                    
                    try {
                        const res = await fetch('api/toggle_pump.php', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({ action: 'emergency_reset' })
                        });
                        const data = await res.json();
                        if (data.success) {
                            alert('Emergency Reset command sent successfully.');
                        } else {
                            alert('Error: ' + (data.error || 'Failed to send command'));
                        }
                    } catch (e) {
                        alert('Connection error. Please check your network.');
                    }
                }

                // --- Real-time Diagnostics ---
                const sse = new EventSource('api/sse.php');
                let lastHeartbeat = Date.now();

                sse.onmessage = function(e) {
                    const data = JSON.parse(e.data);
                    lastHeartbeat = Date.now();
                    if (data.success) updateDiagnosticsUI(data);
                };

                function updateDiagnosticsUI(data) {
                    const isOnline = data.system_status === 'online';
                    
                    // Update main status tags
                    document.querySelectorAll('.sensor-tag').forEach(tag => {
                        tag.className = 'sensor-tag ' + (isOnline ? 'tag-ok' : 'tag-error');
                        tag.textContent = isOnline ? 'Online' : 'Offline';
                    });

                    // Update Heartbeat Card
                    document.getElementById('diag-last-seen').textContent = new Date().toLocaleTimeString();
                    const pulse = document.getElementById('diag-pulse');
                    pulse.style.color = isOnline ? '#22c55e' : '#ef4444';
                    pulse.innerHTML = `<i class="fas fa-signal"></i> ${isOnline ? 'Stable' : 'Lost'}`;

                    // Update Operation Status
                    const pump = document.getElementById('diag-pump-status');
                    const isActive = data.pump_status === 1;
                    pump.innerHTML = `<span class="indicator ${isActive ? 'ind-online' : 'ind-offline'}"></span> ${isActive ? 'ACTIVE' : 'IDLE'}`;
                    
                    const stream = document.getElementById('diag-stream-status');
                    const isLive = isOnline && data.timestamp;
                    stream.style.color = isLive ? '#22c55e' : '#ef4444';
                    stream.textContent = isLive ? 'LIVE' : 'STALE';

                    document.getElementById('diag-sys-mode').textContent = (data.system_mode || 'AUTO').toUpperCase();

                    // Update Sensor Grid
                    if (data.components) {
                        const gridItems = document.querySelectorAll('#sensor-grid > div');
                        gridItems.forEach(item => {
                            const name = item.querySelector('div').textContent.toLowerCase();
                            let identifier = '';
                            if (name.includes('turbidity')) identifier = 'turbidity_sensor';
                            else if (name.includes('tds')) identifier = 'tds_sensor';
                            else if (name.includes('temperature')) identifier = 'temp_sensor';
                            else if (name.includes('level') || name.includes('ultrasonic')) identifier = 'ultrasonic_sensor';

                            if (identifier && data.components[identifier]) {
                                const status = data.components[identifier];
                                const isOk = isOnline && status === 'online';
                                item.querySelector('.indicator').className = 'indicator ' + (isOk ? 'ind-online' : 'ind-warning');
                                item.querySelector('.status-text').textContent = isOnline ? (status === 'online' ? 'Connected' : 'Sensor Timeout') : 'Hub Offline';
                            }
                        });
                    }
                }

                // Rapid offline detection
                setInterval(() => {
                    if (Date.now() - lastHeartbeat > 20000) {
                        updateDiagnosticsUI({ system_status: 'offline', components: {} });
                    }
                }, 5000);
            </script>

        </main>
    </div>
</body>
</html>
