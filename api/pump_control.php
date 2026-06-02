<?php
/**
 * Pump Control API - Vanilla PHP
 * Controls pump operations (for ESP32 to check)
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');

require '../db_connect.php';

// Simple API key authentication
$valid_api_key = 'your-secret-api-key-123';

$api_key = isset($_GET['api_key']) ? $_GET['api_key'] : (isset($_SERVER['HTTP_X_API_KEY']) ? $_SERVER['HTTP_X_API_KEY'] : '');

if ($api_key !== $valid_api_key) {
    http_response_code(401);
    echo json_encode(['error' => 'Invalid API key']);
    exit;
}

// Create pump_commands table if it doesn't exist
$conn->query("
    CREATE TABLE IF NOT EXISTS pump_commands (
        id INT AUTO_INCREMENT PRIMARY KEY,
        command VARCHAR(20) NOT NULL,
        status VARCHAR(20) DEFAULT 'pending',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        executed_at TIMESTAMP NULL,
        INDEX idx_status (status)
    )
");

// GET: Check for pending commands
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        $result = $conn->query("
            SELECT command, id
            FROM pump_commands
            WHERE status = 'pending'
            ORDER BY created_at ASC
            LIMIT 1
        ");

        if ($result && $result->num_rows > 0) {
            $row = $result->fetch_assoc();
            // Mark as executing
            $conn->query("UPDATE pump_commands SET status = 'executing', executed_at = NOW() WHERE id = " . $row['id']);

            echo json_encode([
                'success' => true,
                'command' => $row['command'],
                'command_id' => $row['id']
            ]);
        } else {
            echo json_encode([
                'success' => true,
                'command' => null,
                'message' => 'No pending commands'
            ]);
        }
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
    }
    exit;
}

// POST: Mark command as completed or create new command
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = file_get_contents("php://input");
    $data = json_decode($input, true);

    if (!$data) {
        $data = $_POST;
    }

    // Check if this is a command completion
    if (isset($data['command_id'])) {
        $command_id = intval($data['command_id']);
        try {
            $conn->query("UPDATE pump_commands SET status = 'completed' WHERE id = $command_id");
            echo json_encode(['success' => true, 'message' => 'Command marked as completed']);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
        }
        exit;
    }

    // Otherwise, create new command (existing logic)
    session_start();
    if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'manager') {
        http_response_code(403);
        echo json_encode(['error' => 'Manager access required']);
        exit;
    }

    $command = isset($data['command']) ? $conn->real_escape_string($data['command']) : '';
    $target_ml = isset($data['target_ml']) ? floatval($data['target_ml']) : 0;

    if (!in_array($command, ['start', 'stop', 'auto', 'dose_ml'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid command. Use: start, stop, auto, or dose_ml']);
        exit;
    }

    try {
        if ($command === 'dose_ml') {
            $stmt = $conn->prepare("INSERT INTO pump_commands (command, target_ml, status) VALUES (?, ?, 'pending')");
            $stmt->bind_param("sd", $command, $target_ml);
            $stmt->execute();
            $stmt->close();
        } else {
            $conn->query("INSERT INTO pump_commands (command, status) VALUES ('$command', 'pending')");
        }

        echo json_encode([
            'success' => true,
            'message' => "Pump command '$command' queued successfully"
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
    }
    exit;
}

$conn->close();
?>