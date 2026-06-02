<?php
session_start();
include 'includes/session_sync.php';
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

require 'db_connect.php';
// Check for system-wide maintenance (ESP32 Disabled)
$maint_check = $conn->query("SELECT identifier, is_enabled, maintenance_note FROM hardware_recognition");
$hw_states = [];
$is_maintenance = false;
$maint_note = 'General maintenance in progress.';

while($row = $maint_check->fetch_assoc()) {
    $hw_states[$row['identifier']] = $row['is_enabled'];
    if($row['identifier'] == 'esp32_controller' && $row['is_enabled'] == 0) {
        $is_maintenance = true;
        $maint_note = $row['maintenance_note'] ?? $maint_note;
    }
}

// --- NEW: Fetch Monitoring Thresholds & 24h Stability ---
$settings_res = $conn->query("SELECT max_turbidity, max_tds, max_temp FROM monitoring_settings WHERE id = 1");
$settings = $settings_res->fetch_assoc();

// Check 24h Stability
$stability_query = "
    SELECT COUNT(*) as breach_count 
    FROM sensor_data 
    WHERE recorded_at > DATE_SUB(NOW(), INTERVAL 24 HOUR) 
    AND (turbidity > " . ($settings['max_turbidity'] ?? 5.0) . " OR tds > " . ($settings['max_tds'] ?? 500) . ")
