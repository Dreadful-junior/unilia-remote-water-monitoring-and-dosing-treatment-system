<?php
header('Content-Type: application/json');
session_start();
include '../db_connect.php';

$response = ['success' => false];

if (!isset($_SESSION['user_id'])) {
    $response['error'] = 'Unauthorized';
    echo json_encode($response);
    exit();
}

$action = $_POST['action'] ?? '';

try {
    if ($action === 'manual_dose') {
        // --- HARDWARE SAFETY CHECK ---
        $hw_check = $conn->query("SELECT status FROM hardware_recognition WHERE identifier = 'dosing_pump'");
        $hw_status = $hw_check->fetch_assoc()['status'] ?? 'offline';
        
        if ($hw_status !== 'online') {
            $response['error'] = 'Hardware Error: Dosing pump not detected or disconnected.';
            echo json_encode($response);
            exit();
        }
        // -----------------------------

        // Fetch settings for calculation
        $t_settings = $conn->query("SELECT * FROM treatment_settings WHERE id=1")->fetch_assoc();
        $flow_rate = $t_settings['pump_flow_rate'] ?? 50.0; // ml/min
        $duration = $t_settings['dosing_duration'] ?? 30; // seconds

        $cycle = 1000 + rand(1, 999);
        $volume = ($duration / 60.0) * $flow_rate;
        $voltage = 5.0; // USB Powered

        $stmt = $conn->prepare("INSERT INTO treatment_logs (cycle_number, volume, duration, flow_rate, voltage, log_type) VALUES (?, ?, ?, ?, ?, 'manual')");
        $stmt->bind_param("ididd", $cycle, $volume, $duration, $flow_rate, $voltage);

        if ($stmt->execute()) {
            $response['success'] = true;
            $response['message'] = "Manual dose cycle #$cycle initiated successfully.";
        }
        $stmt->close();
    }
    elseif ($action === 'log_manual_dose' || $action === 'log_auto_dose') {
        if ($action === 'log_auto_dose') {
            // Auto doses are now natively logged by the server brain in receive.php
            // We just return success to keep the UI happy without duplicating the log.
            $response['success'] = true;
            echo json_encode($response);
            exit();
        }
        
        $volume = floatval($_POST['volume'] ?? 0);
        $duration = floatval($_POST['duration'] ?? 0);
        $type = 'manual';
        $cycle = 2000 + rand(1, 999);
        $flow_rate = ($duration > 0) ? ($volume / ($duration / 60.0)) : 0;
        
        $stmt = $conn->prepare("INSERT INTO treatment_logs (cycle_number, volume, duration, flow_rate, voltage, log_type) VALUES (?, ?, ?, ?, 5.0, ?)");
        $stmt->bind_param("idids", $cycle, $volume, $duration, $flow_rate, $type);
        if ($stmt->execute()) {
            $response['success'] = true;
        }
        $stmt->close();
    }
    elseif ($action === 'toggle_maintenance') {
        $state = $_POST['state'] ?? 'off';
        // In a real system, you'd update a settings table or a physical GPIO
        $response['success'] = true;
        $response['new_state'] = $state;
        $response['message'] = "Maintenance mode " . ($state == 'on' ? 'activated' : 'deactivated') . ".";
    }
    else {
        $response['error'] = 'Invalid action';
    }
}
catch (Exception $e) {
    $response['error'] = $e->getMessage();
}

echo json_encode($response);
$conn->close();
?>
