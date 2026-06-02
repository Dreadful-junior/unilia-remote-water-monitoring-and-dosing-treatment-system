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

$msg     = "";
$msgType = "success";

/* =====================================================
   HANDLE TEST NOTIFICATIONS
===================================================== */
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action'])) {

    $settings = $conn->query("SELECT * FROM alert_settings WHERE id=1")->fetch_assoc();

    if ($_POST['action'] === 'test_whatsapp') {
        require_once 'includes/alert_notifier.php';
        $number = $conn->real_escape_string($_POST['test_wa_number'] ?? $settings['whatsapp_number']);
        $apikey = $settings['whatsapp_apikey'];
        if (empty($number) || empty($apikey)) {
            $msg = "WhatsApp number and API key must be saved first.";
            $msgType = "error";
        } else {
            $ok = sendWhatsApp($number, $apikey, "✅ *UniLi Water System*\nTest message successful! Alerts are configured correctly.\nTime: " . date('d M Y H:i'));
            $msg = $ok ? "✅ WhatsApp test message sent to $number!" : "❌ WhatsApp test failed. Check the API key and number.";
            $msgType = $ok ? "success" : "error";
        }
    }

    if ($_POST['action'] === 'test_sms') {
        require_once 'includes/alert_notifier.php';
        $number   = $settings['sms_number'];
        $apikey   = $settings['sms8_apikey'];
        $deviceId = $settings['sms8_device_id'];
        $simSlot  = intval($settings['sms8_sim_slot'] ?? 0);
        if (empty($number) || empty($apikey) || empty($deviceId)) {
            $msg = "SMS number, API key, and Device ID must be saved first.";
            $msgType = "error";
        } else {
            $ok = sendSMS8($number, $apikey, $deviceId, $simSlot, "UniLi Water System: Test SMS successful! Alerts are working. " . date('d/m/Y H:i'));
            $msg = $ok ? "✅ SMS test sent to $number!" : "❌ SMS test failed. Check your SMS8 settings.";
            $msgType = $ok ? "success" : "error";
        }
    }

} elseif ($_SERVER["REQUEST_METHOD"] == "POST") {

    /* =====================================================
       SAVE SETTINGS
    ===================================================== */
    $emailEnabled      = isset($_POST['email_enabled'])     ? 1 : 0;
    $email             = $conn->real_escape_string($_POST['email']             ?? '');
    $whatsappEnabled   = isset($_POST['whatsapp_enabled'])  ? 1 : 0;
    $waNumber          = $conn->real_escape_string($_POST['whatsapp_number']   ?? '');
    $waApiKey          = $conn->real_escape_string($_POST['whatsapp_apikey']   ?? '');
    $smsEnabled        = isset($_POST['sms_enabled'])       ? 1 : 0;
    $smsNumber         = $conn->real_escape_string($_POST['sms_number']        ?? '');
    $sms8ApiKey        = $conn->real_escape_string($_POST['sms8_apikey']       ?? '');
    $sms8DeviceId      = $conn->real_escape_string($_POST['sms8_device_id']    ?? '');
    $sms8SimSlot       = intval($_POST['sms8_sim_slot']     ?? 0);
    $pumpAlertEnabled  = isset($_POST['pump_alert_enabled']) ? 1 : 0;
    $critical          = isset($_POST['critical'])           ? 1 : 0;
    $cooldown          = intval($_POST['cooldown']           ?? 10);

    $sql = "UPDATE alert_settings SET
                email_enabled     = $emailEnabled,
                email_recipient   = '$email',
                whatsapp_enabled  = $whatsappEnabled,
                whatsapp_number   = '$waNumber',
                whatsapp_apikey   = '$waApiKey',
                sms_enabled       = $smsEnabled,
                sms_number        = '$smsNumber',
                sms8_apikey       = '$sms8ApiKey',
                sms8_device_id    = '$sms8DeviceId',
                sms8_sim_slot     = $sms8SimSlot,
                pump_alert_enabled= $pumpAlertEnabled,
                critical_only     = $critical,
                alert_cooldown    = $cooldown
            WHERE id = 1";

    if ($conn->query($sql)) {
        if (function_exists('logActivity')) {
            logActivity($conn, $_SESSION['user_id'], 'UPDATE_ALERTS', "Notification settings updated");
        }
        header("Location: settings_alerts.php?msg=Notification preferences saved&type=success");
        exit();
    } else {
        $msg = "Error: " . $conn->error;
        $msgType = "error";
    }
}

