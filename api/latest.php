<?php
/**
 * Latest Sensor Data API - Vanilla PHP
 * Returns the most recent sensor readings
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require '../db_connect.php';

function computeWaterLevelFromDistance($water_level, $distance_cm, $conn) {
    // Fetch calibration from monitoring_settings
    $res = $conn->query("SELECT tank_height_cm, tank_min_cm FROM monitoring_settings WHERE id = 1 LIMIT 1");
    $settings = $res->fetch_assoc();
    $tankEmptyCm = floatval($settings['tank_height_cm'] ?? 30.0);
    $tankFullCm  = floatval($settings['tank_min_cm']    ?? 5.0);

    if (is_numeric($water_level) && floatval($water_level) > 0) {
        if (!is_numeric($distance_cm) || floatval($distance_cm) <= 0) {
             return floatval($water_level);
        }
    }

    if (is_numeric($distance_cm) && floatval($distance_cm) > 0) {
        $dist = floatval($distance_cm);
        $level = (($tankEmptyCm - $dist) / ($tankEmptyCm - $tankFullCm)) * 100.0;
        return max(0.0, min(100.0, $level));
    }

    return 0.0;
}

define('LIVE_DATA_THRESHOLD_SECONDS', 45);

function isNoDataRow($row) {
    return floatval($row['turbidity']) === 0.0
        && floatval($row['tds']) === 0.0
        && floatval($row['temperature']) === 0.0
        && floatval($row['distance_cm']) === 0.0
        && floatval($row['water_level']) === 0.0;
}

function isStaleRow($row) {
    if (empty($row['recorded_at'])) {
        return true;
    }

    $timestamp = strtotime($row['recorded_at']);
    return $timestamp === false || $timestamp < time() - LIVE_DATA_THRESHOLD_SECONDS;
}

try {
    // Check if ph and chlorine columns exist
    $check_ph = $conn->query("SHOW COLUMNS FROM sensor_data LIKE 'ph'");
    $has_ph = $check_ph->num_rows > 0;

    $check_chlorine = $conn->query("SHOW COLUMNS FROM sensor_data LIKE 'chlorine'");
    $has_chlorine = $check_chlorine->num_rows > 0;

    if ($has_ph && $has_chlorine) {
        $result = $conn->query("
            SELECT turbidity, tds, temperature, 
                   COALESCE(ph, 7.0) as ph, 
                   COALESCE(chlorine, 0.0) as chlorine,
                   COALESCE(distance_cm, 0.0) as distance_cm,
                   COALESCE(water_level, 0.0) as water_level,
                   COALESCE(pump_status, 0) as pump_status,
                   recorded_at
            FROM sensor_data 
            WHERE recorded_at <= NOW()
            ORDER BY recorded_at DESC 
            LIMIT 1
        ");
    }
    else {
        $result = $conn->query("
            SELECT turbidity, tds, temperature, 
                   7.0 as ph, 
                   0.0 as chlorine,
                   0.0 as distance_cm,
                   0.0 as water_level,
                   0 as pump_status,
                   recorded_at
            FROM sensor_data 
            WHERE recorded_at <= NOW()
            ORDER BY recorded_at DESC 
            LIMIT 1
        ");
    }

    // Fetch overall system status (ESP32 status)
    $sys_res = $conn->query("SELECT status, last_seen, ip_address FROM hardware_recognition WHERE identifier = 'esp32_controller' LIMIT 1");
    $sys_status = 'offline';
    $sys_mode = 'auto';
    $esp_ip = '';
    
    if ($sys_res && $sys_res->num_rows > 0) {
        $sys_row = $sys_res->fetch_assoc();
        $esp_ip = $sys_row['ip_address'] ?? '';
        // Stale check for controller (within 45 seconds)
        if ($sys_row['status'] == 'online' && strtotime($sys_row['last_seen']) > (time() - 45)) {
            $sys_status = 'online';
        }
    }
    
    // Get operation_mode, active_chemical, manual_pump_state, and next_auto_run_at from hardware_settings
    $settings_res = $conn->query("SELECT operation_mode, active_chemical, manual_pump_state, next_auto_run_at, TIMESTAMPDIFF(SECOND, NOW(), next_auto_run_at) as remaining_sec FROM hardware_settings WHERE id = 1 LIMIT 1");
    $active_chemical = 'Chlorine';
    $manual_pump_state = 'off';
    $next_auto_run_remaining_sec = null;
    if ($settings_res && $settings_res->num_rows > 0) {
        $settings_row = $settings_res->fetch_assoc();
        $sys_mode = $settings_row['operation_mode'] ?? 'auto';
        $active_chemical = $settings_row['active_chemical'] ?? 'Chlorine';
        $manual_pump_state = $settings_row['manual_pump_state'] ?? 'off';
        $next_auto_run_remaining_sec = $settings_row['remaining_sec'] !== null ? intval($settings_row['remaining_sec']) : null;
    }

    // Fetch tank capacity for the dashboard recommendation
    $mon_res = $conn->query("SELECT tank_capacity_litres FROM monitoring_settings WHERE id = 1 LIMIT 1");
    $tank_capacity = floatval($mon_res->fetch_assoc()['tank_capacity_litres'] ?? 5.0);

    // Fetch real-time pump runtime from hardware_recognition
    $pump_rt_res = $conn->query("SELECT current_runtime_sec FROM hardware_recognition WHERE identifier = 'dosing_pump' LIMIT 1");
    $pump_runtime = intval($pump_rt_res->fetch_assoc()['current_runtime_sec'] ?? 0);

    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();

        if (isStaleRow($row) && $sys_status == 'offline') {
            echo json_encode([
                'success' => true,
                'system_status' => 'offline',
                'turbidity' => 0.0,
                'tds' => 0,
                'temperature' => 0.0,
                'ph' => 7.0,
                'chlorine' => 0.0,
                'distance_cm' => 0.0,
                'water_level' => 0.0,
                'pump_status' => 0,
                'manual_pump_state' => $manual_pump_state,
                'esp_ip' => $esp_ip,
                'pump_runtime' => 0,
                'timestamp' => null,
                'system_mode' => $sys_mode,
                'active_chemical' => $active_chemical,
                'next_auto_run_remaining_sec' => $next_auto_run_remaining_sec,
                'message' => 'System Offline'
            ]);
            exit;
        }

        $waterLevel = computeWaterLevelFromDistance($row['water_level'], $row['distance_cm'], $conn);

        echo json_encode([
            'success' => true,
            'system_status' => $sys_status,
            'turbidity' => floatval($row['turbidity']),
            'tds' => floatval($row['tds']),
            'temperature' => floatval($row['temperature']),
            'ph' => floatval($row['ph']),
            'chlorine' => floatval($row['chlorine']),
            'distance_cm' => floatval($row['distance_cm']),
            'water_level' => $waterLevel,
            'pump_status' => intval($row['pump_status']),
            'manual_pump_state' => $manual_pump_state,
            'esp_ip' => $esp_ip,
            'pump_runtime' => $pump_runtime,
            'timestamp' => $row['recorded_at'],
            'system_mode' => $sys_mode,
            'active_chemical' => $active_chemical,
            'next_auto_run_remaining_sec' => $next_auto_run_remaining_sec,
            'tank_capacity' => $tank_capacity
        ]);
    }
    else {
        // Return default values if no data exists
        echo json_encode([
            'success' => true,
            'system_status' => $sys_status,
            'turbidity' => 0.0,
            'tds' => 0,
            'temperature' => 0.0,
            'ph' => 7.0,
            'chlorine' => 0.0,
            'distance_cm' => 0.0,
            'water_level' => 0.0,
            'pump_status' => 0,
            'esp_ip' => $esp_ip,
            'pump_runtime' => 0,
            'timestamp' => null,
            'system_mode' => $sys_mode,
            'active_chemical' => $active_chemical,
            'next_auto_run_remaining_sec' => $next_auto_run_remaining_sec,
            'message' => ($sys_status == 'online') ? 'System Online (Waiting for Data)' : 'No data available'
        ]);
    }


}
catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Database error: ' . $e->getMessage()
    ]);
    error_log("Latest data fetch error: " . $e->getMessage());
}

$conn->close();
?>
