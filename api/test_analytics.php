<?php
header('Content-Type: application/json');

try {
    require 'db_connect.php';
    
    // Test 1: Check if sensor_data table exists and has data
    $result = $conn->query("SELECT COUNT(*) as count FROM sensor_data");
    $row = $result->fetch_assoc();
    $record_count = $row['count'];
    
    // Test 2: Get latest data
    $latest = $conn->query("SELECT * FROM sensor_data ORDER BY recorded_at DESC LIMIT 1");
    $latest_data = $latest->num_rows > 0 ? $latest->fetch_assoc() : null;
    
    // Test 3: Try to call water_quality_rating.php
    $quality_url = 'http://localhost/water%20system/api/water_quality_rating.php';
    $ch = curl_init($quality_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $quality_response = curl_exec($ch);
    curl_close($ch);
    $quality_data = json_decode($quality_response, true);
    
    echo json_encode([
        'database_status' => 'OK',
        'sensor_data_records' => $record_count,
        'latest_record' => $latest_data,
        'quality_api_response' => $quality_data,
        'errors' => []
    ], JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    echo json_encode([
        'error' => $e->getMessage(),
        'database_status' => 'ERROR'
    ], JSON_PRETTY_PRINT);
}

$conn->close();
?>
