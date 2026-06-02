<?php
/**
 * Sensor Data Reception API - Vanilla PHP
 * Accepts POST data from ESP32/Arduino sensors
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

require '../db_connect.php';

// Ensure sensor_data table exists and has the expected columns
function ensureSensorDataTable($conn) {
    $create_table_sql = "CREATE TABLE IF NOT EXISTS sensor_data (
        id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        turbidity FLOAT NOT NULL,
        tds FLOAT NOT NULL,
        temperature FLOAT NOT NULL,
        ph FLOAT NULL,
        chlorine FLOAT NULL,
        distance_cm FLOAT NULL,
        water_level FLOAT NULL DEFAULT 0.0,
        pump_status TINYINT(1) DEFAULT 0,
        recorded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    $conn->query($create_table_sql);

    $columns = [
        'ph' => 'FLOAT NULL AFTER temperature',
        'chlorine' => 'FLOAT NULL AFTER ph',
        'distance_cm' => 'FLOAT NULL AFTER ph',
        'water_level' => 'FLOAT NULL DEFAULT 0.0 AFTER distance_cm',
        'pump_status' => 'TINYINT(1) DEFAULT 0 AFTER water_level',
        'manual_pump_state' => "ENUM('on', 'off') DEFAULT 'off'"
    ];

    foreach ($columns as $column => $definition) {
        $check = $conn->query("SHOW COLUMNS FROM sensor_data LIKE '$column'");
        if ($check && $check->num_rows === 0) {
            // Check hardware_settings for manual_pump_state if it doesn't fit in sensor_data
            if ($column === 'manual_pump_state') {
                 $check_settings = $conn->query("SHOW COLUMNS FROM hardware_settings LIKE 'manual_pump_state'");
                 if ($check_settings && $check_settings->num_rows === 0) {
                     $conn->query("ALTER TABLE hardware_settings ADD COLUMN manual_pump_state ENUM('on', 'off') DEFAULT 'off'");
                 }
                 continue;
            }
            try {
                $conn->query("ALTER TABLE sensor_data ADD COLUMN $column $definition");
                error_log("Added column: $column");
            } catch (Exception $e) {
                error_log("Error adding column $column: " . $e->getMessage());
            }
        }
    }
}

function isNoDataPayload($turbidity, $tds, $temperature, $distance_cm, $water_level, $ph, $chlorine) {
    return $turbidity === 0.0
        && $tds === 0.0
        && $temperature === 0.0
        && ($distance_cm === null || $distance_cm === 0.0)
        && ($water_level === null || $water_level === 0.0)
        && ($ph === null || $ph === 0.0)
        && ($chlorine === null || $chlorine === 0.0);
}

ensureSensorDataTable($conn);

// Simple API key authentication
$valid_api_key = 'your-secret-api-key-123'; // Change this in production

// Get JSON input or form data
$input = file_get_contents("php://input");

// --- DEBUG LOGGING ---
$log_file = __DIR__ . '/../logs/receive_debug.log';
if (!file_exists(__DIR__ . '/../logs')) mkdir(__DIR__ . '/../logs', 0777, true);
$remote_ip = $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN';
file_put_contents($log_file, "[" . date('Y-m-d H:i:s') . "] IP: $remote_ip | RAW: " . $input . "\n", FILE_APPEND);
// ---------------------

$data = json_decode($input, true);

// If not JSON, try POST data
if (!$data) {
    $data = $_POST;
}

// Validate API key
$api_key = isset($data['api_key']) ? trim($data['api_key']) : '';
if ($api_key !== $valid_api_key) {
    http_response_code(401);
    echo json_encode(['error' => 'Invalid API key']);
    exit;
}

// Update ESP32 Controller status as soon as API key is verified (Heartbeat)
$remote_ip = $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN';

// CHECK IF CONTROLLER IS ENABLED
$check_enabled = $conn->query("SELECT is_enabled FROM hardware_recognition WHERE identifier = 'esp32_controller' LIMIT 1");
$hw_master = $check_enabled->fetch_assoc();
if ($hw_master && $hw_master['is_enabled'] == 0) {
    // If master is disabled, we update 'last_seen' but don't process data
    $conn->query("UPDATE hardware_recognition SET status = 'offline', last_seen = NOW(), ip_address = '$remote_ip' WHERE identifier = 'esp32_controller'");
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Controller Disabled by Administrator']);
    exit;
}

$stmt_hw = $conn->prepare("UPDATE hardware_recognition SET status = 'online', last_seen = NOW(), ip_address = ? WHERE identifier = 'esp32_controller'");
$stmt_hw->bind_param("s", $remote_ip);
$stmt_hw->execute();
$stmt_hw->close();

// Validate required fields
$required_fields = ['turbidity', 'tds', 'temperature'];
foreach ($required_fields as $field) {
    if (!isset($data[$field]) || !is_numeric($data[$field])) {
        http_response_code(400);
        echo json_encode(['error' => "Missing or invalid field: $field"]);
        exit;
    }
}

// Sanitize and prepare data
$turbidity_raw = floatval($data['turbidity']);
$tds_raw = floatval($data['tds']);
$temperature = floatval($data['temperature']);
$ph = isset($data['ph']) && is_numeric($data['ph']) ? floatval($data['ph']) : null;
$chlorine = isset($data['chlorine']) && is_numeric($data['chlorine']) ? floatval($data['chlorine']) : null;
$distance_cm = isset($data['distance_cm']) && is_numeric($data['distance_cm']) ? floatval($data['distance_cm']) : null;
$water_level_raw = isset($data['water_level']) && is_numeric($data['water_level']) ? floatval($data['water_level']) : null;
$pump_status = isset($data['pump']) ? (int)$data['pump'] : 0;
$pump_runtime = isset($data['pump_runtime']) ? (int)$data['pump_runtime'] : 0;
$target_dose = isset($data['target_dose']) ? floatval($data['target_dose']) : 0;

// --- APPLY CALIBRATION SETTINGS ---
$mon_res = $conn->query("SELECT tds_slope, tds_intercept, turbidity_offset, tank_height_cm, tank_min_cm FROM monitoring_settings WHERE id = 1 LIMIT 1");
$mon = $mon_res->fetch_assoc();

$tds_slope = floatval($mon['tds_slope'] ?? 1.0);
$tds_intercept = floatval($mon['tds_intercept'] ?? 0.0);
$turb_offset = floatval($mon['turbidity_offset'] ?? 0.0);

$turbidity = max(0.0, $turbidity_raw + $turb_offset);
$tds = max(0.0, ($tds_raw * $tds_slope) + $tds_intercept);
$water_level = $water_level_raw; // Will be refined below if distance is available

// Log dosing activity if pump is running
if ($pump_status === 1 && $pump_runtime > 0) {
    // We can use this to update a 'live_status' or just log to system activity
    // For now, let's ensure it's available for the SSE to pick up
}

// -----------------------------------------------------
// HARDWARE RECOGNITION & CALIBRATION LOGIC
// -----------------------------------------------------
// We do this BEFORE any exits so status updates even on empty heartbeats
$hw_updates = [];

// Fetch enabled states
$enabled_map = [];
$e_res = $conn->query("SELECT identifier, is_enabled FROM hardware_recognition");
while ($row = $e_res->fetch_assoc()) {
    $enabled_map[$row['identifier']] = $row['is_enabled'];
}

// 1. Temperature (DS18B20 returns -127 if disconnected)
if (($enabled_map['temp_sensor'] ?? 1) == 1) {
    $temp_status = ($temperature == -127.0 || $temperature == 85.0 || $temperature == 0.0) ? 'offline' : 'online';
    $hw_updates['temp_sensor'] = $temp_status;
}

// 2. Ultrasonic (HC-SR04 returns -1 or 0 if no echo)
if (($enabled_map['ultrasonic_sensor'] ?? 1) == 1) {
    // If we have either a raw distance or a calculated water level > 0, the sensor is active
    $has_distance = ($distance_cm !== null && $distance_cm > 0);
    $has_level = ($water_level !== null && $water_level > 0);
    $ultra_status = ($has_distance || $has_level) ? 'online' : 'offline';
    $hw_updates['ultrasonic_sensor'] = $ultra_status;
}

// 3. Turbidity & TDS
// --- HARDWARE FAULT DETECTION (WITH DEBOUNCING) ---
function detectHardwareFault($conn, $identifier, $isFaulty, $componentName) {
    // Ensure the column exists
    $conn->query("ALTER TABLE hardware_recognition ADD COLUMN IF NOT EXISTS consecutive_faults INT DEFAULT 0");
    
    $res = $conn->query("SELECT consecutive_faults, status FROM hardware_recognition WHERE identifier = '$identifier' LIMIT 1");
    if ($res && $res->num_rows > 0) {
        $row = $res->fetch_assoc();
        $count = intval($row['consecutive_faults']);
        $oldStatus = $row['status'];
        
        if ($isFaulty) {
            $count++;
            // If we hit exactly 10, trigger a CRITICAL hardware fault alert
            if ($count === 10) {
                $msg = "HARDWARE FAULT DETECTED: The $componentName appears to be malfunctioning or disconnected (10 consecutive failures). System has marked it as OFFLINE.";
                $stmt = $conn->prepare("INSERT INTO alerts (message, severity, is_read, created_at) VALUES (?, 'critical', 0, NOW())");
                $stmt->bind_param("s", $msg);
                $stmt->execute();
                $stmt->close();
                
                $conn->query("UPDATE hardware_recognition SET status = 'offline', consecutive_faults = $count WHERE identifier = '$identifier'");
            } else {
                $conn->query("UPDATE hardware_recognition SET consecutive_faults = $count WHERE identifier = '$identifier'");
            }
        } else {
            // Normal reading - Reset fault counter
            if ($count > 0) {
                $conn->query("UPDATE hardware_recognition SET consecutive_faults = 0, status = 'online' WHERE identifier = '$identifier'");
            }
        }
    }
}

// Pump Fault Detection (Feedback Loop)
// If server says ON but ESP32 says OFF for too long, it's a fault
$isPumpFaulty = false;
$hw_s = $conn->query("SELECT pump_command, pump_command_time FROM hardware_settings WHERE id = 1 LIMIT 1")->fetch_assoc();
if ($hw_s && ($hw_s['pump_command'] === 'on' || $hw_s['pump_command'] === 'dose') && $pump_status === 0) {
    $cmdTime = strtotime($hw_s['pump_command_time'] ?? 'now');
    if ((time() - $cmdTime) > 12) { // 12s grace period for sync
        $isPumpFaulty = true;
    }
}

// Define sensor fault states for detection
$isTurbidityFaulty = ($turbidity <= 0.1);
$isTdsFaulty       = ($tds <= 5.0);
$isTempFaulty      = ($temperature == -127.0 || $temperature == 85.0 || $temperature == 0.0);
$isUltraFaulty     = !($has_distance || $has_level);

if (($enabled_map['turbidity_sensor'] ?? 1) == 1) detectHardwareFault($conn, 'turbidity_sensor', $isTurbidityFaulty, "Turbidity Sensor");
if (($enabled_map['tds_sensor'] ?? 1) == 1) detectHardwareFault($conn, 'tds_sensor', $isTdsFaulty, "TDS Sensor");
if (($enabled_map['temp_sensor'] ?? 1) == 1) detectHardwareFault($conn, 'temp_sensor', $isTempFaulty, "Temperature Sensor");
if (($enabled_map['ultrasonic_sensor'] ?? 1) == 1) detectHardwareFault($conn, 'ultrasonic_sensor', $isUltraFaulty, "Ultrasonic Sensor");
if (($enabled_map['dosing_pump'] ?? 1) == 1) detectHardwareFault($conn, 'dosing_pump', $isPumpFaulty, "Dosing Pump");
// --------------------------------------------------

if (($enabled_map['turbidity_sensor'] ?? 1) == 1) {
    // Noise floor check: If it's effectively 0 (noise < 0.1), it's offline
    $hw_updates['turbidity_sensor'] = ($turbidity > 0.1) ? 'online' : 'offline';
}
if (($enabled_map['tds_sensor'] ?? 1) == 1) {
    // Noise floor check: If it's effectively 0 (noise < 5.0), it's offline
    $hw_updates['tds_sensor'] = ($tds > 5.0) ? 'online' : 'offline';
}

// 4. Pump Status
// We no longer hardcode it to 'online' here because detectHardwareFault handles the 'offline' state if a command fails.
// However, we update its 'last_seen' to show the controller is talking.
$conn->query("UPDATE hardware_recognition SET last_seen = NOW() WHERE identifier = 'dosing_pump'");

// Update Pump Stats if available
if ($pump_status === 1) {
    $stmt_pump = $conn->prepare("UPDATE hardware_recognition SET last_dose_ml = ?, current_runtime_sec = ? WHERE identifier = 'dosing_pump'");
    $stmt_pump->bind_param("di", $target_dose, $pump_runtime);
    $stmt_pump->execute();
    $stmt_pump->close();
}

foreach ($hw_updates as $id => $status) {
    // Check if entry exists first
    $check_hw = $conn->prepare("SELECT id FROM hardware_recognition WHERE identifier = ?");
    $check_hw->bind_param("s", $id);
    $check_hw->execute();
    $hw_res = $check_hw->get_result();
    
    if ($hw_res->num_rows > 0) {
        $stmt_hw = $conn->prepare("UPDATE hardware_recognition SET status = ?, last_seen = NOW() WHERE identifier = ?");
        $stmt_hw->bind_param("ss", $status, $id);
        $stmt_hw->execute();
        $stmt_hw->close();
    } else {
        // Auto-register new component
        $name = ucwords(str_replace('_', ' ', $id));
        $stmt_hw = $conn->prepare("INSERT INTO hardware_recognition (component_name, identifier, status, last_seen) VALUES (?, ?, ?, NOW())");
        $stmt_hw->bind_param("sss", $name, $id, $status);
        $stmt_hw->execute();
        $stmt_hw->close();
    }
    $check_hw->close();
}

// NOW check if we should ignore the storage part
// Modified: We store data even if it's zero, as long as it's a valid heartbeat
// But we might want to skip if it's EXACTLY the same as last reading and everything is zero?
// Actually, the user wants to see zeros.

// Derive water percentage from distance if needed
if ($distance_cm !== null && $distance_cm > 0) {
    $t_res = $conn->query("SELECT tank_height_cm, tank_min_cm FROM monitoring_settings WHERE id = 1 LIMIT 1");
    $t_row = $t_res->fetch_assoc();
    $tankEmptyCm = floatval($t_row['tank_height_cm'] ?? 30.0);
    $tankFullCm  = floatval($t_row['tank_min_cm']    ?? 5.0);

    $water_level = (($tankEmptyCm - $distance_cm) / ($tankEmptyCm - $tankFullCm)) * 100.0;
    $water_level = max(0.0, min(100.0, $water_level));
}
// -----------------------------------------------------

// Insert into database
try {
    // Check if sensor_data table has ph and chlorine columns
    $check_columns = $conn->query("SHOW COLUMNS FROM sensor_data LIKE 'ph'");
    $has_ph = $check_columns->num_rows > 0;

    $check_columns = $conn->query("SHOW COLUMNS FROM sensor_data LIKE 'chlorine'");
    $has_chlorine = $check_columns->num_rows > 0;

    if ($has_ph && $has_chlorine && $ph !== null && $chlorine !== null) {
        if ($distance_cm !== null && $water_level !== null) {
            $stmt = $conn->prepare("INSERT INTO sensor_data (turbidity, tds, temperature, ph, chlorine, distance_cm, water_level, pump_status, recorded_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())");
            $stmt->bind_param("dddddddi", $turbidity, $tds, $temperature, $ph, $chlorine, $distance_cm, $water_level, $pump_status);
        } elseif ($water_level !== null) {
            $stmt = $conn->prepare("INSERT INTO sensor_data (turbidity, tds, temperature, ph, chlorine, water_level, pump_status, recorded_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
            $stmt->bind_param("ddddddi", $turbidity, $tds, $temperature, $ph, $chlorine, $water_level, $pump_status);
        } else {
            $stmt = $conn->prepare("INSERT INTO sensor_data (turbidity, tds, temperature, ph, chlorine, pump_status, recorded_at) VALUES (?, ?, ?, ?, ?, ?, NOW())");
            $stmt->bind_param("dddddi", $turbidity, $tds, $temperature, $ph, $chlorine, $pump_status);
        }
    } elseif ($water_level !== null && $distance_cm !== null) {
        $stmt = $conn->prepare("INSERT INTO sensor_data (turbidity, tds, temperature, distance_cm, water_level, pump_status, recorded_at) VALUES (?, ?, ?, ?, ?, ?, NOW())");
        $stmt->bind_param("dddddi", $turbidity, $tds, $temperature, $distance_cm, $water_level, $pump_status);
    } elseif ($water_level !== null) {
        $stmt = $conn->prepare("INSERT INTO sensor_data (turbidity, tds, temperature, water_level, pump_status, recorded_at) VALUES (?, ?, ?, ?, ?, NOW())");
        $stmt->bind_param("ddddi", $turbidity, $tds, $temperature, $water_level, $pump_status);
    } else {
        $stmt = $conn->prepare("INSERT INTO sensor_data (turbidity, tds, temperature, pump_status, recorded_at) VALUES (?, ?, ?, ?, NOW())");
        $stmt->bind_param("dddi", $turbidity, $tds, $temperature, $pump_status);
    }

    $stmt->execute();
    $insert_id = $conn->insert_id;
    $stmt->close();

    // Check thresholds and trigger alerts if needed
    if (file_exists('../includes/alert_processor.php')) {
        require '../includes/alert_processor.php';
        if (function_exists('checkThresholds')) {
            // Only pass data for sensors that are ENABLED
            $thresholdData = [];
            if (($enabled_map['turbidity_sensor'] ?? 1) == 1) $thresholdData['turbidity'] = $turbidity;
            if (($enabled_map['tds_sensor'] ?? 1) == 1) $thresholdData['tds'] = $tds;
            if (($enabled_map['temp_sensor'] ?? 1) == 1) $thresholdData['temperature'] = $temperature;
            if (($enabled_map['dosing_pump'] ?? 1) == 1) {
                $thresholdData['pump_status'] = $pump_status;
                $thresholdData['pump_runtime'] = $pump_runtime;
            }
            
            if (!empty($thresholdData)) {
                checkThresholds($conn, $thresholdData);
            }
        }
    }
    
    // --- SERVER-SIDE AUTOMATION SCHEDULER & STATE MANAGEMENT ---
    
    // Ensure necessary columns exist for the server-brain
    $conn->query("ALTER TABLE hardware_settings ADD COLUMN IF NOT EXISTS pump_stop_at DATETIME DEFAULT NULL");
    $conn->query("ALTER TABLE hardware_settings ADD COLUMN IF NOT EXISTS pump_duration_sec INT DEFAULT 60");
    $conn->query("ALTER TABLE hardware_settings ADD COLUMN IF NOT EXISTS pump_command ENUM('on','off','dose') DEFAULT 'off'");
    $conn->query("ALTER TABLE hardware_settings ADD COLUMN IF NOT EXISTS pump_command_time DATETIME DEFAULT NULL");

    $now = time();
    $hw = $conn->query("SELECT * FROM hardware_settings WHERE id = 1 LIMIT 1")->fetch_assoc();
    $ts = $conn->query("SELECT * FROM treatment_settings WHERE id = 1 LIMIT 1")->fetch_assoc();
    $mon = $conn->query("SELECT * FROM monitoring_settings WHERE id = 1 LIMIT 1")->fetch_assoc();

    // Ensure new columns exist for timeouts
    $conn->query("ALTER TABLE monitoring_settings ADD COLUMN IF NOT EXISTS manual_timeout_sec INT DEFAULT 300");
    $conn->query("ALTER TABLE monitoring_settings ADD COLUMN IF NOT EXISTS auto_timeout_sec INT DEFAULT 600");

    $mode        = $hw['operation_mode']    ?? 'manual';
    $pumpCmd     = $hw['pump_command']      ?? 'off';
    $pumpState   = $hw['manual_pump_state'] ?? 'off';
    $pumpStopAt  = $hw['pump_stop_at']      ?? null;
    $durationSec = intval($hw['pump_duration_sec'] ?? 60);
    $maintenance = $hw['maintenance_mode']  ?? 'off';

    // 1. AUTO-STOP LOGIC
    if ($pumpStopAt && strtotime($pumpStopAt) <= $now && $pumpCmd === 'on') {
        $intervalMinutes = intval($ts['auto_interval_minutes'] ?? 30);
        $nextRunAt = date('Y-m-d H:i:s', $now + ($intervalMinutes * 60));
        
        $conn->query("
            UPDATE hardware_settings
            SET
                manual_pump_state = 'off',
                pump_command      = 'off',
                pump_stop_at      = NULL,
                pump_duration_sec = 0,
                next_auto_run_at  = '$nextRunAt'
            WHERE id = 1
        ");
        $pumpCmd   = 'off';
        $pumpState = 'off';
        file_put_contents($log_file, "[" . date('Y-m-d H:i:s') . "] [WATCHDOG] Auto-stop triggered. Next run at: $nextRunAt\n", FILE_APPEND);
        
        // CRITICAL: Reload hardware settings so the scheduler (next) knows we just finished
        $hw = $conn->query("SELECT * FROM hardware_settings WHERE id = 1 LIMIT 1")->fetch_assoc();
    }

    // 2. SERVER-SIDE SCHEDULER (Only runs if in AUTO, not in maintenance, and pump is OFF)
    if ($mode === 'auto' && $maintenance !== 'on' && $pumpState === 'off') {
        $runDurationSec  = intval($ts['auto_run_duration_sec']  ?? intval($ts['dosing_duration'] ?? 60));
        $intervalMinutes = intval($ts['auto_interval_minutes']  ?? 30);
        $maxDailyML      = floatval($ts['max_daily_dose_ml']    ?? 500.0);
        $flowRateMLMin   = floatval($ts['pump_flow_rate']       ?? 100.0);
        $autoEnabled     = intval($ts['auto_enabled']           ?? 1);

        if ($autoEnabled) {
            $todayStart = date('Y-m-d 00:00:00');
            $doseRow = $conn->query("SELECT COALESCE(SUM(volume), 0) AS total FROM treatment_logs WHERE created_at >= '$todayStart'")->fetch_assoc();
            $todayML = floatval($doseRow['total'] ?? 0);
            $sessionML = ($runDurationSec / 60.0) * $flowRateMLMin;

            if (($todayML + $sessionML) <= $maxDailyML) {
                // Always check the last log for sensor-gate safety
                $lastTreat = $conn->query("SELECT MAX(created_at) AS last_time FROM treatment_logs")->fetch_assoc();
                $lastTreatTime = $lastTreat['last_time'] ? strtotime($lastTreat['last_time']) : 0;
                $minutesSinceLast = ($now - $lastTreatTime) / 60.0;

                $nextAutoRun = $hw['next_auto_run_at'] ?? null;
                $intervalElapsed = false;
                
                if ($nextAutoRun && strtotime($nextAutoRun) > 0) {
                    $intervalElapsed = (strtotime($nextAutoRun) <= $now);
                } else {
                    // Fallback to log check if next_auto_run_at is missing or invalid
                    $intervalElapsed = ($minutesSinceLast >= $intervalMinutes);
                }
                
                // thresholds already loaded above
                
                $sensorTriggered = false;
                $triggerReason = '';
                
                if ($mon) {
                    if (floatval($turbidity) > floatval($mon['max_turbidity']) && ($enabled_map['turbidity_sensor'] ?? 1) == 1) {
                        $sensorTriggered = true;
                        $triggerReason .= "turbidity={$turbidity} > {$mon['max_turbidity']} NTU; ";
                    }
                    if (floatval($tds) > floatval($mon['max_tds']) && ($enabled_map['tds_sensor'] ?? 1) == 1) {
                        $sensorTriggered = true;
                        $triggerReason .= "tds={$tds} > {$mon['max_tds']} PPM; ";
                    }
                }
                
                $minSensorGapMin = 2; // Reduced to 2 minutes for faster response
                $sensorAllowed = ($minutesSinceLast >= $minSensorGapMin);
                
                $shouldTrigger = false;
                if ($intervalElapsed) {
                    $shouldTrigger = true;
                    $triggerReason = "Time-based scheduled run (interval elapsed)";
                } elseif ($sensorTriggered && $sensorAllowed) { 
                    // NEW: Allow sensor-based triggers even if interval hasn't elapsed
                    // as long as the minimum safety gap (sensorAllowed) has passed.
                    $shouldTrigger = true;
                    $triggerReason = "Critical Sensor Interrupt: " . $triggerReason;
                } else {
                    // Log why we didn't trigger if it was close
                    if ($sensorTriggered && !$sensorAllowed) {
                        file_put_contents($log_file, "[" . date('Y-m-d H:i:s') . "] [SCHEDULER] Sensor high but blocked by safety gap (2m). Last run was too recent.\n", FILE_APPEND);
                    } elseif ($sensorTriggered && !$intervalElapsed) {
                         file_put_contents($log_file, "[" . date('Y-m-d H:i:s') . "] [SCHEDULER] Sensor high - Bypassing schedule for immediate treatment.\n", FILE_APPEND);
                    }
                }
                
                if ($shouldTrigger) {
                    $stopAt = date('Y-m-d H:i:s', $now + $runDurationSec);
                    // Clear next_auto_run_at so we don't trigger again until this one finishes
                    $conn->query("
                        UPDATE hardware_settings
                        SET
                            manual_pump_state = 'on',
                            pump_command      = 'on',
                            pump_duration_sec = $runDurationSec,
                            pump_command_time = NOW(),
                            pump_stop_at      = '$stopAt',
                            next_auto_run_at  = NULL
                        WHERE id = 1
                    ");
                    $volumeML = round(($runDurationSec / 60.0) * $flowRateMLMin, 2);
                    $conn->query("INSERT INTO treatment_logs (log_type, volume, duration, trigger_reason, created_at) VALUES ('auto', $volumeML, $runDurationSec, '" . $conn->real_escape_string($triggerReason) . "', NOW())");
                    
                    // --- EMERGENCY NOTIFICATION ---
                    if (strpos($triggerReason, 'Critical Sensor Interrupt') !== false) {
                        $alertMsg = "EMERGENCY INTERVENTION: System detected critical water quality issues (" . str_replace("Critical Sensor Interrupt: ", "", $triggerReason) . "). Bypassing scheduled interval to start immediate treatment.";
                        
                        // Insert Alert into DB for Dashboard
                        $stmt_alt = $conn->prepare("INSERT INTO alerts (message, severity, is_read, created_at) VALUES (?, 'emergency', 0, NOW())");
                        $stmt_alt->bind_param("s", $alertMsg);
                        $stmt_alt->execute();
                        $stmt_alt->close();
                        
                        // Send Emergency Emails
                        if (file_exists('../includes/alert_processor.php')) {
                            if (!function_exists('sendAlertEmails')) {
                                require_once '../includes/alert_processor.php';
                            }
                            if (function_exists('sendAlertEmails')) {
                                sendAlertEmails($conn, $alertMsg, 'emergency');
                            }
                        }
                    }
                    // ------------------------------

                    $pumpCmd = 'on';
                    $pumpState = 'on';
                    $durationSec = $runDurationSec;
                    file_put_contents($log_file, "[" . date('Y-m-d H:i:s') . "] [SCHEDULER] Triggered: $triggerReason\n", FILE_APPEND);
                }
            }
        }
    }

    // 3. SYNCHRONIZATION WITH ESP32 & COOLING DOWN LOGIC
    // Refresh $hw and $cmdTime in case the scheduler just triggered a new run
    $hw = $conn->query("SELECT * FROM hardware_settings WHERE id = 1 LIMIT 1")->fetch_assoc();
    $cmdTime = strtotime($hw['pump_command_time'] ?? 'now');
    $secondsSinceCmd = $now - $cmdTime;

    if ($pumpState === 'on' && $pump_status === 0 && $secondsSinceCmd > 10) {
        // Pump was running (or supposed to be) and now it's off, and it's been at least 10s.
        // This is a legitimate completion of a treatment cycle.
        $intervalMinutes = intval($ts['auto_interval_minutes'] ?? 30);
        $nextRunAt = date('Y-m-d H:i:s', $now + ($intervalMinutes * 60));
        
        $conn->query("UPDATE hardware_settings SET manual_pump_state = 'off', pump_command = 'off', pump_stop_at = NULL, next_auto_run_at = '$nextRunAt' WHERE id = 1");
        $pumpState = 'off';
        file_put_contents($log_file, "[" . date('Y-m-d H:i:s') . "] [SYNC] Natural stop detected. Cooling down started. Next run at: $nextRunAt\n", FILE_APPEND);
    } elseif ($pump_status === 0 && $pumpState === 'on' && $pumpCmd === 'off') {
        // Server-side forced stop was received by ESP32
        $conn->query("UPDATE hardware_settings SET manual_pump_state = 'off' WHERE id = 1");
        $pumpState = 'off';
    }
    
    if ($pump_status === 1 && $pumpState === 'on') {
        // Redundant - current_runtime_sec is already updated in hardware_recognition
    }

    // 4. PREPARE ESP32 COMMAND
    $command_mode = $mode;
    $command_state = $pumpCmd;
    $command_extra = 0;
    
    if ($mode === 'auto') {
        // In auto mode, we send the actual pump command state.
        // The ESP32 firmware now handles duration_sec internally.
        $command_mode = 'auto';
        $command_state = $pumpCmd;
    } elseif ($mode === 'manual') {
        $command_mode = 'manual';
        $command_state = $pumpState;
        
        $pump_cmd_res = $conn->query("SELECT id, command, target_ml FROM pump_commands WHERE status = 'pending' AND command = 'dose_ml' ORDER BY created_at DESC LIMIT 1");
        if ($pump_cmd_res && $pump_cmd_res->num_rows > 0) {
            $pump_cmd = $pump_cmd_res->fetch_assoc();
            $command_state = 'dose';
            $command_extra = floatval($pump_cmd['target_ml']);
            $conn->query("UPDATE pump_commands SET status = 'completed', executed_at = NOW() WHERE id = " . $pump_cmd['id']);
        }
    }

    // Return success with command instructions
    $response = [
        'success' => true,
        'message' => 'Data received and stored',
        'id' => $insert_id,
        'timestamp' => date('Y-m-d H:i:s'),
        'command_mode' => $command_mode,
        'command_state' => $command_state,
        'command_extra' => $command_extra,
        'duration_sec' => $durationSec,
        'max_turbidity' => floatval($mon['max_turbidity'] ?? 200),
        'max_tds' => floatval($mon['max_tds'] ?? 500),
        'max_temp' => floatval($mon['max_temp'] ?? 35),
        'tank_height' => floatval($mon['tank_height_cm'] ?? 50.0),
        'tank_capacity' => floatval($mon['tank_capacity_litres'] ?? 10.0),
        'manual_timeout_sec' => intval($mon['manual_timeout_sec'] ?? 300),
        'auto_timeout_sec' => intval($mon['auto_timeout_sec'] ?? 600)
    ];
    
    $json_resp = json_encode($response);
    file_put_contents($log_file, "[" . date('Y-m-d H:i:s') . "] RESP: " . $json_resp . "\n", FILE_APPEND);
    echo $json_resp;
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
    error_log("Sensor data insertion error: " . $e->getMessage());
}

$conn->close();
?>
