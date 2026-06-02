<?php
/**
 * Alerts API - Vanilla PHP
 * Returns alerts and handles alert operations
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

require '../db_connect.php';

// Ensure alerts table exists
$conn->query("
    CREATE TABLE IF NOT EXISTS alerts (
        id INT AUTO_INCREMENT PRIMARY KEY,
        type VARCHAR(50) NOT NULL,
        severity VARCHAR(20) NOT NULL,
        message TEXT NOT NULL,
        sensor_value FLOAT,
        threshold_value VARCHAR(50),
        is_read TINYINT DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_severity (severity),
        INDEX idx_created (created_at),
        INDEX idx_read (is_read)
    )
");

// Handle POST requests (mark as read, etc.)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    session_start();
    if (!isset($_SESSION['user_id'])) {
        http_response_code(401);
        echo json_encode(['error' => 'Unauthorized']);
        exit;
    }
    
    $input = file_get_contents("php://input");
    $data = json_decode($input, true);
    
    if (!$data) {
        $data = $_POST;
    }
    
    if (isset($data['action']) && $data['action'] === 'mark_read') {
        $id = intval($data['id']);
        $conn->query("UPDATE alerts SET is_read = 1 WHERE id = $id");
        $count_result = $conn->query("SELECT COUNT(*) as count FROM alerts WHERE is_read = 0");
        $unread_count = $count_result->fetch_assoc()['count'];
        echo json_encode(['success' => true, 'message' => 'Alert marked as read', 'count' => intval($unread_count)]);
        exit;
    }
    
    if (isset($data['action']) && $data['action'] === 'mark_all_read') {
        $conn->query("UPDATE alerts SET is_read = 1 WHERE is_read = 0");
        echo json_encode(['success' => true, 'message' => 'All alerts marked as read', 'count' => 0]);
        exit;
    }
}

// GET: Return alerts
$unread_only = isset($_GET['unread']) && $_GET['unread'] == '1';
$limit = isset($_GET['limit']) ? intval($_GET['limit']) : 50;
$severity = isset($_GET['severity']) ? $conn->real_escape_string($_GET['severity']) : '';

try {
    $where = [];
    if ($unread_only) {
        $where[] = "is_read = 0";
    }
    if ($severity) {
        $where[] = "severity = '$severity'";
    }
    
    $where_clause = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";
    
    $result = $conn->query("
        SELECT id, type, severity, message, sensor_value, threshold_value, is_read, created_at
        FROM alerts
        $where_clause
        ORDER BY created_at DESC
        LIMIT $limit
    ");
    
    $alerts = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $alerts[] = [
                'id' => intval($row['id']),
                'type' => $row['type'],
                'severity' => $row['severity'],
                'message' => $row['message'],
                'sensor_value' => $row['sensor_value'] ? floatval($row['sensor_value']) : null,
                'threshold_value' => $row['threshold_value'],
                'is_read' => (bool)$row['is_read'],
                'created_at' => $row['created_at']
            ];
        }
    }
    
    // Get unread count
    $count_result = $conn->query("SELECT COUNT(*) as count FROM alerts WHERE is_read = 0");
    $unread_count = $count_result->fetch_assoc()['count'];
    
    echo json_encode([
        'success' => true,
        'alerts' => $alerts,
        'count' => intval($unread_count),
        'total' => count($alerts)
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Database error: ' . $e->getMessage()
    ]);
    error_log("Alerts fetch error: " . $e->getMessage());
}

$conn->close();
?>
