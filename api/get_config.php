<?php
/**
 * Hardware Configuration API
 * Provides ESP32 with latest thresholds, intervals, and calibration factors
 */
header('Content-Type: application/json');
require '../db_connect.php';

try {
    $res = $conn->query("SELECT * FROM monitoring_settings WHERE id = 1");
    if ($res && $res->num_rows > 0) {
        $s = $res->fetch_assoc();
        
        // Fetch Hardware/WiFi settings as well
        $hw_res = $conn->query("SELECT * FROM hardware_settings WHERE id = 1");
        $hw = $hw_res->fetch_assoc();
        
        echo json_encode([
            'success' => true,
            'sampling_interval_sec' => intval($s['sampling_frequency']),
            'wifi' => [
                'ssid' => $hw['wifi_ssid'] ?? '',
                'pass' => $hw['wifi_password'] ?? ''
            ],
            'thresholds' => [
                'max_turbidity' => floatval($s['max_turbidity']),
                'max_tds' => floatval($s['max_tds']),
                'max_temp' => floatval($s['max_temp']),
                'min_water_level' => floatval($s['min_level']),
                'dose_ratio' => floatval($s['dose_ml_per_litre'] ?? 2.0),
                'tank_height' => floatval($s['tank_height_cm'] ?? 25.0),
                'tank_min_cm' => floatval($s['tank_min_cm'] ?? 5.0),
                'tank_capacity' => floatval($s['tank_capacity_litres'] ?? 5.0)
            ],
            'calibration' => [
                'tds_slope' => floatval($s['tds_slope']),
                'tds_intercept' => floatval($s['tds_intercept']),
                'turbidity_offset' => floatval($s['turbidity_offset'])
            ]
        ]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Settings not found']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

$conn->close();
?>
