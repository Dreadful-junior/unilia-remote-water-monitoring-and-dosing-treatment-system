<?php
/**
 * ESP32 Configuration API - Vanilla PHP
 * Pushes configuration to ESP32 hardware
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');

require '../db_connect.php';

// Simple API key authentication
$valid_api_key = 'your-secret-api-key-123'; // Change this in production

// Get API key from header or parameter
$api_key = isset($_GET['api_key']) ? $_GET['api_key'] : (isset($_SERVER['HTTP_X_API_KEY']) ? $_SERVER['HTTP_X_API_KEY'] : '');

if ($api_key !== $valid_api_key) {
    http_response_code(401);
    echo json_encode(['error' => 'Invalid API key']);
    exit;
}

// GET: Return current configuration
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        $result = $conn->query("SELECT * FROM hardware_settings WHERE id = 1");
        if ($result && $result->num_rows > 0) {
            $config = $result->fetch_assoc();
            echo json_encode([
                'success' => true,
                'wifi_ssid' => $config['wifi_ssid'],
                'wifi_password' => $config['wifi_password'],
                'operation_mode' => $config['operation_mode']
            ]);
        } else {
            echo json_encode([
                'success' => true,
                'wifi_ssid' => '',
                'wifi_password' => '',
                'operation_mode' => 'auto'
            ]);
        }
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
    }
    exit;
}

// POST: Update configuration (from web interface)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    session_start();
    if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'manager') {
        http_response_code(403);
        echo json_encode(['error' => 'Manager access required']);
        exit;
    }

    $input = file_get_contents("php://input");
    $data = json_decode($input, true);

    if (!$data) {
        $data = $_POST;
    }

    $ssid = isset($data['wifi_ssid']) ? $conn->real_escape_string($data['wifi_ssid']) : '';
    $password = isset($data['wifi_password']) ? $conn->real_escape_string($data['wifi_password']) : '';
    $mode = isset($data['operation_mode']) ? $conn->real_escape_string($data['operation_mode']) : 'auto';

    try {
        $conn->query("
            UPDATE hardware_settings
            SET wifi_ssid='$ssid',
                wifi_password='$password',
                operation_mode='$mode'
            WHERE id=1
        ");

        echo json_encode([
            'success' => true,
            'message' => 'Configuration updated. ESP32 will fetch on next check-in.'
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
    }
    exit;
}

$conn->close();
?>