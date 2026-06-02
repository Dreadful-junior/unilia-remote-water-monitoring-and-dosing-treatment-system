<?php
header('Content-Type: application/json');
include '../db_connect.php';

$response = ['success' => false, 'logs' => [], 'stats' => []];

try {
    // Fetch logs
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
    $sql = "SELECT * FROM treatment_logs ORDER BY created_at DESC LIMIT $limit";
    $result = $conn->query($sql);

    while ($row = $result->fetch_assoc()) {
        $response['logs'][] = $row;
    }

    // Fetch stats for today
    $today_sql = "SELECT SUM(volume) as total_volume FROM treatment_logs WHERE DATE(created_at) = CURDATE()";
    $today_result = $conn->query($today_sql);
    $today_row = $today_result->fetch_assoc();
    $response['stats']['today_volume'] = $today_row['total_volume'] ?: 0;

    // Always return 5.0V to match USB-powered hardware
    $response['stats']['voltage'] = 5.0; 

    $response['success'] = true;
}
catch (Exception $e) {
    $response['error'] = $e->getMessage();
}

echo json_encode($response);
$conn->close();
?>