";
$stability_res = $conn->query($stability_query);
$is_stable_24h = ($stability_res->fetch_assoc()['breach_count'] == 0);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="assets/vendor/fonts/fonts.css">
    <link rel="stylesheet" href="assets/vendor/fontawesome/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <!-- Custom Design -->
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/dashboard_new.css">
    <script src="assets/js/common.js"></script>

    <style>
        .sensors-row {
            grid-column: 1 / -1;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 1.25rem;
        }

        /* --- EMERGENCY GLOW --- */
        @keyframes pulse-emergency {
            0% { box-shadow: inset 0 0 0 0px rgba(239, 68, 68, 0.4); }
            50% { box-shadow: inset 0 0 60px 15px rgba(239, 68, 68, 0.5); }
            100% { box-shadow: inset 0 0 0 0px rgba(239, 68, 68, 0.4); }
        }
        
        .emergency-active {
            animation: pulse-emergency 1.5s infinite;
            border: 4px solid #ef4444 !important;
            transition: all 0.5s ease-in-out;
        }

        .emergency-banner {
            background: linear-gradient(135deg, #ef4444, #991b1b) !important;
            border: 2px solid #fca5a5 !important;
            box-shadow: 0 10px 25px rgba(239, 68, 68, 0.4) !important;
        }
        
        .emergency-banner .alert-banner-icon {
            animation: fa-spin 2s linear infinite;
        }

        /* --- MAINTENANCE BANNER --- */
        .maint-banner {
            background: linear-gradient(135deg, #3b82f6, #1d4ed8);
            color: white;
            padding: 1rem 2rem;
            display: flex;
            align-items: center;
            gap: 1.5rem;
            border-radius: 20px;
            margin-bottom: 2rem;
            box-shadow: 0 10px 30px rgba(37, 99, 235, 0.2);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        .maint-icon {
            font-size: 2rem;
            opacity: 0.9;
        }
        .maint-content {
            flex: 1;
        }
        .maint-title {
            font-weight: 800;
            font-size: 1.1rem;
            display: block;
            margin-bottom: 0.25rem;
            letter-spacing: -0.02em;
        }
        .maint-msg {
            font-size: 0.9rem;
            opacity: 0.9;
            font-weight: 500;
        }

        /* --- COMPONENT DISABLED OVERLAY --- */
        .card-premium, .tank-section, .pump-control-card {
            position: relative;
        }
        
        .disabled-overlay {
            position: absolute;
            top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(241, 245, 249, 0.7);
            backdrop-filter: blur(2px);
            z-index: 10;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: inherit;
            border: 2px dashed var(--gray-300);
            pointer-events: all;
        }

        .disabled-badge {
            background: var(--gray-800);
            color: white;
            padding: 0.4rem 0.8rem;
            border-radius: 8px;
            font-size: 0.7rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            display: flex;
            align-items: center;
            gap: 0.4rem;
        }
    </style>
</head>

<body class="web-dashboard-body">

    <div class="dashboard-container">
        <?php include 'includes/sidebar.php'; ?>
        <div class="sidebar-overlay" id="sidebarOverlay" onclick="toggleSidebar()"></div>

        <main class="main-content">

            <?php if ($is_maintenance): ?>
            <!-- System Maintenance Banner -->
            <div class="maint-banner">
                <div class="maint-icon">
                    <i class="fas fa-tools"></i>
                </div>
                <div class="maint-content">
                    <span class="maint-title">SYSTEM MAINTENANCE IN PROGRESS</span>
                    <span class="maint-msg">
                        The administrator has temporarily disabled the controller. 
                        <strong>Reason:</strong> <?= htmlspecialchars($maint_note) ?>
                    </span>
                </div>
                <div style="background: rgba(255, 255, 255, 0.15); padding: 0.5rem 1rem; border-radius: 12px; font-size: 0.75rem; font-weight: 700; text-transform: uppercase;">
                    Automation Locked
                </div>
            </div>
            <?php endif; ?>

            <!-- Dashboard Header -->
            <header class="dashboard-header-wide">
                <div class="main-header-welcome">
                    <div style="display: flex; align-items: center; gap: 1rem; margin-bottom: 0.5rem;">
                        <button class="mobile-toggle" onclick="toggleSidebar()">
                            <i class="fas fa-bars"></i>
                        </button>
                        <h1 class="welcome-title" style="margin-bottom: 0;">Welcome back,
                            <?php echo isset($_SESSION['username']) ? explode(' ', trim($_SESSION['username']))[0] : 'User'; ?>!
                        </h1>
                    </div>
                    <p class="welcome-subtitle">Fresh day, fresh water. Let's see how things are looking!</p>
                </div>

                    <?php include 'includes/header_user.php'; ?>
            </header>

            <!-- Critical Alert Banner -->
            <div id="critical-banner" class="critical-alert-banner">
                <i class="fas fa-exclamation-triangle alert-banner-icon"></i>
                <div class="alert-banner-content">
                    <span class="alert-banner-title">SYSTEM ALERT DETECTED</span>
                    <span id="banner-msg" class="alert-banner-msg">One or more parameters are outside safe monitoring
                        thresholds.</span>
                </div>
                <button class="glass"
                    style="padding: 0.5rem 1rem; border-radius: 8px; font-weight: 700; color: #ef4444; border: 1px solid #fee2e2;"
                    onclick="dismissEmergency()">Dismiss</button>
            </div>

            <!-- Main Dashboard Grid -->
            <div class="dashboard-grid">

                <!-- ROW 0: Water Health Overview -->
                <div class="health-overview health-safe" id="health-overview">
                    <div class="health-status-icon" id="health-icon">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div class="health-main-info">
                        <span class="health-status-label">Current Water Health</span>
                        <div class="health-status-text" id="health-text">Analyzing System...</div>
                        <div class="health-status-desc" id="health-desc">Please wait while we calibrate sensor data.</div>
                    </div>
                    <div class="health-metrics-side">
                        <div class="metric-pill">
                            <span class="metric-pill-label">24h Stability</span>
                            <span class="stability-badge stability-high" id="stability-status">Excellent</span>
                        </div>
                        <div class="metric-pill">
                            <span class="metric-pill-label">Overall Score</span>
                            <span class="metric-pill-value" id="health-score-val">--%</span>
                        </div>
                    </div>
                </div>

                <!-- ROW 1: SENSOR CARDS (Now 3: Turbidity, TDS, Temp) -->
                <div class="sensors-row">
                    <!-- Turbidity -->
                    <div class="card-premium glass">
                        <?php if(!($hw_states['turbidity_sensor'] ?? 1)): ?>
                        <div class="disabled-overlay">
                            <span class="disabled-badge"><i class="fas fa-power-off"></i> Disabled</span>
                        </div>
                        <?php endif; ?>
                        <div class="card-accent turbidity"></div>
                        <div class="card-header">
                            <span class="card-label">Turbidity</span>
                            <div class="card-icon" style="background: rgba(14, 165, 233, 0.1); color: var(--primary);">
                                <i class="fas fa-water"></i>
                            </div>
                        </div>
                        <span class="sensor-status-badge badge-safe" id="turbidity-badge">Clear</span>
                        <div class="card-value-large" id="turbidity">--</div>
                        <div class="metric-unit">NTU</div>
                    </div>

                    <!-- TDS -->
                    <div class="card-premium glass">
                        <?php if(!($hw_states['tds_sensor'] ?? 1)): ?>
                        <div class="disabled-overlay">
                            <span class="disabled-badge"><i class="fas fa-power-off"></i> Disabled</span>
                        </div>
                        <?php endif; ?>
                        <div class="card-accent tds"></div>
                        <div class="card-header">
                            <span class="card-label">TDS</span>
                            <div class="card-icon" style="background: rgba(34, 197, 94, 0.1); color: var(--success);"><i
                                    class="fas fa-leaf"></i></div>
                        </div>
                        <span class="sensor-status-badge badge-safe" id="tds-badge">Pure</span>
                        <div class="card-value-large" id="tds">--</div>
                        <div class="metric-unit">PPM</div>
                    </div>

                    <!-- Temp -->
                    <div class="card-premium glass">
                        <?php if(!($hw_states['temp_sensor'] ?? 1)): ?>
                        <div class="disabled-overlay">
                            <span class="disabled-badge"><i class="fas fa-power-off"></i> Disabled</span>
                        </div>
                        <?php endif; ?>
                        <div class="card-accent temp"></div>
                        <div class="card-header">
                            <span class="card-label">Temperature</span>
                            <div class="card-icon" style="background: rgba(239, 68, 68, 0.1); color: var(--danger);"><i
                                    class="fas fa-thermometer-half"></i></div>
                        </div>
                        <span class="sensor-status-badge badge-safe" id="temp-badge">Normal</span>
                        <div class="card-value-large" id="temperature">--</div>
                        <div class="metric-unit">°C</div>
                    </div>
                </div>

                <!-- ROW 2: TANK, PUMP, LOGS -->

                <!-- Water Tank Visualization -->
                <div class="tank-section glass">
                    <?php if(!($hw_states['ultrasonic_sensor'] ?? 1)): ?>
                    <div class="disabled-overlay">
                        <span class="disabled-badge"><i class="fas fa-power-off"></i> Disabled</span>
                    </div>
                    <?php endif; ?>
                    <span class="card-label" style="margin-bottom: 1rem;">Storage Level</span>
                    <div class="tank-container">
                        <div class="tank-percentage" id="tank-percent">0%</div>
                        <div class="water" id="water-level"></div>
                    </div>
                    <p style="margin-top: 0.75rem; font-size: 0.9rem; font-weight: 700; color: var(--text-muted);">
                        Distance: <span id="distance-cm">--</span> cm
                    </p>
                    <p style="margin-top: 0.75rem; font-size: 0.85rem; font-weight: 700; color: var(--text-muted);"
                        id="tank-status">Syncing...</p>
                </div>


                <!-- Water Quality Rating Card -->
                <div class="card-premium glass" style="grid-column: span 1;">
                    <div class="card-accent" style="background: linear-gradient(135deg, #10b981, #6ee7b7);"></div>
                    <div class="card-header">
                        <span class="card-label">Water Quality</span>
                        <div class="card-icon" style="background: rgba(16, 185, 129, 0.1); color: #10b981;">
                            <i class="fas fa-droplet"></i>
                        </div>
                    </div>
                    <div style="margin: 1rem 0;">
                        <div style="font-size: 2.5rem; font-weight: 800; margin-bottom: 0.5rem;" id="quality-score">--</div>
                        <div class="metric-unit" id="quality-status" style="font-size: 1rem; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 1rem;">Analyzing...</div>
                        <div id="quality-indicator" style="width: 100%; height: 6px; background: rgba(255,255,255,0.1); border-radius: 3px; overflow: hidden;">
                            <div style="width: 0%; height: 100%; background: linear-gradient(90deg, #10b981, #06b6d4); transition: width 0.3s ease;" id="quality-bar"></div>
                        </div>
                    </div>
                    <p style="font-size: 0.8rem; color: var(--text-muted); margin-top: 0.75rem; margin-bottom: 0;" id="quality-recommendation">Waiting for data...</p>
                </div>

                <!-- System Logs Section -->
                <div class="logs-section glass">
                    <div class="logs-header">
                        <h3 class="card-label">Recent Activity</h3>
                        <a href="historical.php"
                            style="font-size: 0.75rem; color: var(--primary); font-weight: 700; text-decoration: none;">View
                            All</a>
                    </div>
                    <div class="logs-list" id="logs-list">
                        <!-- Logs populated via JS -->
                        <div style="text-align: center; padding: 2rem; color: var(--text-muted); opacity: 0.5;">
                            <i class="fas fa-history"></i> No recent logs found.
                        </div>
                    </div>
                </div>

            </div>

            <!-- Notification Toasts Container -->
            <div id="toast-container" class="toast-container"></div>

        </main>
    </div>

    <script>

        // System Initialization
        let pumpMode = 'auto';
        let lastAlertId = 0;
        let isBannerDismissed = false;
        let lastSseEvent = Date.now();
        let missedHeartbeats = 0;
        const MAX_MISSED_HEARTBEATS = 3; 
        const OFFLINE_TIMEOUT_MS = 20000; // Fast 20s detection
        const ESP32_IP = "192.168.43.248"; // Direct IP for Zero-Latency Control

        // --- NEW: Dynamic Thresholds from DB ---
        const thresholds = {
            turbidity: <?= json_encode(floatval($settings['max_turbidity'] ?? 5.0)) ?>,
            tds: <?= json_encode(floatval($settings['max_tds'] ?? 500)) ?>,
            temp: <?= json_encode(floatval($settings['max_temp'] ?? 35.0)) ?>,
            isStable24h: <?= json_encode($is_stable_24h) ?>
        };

        function isNoSensorData(data) {
            return Number(data.turbidity) === 0
                && Number(data.tds) === 0
                && Number(data.temperature) === 0
                && Number(data.distance_cm) === 0
                && Number(data.water_level) === 0
                && (data.ph === null || Number(data.ph) === 0 || Number(data.ph) === 7)
                && (data.chlorine === null || Number(data.chlorine) === 0);
        }

        let reconnectingTimer = null;

        function markDisconnected(reason = 'Connection lost') {
            const connStatus = document.getElementById("global-connection-status");
            const sysStatus = document.getElementById("global-sys-status");
            
            // If we're already offline, don't do anything
            if (connStatus && connStatus.textContent === "System Offline") return;

            // Enter "Reconnecting" phase first
            if (connStatus) {
                connStatus.innerHTML = '<i class="fas fa-sync fa-spin"></i> Reconnecting...';
                connStatus.style.color = "#f59e0b";
            }
            if (sysStatus) sysStatus.className = "status-indicator status-warning";

            // Dim the sensor values to show they are stale
            document.querySelectorAll('.card-premium').forEach(card => {
                card.style.opacity = '0.5';
                card.style.filter = 'grayscale(0.5)';
            });

            // Only mark as fully OFFLINE after 15 seconds of sustained silence
            if (reconnectingTimer) clearTimeout(reconnectingTimer);
            reconnectingTimer = setTimeout(() => {
                if (Date.now() - lastSseEvent > OFFLINE_TIMEOUT_MS) {
                    updateValue("turbidity", '--');
                    updateValue("tds", '--');
                    updateValue("temperature", '--');
                    updateValue("distance-cm", '--');
                    updateTank(0);
                    
                    if (connStatus) {
                        connStatus.textContent = "System Offline";
                        connStatus.style.color = "var(--danger)";
                    }
                    if (sysStatus) sysStatus.className = "status-indicator status-inactive";
                    showQualityUnavailable("System Offline");
                }
            }, 15000);
        }

        function calculateQualityScore(data) {
            const turbidity = parseFloat(data.turbidity) || 0;
            const tds = parseFloat(data.tds) || 0;
            
            const max_turbidity = thresholds.turbidity || 800;
            const max_tds = thresholds.tds || 500;

            const calcScore = (val, max) => {
                if (val <= 0) return 100;
                if (val <= max) return 100 - ((val / max) * 25);
                return Math.max(0, 75 - (((val - max) / (max * 0.5)) * 75)); // Faster drop when over limit
            };

            const turbidity_score = calcScore(turbidity, max_turbidity);
            const tds_score = calcScore(tds, max_tds);
            
            // WEAKEST LINK: The system is only as safe as its dirtiest sensor
            return Math.round(Math.min(turbidity_score, tds_score));
        }

        function updateQualityUI(data) {
            const score = calculateQualityScore(data);
            const scoreEl = document.getElementById('quality-score');
            const statusEl = document.getElementById('quality-status');
            const barEl = document.getElementById('quality-bar');
            const recEl = document.getElementById('quality-recommendation');
            
            // Health Overview Elements
            const hOverview = document.getElementById('health-overview');
            const hIcon = document.getElementById('health-icon');
            const hText = document.getElementById('health-text');
            const hDesc = document.getElementById('health-desc');
            const hScoreVal = document.getElementById('health-score-val');
            const hStability = document.getElementById('stability-status');

            if (!scoreEl) return;

            // Check if sensors are "On Air" (Low readings or zero while online)
            const isOnAir = (data.turbidity < 0.1 && data.tds < 1);

            scoreEl.textContent = score + '%';
            if (hScoreVal) hScoreVal.textContent = score + '%';
            barEl.style.width = score + '%';

            // Reset classes
            hOverview.className = 'health-overview';

            // Update Stability Badge
            if (hStability) {
                hStability.className = 'stability-badge';
                if (thresholds.isStable24h) {
                    hStability.textContent = 'Excellent';
                    hStability.classList.add('stability-high');
                } else {
                    hStability.textContent = 'Variable';
                    hStability.classList.add('stability-low');
                }
            }

            const isVeryDirty = (parseFloat(data.turbidity) > 180);

            if (isOnAir) {
                hOverview.classList.add('health-warning');
                hIcon.innerHTML = '<i class="fas fa-wind"></i>';
                hText.textContent = 'SENSORS ON AIR';
                hDesc.textContent = 'The system is online but sensors appear to be out of water. Place them in water for accurate analysis.';
                recEl.textContent = 'Place sensors in the tank to begin monitoring.';
            } else if (score >= 90) {
                const isOptimal = score >= 98;
                statusEl.textContent = isOptimal ? 'Distilled / Pure' : 'Safe Drinking Water';
                statusEl.style.color = '#10b981';
                barEl.style.background = 'linear-gradient(90deg, #10b981, #34d399)';
                recEl.textContent = 'Meets institutional safety standards. Water is safe for consumption.';
                
                hOverview.classList.add('health-safe');
                hIcon.innerHTML = '<i class="fas fa-check-circle"></i>';
                hText.textContent = 'Water is SAFE to consume';
                hDesc.textContent = 'All monitored parameters are within institutional safety limits.';
            } else if (score >= 70) {
                statusEl.textContent = 'Stable (Safe)';
                statusEl.style.color = '#3b82f6';
                barEl.style.background = 'linear-gradient(90deg, #3b82f6, #60a5fa)';
                recEl.textContent = 'Acceptable for most uses. Monitoring recommended.';
                
                hOverview.classList.add('health-safe');
                hIcon.innerHTML = '<i class="fas fa-check-circle"></i>';
                hText.textContent = 'Water is SAFE (Stable)';
                hDesc.textContent = 'Quality is acceptable, but some parameters are slightly elevated.';
            } else if (score >= 40 && !isVeryDirty) {
                statusEl.textContent = 'Fair / Treatment Needed';
                statusEl.style.color = '#f59e0b';
                barEl.style.background = 'linear-gradient(90deg, #f59e0b, #fbbf24)';
                recEl.textContent = 'Water quality is declining. Advice: Check filtration unit or start a treatment cycle.';
                
                hOverview.classList.add('health-warning');
                hIcon.innerHTML = '<i class="fas fa-exclamation-circle"></i>';
                hText.textContent = 'Water is SLIGHTLY DIRTY';
                hDesc.textContent = 'Warning: Water quality has dropped below optimal levels.';
            } else {
                statusEl.textContent = 'Very Dirty / Unsafe';
                statusEl.style.color = '#ef4444';
                barEl.style.background = 'linear-gradient(90deg, #ef4444, #f87171)';
                recEl.textContent = 'CRITICAL: High level of impurities! Advice: Initiate emergency chlorination and do not consume.';
                
                hOverview.classList.add('health-danger');
                hIcon.innerHTML = '<i class="fas fa-exclamation-triangle"></i>';
                hText.textContent = 'Water is VERY DIRTY';
                hDesc.textContent = 'CRITICAL: Immediate treatment required. DO NOT CONSUME.';
            }

            // Update individual sensor badges and card pulses
            updateSensorBadges(data);
        }

        function updateSensorBadges(data) {
            const turbidity = parseFloat(data.turbidity) || 0;
            const tds = parseFloat(data.tds) || 0;
            const temp = parseFloat(data.temperature) || 0;

            // Turbidity Badge
            const turbBadge = document.getElementById('turbidity-badge');
            const turbCard = turbBadge ? turbBadge.closest('.card-premium') : null;
            if (turbBadge) {
                turbBadge.className = 'sensor-status-badge';
                if (turbCard) turbCard.classList.remove('card-pulse-warning', 'card-pulse-danger');

                if (turbidity < 0.1 && tds < 1) {
                    turbBadge.textContent = 'AIR / DRY';
                    turbBadge.classList.add('badge-warning');
                } else if (turbidity <= (thresholds.turbidity * 0.2)) {
                    turbBadge.textContent = 'Crystal Clear';
                    turbBadge.classList.add('badge-safe');
                } else if (turbidity <= thresholds.turbidity) {
                    turbBadge.textContent = 'Safe Range';
                    turbBadge.classList.add('badge-safe');
                } else if (turbidity <= (thresholds.turbidity * 2)) {
                    turbBadge.textContent = 'Slightly Cloudy';
                    turbBadge.classList.add('badge-warning');
                    if (turbCard) turbCard.classList.add('card-pulse-warning');
                } else {
                    turbBadge.textContent = 'Dirty / Unsafe';
                    turbBadge.classList.add('badge-danger');
                    if (turbCard) turbCard.classList.add('card-pulse-danger');
                }
            }

            // TDS Badge
            const tdsBadge = document.getElementById('tds-badge');
            const tdsCard = tdsBadge ? tdsBadge.closest('.card-premium') : null;
            if (tdsBadge) {
                tdsBadge.className = 'sensor-status-badge';
                if (tdsCard) tdsCard.classList.remove('card-pulse-warning', 'card-pulse-danger');

                if (tds < 1 && turbidity < 0.1) {
                    tdsBadge.textContent = 'AIR / DRY';
                    tdsBadge.classList.add('badge-warning');
                } else if (tds <= (thresholds.tds * 0.2)) {
                    tdsBadge.textContent = 'Pure / Distilled';
                    tdsBadge.classList.add('badge-safe');
                } else if (tds <= thresholds.tds) {
                    tdsBadge.textContent = 'Good Minerals';
                    tdsBadge.classList.add('badge-safe');
                } else if (tds <= (thresholds.tds * 2)) {
                    tdsBadge.textContent = 'Hard Water';
                    tdsBadge.classList.add('badge-warning');
                    if (tdsCard) tdsCard.classList.add('card-pulse-warning');
                } else {
                    tdsBadge.textContent = 'Contaminated';
                    tdsBadge.classList.add('badge-danger');
                    if (tdsCard) tdsCard.classList.add('card-pulse-danger');
                }
            }

            // Temp Badge
            const tempBadge = document.getElementById('temp-badge');
            const tempCard = tempBadge ? tempBadge.closest('.card-premium') : null;
            if (tempBadge) {
                tempBadge.className = 'sensor-status-badge';
                if (tempCard) tempCard.classList.remove('card-pulse-warning', 'card-pulse-danger');

                if (temp <= 0) {
                    tempBadge.textContent = 'Freezing';
                    tempBadge.classList.add('badge-warning');
                } else if (temp <= thresholds.temp) {
                    tempBadge.textContent = 'Normal';
                    tempBadge.classList.add('badge-safe');
                } else if (temp <= (thresholds.temp + 5)) {
                    tempBadge.textContent = 'Warm';
                    tempBadge.classList.add('badge-warning');
                    if (tempCard) tempCard.classList.add('card-pulse-warning');
                } else {
                    tempBadge.textContent = 'Very Hot';
                    tempBadge.classList.add('badge-danger');
                    if (tempCard) tempCard.classList.add('card-pulse-danger');
                }
            }
        }

        function showToast(message, severity = 'info') {
            const container = document.getElementById('toast-container');
            const toast = document.createElement('div');
            toast.className = `web-toast ${severity}`;

            let icon = 'fa-info-circle';
            if (severity === 'critical') icon = 'fa-exclamation-triangle';
            else if (severity === 'warning') icon = 'fa-exclamation-circle';
            else if (severity === 'success') icon = 'fa-check-circle';

            toast.innerHTML = `
                <i class="fas ${icon}" style="font-size: 1.25rem;"></i>
                <div style="flex:1">
                    <div style="font-weight:800; font-size:0.8rem; text-transform:uppercase; opacity:0.6; margin-bottom:2px;">${severity}</div>
                    <div style="font-weight:700; font-size:0.9rem; line-height:1.3;">${message}</div>
                </div>
            `;
            container.appendChild(toast);

            setTimeout(() => {
                toast.style.opacity = '0';
                toast.style.transform = 'translateX(20px)';
                setTimeout(() => toast.remove(), 300);
            }, 5000);
        }

        function updateValue(id, val) {
            const el = document.getElementById(id);
            if (el && el.textContent != val) {
                el.textContent = val;
            }
        }

        function updateTank(level) {
            const water = document.getElementById("water-level");
            const percent = document.getElementById("tank-percent");
            const status = document.getElementById("tank-status");

            const normalizedLevel = Math.max(0, Math.min(100, level));
            if (water) water.style.height = normalizedLevel + "%";
            if (percent) percent.textContent = Math.round(normalizedLevel) + "%";

            if (status) {
                if (normalizedLevel <= 0) {
                    status.textContent = "No water detected";
                } else if (normalizedLevel > 80) {
                    status.textContent = "Almost Full";
                } else if (normalizedLevel > 30) {
                    status.textContent = "Normal Storage";
                } else {
                    status.textContent = "Low Level Alert";
                }
            }
        }

        function computeWaterLevelFromDistance(distanceCm) {
            const TANK_EMPTY_CM = 30.0;
            const TANK_FULL_CM = 5.0;
            if (!Number.isFinite(distanceCm) || distanceCm <= 0) {
                return 0;
            }
            const level = ((TANK_EMPTY_CM - distanceCm) / (TANK_EMPTY_CM - TANK_FULL_CM)) * 100;
            return Math.max(0, Math.min(100, level));
        }

        function showQualityUnavailable(reason = 'No water or sensor offline') {
            document.getElementById("quality-score").textContent = '--';
            const statusEl = document.getElementById("quality-status");
            const barEl = document.getElementById("quality-bar");
            statusEl.textContent = reason;
            statusEl.style.color = '#9ca3af';
            barEl.style.width = '0%';
            barEl.style.background = 'rgba(255,255,255,0.15)';
            document.getElementById("quality-recommendation").textContent = 'Quality analysis is unavailable until sensors are connected and water is present.';
        }

        function formatTemp(temp) {
            return temp === -127 ? '-127' : temp.toFixed(1);
        }

        async function updateDashboard(data) {
            missedHeartbeats = 0; // Reset counter on fresh data
            if (reconnectingTimer) {
                clearTimeout(reconnectingTimer);
                reconnectingTimer = null;
                // Restore visibility
                document.querySelectorAll('.card-premium').forEach(card => {
                    card.style.opacity = '1';
                    card.style.filter = 'none';
                });
            }
            
            try {
                const isSensorOffline = data.temperature === -127;
                const isNoDataPayload = isNoSensorData(data);
                const isSystemOnline = data.system_status === 'online';

                if (!isSystemOnline) {
                    markDisconnected(isSensorOffline ? 'Sensor disconnected' : 'System Offline');
                    return;
                }

                // If system is online but we have no sensor data, we show zeros but stay ONLINE
                if (isSystemOnline && (isSensorOffline || isNoDataPayload)) {
                    updateValue("turbidity", '0.00');
                    updateValue("tds", '0');
                    updateValue("temperature", '0.0');
                    updateValue("distance-cm", '0.0');
                    updateTank(0);
                    
                    const connStatus = document.getElementById("global-connection-status");
                    if (connStatus) {
                        connStatus.textContent = "System Online";
                        connStatus.style.color = "#22c55e";
                    }
                    const sysStatus = document.getElementById("global-sys-status");
                    if (sysStatus) sysStatus.className = "status-indicator status-active";
                    
                    showQualityUnavailable('Waiting for sensors...');
                    return;
                }

                updateValue("turbidity", data.turbidity.toFixed(2));
                updateValue("tds", data.tds);
                updateValue("temperature", formatTemp(data.temperature));
                updateValue("distance-cm", Number.isFinite(data.distance_cm) ? data.distance_cm.toFixed(1) : '--');

                const rawWaterLevel = Number.isFinite(data.water_level) ? data.water_level : 0;
                const fallbackTankLevel = Number.isFinite(data.distance_cm) && data.distance_cm > 0 ? computeWaterLevelFromDistance(data.distance_cm) : 0;
                const tankLevel = rawWaterLevel > 0 ? rawWaterLevel : fallbackTankLevel;
                const hasWaterLevel = tankLevel > 0;

                updateTank(tankLevel);

                if (!hasWaterLevel) {
                    document.getElementById("connection-status").textContent = "No water detected";
                    document.getElementById("sys-status").className = "status-indicator status-warning";
                    showQualityUnavailable('No water in tank');
                    updateTank(data.water_level);
                    updateQualityUI(data);

                    const connStatus = document.getElementById("global-connection-status");
                    if (connStatus) {
                        connStatus.textContent = "System Online";
                        connStatus.style.color = "#22c55e";
                    }
                    const sysStatus = document.getElementById("global-sys-status");
                    if (sysStatus) sysStatus.className = "status-indicator status-active";
                    return;
                }

                const connStatus = document.getElementById("global-connection-status");
                if (connStatus) {
                    connStatus.textContent = "System Online";
                    connStatus.style.color = "#22c55e";
                }
                const sysStatus = document.getElementById("global-sys-status");
                if (sysStatus) sysStatus.className = "status-indicator status-active";

                updateQualityUI(data);

                // Update Pump Status UI from actual hardware state
                const pumpState = document.getElementById('pumpState');
                const doseInfo = document.getElementById('dose-info'); // We will add this ID to the UI

                if (pumpState) {
                    if (data.pump_status === 1) {
                        pumpState.innerHTML = '<i class="fas fa-cog fa-spin"></i> RUNNING';
                        pumpState.style.color = '#22c55e';
                        
                        // Show live runtime and dosage info
                        if (doseInfo) {
                            doseInfo.style.display = 'block';
                            doseInfo.innerHTML = `
                                <div style="margin-top: 5px; font-size: 0.8rem; color: #10b981; font-weight: 700;">
                                    Runtime: ${data.current_runtime_sec}s | Target: ${data.last_dose_ml}ml
                                </div>
                            `;
                        }
                    } else {
                        pumpState.innerHTML = 'STOPPED';
                        pumpState.style.color = 'rgba(255,255,255,0.6)';
                        if (doseInfo) doseInfo.style.display = 'none';
                    }

                    // Update UI Buttons based on mode from SSE
                    if (data.system_mode) {
                        applyModeUI(data.system_mode);
                    }
                }

                // checkAlerts(); // Redundant, handled by interval and sse header sync
            } catch (error) {
                console.error("Dashboard update failed:", error);
                const connStatus = document.getElementById("global-connection-status");
                if (connStatus) {
                    connStatus.textContent = "System Offline";
                    connStatus.style.color = "var(--danger)";
                }
                const sysStatus = document.getElementById("global-sys-status");
                if (sysStatus) sysStatus.className = "status-indicator status-inactive";
            }
        }

        async function updateActivities() {
            try {
                const res = await fetch("api/get_activities.php?limit=8");
                const data = await res.json();
                
                const list = document.getElementById("logs-list");
                if (list && data.success) {
                    if (data.activities && data.activities.length > 0) {
                        list.innerHTML = '';
                        data.activities.forEach(act => {
                            const item = document.createElement('div');
                            item.className = 'log-item';
                            const time = new Date(act.created_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });

                            item.innerHTML = `
                                <div style="display:flex; align-items:flex-start; gap:0.75rem; margin-bottom: 1.25rem;">
                                    <div style="background: ${act.color}15; color: ${act.color}; width: 32px; height: 32px; border-radius: 8px; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                                        <i class="fas ${act.icon}" style="font-size: 0.9rem;"></i>
                                    </div>
                                    <div style="flex:1;">
                                        <div style="font-size:0.85rem; font-weight:700; color:var(--text-main); line-height:1.3;">${act.message}</div>
                                        <div style="font-size:0.7rem; color:var(--text-muted); margin-top:2px;">${time} • ${act.source.toUpperCase()}</div>
                                    </div>
                                </div>
                            `;
                            list.appendChild(item);
                        });
                    } else {
                        list.innerHTML = '<div style="text-align: center; padding: 2rem; color: var(--text-muted); opacity: 0.5;"><i class="fas fa-history"></i> No recent activity found.</div>';
                    }
                }
            } catch (e) {
                console.error("Activities update failed", e);
            }
        }

        async function checkAlerts() {
            try {
                const res = await fetch("api/alerts.php?limit=10");
                const data = await res.json();

                if (data.success) {
                    // Update Bell Count (redundant but kept for sync)
                    const unreadCount = data.count || 0;
                    const bell = document.getElementById("global-alert-count");
                    if (bell) {
                        bell.textContent = unreadCount;
                        bell.style.display = unreadCount > 0 ? 'flex' : 'none';
                    }

                    // Update Banner if critical or emergency
                    const activeAlerts = data.alerts.filter(a => (a.severity === 'critical' || a.severity === 'emergency') && !a.is_read);
                    const banner = document.getElementById("critical-banner");
                    const bannerMsg = document.getElementById("banner-msg");
                    const body = document.body;

                    if (activeAlerts.length > 0 && !isBannerDismissed) {
                        const topAlert = activeAlerts[0];
                        banner.classList.add("active");
                        bannerMsg.textContent = topAlert.message;
                        
                        if (topAlert.severity === 'emergency') {
                            banner.classList.add("emergency-banner");
                            body.classList.add("emergency-active");
                            const titleEl = document.querySelector('.alert-banner-title');
                            if (titleEl) titleEl.textContent = "EMERGENCY INTERVENTION ACTIVE";
                        } else {
                            banner.classList.remove("emergency-banner");
                            body.classList.remove("emergency-active");
                            const titleEl = document.querySelector('.alert-banner-title');
                            if (titleEl) titleEl.textContent = "SYSTEM ALERT DETECTED";
                        }
                    } else {
                        banner.classList.remove("active");
                        banner.classList.remove("emergency-banner");
                        body.classList.remove("emergency-active");
                    }

            // Show Toasts for NEW alerts only
            if (data.alerts && data.alerts.length > 0) {
                const newestAlert = data.alerts[0];
                if (lastAlertId !== 0 && newestAlert.id > lastAlertId) {
                    showToast(newestAlert.message, newestAlert.severity);
                }
                lastAlertId = newestAlert.id;
            }
        }
    } catch (e) {
        console.error("Alerts check failed", e);
    }
}

async function dismissEmergency() {
    try {
        isBannerDismissed = true;
        document.getElementById('critical-banner').classList.remove('active');
        document.body.classList.remove('emergency-active');
        
        // Mark all critical/emergency alerts as read in DB so they don't come back on refresh
        await fetch('api/alerts.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'mark_all_read' })
        });
        
        showToast("Emergency alerts acknowledged", "success");
    } catch (e) {
        console.error("Failed to dismiss alerts", e);
    }
}

        async function fetchWaterQualityRating() {
            try {
                const res = await fetch("api/water_quality_rating.php");
                const response = await res.json();

                if (response.success && response.data) {
                    const rating = response.data;
                    
                    // Update score
                    document.getElementById("quality-score").textContent = rating.score;
                    
                    // Update status with color coding
                    const statusEl = document.getElementById("quality-status");
                    const barEl = document.getElementById("quality-bar");
                    statusEl.textContent = rating.overall_status;
                    
                    // Set color based on status
                    let statusColor = '#10b981'; // SAFE - green
                    let barColor = 'linear-gradient(90deg, #10b981, #06b6d4)';
                    
                    if (rating.overall_status === 'ACCEPTABLE') {
                        statusColor = '#f59e0b'; // warning - amber
                        barColor = 'linear-gradient(90deg, #f59e0b, #fbbf24)';
                    } else if (rating.overall_status === 'POOR') {
                        statusColor = '#ef7f0b'; // warning - orange
                        barColor = 'linear-gradient(90deg, #ef7f0b, #f97316)';
                    } else if (rating.overall_status === 'UNSAFE') {
                        statusColor = '#ef4444'; // danger - red
                        barColor = 'linear-gradient(90deg, #ef4444, #dc2626)';
                    }
                    
                    statusEl.style.color = statusColor;
                    barEl.style.background = barColor;
                    barEl.style.width = rating.score + '%';
                    
                    // Update recommendation
                    document.getElementById("quality-recommendation").textContent = rating.recommendation;
                    
                    console.log("Water Quality Rating:", rating);
                } else {
                    document.getElementById("quality-status").textContent = "ERROR";
                    document.getElementById("quality-recommendation").textContent = "Unable to fetch water quality data";
                }
            } catch (error) {
                console.error("Water quality fetch failed:", error);
                document.getElementById("quality-status").textContent = "ERROR";
            }
        }


        // Helper: apply mode buttons state cleanly
        function applyModeUI(mode) {
            const btnAuto   = document.getElementById('btn-auto');
            const btnManual = document.getElementById('btn-manual');
            const manualBox = document.getElementById('manual-controls');
            if (mode === 'auto') {
                btnAuto.classList.add('btn-mode-active');
                btnManual.classList.remove('btn-mode-active');
                manualBox.style.display = 'none';
            } else {
                btnManual.classList.add('btn-mode-active');
                btnAuto.classList.remove('btn-mode-active');
                manualBox.style.display = 'block';
            }
        }

        async function setMode(mode) {
            console.log('setMode called with:', mode);
            applyModeUI(mode); // Instant visual feedback
            try {
                console.log('Making fetch request...');
                const res = await fetch('api/toggle_pump.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    credentials: 'same-origin',
                    body: JSON.stringify({ action: 'set_mode', mode: mode })
                });
                console.log('Fetch response status:', res.status);
                const result = await res.json();
                console.log('Response data:', result);
                if (result.success) {
                    showToast(`Switched to ${mode.toUpperCase()} mode`, "success");
                } else {
                    showToast(result.error || "Failed to change mode", "danger");
                    // Revert UI on failure
                    fetch('api/latest.php').then(r => r.json()).then(data => {
                        if (data.system_mode) applyModeUI(data.system_mode);
                    });
                }
            } catch (e) {
                console.error('Fetch error:', e);
                showToast("Failed to change mode", "danger");
            }
        }

        async function pumpOn() { 
            try {
                document.getElementById('pumpState').innerHTML = '<i class="fas fa-sync fa-spin"></i> STARTING...';
                document.getElementById('pumpState').style.color = 'var(--primary)';
                const res = await fetch('api/toggle_pump.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    credentials: 'same-origin',
                    body: JSON.stringify({ action: 'pump_control', state: 'on' })
                });
                const result = await res.json();
                if (result.success) {
                    showToast("Pump ON command sent", "success");
                }
            } catch (e) {
                showToast("Failed to send command", "danger");
            }
        }
        
        async function pumpOff() { 
            try {
                document.getElementById('pumpState').innerHTML = '<i class="fas fa-sync fa-spin"></i> STOPPING...';
                document.getElementById('pumpState').style.color = 'rgba(255,255,255,0.6)';
                const res = await fetch('api/toggle_pump.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    credentials: 'same-origin',
                    body: JSON.stringify({ action: 'pump_control', state: 'off' })
                });
                const result = await res.json();
                if (result.success) {
                    showToast("Pump OFF command sent", "success");
                }
            } catch (e) {
                showToast("Failed to send command", "danger");
            }
        }

        // Initialize SSE for real-time updates
        const eventSource = new EventSource('api/sse.php');

        eventSource.onmessage = function(event) {
            lastSseEvent = Date.now();
            const data = JSON.parse(event.data);
            if (data.success) {
                updateDashboard(data);
            } else {
                console.error("SSE error:", data.error);
                markDisconnected('Connection Lost');
            }
        };

        eventSource.onerror = function(event) {
            // SSE automatically attempts to reconnect. 
            // We don't mark offline here immediately to avoid flickers during normal stream refreshes.
            console.warn("SSE stream refreshing...");
        };

        // Initial load
        checkAlerts();
        updateActivities();

        // Keep alerts and activities polling every 5 seconds
        setInterval(checkAlerts, 5000);
        setInterval(updateActivities, 8000); // Activities can be slightly slower

        setInterval(() => {
            if (Date.now() - lastSseEvent > (OFFLINE_TIMEOUT_MS / 2)) {
                missedHeartbeats++;
                console.warn(`Missed heartbeat ${missedHeartbeats}/${MAX_MISSED_HEARTBEATS}`);
                
                if (missedHeartbeats >= MAX_MISSED_HEARTBEATS) {
                    markDisconnected('Heartbeat lost');
                }
            } else {
                missedHeartbeats = 0;
            }
        }, 5000);
    </script>

</body>

</html>
