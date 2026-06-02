<?php
/**
 * Water Quality Rating API
 * Analyzes sensor data and returns water quality safety rating
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require '../db_connect.php';

try {
    // Get latest sensor data
    $result = $conn->query("
        SELECT turbidity, tds, temperature, recorded_at
        FROM sensor_data 
        ORDER BY recorded_at DESC 
        LIMIT 1
    ");

    if ($result && $result->num_rows > 0) {
        $data = $result->fetch_assoc();
        
        $turbidity = floatval($data['turbidity'] ?? 0);
        $tds = floatval($data['tds'] ?? 0);
        $temperature = floatval($data['temperature'] ?? 25);
        
        // Water Quality Standards (WHO/EPA Guidelines)
        // These are thresholds for safe drinking water
        
        $rating = [
            'overall_status' => 'SAFE',
            'score' => 100,
            'parameters' => [],
            'warnings' => [],
            'recommendations' => []
        ];
        
        // ---- DYNAMIC QUALITY LOGIC (Synced with Dashboard Settings) ----
        $settings_res = $conn->query("SELECT max_turbidity, max_tds FROM monitoring_settings WHERE id = 1");
        $settings = $settings_res->fetch_assoc();
        
        $max_turbidity = floatval($settings['max_turbidity'] ?? 5.0);
        $max_tds = floatval($settings['max_tds'] ?? 500);

        $turbidity_score = $turbidity > 0 ? max(0, min(100, 100 - (($turbidity / $max_turbidity) * 80))) : 100;
        $tds_score = $tds > 0 ? max(0, min(100, 100 - (($tds / $max_tds) * 80))) : 100;
        
        $average_score = round(($turbidity_score + $tds_score) / 2);
        $rating['score'] = $average_score;
        
        // Parameter Details
        $rating['parameters'][] = [
            'name' => 'Turbidity',
            'value' => round($turbidity, 2),
            'unit' => 'NTU',
            'status' => $turbidity_score >= 90 ? 'GOOD' : ($turbidity_score >= 70 ? 'ACCEPTABLE' : 'POOR'),
            'score' => round($turbidity_score),
            'safe_limit' => $max_turbidity
        ];
        
        $rating['parameters'][] = [
            'name' => 'TDS',
            'value' => round($tds, 2),
            'unit' => 'ppm',
            'status' => $tds_score >= 90 ? 'GOOD' : ($tds_score >= 70 ? 'ACCEPTABLE' : 'POOR'),
            'score' => round($tds_score),
            'safe_limit' => $max_tds
        ];

        // Overall Status
        if ($average_score >= 90) {
            $rating['overall_status'] = 'OPTIMAL';
            $rating['recommendation'] = 'Water is SAFE to drink. Parameters are within safe institutional limits.';
        } elseif ($average_score >= 70) {
            $rating['overall_status'] = 'STABLE';
            $rating['recommendation'] = 'Water quality is GOOD but monitoring is recommended.';
        } elseif ($average_score >= 50) {
            $rating['overall_status'] = 'CAUTION';
            $rating['recommendation'] = 'Water quality is FAIR. Consider starting a treatment cycle.';
        } else {
            $rating['overall_status'] = 'CRITICAL';
            $rating['recommendation'] = 'Water is NOT SAFE. Immediate treatment required.';
        }
        
        // Add recommendations based on parameters
        if ($turbidity > 10) {
            $rating['recommendations'][] = 'Use sediment filters or let water settle to reduce turbidity.';
        }
        if ($tds > 500) {
            $rating['recommendations'][] = 'Consider using a water softener or reverse osmosis system to reduce dissolved solids.';
        }
        if ($temperature > 35) {
            $rating['recommendations'][] = 'Water temperature is high. Check for system issues or contamination sources.';
        }
        
        echo json_encode([
            'success' => true,
            'data' => $rating,
            'timestamp' => $data['recorded_at']
        ]);
        
    } else {
        echo json_encode([
            'success' => false,
            'error' => 'No sensor data available',
            'data' => null
        ]);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Error: ' . $e->getMessage()
    ]);
}

$conn->close();
?>
