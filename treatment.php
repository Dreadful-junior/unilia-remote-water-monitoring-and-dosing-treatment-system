<?php
session_start();
include 'includes/session_sync.php';
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

require 'db_connect.php';

// Fetch system mode and active chemical
$settings_res = $conn->query("SELECT operation_mode, active_chemical FROM hardware_settings WHERE id = 1 LIMIT 1");
$active_chemical = 'Chlorine';
$sys_mode = 'auto';

if ($settings_res && $settings_res->num_rows > 0) {
    $settings_row = $settings_res->fetch_assoc();
    $sys_mode        = $settings_row['operation_mode'] ?? 'auto';
    $active_chemical = $settings_row['active_chemical'] ?? 'Chlorine';
}

// Ensure auto scheduling columns exist
$conn->query("ALTER TABLE treatment_settings ADD COLUMN IF NOT EXISTS auto_interval_minutes INT DEFAULT 30");
$conn->query("ALTER TABLE treatment_settings ADD COLUMN IF NOT EXISTS auto_run_duration_sec INT DEFAULT 60");

// Fetch treatment settings for limits and flow rate
$t_res      = $conn->query("SELECT * FROM treatment_settings WHERE id = 1");
$t_settings = $t_res ? $t_res->fetch_assoc() : [];

$db_run_sec       = intval($t_settings['auto_run_duration_sec']  ?? $t_settings['dosing_duration'] ?? 60);
$db_run_min       = max(1, round($db_run_sec / 60, 1));
$db_interval_min  = intval($t_settings['auto_interval_minutes'] ?? 30);
$max_daily_ml     = intval($t_settings['max_daily_dose_ml']     ?? 500);
$flow_rate_ml_min = floatval($t_settings['pump_flow_rate']      ?? 100.0);

$is_admin = isset($_SESSION['role']) && in_array($_SESSION['role'], ['admin', 'manager']);

