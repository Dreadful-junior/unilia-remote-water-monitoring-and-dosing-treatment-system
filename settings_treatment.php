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
   RESET DAILY DOSE
=====================================================*/
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == "reset_dose") {
    $conn->query("DELETE FROM treatment_logs WHERE DATE(created_at) = CURDATE()");
    $affected = $conn->affected_rows;
    if (function_exists('logActivity')) {
        logActivity($conn, $_SESSION['user_id'], 'RESET_DAILY_DOSE', "Daily dose counter manually reset ($affected records cleared)");
    }
    header("Location: settings_treatment.php?msg=Daily dose counter reset to 0ml ($affected records cleared)&type=success");
    exit();
}

/* =====================================================
   SAVE CONFIGURATION
=====================================================*/
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == "save") {

    $duration = intval($_POST['duration']);
    $conc = intval($_POST['concentration']);
    $retry = intval($_POST['retry']);
    $flow_rate = floatval($_POST['flow_rate']);
    $auto = isset($_POST['auto']) ? 1 : 0;
    $auto_interval = max(1, intval($_POST['auto_interval'] ?? 30));
    
    // Intelligent Rules
    $turb_threshold = floatval($_POST['turbidity_threshold']);
    $tds_threshold = floatval($_POST['tds_threshold']);
    
    // Safety
    $max_daily = floatval($_POST['max_daily_dose']);

    // Add auto_interval_minutes column if it doesn't exist yet
    $conn->query("ALTER TABLE treatment_settings ADD COLUMN IF NOT EXISTS auto_interval_minutes INT DEFAULT 30");

    $sql = "UPDATE treatment_settings
          SET dosing_duration=$duration,
              chemical_concentration=$conc,
              retry_attempts=$retry,
              pump_flow_rate=$flow_rate,
              auto_enabled=$auto,
              auto_interval_minutes=$auto_interval,
              turbidity_threshold=$turb_threshold,
              tds_threshold=$tds_threshold,
              max_daily_dose_ml=$max_daily
          WHERE id=1";

    if ($conn->query($sql)) {
        if (function_exists('logActivity')) {
            logActivity($conn, $_SESSION['user_id'], 'UPDATE_TREATMENT', "Intelligent treatment settings updated");
        }
        header("Location: settings_treatment.php?msg=Smart Configuration saved successfully&type=success");
        exit();
    } else {
        $msg = "Error: " . $conn->error;
        $msgType = "error";
    }
}

/* =====================================================
   FETCH SETTINGS
=====================================================*/
// Add auto_interval_minutes column if it doesn't exist yet (safe migration)
$conn->query("ALTER TABLE treatment_settings ADD COLUMN IF NOT EXISTS auto_interval_minutes INT DEFAULT 30");
$settings = $conn->query("SELECT * FROM treatment_settings WHERE id=1")->fetch_assoc();

