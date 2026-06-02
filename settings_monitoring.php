<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

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
   SAVE SETTINGS
=====================================================*/
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $freq = intval($_POST['sampling']);
    $retention = $conn->real_escape_string($_POST['retention']);
    
    // Thresholds
    $turb = floatval($_POST['turbidity']);
    $tds = intval($_POST['tds']);
    $temp = floatval($_POST['temp']);
    $level = floatval($_POST['level']);
    
    // Calibration
    $tds_slope = floatval($_POST['tds_slope']);
    $tds_intercept = floatval($_POST['tds_intercept']);
    $turb_offset = floatval($_POST['turbidity_offset']);

    $sql = "UPDATE monitoring_settings
          SET sampling_frequency=$freq,
              data_retention='$retention',
              max_turbidity=$turb,
              max_tds=$tds,
              max_temp=$temp,
              min_level=$level,
              tds_slope=$tds_slope,
              tds_intercept=$tds_intercept,
              turbidity_offset=$turb_offset,
              dose_ml_per_litre=" . floatval($_POST['dose_ratio']) . ",
              tank_height_cm=" . floatval($_POST['tank_height']) . ",
              tank_capacity_litres=" . floatval($_POST['tank_capacity']) . ",
              manual_timeout_sec=" . intval($_POST['manual_timeout']) . ",
              auto_timeout_sec=" . intval($_POST['auto_timeout']) . ",
              max_pump_runtime_sec=" . (intval($_POST['max_runtime_mins']) * 60) . "
          WHERE id=1";

    if ($conn->query($sql)) {
        if (function_exists('logActivity')) {
            logActivity($conn, $_SESSION['user_id'], 'UPDATE_MONITORING', "Advanced monitoring settings updated");
        }
        header("Location: settings_monitoring.php?msg=Advanced settings saved successfully&type=success");
        exit();
    } else {
        $msg = "Error saving settings: " . $conn->error;
        $msgType = "error";
    }
}

