<?php
/**
 * Alert Notifier — SMS8.io & CallMeBot WhatsApp
 * Handles actual sending of SMS and WhatsApp messages.
 */

/**
 * Send a WhatsApp message via CallMeBot (FREE).
 * Docs: https://www.callmebot.com/blog/free-api-whatsapp-messages/
 *
 * @param string $phone  International format e.g. +265999123456
 * @param string $apikey CallMeBot API key (get from WhatsApp bot)
 * @param string $message Plain text message
 * @return bool
 */
function sendWhatsApp(string $phone, string $apikey, string $message): bool
{
    $url = "https://api.callmebot.com/whatsapp.php?" . http_build_query([
        'phone'  => $phone,
        'text'   => $message,
        'apikey' => $apikey
    ]);

    $ctx = stream_context_create(['http' => [
        'timeout'        => 10,
        'ignore_errors'  => true,
    ]]);

    $response = @file_get_contents($url, false, $ctx);

    if ($response === false) {
        error_log("[WhatsApp] Request failed to $phone");
        return false;
    }

    // CallMeBot returns "Message queued" on success
    $ok = stripos($response, 'Message queued') !== false ||
          stripos($response, 'OK')             !== false;

    if (!$ok) {
        error_log("[WhatsApp] Unexpected response: $response");
    }

    return $ok;
}

/**
 * Send an SMS via SMS8.io (uses your Android phone as gateway).
 * Docs: https://sms8.io
 *
 * @param string $phone     E.164 format e.g. +265999123456
 * @param string $apikey    Your SMS8.io API key
 * @param string $deviceId  Device ID from SMS8 dashboard
 * @param int    $simSlot   SIM slot (0 = SIM1, 1 = SIM2)
 * @param string $message   SMS text (keep under 160 chars)
 * @return bool
 */
function sendSMS8(string $phone, string $apikey, string $deviceId, int $simSlot, string $message): bool
{
    $devices = json_encode(["$deviceId|$simSlot"]);

    $url = "https://app.sms8.io/services/send.php?" . http_build_query([
        'key'     => $apikey,
        'number'  => $phone,
        'message' => $message,
        'devices' => $devices,
        'type'    => 'sms'
    ]);

    $ctx = stream_context_create(['http' => [
        'timeout'       => 15,
        'ignore_errors' => true,
    ]]);

    $response = @file_get_contents($url, false, $ctx);

    if ($response === false) {
        error_log("[SMS8] Request failed to $phone");
        return false;
    }

    $data = json_decode($response, true);

    // SMS8 returns {"success": true} on success
    $ok = isset($data['success']) && $data['success'] === true;

    if (!$ok) {
        error_log("[SMS8] Failed response: $response");
    }

    return $ok;
}

/**
 * Format a short alert message suitable for SMS (160 chars max).
 *
 * @param array  $alerts   Array of alert arrays from alert_processor
 * @param string $severity 'critical' or 'warning'
 * @return string
 */
function formatSMSMessage(array $alerts, string $severity): string
{
    $prefix = strtoupper($severity) === 'critical'
        ? '🚨 WATER ALERT'
        : '⚠️ WATER WARNING';

    $parts = [];
    foreach ($alerts as $a) {
        $type = strtoupper($a['type']);
        $val  = is_numeric($a['value']) ? round($a['value'], 1) : $a['value'];
        if ($a['type'] === 'runtime') {
            $val = floor($a['value'] / 60) . 'm';
        }
        $parts[] = "$type: $val";
    }

    $summary = implode(' | ', $parts);
    $msg     = "$prefix\n$summary\nCheck dashboard immediately.";

    // Trim to 160 chars for standard SMS
    if (strlen($msg) > 160) {
        $msg = substr($msg, 0, 157) . '...';
    }

    return $msg;
}

/**
 * Format a longer WhatsApp message with full details.
 *
 * @param array  $alerts
 * @param string $severity
 * @return string
 */
function formatWhatsAppMessage(array $alerts, string $severity, bool $pumpActive = false): string
{
    $emoji   = strtoupper($severity) === 'critical' ? '🚨' : '⚠️';
    $time    = date('d M Y, H:i');
    $lines   = ["$emoji *UniLi Water System Alert*", "Time: $time", ""];

    foreach ($alerts as $a) {
        $val  = is_numeric($a['value']) ? round($a['value'], 2) : $a['value'];
        if ($a['type'] === 'runtime') {
            $val = floor($a['value'] / 60) . 'm ' . ($a['value'] % 60) . 's';
            $limit = floor($a['threshold'] / 60) . 'm';
            $lines[] = "• *Pump Run Time* [CRITICAL]: $val (limit: $limit)";
        } else {
            $sev  = strtoupper($a['severity']);
            $lines[] = "• *$type* [$sev]: $val (limit: {$a['threshold']})";
        }
    }

    if ($pumpActive) {
        $lines[] = "";
        $lines[] = "💧 *Treatment pump has been activated automatically.*";
    }

    $lines[] = "";
    $lines[] = "Please check the monitoring dashboard for details.";

    return implode("\n", $lines);
}