/* =====================================================
   FETCH SETTINGS
===================================================== */
$settings = $conn->query("SELECT * FROM alert_settings WHERE id=1")->fetch_assoc();

if (isset($_GET['msg'])) {
    $msg     = htmlspecialchars($_GET['msg']);
    $msgType = isset($_GET['type']) ? htmlspecialchars($_GET['type']) : "success";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notification Center | UniLi Water System</title>
    <link rel="stylesheet" href="assets/css/style.css?v=2.2">
    <link rel="stylesheet" href="assets/css/dashboard_new.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .notification-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(420px, 1fr));
            gap: 2rem;
            margin-top: 2rem;
        }
        .channel-card { border-top: 4px solid var(--primary); }
        .whatsapp-card { border-top-color: #25d366; }
        .whatsapp-card .toggle-switch input:checked + .toggle-slider { background-color: #25d366; }
        .sms-card { border-top-color: #6366f1; }
        .sms-card .toggle-switch input:checked + .toggle-slider { background-color: #6366f1; }
        .email-card { border-top-color: var(--primary); }
        .spam-card { border-top-color: #f59e0b; }
        .spam-card .toggle-switch input:checked + .toggle-slider { background-color: #f59e0b; }

        .card-badge {
            display: inline-block;
            padding: 0.2rem 0.6rem;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 700;
            letter-spacing: 0.05em;
            text-transform: uppercase;
            margin-left: 0.5rem;
        }
        .badge-free { background: rgba(34,197,94,0.15); color: #16a34a; }
        .badge-android { background: rgba(99,102,241,0.15); color: #6366f1; }

        .test-btn {
            margin-top: 1rem;
            padding: 0.5rem 1.2rem;
            border-radius: 10px;
            border: 2px solid currentColor;
            background: transparent;
            font-size: 0.85rem;
            font-weight: 600;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.2s;
        }
        .test-btn:hover { opacity: 0.85; transform: translateY(-1px); }
        .test-btn-green { color: #25d366; }
        .test-btn-purple { color: #6366f1; }

        .helper-text {
            font-size: 0.78rem;
            color: var(--gray-400);
            margin-top: 0.3rem;
        }
        .helper-link { color: var(--primary); text-decoration: underline; }
        .step-list {
            background: rgba(255,255,255,0.04);
            border-radius: 10px;
            padding: 1rem 1.2rem;
            margin-top: 0.8rem;
            border: 1px solid rgba(255,255,255,0.08);
        }
        .step-list li {
            margin: 0.4rem 0;
            font-size: 0.82rem;
            color: var(--gray-300);
            line-height: 1.5;
        }
        .step-list li strong { color: var(--gray-100); }
        .step-list code {
            background: rgba(255,255,255,0.08);
            padding: 0.1rem 0.4rem;
            border-radius: 4px;
            font-family: monospace;
            font-size: 0.8rem;
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <?php include 'includes/sidebar.php'; ?>

        <main class="main-content">
            <header class="dashboard-header-wide">
                <div class="main-header-welcome">
                    <div style="display:flex;align-items:center;gap:1rem;margin-bottom:0.5rem;">
                        <a href="settings.php" class="btn-back" style="text-decoration:none;color:var(--primary);font-size:1.1rem;display:flex;align-items:center;gap:0.5rem;background:rgba(14,165,233,0.1);padding:0.5rem 1rem;border-radius:12px;transition:all 0.2s;font-weight:700;">
                            <i class="fas fa-arrow-left"></i> <span>Back</span>
                        </a>
                        <h1 class="welcome-title" style="margin-bottom:0;">Notification Center</h1>
                    </div>
                    <p class="welcome-subtitle">
                        <a href="settings.php" style="text-decoration:none;color:inherit;opacity:0.7;">System Settings</a>
                        <i class="fas fa-chevron-right" style="font-size:0.7rem;margin:0 0.5rem;opacity:0.5;"></i>
                        <span style="font-weight:600;">Alerts & Notifications</span>
                    </p>
                </div>
                <div style="display:flex;align-items:center;gap:1rem;">
                    <button class="btn btn-primary" onclick="document.getElementById('alertForm').submit()">
                        <i class="fas fa-save"></i> Save All Settings
                    </button>
                    <?php include 'includes/header_user.php'; ?>
                </div>
            </header>

            <?php if ($msg): ?>
                <div class="alert <?= $msgType == 'success' ? 'alert-success' : 'alert-error' ?>" style="margin-top:1rem;">
                    <i class="fas <?= $msgType == 'success' ? 'fa-check-circle' : 'fa-exclamation-circle' ?>"></i>
                    <?= $msg ?>
                </div>
            <?php endif; ?>

            <form id="alertForm" method="POST">
                <div class="notification-grid">

                    <!-- ═══════════════════════════════ WHATSAPP ═══════════════════════════════ -->
                    <div class="settings-card glass channel-card whatsapp-card">
                        <div class="card-header">
                            <h3 class="card-title">
                                <i class="fab fa-whatsapp" style="color:#25d366;"></i>
                                WhatsApp Alerts
                                <span class="card-badge badge-free">FREE</span>
                            </h3>
                            <p class="card-desc">Instant alerts sent directly to your WhatsApp via CallMeBot.</p>
                        </div>

                        <div class="form-grid">
                            <div class="toggle-wrapper">
                                <div class="toggle-info">
                                    <span class="toggle-label">Enable WhatsApp</span>
                                    <span class="toggle-desc">Receive real-time alerts on your phone.</span>
                                </div>
                                <label class="toggle-switch">
                                    <input type="checkbox" name="whatsapp_enabled" <?= $settings['whatsapp_enabled'] ? "checked" : "" ?>>
                                    <span class="toggle-slider"></span>
                                </label>
                            </div>

                            <div class="input-group">
                                <label class="input-label">Phone Number (International Format)</label>
                                <input type="text" name="whatsapp_number" class="premium-input"
                                       value="<?= htmlspecialchars($settings['whatsapp_number'] ?? $settings['sms_number'] ?? '') ?>"
                                       placeholder="+265999123456">
                                <div class="helper-text">Include country code, e.g. +265 for Malawi, +263 for Zimbabwe</div>
                            </div>

                            <div class="input-group">
                                <label class="input-label">CallMeBot API Key</label>
                                <input type="password" name="whatsapp_apikey" class="premium-input"
                                       value="<?= htmlspecialchars($settings['whatsapp_apikey'] ?? '') ?>"
                                       placeholder="Enter your CallMeBot API key">
                                <div class="helper-text">
                                    <strong>How to get your key (one-time setup):</strong>
                                    <ol class="step-list">
                                        <li>Save <strong>+34 684 770 005</strong> as a contact named "CallMeBot"</li>
                                        <li>Send this message to that number on WhatsApp:<br><code>I allow callmebot to send me messages</code></li>
                                        <li>You'll receive your API key via WhatsApp within seconds</li>
                                    </ol>
                                </div>
                            </div>

                            <!-- Test Button -->
                            <form method="POST" style="margin-top:0.5rem;">
                                <input type="hidden" name="action" value="test_whatsapp">
                                <button type="submit" class="test-btn test-btn-green">
                                    <i class="fab fa-whatsapp"></i> Send Test Message
                                </button>
                            </form>
                        </div>
                    </div>

                    <!-- ═══════════════════════════════ SMS8.io ═══════════════════════════════ -->
                    <div class="settings-card glass channel-card sms-card">
                        <div class="card-header">
                            <h3 class="card-title">
                                <i class="fas fa-sms" style="color:#6366f1;"></i>
                                SMS Alerts (SMS8.io)
                                <span class="card-badge badge-android">Android Gateway</span>
                            </h3>
                            <p class="card-desc">Send SMS through your own Android phone — no SIM fees.</p>
                        </div>

                        <div class="form-grid">
                            <div class="toggle-wrapper">
                                <div class="toggle-info">
                                    <span class="toggle-label">Enable SMS</span>
                                    <span class="toggle-desc">Send text alerts via SMS8.io gateway.</span>
                                </div>
                                <label class="toggle-switch">
                                    <input type="checkbox" name="sms_enabled" <?= ($settings['sms_enabled'] ?? 0) ? "checked" : "" ?>>
                                    <span class="toggle-slider"></span>
                                </label>
                            </div>

                            <div class="input-group">
                                <label class="input-label">Recipient Phone Number</label>
                                <input type="text" name="sms_number" class="premium-input"
                                       value="<?= htmlspecialchars($settings['sms_number'] ?? '') ?>"
                                       placeholder="+265999123456">
                                <div class="helper-text">Number to receive the SMS alerts (can be same or different from WhatsApp).</div>
                            </div>

                            <div class="input-group">
                                <label class="input-label">SMS8.io API Key</label>
                                <input type="password" name="sms8_apikey" class="premium-input"
                                       value="<?= htmlspecialchars($settings['sms8_apikey'] ?? '') ?>"
                                       placeholder="Your SMS8.io API key">
                                <div class="helper-text">
                                    Get this from your <a href="https://sms8.io" target="_blank" class="helper-link">SMS8.io dashboard</a> after installing the Android app.
                                </div>
                            </div>

                            <div class="input-group">
                                <label class="input-label">Device ID</label>
                                <input type="text" name="sms8_device_id" class="premium-input"
                                       value="<?= htmlspecialchars($settings['sms8_device_id'] ?? '') ?>"
                                       placeholder="e.g. 182">
                                <div class="helper-text">Found in your SMS8 dashboard under <strong>Devices</strong>.</div>
                            </div>

                            <div class="input-group">
                                <label class="input-label">SIM Slot</label>
                                <select name="sms8_sim_slot" class="premium-input premium-select">
                                    <option value="0" <?= ($settings['sms8_sim_slot'] ?? 0) == 0 ? 'selected' : '' ?>>SIM 1 (Slot 0)</option>
                                    <option value="1" <?= ($settings['sms8_sim_slot'] ?? 0) == 1 ? 'selected' : '' ?>>SIM 2 (Slot 1)</option>
                                </select>
                                <div class="helper-text">Which SIM card in your Android device to use.</div>
                            </div>

                            <!-- Setup Guide -->
                            <div class="helper-text" style="margin-top:0.5rem;">
                                <strong>SMS8.io Quick Setup:</strong>
                                <ol class="step-list">
                                    <li>Register at <a href="https://sms8.io" target="_blank" class="helper-link">sms8.io</a></li>
                                    <li>Install the <strong>SMS8 app</strong> on your Android phone</li>
                                    <li>Scan the QR code from the dashboard to connect your phone</li>
                                    <li>Copy your <strong>API Key</strong> and <strong>Device ID</strong> here</li>
                                </ol>
                            </div>

                            <!-- Test Button -->
                            <form method="POST" style="margin-top:0.5rem;">
                                <input type="hidden" name="action" value="test_sms">
                                <button type="submit" class="test-btn test-btn-purple">
                                    <i class="fas fa-paper-plane"></i> Send Test SMS
                                </button>
                            </form>
                        </div>
                    </div>

                    <!-- ═══════════════════════════════ EMAIL ═══════════════════════════════ -->
                    <div class="settings-card glass channel-card email-card">
                        <div class="card-header">
                            <h3 class="card-title">
                                <i class="fas fa-envelope"></i> Email Alerts
                            </h3>
                            <p class="card-desc">Formal HTML reports for auditing and record keeping.</p>
                        </div>

                        <div class="form-grid">
                            <div class="toggle-wrapper">
                                <div class="toggle-info">
                                    <span class="toggle-label">Enable Email</span>
                                    <span class="toggle-desc">Broadcast detailed HTML alerts to all active technicians and managers.</span>
                                </div>
                                <label class="toggle-switch">
                                    <input type="checkbox" name="email_enabled" <?= $settings['email_enabled'] ? "checked" : "" ?>>
                                    <span class="toggle-slider"></span>
                                </label>
                            </div>

                            <div class="input-group" style="opacity: 0.6; pointer-events: none;">
                                <label class="input-label">Recipients</label>
                                <div style="font-size: 0.85rem; color: var(--gray-300); background: rgba(255,255,255,0.05); padding: 10px; border-radius: 8px;">
                                    <i class="fas fa-users"></i> All Active Technicians & Managers
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- ═══════════════════════════════ SPAM SHIELD ═══════════════════════════════ -->
                    <div class="settings-card glass channel-card spam-card" style="grid-column: 1 / -1;">
                        <div class="card-header">
                            <h3 class="card-title"><i class="fas fa-shield-virus"></i> Alert Behavior</h3>
                            <p class="card-desc">Control frequency and scope of alerts to avoid notification fatigue.</p>
                        </div>

                        <div class="form-grid" style="grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));">
                            <div class="input-group">
                                <label class="input-label">Notification Cooldown</label>
                                <select name="cooldown" class="premium-input premium-select">
                                    <option value="1"  <?= ($settings['alert_cooldown'] ?? 10) == 1  ? "selected" : "" ?>>Every 1 Minute (High Noise)</option>
                                    <option value="5"  <?= ($settings['alert_cooldown'] ?? 10) == 5  ? "selected" : "" ?>>Every 5 Minutes</option>
                                    <option value="10" <?= ($settings['alert_cooldown'] ?? 10) == 10 ? "selected" : "" ?>>Every 10 Minutes (Standard)</option>
                                    <option value="30" <?= ($settings['alert_cooldown'] ?? 10) == 30 ? "selected" : "" ?>>Every 30 Minutes (Quiet)</option>
                                    <option value="60" <?= ($settings['alert_cooldown'] ?? 10) == 60 ? "selected" : "" ?>>Hourly</option>
                                </select>
                                <div class="helper-text">Minimum gap between repeated notifications for the same issue.</div>
                            </div>

                            <div class="toggle-wrapper">
                                <div class="toggle-info" style="margin-top:1rem;">
                                    <span class="toggle-label">Critical Alerts Only</span>
                                    <span class="toggle-desc">Ignore warnings; only notify on critical threshold breaches.</span>
                                </div>
                                <label class="toggle-switch">
                                    <input type="checkbox" name="critical" <?= ($settings['critical_only'] ?? 0) ? "checked" : "" ?>>
                                    <span class="toggle-slider"></span>
                                </label>
                            </div>

                            <div class="toggle-wrapper">
                                <div class="toggle-info" style="margin-top:1rem;">
                                    <span class="toggle-label">Pump Treatment Alerts</span>
                                    <span class="toggle-desc">Send alert when pump auto-activates due to bad water quality.</span>
                                </div>
                                <label class="toggle-switch">
                                    <input type="checkbox" name="pump_alert_enabled" <?= ($settings['pump_alert_enabled'] ?? 1) ? "checked" : "" ?>>
                                    <span class="toggle-slider"></span>
                                </label>
                            </div>
                        </div>
                    </div>

                </div>
            </form>
        </main>
    </div>
</body>
</html>