// Today's total dose
$dose_row = $conn->query("SELECT COALESCE(SUM(volume),0) as total FROM treatment_logs WHERE DATE(created_at) = CURDATE()")->fetch_assoc();
$today_dose = floatval($dose_row['total'] ?? 0);

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
    <title>Treatment Logic | UniLi Water System</title>
    <link rel="stylesheet" href="assets/css/style.css?v=2.2">
    <link rel="stylesheet" href="assets/css/dashboard_new.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .logic-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
            gap: 2rem;
            margin-top: 2rem;
        }

        .rule-card {
            border-left: 5px solid var(--primary);
        }

        .safety-card {
            border-left: 5px solid #ef4444;
            background: #fffafa;
        }

        .logic-operator {
            display: inline-block;
            background: var(--primary-100);
            color: var(--primary-700);
            padding: 2px 8px;
            border-radius: 4px;
            font-weight: 800;
            font-size: 0.8rem;
            margin: 0 4px;
        }

        .input-with-unit {
            position: relative;
            display: flex;
            align-items: center;
        }

        .input-with-unit span {
            position: absolute;
            right: 1rem;
            font-size: 0.75rem;
            font-weight: 700;
            color: var(--gray-400);
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
                        <h1 class="welcome-title" style="margin-bottom: 0;">Treatment Logic</h1>
                    </div>
                    <p class="welcome-subtitle">
                        <a href="settings.php" style="text-decoration: none; color: inherit; opacity: 0.7;">System Settings</a>
                        <i class="fas fa-chevron-right" style="font-size: 0.7rem; margin: 0 0.5rem; opacity: 0.5;"></i>
                        <span style="font-weight: 600;">Treatment</span>
                    </p>
                </div>
                <div style="display: flex; align-items: center; gap: 1rem;">
                    <!-- Reset Daily Dose -->
                    <form method="POST" style="margin:0;" onsubmit="return confirm('Reset today\'s dosage counter to 0ml? This will clear all treatment logs for today.');">
                        <input type="hidden" name="action" value="reset_dose">
                        <button type="submit" style="background: rgba(239,68,68,0.12); color: #ef4444; border: 1px solid rgba(239,68,68,0.3); padding: 0.5rem 1rem; border-radius: 12px; font-weight: 800; cursor: pointer; font-size: 0.85rem; display: flex; align-items: center; gap: 0.5rem; transition: all 0.2s;" onmouseover="this.style.background='rgba(239,68,68,0.22)'" onmouseout="this.style.background='rgba(239,68,68,0.12)'">
                            <i class="fas fa-undo"></i> Reset Daily Dose
                            <span style="background: rgba(239,68,68,0.2); padding: 2px 8px; border-radius: 10px; font-size: 0.75rem;"><?= number_format($today_dose, 1) ?>ml today</span>
                        </button>
                    </form>
                    <button class="btn btn-primary" onclick="document.getElementById('treatmentForm').submit()">
                        <i class="fas fa-save"></i> Save Intelligent Logic
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

            <form id="treatmentForm" method="POST">
                <input type="hidden" name="action" value="save">
                
                <div class="logic-grid">
                    
                    <!-- AUTOMATIC TREATMENT RULES -->
                    <div class="settings-card glass rule-card">
                        <div class="card-header">
                            <h3 class="card-title"><i class="fas fa-robot"></i> Automated Treatment Rules</h3>
                            <p class="card-desc">The system will activate the dosing pump based on these triggers.</p>
                        </div>
                        
                        <div style="background: #f8fafc; padding: 1rem; border-radius: 12px; margin-bottom: 1.5rem; border: 1px solid #e2e8f0;">
                            <p style="font-size: 0.9rem; color: #475569; line-height: 1.6;">
                                <span class="logic-operator">IF</span> Water Quality is unsafe 
                                <span class="logic-operator">AND</span> Operation Mode is 
                                <span class="logic-operator">AUTO</span>
                                <span class="logic-operator">THEN</span> Activate Dosing Pump.
                            </p>
                        </div>

                        <div class="form-grid">
                            <div class="input-group">
                                <label class="input-label">Turbidity Activation Point</label>
                                <div class="input-with-unit">
                                    <input type="number" step="0.1" name="turbidity_threshold" class="premium-input" value="<?= $settings['turbidity_threshold'] ?>">
                                    <span>NTU</span>
                                </div>
                                <div class="helper-text">Trigger dose if turbidity exceeds this value.</div>
                            </div>

                            <div class="input-group">
                                <label class="input-label">TDS Activation Point</label>
                                <div class="input-with-unit">
                                    <input type="number" step="1" name="tds_threshold" class="premium-input" value="<?= $settings['tds_threshold'] ?>">
                                    <span>PPM</span>
                                </div>
                                <div class="helper-text">Trigger dose if TDS exceeds this value.</div>
                            </div>
                        </div>
                    </div>

                    <!-- PUMP DURATION & FLOW -->
                    <div class="settings-card glass">
                        <div class="card-header">
                            <h3 class="card-title"><i class="fas fa-faucet"></i> Dosing Parameters</h3>
                            <p class="card-desc">Configure the hardware timing for each treatment cycle.</p>
                        </div>

                        <div class="form-grid">
                            <div class="input-group">
                                <label class="input-label">Dosing Duration (per cycle)</label>
                                <div class="input-with-unit">
                                    <input type="number" name="duration" class="premium-input" value="<?= $settings['dosing_duration'] ?>" min="1">
                                    <span>SEC</span>
                                </div>
                                <div class="helper-text">How long the pump runs per treatment cycle. e.g. 60 = 1 minute.</div>
                            </div>

                            <div class="input-group">
                                <label class="input-label">Treatment Interval</label>
                                <div class="input-with-unit">
                                    <input type="number" name="auto_interval" class="premium-input" value="<?= $settings['auto_interval_minutes'] ?? 30 ?>" min="1" max="1440">
                                    <span>MIN</span>
                                </div>
                                <div class="helper-text">Wait time between each auto treatment cycle. e.g. 30 = every 30 minutes.</div>
                            </div>

                            <div class="input-group">
                                <label class="input-label">Pump Flow Rate</label>
                                <div class="input-with-unit">
                                    <input type="number" step="0.1" name="flow_rate" class="premium-input" value="<?= $settings['pump_flow_rate'] ?>">
                                    <span>ML/MIN</span>
                                </div>
                                <div class="helper-text">Used for calculating chemical usage.</div>
                            </div>
                        </div>

                        <div class="toggle-wrapper" style="margin-top: 1.5rem;">
                            <div class="toggle-info">
                                <span class="toggle-label">Enable Automation</span>
                                <span class="toggle-desc">Toggle the system's ability to act autonomously.</span>
                            </div>
                            <label class="toggle-switch">
                                <input type="checkbox" name="auto" <?= $settings['auto_enabled'] ? "checked" : "" ?>>
                                <span class="toggle-slider"></span>
                            </label>
                        </div>
                    </div>

                    <!-- SAFETY CONTROLS -->
                    <div class="settings-card safety-card" style="grid-column: 1 / -1;">
                        <div class="card-header">
                            <h3 class="card-title" style="color: #b91c1c;"><i class="fas fa-shield-alt"></i> Safety Interlocks</h3>
                            <p class="card-desc">Hard limits to prevent overdosing and hardware failure.</p>
                        </div>

                        <div class="form-grid" style="grid-template-columns: repeat(3, 1fr);">
                            <div class="input-group">
                                <label class="input-label">Max Daily Dose Limit</label>
                                <div class="input-with-unit">
                                    <input type="number" step="1" name="max_daily_dose" class="premium-input" value="<?= $settings['max_daily_dose_ml'] ?>">
                                    <span>ML/DAY</span>
                                </div>
                                <div class="helper-text">System will lock if this volume is reached.</div>
                            </div>

                            <div class="input-group">
                                <label class="input-label">Chemical Concentration</label>
                                <div class="input-with-unit">
                                    <input type="number" name="concentration" class="premium-input" value="<?= $settings['chemical_concentration'] ?>">
                                    <span>%</span>
                                </div>
                                <div class="helper-text">Strength of the dosing solution.</div>
                            </div>

                            <div class="input-group">
                                <label class="input-label">Max Retry Attempts</label>
                                <input type="number" name="retry" class="premium-input" value="<?= $settings['retry_attempts'] ?>">
                                <div class="helper-text">Stop and alert if safety is not reached.</div>
                            </div>
                        </div>
                    </div>

                </div>
            </form>

            <!-- MANUAL OVERRIDE -->
            <form method="POST" style="margin-top: 2rem;">
                <input type="hidden" name="action" value="trigger">
                <div class="settings-card glass" style="border-top: 4px solid var(--gray-400);">
                    <div class="card-header">
                        <h3 class="card-title">Manual Emergency Override</h3>
                        <p class="card-desc">Force the system to dose immediately regardless of sensor state.</p>
                    </div>
                    <div style="display: flex; justify-content: center; padding: 1rem;">
                        <button class="btn btn-danger" style="padding: 1rem 3rem;" 
                                onclick="return confirm('WARNING: Manual dosing overrides all safety checks. Proceed?')">
                            <i class="fas fa-bolt"></i> FORCE DOSE CYCLE
                        </button>
                    </div>
                </div>
            </form>
        </main>
    </div>
</body>
</html>
