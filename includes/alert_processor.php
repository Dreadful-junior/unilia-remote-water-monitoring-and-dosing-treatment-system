<?php
/**
 * Alert Processing System
 * Checks sensor readings against thresholds and triggers SMS8.io + WhatsApp alerts.
 */

require_once __DIR__ . '/alert_notifier.php';
require_once __DIR__ . '/mailer.php';

function checkThresholds($conn, $sensor_data)
{
    // ── 1. Load alert settings ──────────────────────────────────────────────
    $alert_result = $conn->query("SELECT * FROM alert_settings WHERE id = 1");
    if (!$alert_result || $alert_result->num_rows == 0) {
        return; // Not configured yet
    }
    $s = $alert_result->fetch_assoc(); // $s = settings shorthand

    // ── 2. Load monitoring thresholds ───────────────────────────────────────
    $mon_result = $conn->query("SELECT * FROM monitoring_settings WHERE id = 1");
    if (!$mon_result || $mon_result->num_rows == 0) {
        return;
    }
    $thresholds = $mon_result->fetch_assoc();

    // ── 3. Check cooldown — skip if last notification was too recent ─────────
    $cooldownMinutes = max(1, intval($s['alert_cooldown'] ?? 10));
    if (!empty($s['last_notified_at'])) {
        $lastNotified = strtotime($s['last_notified_at']);
        $elapsed      = (time() - $lastNotified) / 60; // minutes
        if ($elapsed < $cooldownMinutes) {
            // Still within cooldown — still store alerts in DB, just don't re-send
            _storeAlerts($conn, _detectAlerts($sensor_data, $thresholds));
            return;
        }
    }

    // ── 4. Detect all threshold breaches ────────────────────────────────────
    $alerts         = _detectAlerts($sensor_data, $thresholds);
    $critical_alerts = array_filter($alerts, fn($a) => $a['severity'] === 'critical');

    if (empty($alerts)) {
        return; // Water is fine — nothing to do
    }

    // ── 5. Filter: if critical_only is set, ignore warnings ─────────────────
    if ($s['critical_only'] && empty($critical_alerts)) {
        return;
    }

    // ── 6. Store new alerts in DB (with 1-hour per-type dedup) ──────────────
    _storeAlerts($conn, $alerts);

    // ── 7. Determine overall severity for message formatting ────────────────
    $severity    = !empty($critical_alerts) ? 'critical' : 'warning';
    $pumpActive  = isset($sensor_data['pump_status']) && $sensor_data['pump_status'] == 1;
    $alertsToSend = $s['critical_only'] ? array_values($critical_alerts) : $alerts;

    // ── 8. Send WhatsApp ─────────────────────────────────────────────────────
    $waSent = false;
    if ($s['whatsapp_enabled'] && !empty($s['whatsapp_number']) && !empty($s['whatsapp_apikey'])) {
        $waMsg = formatWhatsAppMessage($alertsToSend, $severity, $pumpActive);
        $waSent = sendWhatsApp($s['whatsapp_number'], $s['whatsapp_apikey'], $waMsg);
        if ($waSent) {
            error_log("[ALERTS] WhatsApp sent to {$s['whatsapp_number']}");
        }
    }

    // ── 9. Send SMS via SMS8.io ──────────────────────────────────────────────
    $smsSent = false;
    if ($s['sms_enabled'] && !empty($s['sms_number']) && !empty($s['sms8_apikey']) && !empty($s['sms8_device_id'])) {
        $smsMsg = formatSMSMessage($alertsToSend, $severity);
        $smsSent = sendSMS8(
            $s['sms_number'],
            $s['sms8_apikey'],
            $s['sms8_device_id'],
            intval($s['sms8_sim_slot'] ?? 0),
            $smsMsg
        );
        if ($smsSent) {
            error_log("[ALERTS] SMS8 sent to {$s['sms_number']}");
        }
    }

    // ── 10. Send Email to All Active Technicians & Managers ──────────────────
    if ($s['email_enabled']) {
        $messages = array_column($alertsToSend, 'message');
        $alertSummary = implode("\n", $messages);
        
        // Fetch all active and verified technicians and managers
        $user_res = $conn->query("SELECT email, fullname FROM users WHERE role IN ('technician', 'manager') AND account_status = 'active' AND is_verified = 1");
        if ($user_res && $user_res->num_rows > 0) {
            $severityText = !empty($critical_alerts) ? 'CRITICAL' : 'WARNING';
            $color = !empty($critical_alerts) ? '#dc2626' : '#f59e0b';
            
            // Build items HTML for the email body
            $itemsHtml = '';
            foreach ($alertsToSend as $a) {
                $typeLabel = ucfirst($a['type']);
                $itemsHtml .= "
                <div style='background: #f8fafc; padding: 15px; margin: 10px 0; border-radius: 8px; border-left: 4px solid $color;'>
                    <strong style='color: $color;'>$typeLabel</strong>: {$a['message']}
                </div>";
            }

            $emailBody = "
                <h2 style='color: $color; margin-top: 0;'>Water System Alert: $severityText</h2>
                <p>The following issues have been detected by the monitoring system:</p>
                $itemsHtml
                <p style='margin-top: 20px;'>Please log in to the dashboard immediately to take action.</p>
                <a href='" . (include(__DIR__ . '/../config/email.php'))['base_url'] . "/dashboard.php' 
                   style='display: inline-block; background: $color; color: white; padding: 12px 24px; border-radius: 8px; text-decoration: none; font-weight: bold;'>
                   View Dashboard
                </a>";

            while ($user = $user_res->fetch_assoc()) {
                sendSystemEmail(
                    $user['email'], 
                    $user['fullname'], 
                    "[$severityText] Water System Alert — " . date('H:i'), 
                    $emailBody
                );
            }
        }
    }

    // ── 11. Update last_notified_at so cooldown applies ─────────────────────
    if ($waSent || $smsSent || $s['email_enabled']) {
        $conn->query("UPDATE alert_settings SET last_notified_at = NOW() WHERE id = 1");
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// PRIVATE HELPERS
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Detect threshold breaches and return array of alert objects.
 */
function _detectAlerts(array $sensor_data, array $thresholds): array
{
    $alerts = [];

    // Turbidity
    if (isset($sensor_data['turbidity']) && $sensor_data['turbidity'] > $thresholds['max_turbidity']) {
        $severity = ($sensor_data['turbidity'] > $thresholds['max_turbidity'] * 1.5) ? 'critical' : 'warning';
        $val = number_format($sensor_data['turbidity'], 2);
        $alerts[] = [
            'type'      => 'turbidity',
            'value'     => $sensor_data['turbidity'],
            'threshold' => $thresholds['max_turbidity'],
            'severity'  => $severity,
            'message'   => "Poor water clarity detected ($val NTU). High turbidity indicates potential contaminants; treatment is required."
        ];
    }

    // TDS
    if (isset($sensor_data['tds']) && $sensor_data['tds'] > $thresholds['max_tds']) {
        $severity = ($sensor_data['tds'] > $thresholds['max_tds'] * 1.5) ? 'critical' : 'warning';
        $val = number_format($sensor_data['tds'], 0);
        $alerts[] = [
            'type'      => 'tds',
            'value'     => $sensor_data['tds'],
            'threshold' => $thresholds['max_tds'],
            'severity'  => $severity,
            'message'   => "High dissolved solids detected ($val PPM). Water may have high mineral content or contamination; treatment is recommended."
        ];
    }

    // Temperature — extreme readings
    if (isset($sensor_data['temperature']) && $sensor_data['temperature'] > -100) {
        if ($sensor_data['temperature'] < 0 || $sensor_data['temperature'] > 50) {
            $val = number_format($sensor_data['temperature'], 1);
            $alerts[] = [
                'type'      => 'temperature',
                'value'     => $sensor_data['temperature'],
                'threshold' => '0-50°C',
                'severity'  => 'critical',
                'message'   => "Critical temperature reading ({$val}°C). Extreme temperatures can affect water safety; immediate inspection required."
            ];
        }
    }


    // Overdosing / Long Run risk
    if (isset($sensor_data['pump_status']) && $sensor_data['pump_status'] == 1) {
        // 1. Long Run Alert
        $maxRuntime = intval($thresholds['max_pump_runtime_sec'] ?? 600);
        $currRuntime = intval($sensor_data['pump_runtime'] ?? 0);
        
        if ($currRuntime > $maxRuntime) {
            $mins = floor($currRuntime / 60);
            $alerts[] = [
                'type'      => 'runtime',
                'value'     => $currRuntime,
                'threshold' => $maxRuntime,
                'severity'  => 'critical',
                'message'   => "Pump Over-Run Detected: The dosing pump has been running for over $mins minutes, exceeding the safe limit set by the technician."
            ];
        }

        // 2. Overdosing check
        $turbOk = isset($sensor_data['turbidity']) && $sensor_data['turbidity'] < ($thresholds['max_turbidity'] * 0.8);
        $tdsOk  = isset($sensor_data['tds'])       && $sensor_data['tds']       < ($thresholds['max_tds'] * 0.8);
        if ($turbOk && $tdsOk && $currRuntime < $maxRuntime) { // Only warning if not already in runtime critical
            $alerts[] = [
                'type'      => 'safety',
                'value'     => 1,
                'threshold' => 'Good quality',
                'severity'  => 'warning',
                'message'   => "Overdosing Risk: Pump is running while water quality is already within safe limits. Consider stopping treatment."
            ];
        }
    }

    return $alerts;
}

/**
 * Store alerts in DB with per-type hourly dedup.
 */
function _storeAlerts($conn, array $alerts): void
{
    if (empty($alerts)) return;

    // Ensure table exists
    $conn->query("
        CREATE TABLE IF NOT EXISTS alerts (
            id INT AUTO_INCREMENT PRIMARY KEY,
            type VARCHAR(50) NOT NULL,
            severity VARCHAR(20) NOT NULL,
            message TEXT NOT NULL,
            sensor_value FLOAT,
            threshold_value VARCHAR(50),
            notified_sms TINYINT DEFAULT 0,
            notified_whatsapp TINYINT DEFAULT 0,
            is_read TINYINT DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    foreach ($alerts as $alert) {
        // Skip if same type alert was created in the last 1 hour (unread)
        $stmt = $conn->prepare("SELECT id FROM alerts WHERE type = ? AND is_read = 0 AND created_at > (NOW() - INTERVAL 1 HOUR) LIMIT 1");
        $stmt->bind_param("s", $alert['type']);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows == 0) {
            $ins = $conn->prepare("INSERT INTO alerts (type, severity, message, sensor_value, threshold_value) VALUES (?, ?, ?, ?, ?)");
            $threshold_str = is_array($alert['threshold']) ? implode('-', $alert['threshold']) : (string)$alert['threshold'];
            $ins->bind_param("sssds", $alert['type'], $alert['severity'], $alert['message'], $alert['value'], $threshold_str);
            $ins->execute();
            $ins->close();
        }
        $stmt->close();
    }
}