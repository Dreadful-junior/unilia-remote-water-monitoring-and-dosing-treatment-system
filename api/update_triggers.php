<?php
/**
 * Update Triggers API
 * Allows quick updating of max_turbidity and max_tds from the dashboard
 */
header('Content-Type: application/json');
require '../db_connect.php';

$input = file_get_contents("php://input");
$data = json_decode($input, true);

$turbidity = floatval($data['turbidity'] ?? 0);
$tds = floatval($data['tds'] ?? 0);
$tankHeight = floatval($data['tank_height'] ?? 0);
$tankMin = floatval($data['tank_min'] ?? 0);

if ($turbidity <= 0 || $tds <= 0) {
    echo json_encode(['success' => false, 'error' => 'Invalid values. Must be greater than 0.']);
    exit;
}

try {
    $stmt = $conn->prepare("UPDATE monitoring_settings SET max_turbidity = ?, max_tds = ?, tank_height_cm = ?, tank_min_cm = ? WHERE id = 1");
    $stmt->bind_param("dddd", $turbidity, $tds, $tankHeight, $tankMin);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $conn->error]);
    }
    $stmt->close();
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

$conn->close();
?>