/* =====================================================
   FETCH SETTINGS
=====================================================*/
$settings = $conn->query("SELECT * FROM monitoring_settings WHERE id=1")->fetch_assoc();

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
    <title>Monitoring Configuration | UniLi Water System</title>
    <link rel="stylesheet" href="assets/css/style.css?v=2.2">
    <link rel="stylesheet" href="assets/css/dashboard_new.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .settings-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
            gap: 2rem;
            margin-top: 2rem;
        }
        
        .calibration-card {
            background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
            border: 1px solid var(--primary-100);
        }
        
        .academic-note {
            background: #eff6ff;
            border-left: 4px solid #3b82f6;
            padding: 1rem;
            margin-bottom: 1.5rem;
            font-size: 0.85rem;
            color: #1e40af;
            border-radius: 0 8px 8px 0;
        }

        .input-unit {
            position: relative;
            display: flex;
            align-items: center;
        }

        .input-unit span {
            position: absolute;
            right: 1rem;
            color: var(--gray-400);
            font-weight: 600;
            font-size: 0.8rem;
        }

        .input-unit input {
            padding-right: 3rem;
        }
        .btn-reset {
            background: #f1f5f9;
            color: #475569;
            border: 1px solid #e2e8f0;
            padding: 0.75rem 1.5rem;
            border-radius: var(--radius-xl);
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        
        .btn-reset:hover {
            background: #e2e8f0;
            color: #1e293b;
        }
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
                        <h1 class="welcome-title" style="margin-bottom: 0;">Monitoring Configuration</h1>
                    </div>
                    <p class="welcome-subtitle">
                        <a href="settings.php" style="text-decoration: none; color: inherit; opacity: 0.7;">System Settings</a>
                        <i class="fas fa-chevron-right" style="font-size: 0.7rem; margin: 0 0.5rem; opacity: 0.5;"></i>
                        <span style="font-weight: 600;">Monitoring</span>
                    </p>
                </div>
                <div style="display: flex; align-items: center; gap: 1rem;">
                    <button class="btn btn-primary" onclick="document.getElementById('monitoringForm').submit()">
                        <i class="fas fa-save"></i> Save All Config
                    </button>
                    <?php include 'includes/header_user.php'; ?>
                </div>
            </header>

            <?php if ($msg): ?>
                <div class="alert <?= $msgType == 'success' ? 'alert-success' : 'alert-error' ?>" style="margin-top: 1rem;">
                    <i class="fas <?= $msgType == 'success' ? 'fa-check-circle' : 'fa-exclamation-circle' ?>"></i>
                    <?= $msg ?>
                </div>
            <?php endif; ?>

            <form id="monitoringForm" method="POST">
                <div class="settings-grid">
                    
                    <!-- SAMPLING & RETENTION -->
                    <div class="settings-card glass">
                        <div class="card-header">
                            <h3 class="card-title"><i class="fas fa-clock"></i> Data Transmission</h3>
                            <p class="card-desc">Control how often the ESP32 hardware reports to the server.</p>
                        </div>
                        
                        <div class="form-grid">
                            <div class="input-group">
                                <label class="input-label">Sampling Interval</label>
                                <select name="sampling" class="premium-input premium-select">
                                    <option value="5" <?= $settings['sampling_frequency'] == 5 ? "selected" : "" ?>>5 Seconds (Live Debugging)</option>
                                    <option value="30" <?= $settings['sampling_frequency'] == 30 ? "selected" : "" ?>>30 Seconds (Responsive)</option>
                                    <option value="60" <?= $settings['sampling_frequency'] == 60 ? "selected" : "" ?>>1 Minute (Institutional Standard)</option>
                                    <option value="300" <?= $settings['sampling_frequency'] == 300 ? "selected" : "" ?>>5 Minutes (Power Saving)</option>
                                </select>
                            </div>
                            
                            <div class="input-group">
                                <label class="input-label">Data Retention</label>
                                <select name="retention" class="premium-input premium-select">
                                    <option value="90" <?= $settings['data_retention'] == "90" ? "selected" : "" ?>>90 Days</option>
                                    <option value="365" <?= $settings['data_retention'] == "365" ? "selected" : "" ?>>1 Year</option>
                                    <option value="forever" <?= $settings['data_retention'] == "forever" ? "selected" : "" ?>>Indefinite</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- THRESHOLDS -->
                    <div class="settings-card glass">
                        <div class="card-header">
                            <h3 class="card-title"><i class="fas fa-exclamation-triangle"></i> Safety Thresholds</h3>
                            <p class="card-desc">Automatic alerts will trigger if readings exceed these limits.</p>
                        </div>
                        
                        <div class="form-grid" style="grid-template-columns: 1fr 1fr;">
                            <div class="input-group">
                                <label class="input-label">Max Turbidity</label>
                                <div class="input-unit">
                                    <input type="number" step="0.1" name="turbidity" class="premium-input" value="<?= $settings['max_turbidity'] ?>">
                                    <span>NTU</span>
                                </div>
                            </div>
                            <div class="input-group">
                                <label class="input-label">Max TDS</label>
                                <div class="input-unit">
                                    <input type="number" name="tds" class="premium-input" value="<?= $settings['max_tds'] ?>">
                                    <span>PPM</span>
                                </div>
                            </div>
                            <div class="input-group">
                                <label class="input-label">Max Temperature</label>
                                <div class="input-unit">
                                    <input type="number" step="0.1" name="temp" class="premium-input" value="<?= $settings['max_temp'] ?>">
                                    <span>°C</span>
                                </div>
                            </div>
                            <div class="input-group">
                                <label class="input-label">Min Water Level</label>
                                <div class="input-unit">
                                    <input type="number" step="1" name="level" class="premium-input" value="<?= $settings['min_level'] ?>">
                                    <span>%</span>
                                </div>
                            </div>
                            <div class="input-group" style="grid-column: span 2; margin-top: 1rem; border-top: 1px solid var(--gray-100); padding-top: 1rem;">
                                <label class="input-label" style="color: var(--primary-700);"><i class="fas fa-fill-drip"></i> Chemical Dosing Ratio</label>
                                <div class="input-unit">
                                    <input type="number" step="0.1" name="dose_ratio" class="premium-input" style="border-color: var(--primary-200); background: rgba(14, 165, 233, 0.05);" value="<?= $settings['dose_ml_per_litre'] ?? 2.0 ?>">
                                    <span style="right: 5.5rem; color: var(--primary-600);">mL / Litre</span>
                                </div>
                                <div class="helper-text">Amount of chlorine to add for every 1 litre of dirty water detected.</div>
                            </div>

                            <div class="input-group" style="margin-top: 1rem;">
                                <label class="input-label">Tank Height</label>
                                <div class="input-unit">
                                    <input type="number" step="1" name="tank_height" class="premium-input" value="<?= $settings['tank_height_cm'] ?? 25.0 ?>">
                                    <span>CM</span>
                                </div>
                            </div>
                            <div class="input-group" style="margin-top: 1rem;">
                                <label class="input-label">Tank Capacity</label>
                                <div class="input-unit">
                                    <input type="number" step="0.1" name="tank_capacity" class="premium-input" value="<?= $settings['tank_capacity_litres'] ?? 5.0 ?>">
                                    <span>Litres</span>
                                </div>
                            </div>
                            <div class="input-group" style="grid-column: span 2; margin-top: 1rem; border-top: 1px solid var(--gray-100); padding-top: 1rem;">
                                <label class="input-label" style="color: #ef4444;"><i class="fas fa-stopwatch"></i> Pump Runtime Safety Limit</label>
                                <div class="input-unit">
                                    <input type="number" step="1" name="max_runtime_mins" class="premium-input" style="border-color: #fca5a5; background: rgba(239, 68, 68, 0.05);" value="<?= floor(($settings['max_pump_runtime_sec'] ?? 600) / 60) ?>">
                                    <span style="right: 5.5rem; color: #b91c1c;">Minutes</span>
                                </div>
                                <div class="helper-text">If the pump runs continuously longer than this, a <b>CRITICAL</b> alert will be sent via Email and SMS.</div>
                            </div>

                            <div class="input-group" style="margin-top: 1rem;">
                                <label class="input-label"><i class="fas fa-hand-pointer"></i> Manual Safety Timeout</label>
                                <div class="input-unit">
                                    <input type="number" step="1" name="manual_timeout" class="premium-input" value="<?= $settings['manual_timeout_sec'] ?? 300 ?>">
                                    <span>SEC</span>
                                </div>
                                <div class="helper-text">Manual mode cutoff.</div>
                            </div>
                            <div class="input-group" style="margin-top: 1rem;">
                                <label class="input-label"><i class="fas fa-robot"></i> Auto Safety Timeout</label>
                                <div class="input-unit">
                                    <input type="number" step="1" name="auto_timeout" class="premium-input" value="<?= $settings['auto_timeout_sec'] ?? 600 ?>">
                                    <span>SEC</span>
                                </div>
                                <div class="helper-text">Auto treatment cycle cutoff.</div>
                            </div>
                        </div>
                    </div>

                    <!-- CALIBRATION (THE ACADEMIC PART) -->
                    <div class="settings-card calibration-card" style="grid-column: span 1;">
                        <div class="card-header">
                            <h3 class="card-title"><i class="fas fa-microscope"></i> TDS Calibration</h3>
                            <p class="card-desc">Adjust the linear regression model for the TDS sensor.</p>
                        </div>
                        
                        <div class="academic-note">
                            <strong>Formula:</strong> TDS<sub>final</sub> = (Reading × Slope) + Intercept
                        </div>
                        
                        <div class="form-grid">
                            <div class="input-group">
                                <label class="input-label">Calibration Slope (k)</label>
                                <input type="number" step="0.001" name="tds_slope" class="premium-input" value="<?= $settings['tds_slope'] ?>">
                            </div>
                            <div class="input-group">
                                <label class="input-label">Calibration Intercept</label>
                                <input type="number" step="0.01" name="tds_intercept" class="premium-input" value="<?= $settings['tds_intercept'] ?>">
                            </div>
                        </div>
                    </div>

                    <div class="settings-card calibration-card" style="grid-column: span 1;">
                        <div class="card-header">
                            <h3 class="card-title"><i class="fas fa-vial"></i> Turbidity Calibration</h3>
                            <p class="card-desc">Zero-point adjustment for the turbidity sensor.</p>
                        </div>
                        
                        <div class="academic-note">
                            <strong>Offset:</strong> Adjust this value if the sensor reads above 0.0 in distilled water.
                        </div>
                        
                        <div class="form-grid">
                            <div class="input-group">
                                <label class="input-label">Zero-Point Offset</label>
                                <input type="number" step="0.01" name="turbidity_offset" class="premium-input" value="<?= $settings['turbidity_offset'] ?>">
                            </div>
                        </div>
                    </div>

                    <!-- QUALITY STANDARDS REFERENCE -->
                    <div class="settings-card glass" style="grid-column: span 2; border: 1px solid var(--primary-200); background: linear-gradient(to bottom right, rgba(14, 165, 233, 0.05), transparent);">
                        <div class="card-header">
                            <h3 class="card-title" style="color: var(--primary-700);"><i class="fas fa-award"></i> Water Quality Benchmarks</h3>
                            <p class="card-desc">Use these industry-standard values to set your thresholds and define water types.</p>
                        </div>
                        
                        <div style="padding: 1.5rem; overflow-x: auto;">
                            <table style="width: 100%; border-collapse: collapse; font-size: 0.9rem;">
                                <thead>
                                    <tr style="text-align: left; border-bottom: 2px solid var(--gray-200);">
                                        <th style="padding: 0.75rem; color: var(--gray-600);">Water Category</th>
                                        <th style="padding: 0.75rem; color: var(--gray-600);">Turbidity (NTU)</th>
                                        <th style="padding: 0.75rem; color: var(--gray-600);">TDS (PPM)</th>
                                        <th style="padding: 0.75rem; color: var(--gray-600);">System Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr style="border-bottom: 1px solid var(--gray-100);">
                                        <td style="padding: 1rem 0.75rem;"><span style="color: #0ea5e9; font-weight: 700;">Distilled / Ultra-Pure</span></td>
                                        <td style="padding: 1rem 0.75rem;">0.0 - 0.5</td>
                                        <td style="padding: 1rem 0.75rem;">0 - 10</td>
                                        <td style="padding: 1rem 0.75rem; color: var(--gray-500);">Monitoring only</td>
                                    </tr>
                                    <tr style="border-bottom: 1px solid var(--gray-100); background: rgba(34, 197, 94, 0.03);">
                                        <td style="padding: 1rem 0.75rem;"><span style="color: #22c55e; font-weight: 700;">WHO Drinking Standard</span></td>
                                        <td style="padding: 1rem 0.75rem;">0.5 - 5.0</td>
                                        <td style="padding: 1rem 0.75rem;">10 - 500</td>
                                        <td style="padding: 1rem 0.75rem; color: #166534; font-weight: 600;">Optimal Safety</td>
                                    </tr>
                                    <tr style="border-bottom: 1px solid var(--gray-100);">
                                        <td style="padding: 1rem 0.75rem;"><span style="color: #f59e0b; font-weight: 700;">Acceptable (Non-Potable)</span></td>
                                        <td style="padding: 1rem 0.75rem;">5.0 - 50.0</td>
                                        <td style="padding: 1rem 0.75rem;">500 - 1000</td>
                                        <td style="padding: 1rem 0.75rem; color: #92400e;">Warning Alert</td>
                                    </tr>
                                    <tr>
                                        <td style="padding: 1rem 0.75rem;"><span style="color: #ef4444; font-weight: 700;">Dirty / Contaminated</span></td>
                                        <td style="padding: 1rem 0.75rem;">> 50.0</td>
                                        <td style="padding: 1rem 0.75rem;">> 1000</td>
                                        <td style="padding: 1rem 0.75rem; color: #b91c1c; font-weight: 700;">Auto-Chlorination ON</td>
                                    </tr>
                                </tbody>
                            </table>
                            <div class="academic-note" style="margin-top: 1.5rem; background: rgba(14, 165, 233, 0.1);">
                                <i class="fas fa-info-circle"></i> <strong>How to use this:</strong> Adjust your <strong>Safety Thresholds</strong> above to match the category you want to target. For example, to ensure "WHO Drinking Standard," set your Max Turbidity to <strong>5.0</strong> and Max TDS to <strong>500</strong>.
                            </div>
                        </div>
                    </div>

                </div>

                <div style="display: flex; gap: 1.5rem; margin-top: 2rem; padding: 1.5rem; background: white; border-radius: var(--radius-2xl); box-shadow: var(--shadow-sm); border: 1px solid var(--gray-200);">
                    <button type="submit" class="btn-primary" style="flex: 2;">
                        <i class="fas fa-save"></i> Save Configuration
                    </button>
                    <button type="button" class="btn-reset" onclick="resetCalibration()" style="flex: 1;">
                        <i class="fas fa-undo"></i> Reset to Defaults
                    </button>
                </div>
            </form>

            <script>
                function resetCalibration() {
                    if (confirm("Reset all calibration and safety settings to factory defaults? (This won't save until you click 'Save Configuration')")) {
                        // Calibration
                        document.getElementsByName('tds_slope')[0].value = "1.0";
                        document.getElementsByName('tds_intercept')[0].value = "0.0";
                        document.getElementsByName('turbidity_offset')[0].value = "0.0";
                        
                        // Tank
                        document.getElementsByName('tank_height')[0].value = "50";
                        document.getElementsByName('tank_capacity')[0].value = "10.0";
                        
                        // Timeouts
                        document.getElementsByName('manual_timeout')[0].value = "300";
                        document.getElementsByName('auto_timeout')[0].value = "600";
                        
                        // Thresholds (Standard safe limits)
                        document.getElementsByName('turbidity')[0].value = "5.0";
                        document.getElementsByName('tds')[0].value = "500";
                        document.getElementsByName('temp')[0].value = "35.0";
                        document.getElementsByName('level')[0].value = "20.0";
                        
                        // Visual feedback
                        const btn = document.querySelector('.btn-reset');
                        const originalText = btn.innerHTML;
                        btn.innerHTML = '<i class="fas fa-check"></i> Values Restored';
                        btn.style.borderColor = "#22c55e";
                        btn.style.color = "#166534";
                        
                        setTimeout(() => {
                            btn.innerHTML = originalText;
                            btn.style.borderColor = "";
                            btn.style.color = "";
                        }, 2500);
                    }
                }
            </script>
        </main>
    </div>
</body>
</html>
