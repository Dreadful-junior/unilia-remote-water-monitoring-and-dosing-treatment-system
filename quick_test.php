<?php
require 'db_connect.php';

echo "Database connected successfully\n";

$result = $conn->query('SELECT operation_mode FROM hardware_settings WHERE id=1');
if ($result && $result->num_rows > 0) {
    $row = $result->fetch_assoc();
    echo 'Current mode: ' . $row['operation_mode'] . "\n";
} else {
    echo "No settings found\n";
}

// Check pump_commands table
$result = $conn->query('SHOW TABLES LIKE "pump_commands"');
if ($result && $result->num_rows > 0) {
    echo "pump_commands table exists\n";

    $pending = $conn->query('SELECT COUNT(*) as count FROM pump_commands WHERE status="pending"');
    $row = $pending->fetch_assoc();
    echo 'Pending commands: ' . $row['count'] . "\n";
} else {
    echo "pump_commands table does not exist\n";
}

$conn->close();
?>
