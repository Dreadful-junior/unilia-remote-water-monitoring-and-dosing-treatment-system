<?php
/**
 * Test Pump Control System
 */

require 'db_connect.php';

echo "<h1>Pump Control System Test</h1>";

// Test 1: Check hardware_settings table
echo "<h2>Test 1: Hardware Settings</h2>";
$result = $conn->query("SELECT * FROM hardware_settings WHERE id=1");
if ($result && $result->num_rows > 0) {
    $settings = $result->fetch_assoc();
    echo "<p>Current operation mode: <strong>" . $settings['operation_mode'] . "</strong></p>";
} else {
    echo "<p style='color:red'>ERROR: hardware_settings table not found or empty</p>";
}

// Test 2: Check pump_commands table
echo "<h2>Test 2: Pump Commands Table</h2>";
$result = $conn->query("SHOW TABLES LIKE 'pump_commands'");
if ($result && $result->num_rows > 0) {
    echo "<p style='color:green'>✓ pump_commands table exists</p>";

    // Check for pending commands
    $pending = $conn->query("SELECT COUNT(*) as count FROM pump_commands WHERE status='pending'");
    $row = $pending->fetch_assoc();
    echo "<p>Pending commands: " . $row['count'] . "</p>";
} else {
    echo "<p style='color:red'>✗ pump_commands table does not exist</p>";
}

// Test 3: Test API endpoints
echo "<h2>Test 3: API Endpoints</h2>";

// Test pump_control.php GET
echo "<p>Testing pump_control.php GET...</p>";
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, "http://localhost/water%20system/api/pump_control.php?api_key=your-secret-api-key-123");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
$response = curl_exec($ch);
if (curl_errno($ch)) {
    echo "<p style='color:red'>✗ GET request failed: " . curl_error($ch) . "</p>";
} else {
    $data = json_decode($response, true);
    if ($data && isset($data['success'])) {
        echo "<p style='color:green'>✓ GET request successful</p>";
    } else {
        echo "<p style='color:red'>✗ GET request returned invalid response</p>";
    }
}
curl_close($ch);

// Test toggle_pump.php mode setting
echo "<p>Testing toggle_pump.php mode setting...</p>";
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, "http://localhost/water%20system/api/toggle_pump.php");
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
    'action' => 'set_mode',
    'mode' => 'manual'
]));
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
$response = curl_exec($ch);
if (curl_errno($ch)) {
    echo "<p style='color:red'>✗ Mode setting failed: " . curl_error($ch) . "</p>";
} else {
    $data = json_decode($response, true);
    if ($data && isset($data['success']) && $data['success']) {
        echo "<p style='color:green'>✓ Mode setting successful</p>";
    } else {
        echo "<p style='color:red'>✗ Mode setting failed</p>";
    }
}
curl_close($ch);

echo "<h2>Test Complete</h2>";
$conn->close();
?>