// Fetch hardware states for safety overlays
$hw_check = $conn->query("SELECT identifier, is_enabled FROM hardware_recognition");
$hw_states = [];
while($row = $hw_check->fetch_assoc()) {
    $hw_states[$row['identifier']] = $row['is_enabled'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Treatment Control | UniLi Remote Water Monitoring System</title>

    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/dashboard_new.css">
    <script src="assets/js/common.js"></script>

    <style>
        .treatment-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1.25rem;
            margin-bottom: 2rem;
        }

        .control-card {
            grid-column: 1 / -1;
            padding: 2.5rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .maintenance-card {
            grid-column: 1 / -1;
            padding: 2rem 2.5rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        /* Timeline Styles */
        .timeline-container {
            grid-column: 1 / -1;
            padding: 2.5rem;
        }

        .timeline {
            position: relative;
            padding-left: 3rem;
            margin-top: 2rem;
        }

        .timeline::before {
            content: '';
            position: absolute;
            left: 11px;
            top: 0;
            bottom: 0;
            width: 2px;
            background: rgba(255, 255, 255, 0.1);
        }

        .timeline-item {
            position: relative;
            margin-bottom: 2.5rem;
        }

        /* --- COMPONENT DISABLED OVERLAY --- */
        .status-card, .pump-panel, .maintenance-card, .timeline-container {
            position: relative;
        }
        
        .disabled-overlay {
            position: absolute;
            top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(241, 245, 249, 0.7);
            backdrop-filter: blur(2px);
            z-index: 100;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: inherit;
            border: 2px dashed var(--gray-300);
            pointer-events: all; /* Block clicks */
        }

        .disabled-badge {
            background: var(--gray-800);
            color: white;
            padding: 0.6rem 1rem;
            border-radius: 10px;
            font-size: 0.8rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .timeline-dot {
            position: absolute;
            left: -33px;
            width: 24px;
            height: 24px;
            border-radius: 50%;
            background: var(--surface-bg);
            border: 2px solid var(--primary);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 1;
            box-shadow: var(--shadow-sm);
        }

        .timeline-dot i { font-size: 0.7rem; color: var(--primary); }
        .timeline-dot.manual { border-color: #f59e0b; }
        .timeline-dot.manual i { color: #f59e0b; }

        .timeline-content {
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(8px);
            padding: 1.5rem;
            border-radius: 16px;
            border: 1px solid rgba(255, 255, 255, 0.1);
            transition: all 0.3s ease;
        }

        .timeline-content:hover {
            transform: translateX(8px);
            background: rgba(255, 255, 255, 0.08);
            border-color: rgba(255, 255, 255, 0.2);
        }

        .timeline-header {
            display: flex;
            justify-content: space-between;
            margin-bottom: 1rem;
        }

        .timeline-title { font-weight: 700; color: var(--text-main); }
        .timeline-time  { font-size: 0.85rem; color: var(--text-muted); font-weight: 600; }

        .timeline-details { display: flex; gap: 2rem; }

        .detail-item { display: flex; flex-direction: column; }
        .detail-label {
            font-size: 0.75rem;
            text-transform: uppercase;
            font-weight: 700;
            color: var(--text-muted);
            letter-spacing: 0.5px;
        }
        .detail-value { font-size: 1.1rem; font-weight: 700; color: var(--primary); }

        /* Maintenance Toggle */
        .switch { position: relative; display: inline-block; width: 50px; height: 28px; }
        .switch input { opacity: 0; width: 0; height: 0; }
        .slider {
            position: absolute; cursor: pointer;
            top: 0; left: 0; right: 0; bottom: 0;
            background-color: rgba(255, 255, 255, 0.1);
            transition: .4s; border-radius: 34px;
        }
        .slider:before {
            position: absolute; content: "";
            height: 20px; width: 20px;
            left: 4px; bottom: 4px;
            background-color: white; transition: .4s; border-radius: 50%;
        }
        input:checked + .slider { background-color: var(--primary); }
        input:checked + .slider:before { transform: translateX(22px); }

        .btn-dose {
            padding: 0.8rem 1.5rem;
            background: var(--primary); color: white;
            border: none; border-radius: 12px;
            font-weight: 700; cursor: pointer;
            transition: all 0.3s; font-size: 0.9rem; letter-spacing: 0.5px;
        }
        .btn-dose:hover { transform: translateY(-2px); box-shadow: 0 8px 20px rgba(14,165,233,0.3); }
        .btn-dose:active { transform: translateY(0); }
        .btn-dose:disabled { opacity: 0.4; cursor: not-allowed; transform: none; }

        .metric-card-premium {
            padding: 1.5rem; border-radius: 20px;
            position: relative; overflow: hidden;
        }

        /* Pump Tech Card Styles */
        .pump-tech-card {
            background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);
            border: 1px solid rgba(0, 0, 0, 0.05);
            border-radius: 24px; padding: 1.5rem;
            position: relative; overflow: hidden;
            display: flex; flex-direction: column; gap: 1.25rem;
            box-shadow: 0 10px 30px rgba(0,0,0,0.03);
            transition: all 0.3s ease;
        }
        .pump-tech-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 45px rgba(0,0,0,0.08);
            border-color: var(--primary);
        }

        .pump-tech-bg-icon {
            position: absolute; right: -10%; top: -10%;
            font-size: 8rem; color: rgba(14,165,233,0.03);
            transform: rotate(-15deg); z-index: 0;
        }

        .pump-header { display: flex; justify-content: space-between; align-items: flex-start; position: relative; z-index: 1; }

        .pump-badge {
            background: rgba(14,165,233,0.1); color: var(--primary);
            padding: 4px 12px; border-radius: 20px;
            font-size: 0.65rem; font-weight: 800;
            text-transform: uppercase; letter-spacing: 0.05em;
        }

        .pump-info-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; position: relative; z-index: 1; }

        .info-pill { display: flex; align-items: center; gap: 0.5rem; font-size: 0.75rem; color: var(--text-muted); font-weight: 600; }
        .info-pill i { color: var(--primary); font-size: 0.8rem; }

        .voltage-display-mini { margin-top: 0.5rem; display: flex; align-items: baseline; gap: 0.25rem; }
        .voltage-val { font-size: 3rem; font-weight: 900; color: var(--text-main); letter-spacing: -0.05em; line-height: 0.9; }
        .voltage-unit { font-size: 1.25rem; font-weight: 800; color: var(--primary); margin-left: 0.1rem; }

        /* Technical Modal */
        .tech-modal-overlay {
            position: fixed; top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(15,23,42,0.6); backdrop-filter: blur(10px);
            z-index: 1000; display: none; align-items: center; justify-content: center;
            opacity: 0; transition: all 0.3s ease;
        }
        .tech-modal-overlay.active { display: flex; opacity: 1; }

        .tech-modal {
            background: white; width: 90%; max-width: 500px;
            border-radius: 28px; padding: 2.5rem; position: relative;
            box-shadow: 0 25px 60px rgba(0,0,0,0.2);
            transform: translateY(20px); transition: all 0.3s ease;
        }
        .tech-modal-overlay.active .tech-modal { transform: translateY(0); }

        .modal-close {
            position: absolute; top: 1.5rem; right: 1.5rem;
            width: 36px; height: 36px; background: #f1f5f9;
            border-radius: 50%; display: flex; align-items: center;
            justify-content: center; cursor: pointer; color: var(--text-muted); transition: all 0.2s;
        }
        .modal-close:hover { background: #ef4444; color: white; }

        .tech-spec-row { display: flex; justify-content: space-between; padding: 0.8rem 0; border-bottom: 1px solid #f1f5f9; }
        .spec-label { font-weight: 600; color: var(--text-muted); font-size: 0.9rem; }
        .spec-value { font-weight: 800; color: var(--text-main); font-size: 0.9rem; }

        /* Manual controls */
        #manual-controls { display: none; width: 100%; gap: 0.5rem; }
        #manual-controls.visible { display: flex !important; }

        /* Save schedule feedback */
        .schedule-saved-badge {
            display: inline-flex; align-items: center; gap: 0.35rem;
            background: rgba(16,185,129,0.12); color: #10b981;
            border: 1px solid rgba(16,185,129,0.3);
            padding: 3px 10px; border-radius: 20px;
            font-size: 0.7rem; font-weight: 800;
            opacity: 0; transition: opacity 0.4s;
        }
        .schedule-saved-badge.show { opacity: 1; }

        /* Toast */
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(12px); }
            to   { opacity: 1; transform: translateY(0); }
        }
    </style>
</head>

<body class="web-dashboard-body">
<div class="dashboard-container">
    <?php include 'includes/sidebar.php'; ?>
    <div class="sidebar-overlay" id="sidebarOverlay" onclick="toggleSidebar()"></div>

    <main class="main-content">
        <header class="dashboard-header-wide">
            <div class="main-header-welcome">
                <div style="display:flex;align-items:center;gap:1rem;margin-bottom:0.5rem;">
                    <button class="mobile-toggle" onclick="toggleSidebar()"><i class="fas fa-bars"></i></button>
                    <h1 class="welcome-title" style="margin-bottom:0;">Treatment Control</h1>
                </div>
                <p class="welcome-subtitle">Manage dosing cycles and system maintenance</p>
            </div>
            <?php include 'includes/header_user.php'; ?>
        </header>

        <div class="treatment-grid">

            <!-- ══ TODAY'S DOSAGE + AUTO SCHEDULE SETTINGS ══ -->
            <div class="metric-card" style="display:flex;flex-direction:column;gap:1rem;">
                <div class="metric-header" style="display:flex;justify-content:space-between;align-items:center;">
                    <span class="metric-title"><i class="fas fa-tint"></i> Today's Dosage</span>
                    <?php if ($is_admin): ?>
                    <button onclick="resetDailyDose()" id="btn-reset-dose"
                        style="background:rgba(239,68,68,0.15);color:#ef4444;border:1px solid rgba(239,68,68,0.3);padding:4px 10px;border-radius:8px;font-size:0.7rem;font-weight:800;cursor:pointer;transition:all 0.2s;text-transform:uppercase;letter-spacing:0.5px;"
                        onmouseover="this.style.background='rgba(239,68,68,0.25)'" onmouseout="this.style.background='rgba(239,68,68,0.15)'">
                        <i class="fas fa-undo"></i> RESET
                    </button>
                    <?php endif; ?>
                </div>
                <div class="metric-value" id="today-volume">0<small class="metric-unit">ml</small></div>

                <!-- Auto Run Duration -->
                <div style="border-top:1px solid rgba(255,255,255,0.08);padding-top:0.75rem;">
                    <div style="font-size:0.7rem;text-transform:uppercase;font-weight:800;color:var(--text-muted);letter-spacing:0.5px;margin-bottom:0.5rem;">
                        <i class="fas fa-stopwatch" style="color:var(--primary);"></i> AUTO RUN DURATION
                    </div>
                    <div style="display:flex;align-items:center;gap:0.5rem;">
                        <input type="number" id="auto-run-duration" min="1" max="120" value="<?= $db_run_min ?>"
                            style="width:60px;padding:4px 6px;border-radius:8px;border:1px solid rgba(255,255,255,0.15);background:rgba(255,255,255,0.05);color:var(--text-main);font-weight:700;font-size:0.9rem;text-align:center;">
                        <span style="font-size:0.8rem;color:var(--text-muted);font-weight:600;">minutes per treatment</span>
                    </div>
                    <div style="font-size:0.7rem;color:var(--text-muted);margin-top:0.3rem;">Pump auto-stops after this time in AUTO mode.</div>
                </div>

                <!-- Auto Interval -->
                <div style="border-top:1px solid rgba(255,255,255,0.08);padding-top:0.75rem;">
                    <div style="font-size:0.7rem;text-transform:uppercase;font-weight:800;color:var(--text-muted);letter-spacing:0.5px;margin-bottom:0.5rem;">
                        <i class="fas fa-redo" style="color:var(--primary);"></i> TREATMENT INTERVAL
                    </div>
                    <div style="display:flex;align-items:center;gap:0.5rem;">
                        <input type="number" id="auto-interval-duration" min="1" max="1440" value="<?= $db_interval_min ?>"
                            style="width:60px;padding:4px 6px;border-radius:8px;border:1px solid rgba(255,255,255,0.15);background:rgba(255,255,255,0.05);color:var(--text-main);font-weight:700;font-size:0.9rem;text-align:center;">
                        <span style="font-size:0.8rem;color:var(--text-muted);font-weight:600;">minutes between treatments</span>
                    </div>
                    <div style="font-size:0.7rem;color:var(--text-muted);margin-top:0.3rem;">
                        Next treatment: <span id="next-treatment-countdown" style="font-weight:700;color:var(--primary);">—</span>
                    </div>
                </div>

                <!-- Save Schedule Button -->
                <div style="border-top:1px solid rgba(255,255,255,0.08);padding-top:0.75rem;display:flex;align-items:center;gap:0.75rem;">
                    <button onclick="saveAutoSchedule()"
                        style="background:var(--primary);color:#fff;border:none;padding:6px 14px;border-radius:8px;font-size:0.75rem;font-weight:800;cursor:pointer;transition:all 0.2s;letter-spacing:0.04em;"
                        onmouseover="this.style.opacity='.85'" onmouseout="this.style.opacity='1'">
                        <i class="fas fa-save"></i> SAVE SCHEDULE
                    </button>
                    <span class="schedule-saved-badge" id="schedule-saved-badge"><i class="fas fa-check"></i> Saved</span>
                </div>
            </div>

            <!-- ══ PUMP TECH CARD ══ -->
            <div class="pump-tech-card">
                <i class="fas fa-microchip pump-tech-bg-icon"></i>
                <div class="pump-header">
                    <div style="display:flex;flex-direction:column;gap:0.25rem;">
                        <span class="pump-badge">Industrial Grade</span>
                        <h3 style="font-weight:800;font-size:1.1rem;color:var(--text-main);margin:0;">Micro Dosing Engine</h3>
                        <span style="font-size:0.75rem;color:var(--text-muted);font-weight:600;">High-Precision Submerged Pump</span>
                    </div>
                    <div class="voltage-display-mini">
                        <span class="voltage-val" id="pump-voltage">5.0</span>
                        <span class="voltage-unit">V</span>
                    </div>
                </div>
                <div style="height:1px;background:rgba(0,0,0,0.05);width:100%;"></div>
                <div class="pump-info-grid">
                    <div class="info-pill"><i class="fas fa-shield-alt"></i><span>Brushless DC Motor</span></div>
                    <div class="info-pill"><i class="fas fa-volume-mute"></i><span>Silent Operation</span></div>
                    <div class="info-pill"><i class="fas fa-water"></i><span>Fully Submersible</span></div>
                    <div class="info-pill"><i class="fas fa-clock"></i><span>24/7 Duty Cycle</span></div>
                </div>
                <div onclick="showPumpTechSpecs()"
                    style="cursor:pointer;background:rgba(14,165,233,0.05);padding:0.75rem;border-radius:12px;font-size:0.7rem;color:var(--primary);font-weight:700;display:flex;align-items:center;gap:0.5rem;transition:all 0.2s;"
                    onmouseover="this.style.background='rgba(14,165,233,0.12)'" onmouseout="this.style.background='rgba(14,165,233,0.05)'">
                    <i class="fas fa-info-circle"></i>
                    Precision micro-pump optimized for automated chemical injection. (Click for Specs)
                </div>
            </div>

            <!-- ══ DOSING STATUS CARD ══ -->
            <div class="metric-card-premium glass" style="grid-column:span 1;display:flex;flex-direction:column;gap:1rem;">
                <?php if(!($hw_states['dosing_pump'] ?? 1) || !($hw_states['esp32_controller'] ?? 1)): ?>
                <div class="disabled-overlay">
                    <span class="disabled-badge"><i class="fas fa-power-off"></i> Pump Disabled</span>
                </div>
                <?php endif; ?>
                <div class="metric-header" style="justify-content:space-between;">
                    <span class="metric-title" style="color:var(--primary);font-weight:700;display:flex;align-items:center;gap:0.5rem;">
                        <i class="fas fa-vial"></i>

                        <!-- Chemical Name Display / Editor -->
                        <div id="chemical-display-container" style="display:flex;align-items:center;gap:0.5rem;">
                            <span id="chemical-name-display"><?= htmlspecialchars($active_chemical) ?></span>
                            <button onclick="toggleChemicalEdit(true)" style="background:none;border:none;color:var(--text-muted);cursor:pointer;font-size:0.8rem;padding:4px;">
                                <i class="fas fa-pencil-alt"></i>
                            </button>
                        </div>
                        <div id="chemical-edit-container" style="display:none;align-items:center;gap:0.25rem;">
                            <input type="text" id="chemical-input" value="<?= htmlspecialchars($active_chemical) ?>"
                                style="padding:4px 8px;border-radius:6px;border:2px solid var(--primary);background:rgba(255,255,255,0.8);font-size:0.9rem;width:120px;font-weight:700;">
                            <button onclick="saveChemicalName()" style="background:var(--primary);color:white;border:none;padding:4px 8px;border-radius:6px;cursor:pointer;"><i class="fas fa-check"></i></button>
                            <button onclick="toggleChemicalEdit(false)" style="background:var(--gray-200);color:var(--text-muted);border:none;padding:4px 8px;border-radius:6px;cursor:pointer;"><i class="fas fa-times"></i></button>
                        </div>

                        Dosing Status
                    </span>
                    <div id="injection-heartbeat" class="status-pulse" style="display:none;"></div>
                </div>

                <div style="display:flex;flex-direction:column;gap:0.75rem;">
                    <div style="display:flex;justify-content:space-between;align-items:center;">
                        <span style="font-size:0.85rem;color:var(--text-muted);font-weight:600;">Dosing Pump</span>
                        <span id="dosing-pump-status" style="font-weight:800;font-size:0.9rem;color:var(--text-muted);">OFF</span>
                    </div>
                    <div style="display:flex;justify-content:space-between;align-items:center;">
                        <span style="font-size:0.85rem;color:var(--text-muted);font-weight:600;">Treatment Duration</span>
                        <span id="treatment-duration" style="font-weight:800;font-size:1.1rem;color:var(--primary);">00:00:00</span>
                    </div>
                    <div style="display:flex;justify-content:space-between;align-items:center;">
                        <span style="font-size:0.85rem;color:var(--text-muted);font-weight:600;">Session Volume</span>
                        <span id="session-volume" style="font-weight:800;font-size:0.9rem;color:var(--primary);">0 ml</span>
                    </div>
                    <div style="display:flex;justify-content:space-between;align-items:center;">
                        <span style="font-size:0.85rem;color:var(--text-muted);font-weight:600;">Injection Activity</span>
                        <span id="injection-activity" style="font-weight:800;font-size:0.9rem;color:var(--text-muted);">INACTIVE</span>
                    </div>
                    <div style="display:flex;justify-content:space-between;align-items:center;">
                        <span style="font-size:0.85rem;color:var(--text-muted);font-weight:600;">Daily Limit</span>
                        <span style="font-weight:800;font-size:0.9rem;color:var(--text-muted);"><?= $max_daily_ml ?> ml</span>
                    </div>
                    <div style="display:flex;justify-content:space-between;align-items:center;">
                        <span style="font-size:0.85rem;color:var(--text-muted);font-weight:600;">Mode</span>
                        <span id="stat-mode" style="font-weight:800;font-size:0.9rem;color:var(--text-muted);">—</span>
                    </div>
                </div>
            </div>

            <!-- ══ TREATMENT CONTROL PANEL ══ -->
            <div class="pump-panel glass" style="padding:1.5rem;display:flex;flex-direction:column;align-items:center;text-align:center;">
                <?php if(!($hw_states['dosing_pump'] ?? 1) || !($hw_states['esp32_controller'] ?? 1)): ?>
                <div class="disabled-overlay">
                    <span class="disabled-badge"><i class="fas fa-lock"></i> Controls Locked</span>
                </div>
                <?php endif; ?>
                <span class="card-label">Pump Status</span>
                <h3 style="margin:0.5rem 0 1.25rem;font-weight:700;">Treatment Control</h3>

                <div class="pump-status" style="margin-bottom:1.25rem;background:none;border:none;padding:0;">
                    <div id="pump-icon-main" class="pump-icon standby" style="width:64px;height:64px;font-size:1.5rem;">
                        <i class="fas fa-cog"></i>
                    </div>
                </div>

                <div id="pumpState" style="font-size:1.1rem;font-weight:800;margin-bottom:1.5rem;color:var(--text-muted);">STANDBY</div>

                <!-- Mode Toggle -->
                <div class="mode-toggle" style="margin-bottom:1rem;width:100%;display:flex;gap:0.5rem;">
                    <button onclick="setMode('auto')"   id="btn-auto"   style="flex:1;padding:0.6rem;">AUTO</button>
                    <button onclick="setMode('manual')" id="btn-manual" style="flex:1;padding:0.6rem;">MANUAL</button>
                </div>

                <!-- AUTO: trigger thresholds info -->
                <div id="auto-benchmarks" style="display:none;margin-bottom:1rem;width:100%;background:#f8fafc;padding:0.75rem;border-radius:8px;border:1px dashed #cbd5e1;font-size:0.75rem;color:var(--text-muted);text-align:left;">
                    <div style="display:flex;justify-content:space-between;align-items:center;">
                        <div><i class="fas fa-sliders-h" style="color:var(--primary);"></i> <b>AUTO TRIGGERS:</b></div>
                        <button id="btn-edit-triggers" onclick="toggleTriggerEdit(true)" style="color:var(--primary);font-weight:800;background:rgba(14,165,233,0.1);padding:4px 10px;border-radius:6px;border:none;cursor:pointer;transition:background 0.2s;" onmouseover="this.style.background='rgba(14,165,233,0.2)'" onmouseout="this.style.background='rgba(14,165,233,0.1)'">
                            <i class="fas fa-edit"></i> Edit
                        </button>
                    </div>
                    <div style="margin-top:0.5rem;padding-left:1.2rem;" id="trigger-view-state">
                        <span id="trigger-text">Loading...</span>
                    </div>
                    <div id="trigger-edit-state" style="display:none;margin-top:0.5rem;padding-left:1.2rem;flex-direction:column;gap:0.5rem;">
                        <div style="display:flex;align-items:center;justify-content:space-between;">
                            <span>Turbidity &gt;</span>
                            <div><input type="number" id="edit-turb" style="width:60px;padding:4px;border:1px solid #cbd5e1;border-radius:4px;text-align:center;font-weight:700;"> NTU</div>
                        </div>
                        <div style="display:flex;align-items:center;justify-content:space-between;">
                            <span>TDS &gt;</span>
                            <div><input type="number" id="edit-tds" style="width:60px;padding:4px;border:1px solid #cbd5e1;border-radius:4px;text-align:center;font-weight:700;"> PPM</div>
                        </div>
                        <div style="border-top:1px solid #e2e8f0;margin:0.5rem 0;padding-top:0.5rem;">
                            <div style="font-size:0.65rem;color:var(--text-muted);font-weight:700;text-transform:uppercase;margin-bottom:0.4rem;">Tank Calibration</div>
                            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:0.4rem;">
                                <span>Tank Depth</span>
                                <div><input type="number" id="edit-tank-h" style="width:60px;padding:4px;border:1px solid #cbd5e1;border-radius:4px;text-align:center;font-weight:700;"> cm</div>
                            </div>
                            <div style="display:flex;align-items:center;justify-content:space-between;">
                                <span>Sensor Gap (Full)</span>
                                <div><input type="number" id="edit-tank-min" style="width:60px;padding:4px;border:1px solid #cbd5e1;border-radius:4px;text-align:center;font-weight:700;"> cm</div>
                            </div>
                        </div>
                        <div style="display:flex;gap:0.5rem;justify-content:flex-end;margin-top:0.25rem;">
                            <button onclick="toggleTriggerEdit(false)" style="background:var(--gray-200);color:var(--text-muted);border:none;padding:4px 10px;border-radius:6px;cursor:pointer;font-weight:600;">Cancel</button>
                            <button onclick="saveTriggers()" style="background:var(--primary);color:white;border:none;padding:4px 10px;border-radius:6px;cursor:pointer;font-weight:600;">Save</button>
                        </div>
                    </div>
                </div>

                <!-- AUTO: Force Stop -->
                <div id="auto-controls" style="display:none;width:100%;margin-bottom:1rem;">
                    <button class="pump-btn" onclick="emergencyStop()"
                        style="width:100%;background:#ef4444;color:white;padding:0.6rem;font-weight:800;border-radius:8px;border:none;cursor:pointer;transition:all 0.2s;box-shadow:0 4px 12px rgba(239,68,68,0.3);">
                        <i class="fas fa-exclamation-triangle"></i> FORCE STOP PUMP
                    </button>
                </div>

                <!-- MANUAL: Start / Stop -->
                <div id="manual-controls">
                    <button class="pump-btn btn-start" id="btn-pump-start" onclick="pumpOn()"  style="flex:1;padding:0.6rem;font-size:0.85rem;">START</button>
                    <button class="pump-btn btn-stop"  id="btn-pump-stop"  onclick="pumpOff()" style="flex:1;padding:0.6rem;font-size:0.85rem;" disabled>STOP</button>
                </div>

                <!-- Manual Volumetric Dose -->
                <div id="dose-section" style="border-top:1px solid rgba(255,255,255,0.1);padding-top:1.5rem;margin-top:1.5rem;display:none;width:100%;">
                    <span style="display:block;font-size:0.75rem;text-transform:uppercase;font-weight:800;color:var(--text-muted);margin-bottom:0.75rem;">Manual Dosage (ML)</span>
                    <div style="display:flex;gap:0.75rem;">
                        <input type="number" id="ml-input" value="10"
                            style="flex:1;background:rgba(255,255,255,0.05);border:1px solid rgba(255,255,255,0.1);border-radius:12px;color:var(--text-main);padding:0.6rem;font-weight:700;font-size:1rem;text-align:center;">
                        <button class="btn-dose" style="flex:1.2" onclick="triggerManualDose()"><i class="fas fa-bolt"></i> DOSE NOW</button>
                    </div>
                    <div id="smart-dose-guideline" style="margin-top:0.75rem;font-size:0.75rem;color:var(--text-muted);background:rgba(14,165,233,0.05);padding:0.5rem;border-radius:8px;text-align:left;line-height:1.4;">
                        <i class="fas fa-robot"></i> <span id="smart-dose-text">Awaiting ultrasonic data...</span>
                    </div>
                </div>
            </div>

            <!-- ══ TREATMENT HISTORY ══ -->
            <div class="timeline-container glass">
                <h3 style="margin-bottom:0.5rem;">Treatment History</h3>
                <p style="color:var(--gray-500);font-size:0.9rem;margin-bottom:2rem;">Recent dosing cycles and manual actions</p>
                <div class="timeline" id="treatment-timeline">
                    <div style="text-align:center;padding:3rem;color:var(--gray-400);">
                        <i class="fas fa-circle-notch fa-spin"></i> Loading history...
                    </div>
                </div>
            </div>

        </div><!-- /treatment-grid -->
    </main>
</div>

<!-- Technical Specifications Modal -->
<div class="tech-modal-overlay" id="pumpTechModal">
    <div class="tech-modal">
        <div class="modal-close" onclick="hidePumpTechSpecs()"><i class="fas fa-times"></i></div>
        <div style="display:flex;align-items:center;gap:1rem;margin-bottom:2rem;">
            <div style="width:48px;height:48px;background:rgba(14,165,233,0.1);border-radius:12px;display:flex;align-items:center;justify-content:center;color:var(--primary);font-size:1.2rem;">
                <i class="fas fa-microchip"></i>
            </div>
            <div>
                <h2 style="font-weight:800;font-size:1.25rem;margin:0;color:var(--text-main);">Pump Specifications</h2>
                <p style="margin:0;font-size:0.8rem;color:var(--text-muted);font-weight:600;">UniLi DP-200 Series</p>
            </div>
        </div>
        <div class="tech-spec-row"><span class="spec-label">Model Architecture</span><span class="spec-value">Micro-Submerged DC</span></div>
        <div class="tech-spec-row"><span class="spec-label">Operating Voltage</span><span class="spec-value">3.0V - 6.0V DC</span></div>
        <div class="tech-spec-row"><span class="spec-label">Load Current</span><span class="spec-value">120mA - 200mA</span></div>
        <div class="tech-spec-row"><span class="spec-label">Flow Rate (Max)</span><span class="spec-value">100 - 120 L/H</span></div>
        <div class="tech-spec-row"><span class="spec-label">Lifting Head</span><span class="spec-value">40cm - 110cm</span></div>
        <div class="tech-spec-row"><span class="spec-label">Water Resistance</span><span class="spec-value">IPX8 Submersible</span></div>
        <div class="tech-spec-row" style="border:none;"><span class="spec-label">Housing Material</span><span class="spec-value">Engineering Plastic</span></div>
        <div style="margin-top:2rem;background:#f8fafc;padding:1rem;border-radius:16px;border:1px dashed #cbd5e1;">
            <p style="margin:0;font-size:0.75rem;color:var(--text-muted);line-height:1.5;">
                <i class="fas fa-certificate" style="color:#f59e0b;margin-right:4px;"></i>
                Certified for precision chemical dosing and long-duration continuous operation in aquatic monitoring environments.
            </p>
        </div>
    </div>
</div>

<script>
// ════════════════════════════════════════════════════════════════
// CONSTANTS FROM PHP
// ════════════════════════════════════════════════════════════════
const MAX_DAILY_ML   = <?= $max_daily_ml ?>;
const FLOW_RATE_SEC  = <?= $flow_rate_ml_min ?> / 60.0;   // ml per second
const IS_ADMIN       = <?= $is_admin ? 'true' : 'false' ?>;

// ════════════════════════════════════════════════════════════════
// MUTABLE STATE  — single source of truth
// ════════════════════════════════════════════════════════════════
let pumpMode         = '<?= $sys_mode ?>';   // 'manual' | 'auto'
let ESP32_IP         = '';

let isRunning        = false;
let treatmentStart   = null;   // Date when current session started
let currentDayML     = 0;
let sessionML        = 0;
let safetyLock       = false;
let isStopping       = false;  // guard against double-stop
let justReset        = false;  // guard server sync right after reset

// Timers
let durationTick     = null;
let volumeTick       = null;

// Auto-loop state
let autoRunMs        = <?= $db_run_min * 60 * 1000 ?>;
let autoIntervalMs   = <?= $db_interval_min * 60 * 1000 ?>;
let autoRunTimer     = null;
let autoStopTimer    = null;
let nextRunAt        = null;
let autoCycleActive  = false;
let countdownTick    = null;

// Logs (localStorage + DB seed)
let treatmentLogs    = JSON.parse(localStorage.getItem('treatment_logs') || '[]');

// ════════════════════════════════════════════════════════════════
// MODAL
// ════════════════════════════════════════════════════════════════
function showPumpTechSpecs() {
    const m = document.getElementById('pumpTechModal');
    m.style.display = 'flex';
    setTimeout(() => m.classList.add('active'), 10);
}
function hidePumpTechSpecs() {
    const m = document.getElementById('pumpTechModal');
    m.classList.remove('active');
    setTimeout(() => m.style.display = 'none', 300);
}
window.onclick = e => { if (e.target === document.getElementById('pumpTechModal')) hidePumpTechSpecs(); };

// ════════════════════════════════════════════════════════════════
// TIMER HELPERS
// ════════════════════════════════════════════════════════════════
function startTimers() {
    // Clear any existing ticks before starting fresh — prevents stacking
    // across multiple cycles which causes increasing delay each run
    stopTimers();

    durationTick = setInterval(() => {
        if (!treatmentStart) return;
        const diff = Math.floor((Date.now() - treatmentStart) / 1000);
        const h = String(Math.floor(diff / 3600)).padStart(2, '0');
        const m = String(Math.floor((diff % 3600) / 60)).padStart(2, '0');
        const s = String(diff % 60).padStart(2, '0');
        document.getElementById('treatment-duration').textContent = `${h}:${m}:${s}`;
    }, 1000);

    volumeTick = setInterval(() => {
        if (!treatmentStart) return;
        sessionML    += FLOW_RATE_SEC;
        currentDayML += FLOW_RATE_SEC;
        document.getElementById('session-volume').textContent = sessionML.toFixed(1) + ' ml';
        updateDoseDisplay(currentDayML);

        // Safety lockout
        if (currentDayML >= MAX_DAILY_ML && !safetyLock) {
            safetyLock = true;
            pumpOff();
            showToast('⚠ Maximum daily dose reached. Pump locked until admin reset.', 'danger');
        }
    }, 1000);
}

function stopTimers() {
    if (durationTick) { clearInterval(durationTick); durationTick = null; }
    if (volumeTick)   { clearInterval(volumeTick);   volumeTick   = null; }
    document.getElementById('treatment-duration').textContent = '00:00:00';
}

// ════════════════════════════════════════════════════════════════
// DOSE DISPLAY  (today's volume shown in metric card)
// ════════════════════════════════════════════════════════════════
function updateDoseDisplay(ml) {
    currentDayML = ml;
    document.getElementById('today-volume').innerHTML =
        `${ml.toFixed(1)}<small class="metric-unit">ml</small>`;
}

// ════════════════════════════════════════════════════════════════
// UI STATE  — drives every visual element
// ════════════════════════════════════════════════════════════════
function renderUI() {
    const stateEl    = document.getElementById('pumpState');
    const iconEl     = document.getElementById('pump-icon-main');
    const pumpStatus = document.getElementById('dosing-pump-status');
    const activity   = document.getElementById('injection-activity');
    const pulse      = document.getElementById('injection-heartbeat');
    const statMode   = document.getElementById('stat-mode');

    // Pump running visuals
    if (isRunning) {
        stateEl.innerHTML            = '<span style="color:#10b981;">RUNNING</span>';
        iconEl.className             = 'pump-icon active';
        iconEl.querySelector('i').className = 'fas fa-cog fa-spin';
        pumpStatus.textContent       = 'ON';
        pumpStatus.style.color       = '#10b981';
        activity.textContent         = 'ACTIVE';
        activity.style.color         = '#10b981';
        if (pulse) pulse.style.display = 'block';
    } else {
        stateEl.innerHTML            = '<span style="color:#64748b;">STANDBY</span>';
        iconEl.className             = 'pump-icon standby';
        iconEl.querySelector('i').className = 'fas fa-cog';
        pumpStatus.textContent       = 'OFF';
        pumpStatus.style.color       = 'var(--text-muted)';
        activity.textContent         = 'INACTIVE';
        activity.style.color         = 'var(--text-muted)';
        if (pulse) pulse.style.display = 'none';
    }

    statMode.textContent = pumpMode.toUpperCase();

    // Mode panels
    const btnAuto        = document.getElementById('btn-auto');
    const btnManual      = document.getElementById('btn-manual');
    const manualControls = document.getElementById('manual-controls');
    const autoControls   = document.getElementById('auto-controls');
    const autoBenchmarks = document.getElementById('auto-benchmarks');
    const doseSection    = document.getElementById('dose-section');

    if (pumpMode === 'auto') {
        btnAuto.style.background   = safetyLock ? '#ef4444' : 'var(--primary)';
        btnAuto.style.color        = 'white';
        btnAuto.textContent        = safetyLock ? 'LOCKED' : 'AUTO';
        btnManual.style.background = 'var(--gray-100)';
        btnManual.style.color      = 'var(--gray-600)';

        manualControls.classList.remove('visible');
        doseSection.style.display    = 'none';
        autoBenchmarks.style.display = 'block';
        autoControls.style.display   = isRunning ? 'block' : 'none';
    } else {
        // MANUAL
        btnManual.style.background = '#f59e0b';
        btnManual.style.color      = 'white';
        btnAuto.style.background   = 'var(--gray-100)';
        btnAuto.style.color        = 'var(--gray-600)';
        btnAuto.textContent        = 'AUTO';

        manualControls.classList.add('visible');
        doseSection.style.display    = 'block';
        autoBenchmarks.style.display = 'none';
        autoControls.style.display   = 'none';

        // Start / Stop button states
        document.getElementById('btn-pump-start').disabled = isRunning || safetyLock;
        document.getElementById('btn-pump-stop').disabled  = !isRunning;
    }
}

// ════════════════════════════════════════════════════════════════
// MODE SWITCHING
// ════════════════════════════════════════════════════════════════
async function setMode(mode) {
    if (safetyLock && mode === 'auto') {
        showToast('Dose lockout active — cannot switch to AUTO until admin resets the counter.', 'danger');
        return;
    }
    try {
        const res  = await fetch('api/toggle_pump.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `action=set_mode&mode=${mode}`
        });
        const data = await res.json();
        if (data.success) {
            pumpMode = mode;
            if (mode === 'manual') {
                clearAutoTimers();
                nextRunAt = null;
                stopCountdown();
            } else {
                scheduleNextAutoRun();
            }
            renderUI();
        } else {
            showToast('Mode switch failed: ' + (data.error || 'unknown'), 'danger');
        }
    } catch(e) { console.error('setMode', e); }
}

// ════════════════════════════════════════════════════════════════
// PUMP ON / OFF  (manual)
// ════════════════════════════════════════════════════════════════
async function pumpOn() {
    if (safetyLock) { showToast('System Locked: Max Daily Dose Exceeded. Ask an admin to reset.', 'danger'); return; }
    if (isRunning)  return;

    isStopping = false;

    try {
        const res  = await fetch('api/toggle_pump.php', {
            method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'},
            body:'action=pump_control&state=on'
        });
        const data = await res.json();
        if (!data.success) {
            showToast('Failed to start pump: ' + (data.error || 'Server error'), 'danger');
            return;
        }
    } catch(e) {
        showToast('Connection error — pump not started.', 'danger');
        return;
    }

    // Only update UI after server confirms
    isRunning      = true;
    treatmentStart = new Date();
    sessionML      = 0;
    startTimers();
    renderUI();

    if (ESP32_IP) fetch(`http://${ESP32_IP}/pump/on`, { mode:'no-cors' }).catch(()=>{});
}

async function pumpOff(logType) {
    if (isStopping) return;
    isStopping = true;

    const type = logType || pumpMode;
    if (treatmentStart) await logEntry(type);

    isRunning      = false;
    isStopping     = false;
    treatmentStart = null;
    sessionML      = 0;
    stopTimers();
    document.getElementById('session-volume').textContent = '0 ml';
    renderUI();

    if (ESP32_IP) fetch(`http://${ESP32_IP}/pump/off`, { mode:'no-cors' }).catch(()=>{});
    try {
        await fetch('api/toggle_pump.php', {
            method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'},
            body:'action=pump_control&state=off'
        });
    } catch(e) { console.error('pumpOff API', e); }
}

// ════════════════════════════════════════════════════════════════
// MANUAL VOLUMETRIC DOSE
// ════════════════════════════════════════════════════════════════
async function triggerManualDose() {
    const amount = parseFloat(document.getElementById('ml-input').value);
    if (!amount || amount <= 0) { showToast('Enter a valid amount in ml.', 'danger'); return; }
    if (safetyLock || (currentDayML + amount) > MAX_DAILY_ML) {
        showToast(`Dose exceeds Max Daily Limit (${MAX_DAILY_ML} ml). Blocked.`, 'danger');
        return;
    }

    // Calculate run time from flow rate, then fire pump + auto-stop
    const runSec = Math.round(amount / FLOW_RATE_SEC);
    await pumpOn();

    setTimeout(async () => {
        if (isRunning) await pumpOff('manual');
    }, runSec * 1000);

    showToast(`Dosing ${amount} ml — auto-stops in ${runSec}s.`, 'success');
}

// ════════════════════════════════════════════════════════════════
// AUTO LOOP
// ════════════════════════════════════════════════════════════════
function getAutoRunMs()      { return (parseFloat(document.getElementById('auto-run-duration').value)      || 1)  * 60000; }
function getAutoIntervalMs() { return (parseFloat(document.getElementById('auto-interval-duration').value) || 30) * 60000; }

function clearAutoTimers() {
    if (autoRunTimer)  { clearTimeout(autoRunTimer);  autoRunTimer  = null; }
    if (autoStopTimer) { clearTimeout(autoStopTimer); autoStopTimer = null; }
    autoCycleActive = false;
    nextRunAt       = null;
}

function stopCountdown() {
    if (countdownTick) { clearInterval(countdownTick); countdownTick = null; }
    const el = document.getElementById('next-treatment-countdown');
    if (el) el.textContent = '—';
}

function scheduleNextAutoRun() {
    if (pumpMode !== 'auto' || safetyLock || autoCycleActive) return;
    clearAutoTimers();
    // We no longer trigger from Javascript. The server (receive.php) is the master.
    // We just start the local countdown display logic.
    startCountdown();
}

function startCountdown() {
    stopCountdown();
    countdownTick = setInterval(() => {
        const el = document.getElementById('next-treatment-countdown');
        if (!el) return;
        if (pumpMode !== 'auto') { el.textContent = '—'; return; }
        if (safetyLock)          { el.textContent = 'LOCKED'; return; }
        if (isRunning)           { el.textContent = 'Running…'; return; }
        if (!nextRunAt)          { el.textContent = 'Scheduling…'; return; }

        const rem = Math.max(0, nextRunAt - Date.now());
        const m   = String(Math.floor(rem / 60000)).padStart(2, '0');
        const s   = String(Math.floor((rem % 60000) / 1000)).padStart(2, '0');
        el.textContent = rem > 0 ? `${m}:${s}` : 'Starting…';
    }, 1000);
}

// ════════════════════════════════════════════════════════════════
// SAVE AUTO SCHEDULE  (persists to DB + restarts loop)
// ════════════════════════════════════════════════════════════════
async function saveAutoSchedule() {
    const runMin = parseFloat(document.getElementById('auto-run-duration').value);
    const intMin = parseFloat(document.getElementById('auto-interval-duration').value);

    if (!runMin || runMin < 1)  { showToast('Run duration must be at least 1 minute.', 'danger'); return; }
    if (!intMin || intMin < 1)  { showToast('Interval must be at least 1 minute.', 'danger'); return; }

    autoRunMs      = runMin * 60000;
    autoIntervalMs = intMin * 60000;

    try {
        const res  = await fetch('api/toggle_pump.php', {
            method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'},
            body:`action=save_auto_schedule&run_min=${runMin}&interval_min=${intMin}`
        });
        const data = await res.json();
        if (data.success) {
            // Restart auto loop with updated values
            if (pumpMode === 'auto' && !isRunning) scheduleNextAutoRun();
            // Visual confirmation
            const badge = document.getElementById('schedule-saved-badge');
            badge.classList.add('show');
            setTimeout(() => badge.classList.remove('show'), 3000);
            showToast(`Schedule saved: run ${runMin} min every ${intMin} min ✓`, 'success');
        } else {
            showToast('Save failed: ' + (data.error || 'Unknown error'), 'danger');
        }
    } catch(e) { showToast('Connection error saving schedule.', 'danger'); }
}

// ════════════════════════════════════════════════════════════════
// EMERGENCY STOP
// ════════════════════════════════════════════════════════════════
async function emergencyStop() {
    if (!confirm('FORCE STOP will immediately halt the pump and switch to MANUAL mode. Continue?')) return;
    clearAutoTimers();
    stopCountdown();
    if (isRunning) await pumpOff('auto');
    await setMode('manual');
    nextRunAt = null;
    showToast('Pump force-stopped and switched to MANUAL.', 'danger');
}

// ════════════════════════════════════════════════════════════════
// TREATMENT LOGS
// ════════════════════════════════════════════════════════════════
async function logEntry(type) {
    const now      = new Date();
    const duration = treatmentStart ? Math.round((now - treatmentStart) / 1000) : 0;
    const volume   = +(duration * FLOW_RATE_SEC).toFixed(1);

    const entry = { id: Date.now(), type, time: now.toISOString(), duration, volume };
    treatmentLogs.unshift(entry);
    if (treatmentLogs.length > 60) treatmentLogs.pop();
    localStorage.setItem('treatment_logs', JSON.stringify(treatmentLogs));
    renderTreatmentLogs();

    try {
        await fetch('api/treatment_control.php', {
            method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'},
            body:`action=log_${type}_dose&volume=${volume}&duration=${duration}`
        });
    } catch(e) { console.error('logEntry DB sync failed', e); }
}

function renderTreatmentLogs() {
    const el = document.getElementById('treatment-timeline');
    if (!treatmentLogs.length) {
        el.innerHTML = '<div style="text-align:center;padding:2rem;color:var(--gray-400);">No treatment history yet.</div>';
        return;
    }
    el.innerHTML = treatmentLogs.map(log => {
        const d     = new Date(log.time);
        const time  = d.toLocaleTimeString([], { hour:'2-digit', minute:'2-digit' });
        const date  = d.toLocaleDateString([], { month:'short', day:'numeric' });
        const isM   = log.type === 'manual';
        return `
        <div class="timeline-item">
            <div class="timeline-dot ${isM ? 'manual' : ''}">
                <i class="fas ${isM ? 'fa-hand-paper' : 'fa-magic'}"></i>
            </div>
            <div class="timeline-content">
                <div class="timeline-header">
                    <span class="timeline-title">${isM ? 'Manual Treatment' : 'Auto Treatment'}</span>
                    <span class="timeline-time">${date} ${time}</span>
                </div>
                <div class="timeline-details">
                    <div class="detail-item">
                        <span class="detail-label">Volume</span>
                        <span class="detail-value">${log.volume} ml</span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Duration</span>
                        <span class="detail-value">${log.duration}s</span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Type</span>
                        <span class="detail-value" style="color:${isM ? '#f59e0b' : 'var(--primary)'};">${isM ? 'MANUAL' : 'AUTO'}</span>
                    </div>
                </div>
            </div>
        </div>`;
    }).join('');
}

// ════════════════════════════════════════════════════════════════
// RESET DAILY DOSE  (admin)
// ════════════════════════════════════════════════════════════════
async function resetDailyDose() {
    if (!confirm("Reset today's dosage counter to 0 ml? Today's logs will be cleared.")) return;

    const btn = document.getElementById('btn-reset-dose');
    if (btn) { btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Resetting…'; }

    try {
        const res  = await fetch('api/toggle_pump.php', {
            method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'},
            body:'action=reset_daily_dose'
        });
        const data = await res.json();

        if (data.success) {
            justReset    = true;
            safetyLock   = false;
            currentDayML = 0;
            sessionML    = 0;
            updateDoseDisplay(0);
            document.getElementById('session-volume').textContent = '0 ml';

            // Clear today's logs from localStorage
            const today = new Date().toDateString();
            treatmentLogs = treatmentLogs.filter(l => new Date(l.time).toDateString() !== today);
            localStorage.setItem('treatment_logs', JSON.stringify(treatmentLogs));
            renderTreatmentLogs();

            // Re-enable buttons
            if (pumpMode === 'manual') {
                document.getElementById('btn-pump-start').disabled = false;
            }
            if (pumpMode === 'auto' && !isRunning) scheduleNextAutoRun();

            showToast('Daily dose reset to 0 ml ✓', 'success');
            setTimeout(() => { justReset = false; }, 8000);
        } else {
            showToast('Reset failed: ' + (data.error || 'Unknown'), 'danger');
        }
    } catch(e) {
        showToast('Connection error during reset.', 'danger');
    } finally {
        if (btn) { btn.disabled = false; btn.innerHTML = '<i class="fas fa-undo"></i> RESET'; }
    }
}

// ════════════════════════════════════════════════════════════════
// MAINTENANCE TOGGLE
// ════════════════════════════════════════════════════════════════
async function toggleMaintenance(el) {
    const state = el.checked ? 'on' : 'off';
    try {
        const res  = await fetch('api/treatment_control.php', {
            method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'},
            body:`action=toggle_maintenance&state=${state}`
        });
        const data = await res.json();
        if (!data.success) { el.checked = !el.checked; showToast('Maintenance toggle failed.', 'danger'); }
        else showToast(`Maintenance mode ${state === 'on' ? 'ENABLED' : 'DISABLED'}.`, 'success');
    } catch(e) { el.checked = !el.checked; showToast('Connection error.', 'danger'); }
}

// ════════════════════════════════════════════════════════════════
// TRIGGERS / THRESHOLDS
// ════════════════════════════════════════════════════════════════
let currentTriggerTurb = 50;
let currentTriggerTds  = 500;
let currentTankHeight  = 30;
let currentTankMin     = 5;

async function fetchThresholds() {
    try {
        const res  = await fetch('api/get_config.php');
        const data = await res.json();
        if (data.success && data.thresholds) {
            currentTriggerTurb = data.thresholds.max_turbidity;
            currentTriggerTds  = data.thresholds.max_tds;
            currentTankHeight  = data.thresholds.tank_height_cm || 30;
            currentTankMin     = data.thresholds.tank_min_cm || 5;

            document.getElementById('trigger-text').innerHTML =
                `Turbidity &gt; <span style="font-weight:800;color:var(--text-main);">${currentTriggerTurb} NTU</span>
                &nbsp;|&nbsp; TDS &gt; <span style="font-weight:800;color:var(--text-main);">${currentTriggerTds} PPM</span>
                <br><span style="font-size:0.6rem;color:var(--text-muted);">Tank Depth: ${currentTankHeight}cm | Gap: ${currentTankMin}cm</span>`;
        }
    } catch(e) { /* silent */ }
}

function toggleTriggerEdit(editing) {
    document.getElementById('trigger-view-state').style.display  = editing ? 'none' : 'block';
    document.getElementById('trigger-edit-state').style.display  = editing ? 'flex'  : 'none';
    document.getElementById('btn-edit-triggers').style.display   = editing ? 'none' : 'block';
    if (editing) {
        document.getElementById('edit-turb').value = currentTriggerTurb;
        document.getElementById('edit-tds').value  = currentTriggerTds;
        document.getElementById('edit-tank-h').value = currentTankHeight;
        document.getElementById('edit-tank-min').value = currentTankMin;
    }
}

async function saveTriggers() {
    const turb    = parseFloat(document.getElementById('edit-turb').value);
    const tds     = parseFloat(document.getElementById('edit-tds').value);
    const tankH   = parseFloat(document.getElementById('edit-tank-h').value);
    const tankMin = parseFloat(document.getElementById('edit-tank-min').value);

    if (turb <= 0 || tds <= 0 || tankH <= 0) { showToast('Values must be greater than zero.', 'danger'); return; }
    
    try {
        const res  = await fetch('api/update_triggers.php', {
            method:'POST', headers:{'Content-Type':'application/json'},
            body: JSON.stringify({ 
                turbidity: turb, 
                tds,
                tank_height: tankH,
                tank_min: tankMin
            })
        });
        const data = await res.json();
        if (data.success) { toggleTriggerEdit(false); fetchThresholds(); showToast('Settings saved ✓', 'success'); }
        else showToast('Error saving settings: ' + data.error, 'danger');
    } catch(e) { showToast('Connection error.', 'danger'); }
}

// ════════════════════════════════════════════════════════════════
// CHEMICAL NAME EDITOR
// ════════════════════════════════════════════════════════════════
function toggleChemicalEdit(editing) {
    document.getElementById('chemical-display-container').style.display = editing ? 'none' : 'flex';
    document.getElementById('chemical-edit-container').style.display    = editing ? 'flex'  : 'none';
    if (editing) document.getElementById('chemical-input').focus();
}

async function saveChemicalName() {
    const newName     = document.getElementById('chemical-input').value.trim();
    const currentName = document.getElementById('chemical-name-display').textContent;
    if (!newName) return;
    if (newName === currentName) { toggleChemicalEdit(false); return; }
    try {
        const res  = await fetch('api/update_chemical.php', {
            method:'POST', headers:{'Content-Type':'application/json'},
            body: JSON.stringify({ name: newName })
        });
        const data = await res.json();
        if (data.success) {
            document.getElementById('chemical-name-display').textContent = newName;
            toggleChemicalEdit(false);
            showToast('Chemical name updated ✓', 'success');
        }
    } catch(e) { showToast('Connection error.', 'danger'); }
}

// ════════════════════════════════════════════════════════════════
// TOAST
// ════════════════════════════════════════════════════════════════
function showToast(msg, type) {
    const t = document.createElement('div');
    t.textContent = msg;
    const bg = type === 'success' ? '#10b981' : '#ef4444';
    t.style.cssText = `position:fixed;bottom:1.5rem;right:1.5rem;background:${bg};color:#fff;padding:0.75rem 1.25rem;border-radius:12px;font-weight:700;font-size:0.85rem;z-index:9999;box-shadow:0 8px 24px rgba(0,0,0,0.25);animation:fadeInUp .3s ease;max-width:320px;`;
    document.body.appendChild(t);
    setTimeout(() => t.remove(), 3500);
}

// ════════════════════════════════════════════════════════════════
// SERVER POLLING
// ════════════════════════════════════════════════════════════════
async function fetchSystemState() {
    try {
        const res  = await fetch('api/latest.php');
        const data = await res.json();
        if (!data.success) return;

        if (data.esp_ip) ESP32_IP = data.esp_ip;

        // Sync chemical name (when editor is closed)
        if (document.getElementById('chemical-edit-container').style.display === 'none' && data.active_chemical) {
            document.getElementById('chemical-name-display').textContent = data.active_chemical;
            document.getElementById('chemical-input').value              = data.active_chemical;
        }

        // Sync mode from server (only when no active local session)
        if (data.system_mode && data.system_mode !== pumpMode && !treatmentStart) {
            pumpMode = data.system_mode;
            if (pumpMode === 'manual') {
                clearAutoTimers();
                stopCountdown();
            }
            renderUI();
        }

        // Sync next_auto_run_at from server
        if (data.next_auto_run_remaining_sec !== null) {
            const remainingSec = parseInt(data.next_auto_run_remaining_sec);
            // Only update nextRunAt if it's significantly different (prevent jitter)
            const projected = Date.now() + (remainingSec * 1000);
            if (!nextRunAt || Math.abs(nextRunAt - projected) > 5000) {
                nextRunAt = projected;
            }
            if (pumpMode === 'auto' && !isRunning && !countdownTick) {
                startCountdown();
            }
        }

        // ── SYNC RUNNING STATE FROM SERVER ──
        // If the server says the pump should be ON but our UI is idle, sync up.
        // If the server says it's OFF but our UI is running, sync down.
        if (!isStopping) {
            // Trust manual_pump_state as the command intent
            const serverCommandOn = data.manual_pump_state === 'on';
            
            if (serverCommandOn && !isRunning) {
                // Server triggered a run (either manual or auto) — sync UI
                isRunning      = true;
                // Calculate an approximate start time if we don't have one
                const runtime  = data.pump_runtime || 0;
                treatmentStart = new Date(Date.now() - (runtime * 1000));
                sessionML      = 0;
                startTimers();
                renderUI();
            } else if (!serverCommandOn && isRunning) {
                // Server stopped (watchdog or manual stop) — sync UI
                isRunning      = false;
                treatmentStart = null;
                sessionML      = 0;
                stopTimers();
                document.getElementById('session-volume').textContent = '0 ml';
                renderUI();
            }
        }

        // Smart dose guideline
        if (data.water_level !== undefined) {
            const capacity = data.tank_capacity || 5.0;
            const liters = (data.water_level / 100) * capacity;
            const rec    = (liters * 2.0).toFixed(1);
            const el     = document.getElementById('smart-dose-text');
            if (el) el.innerHTML = `<b>Water Level:</b> ${liters.toFixed(1)}L (${data.water_level.toFixed(0)}%). <b>Recommend:</b> ${rec} ml`;
        }
    } catch(e) { /* silent */ }
}

async function fetchTreatmentData() {
    try {
        const res  = await fetch('api/treatment_data.php');
        const data = await res.json();
        if (!data.success) return;

        // Sync today's volume from server (when pump not running and no recent reset)
        if (data.stats && !justReset) {
            const sv = parseFloat(data.stats.today_volume) || 0;
            if (!isRunning || sv > currentDayML + 5) {
                updateDoseDisplay(sv);
            }
        }

        // Voltage
        if (data.stats && data.stats.voltage) {
            const voltEl = document.getElementById('pump-voltage');
            if (voltEl) voltEl.textContent = data.stats.voltage;
        }

        // Seed logs from DB on first load (localStorage empty)
        if (treatmentLogs.length === 0 && data.logs && data.logs.length > 0) {
            treatmentLogs = data.logs.map(l => ({
                id:       new Date(l.created_at).getTime(),
                type:     l.log_type || 'auto',
                time:     l.created_at,
                duration: l.duration || 0,
                volume:   parseFloat(l.volume) || 0
            }));
            localStorage.setItem('treatment_logs', JSON.stringify(treatmentLogs));
            renderTreatmentLogs();
        }
    } catch(e) { console.error('fetchTreatmentData', e); }
}

// ════════════════════════════════════════════════════════════════
// BOOT
// ════════════════════════════════════════════════════════════════
(function init() {
    renderUI();
    renderTreatmentLogs();
    fetchThresholds();
    fetchSystemState();
    fetchTreatmentData();

    // Start auto loop only if in auto mode and not locked.
    // scheduleNextAutoRun() always waits the full interval before
    // firing — it never runs immediately on page load, preventing
    // duplicate treatments if the page is refreshed mid-cycle.
    if (pumpMode === 'auto' && !safetyLock) scheduleNextAutoRun();

    setInterval(() => {
        fetchSystemState();
        fetchTreatmentData();
    }, 5000);
})();
</script>
</body>
</html>
