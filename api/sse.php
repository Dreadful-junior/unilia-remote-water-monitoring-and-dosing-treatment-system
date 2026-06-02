<?php
/**
 * Server-Sent Events (SSE) for Real-Time Sensor Data
 * Streams the latest sensor readings to the dashboard
 */

header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('Access-Control-Allow-Origin: *');

require '../db_connect.php';

// Prevent PHP from timing out for this long-running stream
set_time_limit(0);

function computeWaterLevelFromDistance($water_level, $distance_cm, $tank_height, $tank_full_offset = 2.0) {
    // 1. If ESP32 already sent a valid non-zero percentage, use it
    if (is_numeric($water_level) && floatval($water_level) > 0.1) {
        return floatval($water_level);
    }

    // 2. Otherwise calculate from raw distance using real tank height
    if (is_numeric($distance_cm) && floatval($distance_cm) > 0) {
        $dist = floatval($distance_cm);
        $emptyDist = floatval($tank_height);
        $fullDist = floatval($tank_full_offset);
        
        // Safety: ensure we don't divide by zero or negative
        if ($emptyDist <= $fullDist) $emptyDist = $fullDist + 10; 

        $level = (1.0 - (($dist - $fullDist) / ($emptyDist - $fullDist))) * 100.0;
        return max(0.0, min(100.0, $level));
    }

    return 0.0;
}

define('LIVE_DATA_THRESHOLD_SECONDS', 30);

function isNoDataRow($row) {
    return floatval($row['turbidity']) === 0.0
        && floatval($row['tds']) === 0.0
        && floatval($row['temperature']) === 0.0
        && floatval($row['distance_cm']) === 0.0
        && floatval($row['water_level']) === 0.0;
}

function isStaleRow($row) {
    return isset($row['is_stale']) && $row['is_stale'] == 1;
}

$lastTimestamp = null;

while (true) {
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
                       recorded_at,
                       (TIMESTAMPDIFF(SECOND, recorded_at, NOW()) > " . LIVE_DATA_THRESHOLD_SECONDS . ") as is_stale
                FROM sensor_data
                WHERE recorded_at <= NOW()
                ORDER BY recorded_at DESC
                LIMIT 1
            ");
        } else {
            $result = $conn->query("
                SELECT turbidity, tds, temperature,
                       7.0 as ph,
                       0.0 as chlorine,
                       0.0 as distance_cm,
                       0.0 as water_level,
                       0 as pump_status,
                       recorded_at,
                       (TIMESTAMPDIFF(SECOND, recorded_at, NOW()) > " . LIVE_DATA_THRESHOLD_SECONDS . ") as is_stale
                FROM sensor_data
                WHERE recorded_at <= NOW()
                ORDER BY recorded_at DESC
                LIMIT 1
            ");
        }

        // Fetch overall system status using SQL-side time comparison to avoid drift
        $sys_res = $conn->query("
            SELECT status, mode, manual_state, last_dose_ml, current_runtime_sec,
            (TIMESTAMPDIFF(SECOND, last_seen, NOW()) < 30) as is_live
            FROM hardware_recognition 
            WHERE identifier = 'esp32_controller' 
            LIMIT 1
        ");
        
        // Also fetch pump specific info
        $pump_res = $conn->query("SELECT last_dose_ml, current_runtime_sec FROM hardware_recognition WHERE identifier = 'dosing_pump' LIMIT 1");
        $pump_info = ($pump_res && $pump_res->num_rows > 0) ? $pump_res->fetch_assoc() : ['last_dose_ml' => 0, 'current_runtime_sec' => 0];

        // Fetch Tank Settings for accurate real-time level calculation
        $tank_res = $conn->query("SELECT tank_height_cm FROM monitoring_settings WHERE id = 1");
        $tank_cfg = ($tank_res && $tank_res->num_rows > 0) ? $tank_res->fetch_assoc() : ['tank_height_cm' => 25.0];
        $tankHeight = $tank_cfg['tank_height_cm'] ?: 25.0;

        $sys_status = 'offline';
        $sys_mode = 'auto';
        $sys_manual_state = 'off';
        
        // Fetch individual component statuses
        $comp_res = $conn->query("SELECT identifier, status, (TIMESTAMPDIFF(SECOND, last_seen, NOW()) < 60) as is_live FROM hardware_recognition");
        $components = [];
        if ($comp_res) {
            while($c = $comp_res->fetch_assoc()) {
                $components[$c['identifier']] = ($c['status'] == 'online' && $c['is_live'] == 1) ? 'online' : 'offline';
            }
        }

        if ($sys_res && $sys_res->num_rows > 0) {
            $sys_row = $sys_res->fetch_assoc();
            $sys_mode = $sys_row['mode'] ?? 'auto';
            $sys_manual_state = $sys_row['manual_state'] ?? 'off';
            
            if ($sys_row['status'] == 'online' && $sys_row['is_live'] == 1) {
                $sys_status = 'online';
            }
        }

        if ($result && $result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $currentTimestamp = $row['recorded_at'];

            // Modified: If it's no data row, but system is online, we still send success with 0s
            // We only mark "No live data available" if it's both stale/empty AND the system is offline
            if (isNoDataRow($row) || isStaleRow($row)) {
                $data = [
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
                    'timestamp' => null,
                    'components' => $components,
                    'message' => ($sys_status == 'online') ? 'System Online (No Sensor Data)' : 'No live data available'
                ];

                echo "data: " . json_encode($data) . "\n\n";
                ob_flush();
                flush();
                continue;
            }

            // Send data if it's new or first time
            if ($lastTimestamp !== $currentTimestamp) {
                $waterLevel = computeWaterLevelFromDistance($row['water_level'], $row['distance_cm'], $tankHeight);
                $data = [
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
                    'timestamp' => $row['recorded_at'],
                    'system_mode' => $sys_mode,
                    'manual_state' => $sys_manual_state,
                    'last_dose_ml' => floatval($pump_info['last_dose_ml']),
                    'current_runtime_sec' => intval($pump_info['current_runtime_sec']),
                    'components' => $components
                ];

                echo "data: " . json_encode($data) . "\n\n";
                ob_flush();
                flush();

                $lastTimestamp = $currentTimestamp;
            }
        } else {
            // No data, send default
            $data = [
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
                'timestamp' => null,
                'system_mode' => $sys_mode,
                'manual_state' => $sys_manual_state,
                'components' => $components,
                'message' => ($sys_status == 'online') ? 'System Online (Waiting for Data)' : 'No data available'
            ];

            echo "data: " . json_encode($data) . "\n\n";
            ob_flush();
            flush();
        }
    } catch (Exception $e) {
        $errorData = [
            'success' => false,
            'error' => 'Database error: ' . $e->getMessage()
        ];
        echo "data: " . json_encode($errorData) . "\n\n";
        ob_flush();
        flush();
        error_log("SSE error: " . $e->getMessage());
    }

    // Sleep for 1 second before checking again
    sleep(1);
}

$conn->close();
?>