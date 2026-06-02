<?php
session_start();                        // MUST be first — before any headers
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
require '../db_connect.php';

// Log RAW request for deep debugging
$raw_input = file_get_contents("php://input");
$log_msg = date('Y-m-d H:i:s') . " - RECV: " . json_encode($_REQUEST) . " | RAW: " . $raw_input . "\n";
file_put_contents('toggle_log.txt', $log_msg, FILE_APPEND);

// Merge all inputs
$data = array_merge($_GET, $_POST, (array)json_decode($raw_input, true));

$action = $data['action'] ?? null;
$state = $data['state'] ?? null;
$mode = $data['mode'] ?? null;
$force_off = $data['force_off'] ?? null;

// --- MASTER CONTROLLER ENABLED CHECK ---
$check_master = $conn->query("SELECT is_enabled FROM hardware_recognition WHERE identifier = 'esp32_controller' LIMIT 1");
$master_row = $check_master->fetch_assoc();
if ($master_row && $master_row['is_enabled'] == 0) {
    echo json_encode(['success' => false, 'error' => 'Action Blocked: The ESP32 Controller is currently DISABLED. Please re-enable it in Hardware Settings first.']);
    exit;
}
// ----------------------------------------

// ─── MANUAL pump control (sets mode to manual) ───
if ($action === 'pump_control' && isset($state)) {
    if ($state === 'off') {
        $conn->query("UPDATE pump_commands SET status = 'cancelled' WHERE status = 'pending'");
    }
    $sql = "UPDATE hardware_settings SET manual_pump_state = '$state', pump_command = '$state', pump_stop_at = NULL, pump_duration_sec = 0, pump_command_time = NOW() WHERE id = 1";
    if ($conn->query($sql)) {
        echo json_encode(['success' => true, 'message' => "State set to $state"]);
    } else {
        echo json_encode(['success' => false, 'error' => $conn->error]);
    }
    exit;
}

// ─── AUTO pump on/off (preserves auto mode) ───
if ($action === 'auto_pump_on') {
    // Check for cooling down period
    $check = $conn->query("SELECT next_auto_run_at FROM hardware_settings WHERE id = 1")->fetch_assoc();
    if ($check && $check['next_auto_run_at'] && strtotime($check['next_auto_run_at']) > time()) {
        $remaining = strtotime($check['next_auto_run_at']) - time();
        echo json_encode(['success' => false, 'error' => "System is cooling down. Please wait $remaining seconds."]);
        exit;
    }

    // Start pump in auto mode — keep operation_mode = 'auto'
    $duration_sec = intval($data['duration_sec'] ?? 60);
    $sql = "UPDATE hardware_settings SET operation_mode = 'auto', manual_pump_state = 'on', pump_command = 'on', pump_stop_at = DATE_ADD(NOW(), INTERVAL $duration_sec SECOND), pump_duration_sec = $duration_sec, pump_command_time = NOW(), next_auto_run_at = NULL WHERE id = 1";
    if ($conn->query($sql)) {
        echo json_encode(['success' => true, 'message' => "Auto pump ON for {$duration_sec}s"]);
    } else {
        echo json_encode(['success' => false, 'error' => $conn->error]);
    }
    exit;
}

if ($action === 'auto_pump_off') {
    // Stop pump but stay in auto mode
    $sql = "UPDATE hardware_settings SET manual_pump_state = 'off', pump_command = 'off', pump_stop_at = NULL, pump_duration_sec = 0, pump_command_time = NOW() WHERE id = 1";
    if ($conn->query($sql)) {
        echo json_encode(['success' => true, 'message' => 'Auto pump OFF']);
    } else {
        echo json_encode(['success' => false, 'error' => $conn->error]);
    }
    exit;
}

// ─── MANUAL volumetric dose ───
if ($action === 'manual_dose' && isset($data['amount'])) {
    $amount = floatval($data['amount']);
    $stmt = $conn->prepare("INSERT INTO pump_commands (command, target_ml, status) VALUES ('dose_ml', ?, 'pending')");
    $stmt->bind_param("d", $amount);
    
    if ($stmt->execute()) {
        // Also ensure mode is manual
        $conn->query("UPDATE hardware_settings SET operation_mode = 'manual' WHERE id = 1");
        echo json_encode(['success' => true, 'message' => "Dose of $amount ml queued"]);
    } else {
        echo json_encode(['success' => false, 'error' => $conn->error]);
    }
    $stmt->close();
    exit;
}

// ─── Switch mode ───
if ($action === 'set_mode' && $mode) {
    $safe_mode = $conn->real_escape_string($mode);
    $sql = "UPDATE hardware_settings SET operation_mode = '$safe_mode'";
    if ($force_off) {
        $sql .= ", manual_pump_state = 'off', pump_command = 'off', pump_stop_at = NULL, pump_duration_sec = 0";
        $conn->query("UPDATE pump_commands SET status = 'cancelled' WHERE status = 'pending'");
    }
    if ($safe_mode === 'auto') {
        $sql .= ", next_auto_run_at = NOW()";
    }
    $sql .= " WHERE id = 1";
    
    if ($conn->query($sql)) {
        echo json_encode(['success' => true, 'mode' => $mode]);
    } else {
        echo json_encode(['success' => false, 'error' => $conn->error]);
    }
    exit;
}

// ─── Reset daily dose counter ───
if ($action === 'reset_daily_dose') {
    // Session is already started at top of file.
    // Only admins/managers can reset — check role.
    $role = $_SESSION['role'] ?? '';
    if (!isset($_SESSION['user_id']) || !in_array($role, ['manager', 'admin', 'technician'])) {
        echo json_encode(['success' => false, 'error' => 'Insufficient permissions. Please log in.', 'session_role' => $role]);
        exit;
    }
    // Delete today's treatment logs to reset the daily counter
    $deleted = $conn->query("DELETE FROM treatment_logs WHERE DATE(created_at) = CURDATE()");
    $rows_affected = $conn->affected_rows;
    echo json_encode(['success' => true, 'message' => "Daily dose counter reset to 0. ($rows_affected records cleared)"]);
    exit;
}

// ─── Save Auto Schedule ───
if ($action === 'save_auto_schedule') {
    $run_min = intval($data['run_min'] ?? 1);
    $interval_min = intval($data['interval_min'] ?? 30);
    $run_sec = $run_min * 60;
    
    $sql = "UPDATE treatment_settings SET auto_run_duration_sec = $run_sec, auto_interval_minutes = $interval_min WHERE id = 1";
    if ($conn->query($sql)) {
        // Reset next run time to NOW so the scheduler evaluates immediately
        $conn->query("UPDATE hardware_settings SET next_auto_run_at = NOW() WHERE id = 1");
        echo json_encode(['success' => true, 'message' => "Schedule saved"]);
    } else {
        echo json_encode(['success' => false, 'error' => $conn->error]);
    }
    exit;
}

echo json_encode(['success' => false, 'error' => 'No valid action', 'received' => $data]);
?>
