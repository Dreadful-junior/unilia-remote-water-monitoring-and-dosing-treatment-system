<?php
include __DIR__ . '/../db_connect.php';

// Add auto_pump_stop_at column to hardware_settings
$check = $conn->query("SHOW COLUMNS FROM hardware_settings LIKE 'auto_pump_stop_at'");
if ($check->num_rows === 0) {
    $conn->query("ALTER TABLE hardware_settings ADD COLUMN auto_pump_stop_at DATETIME NULL DEFAULT NULL");
    echo "Added auto_pump_stop_at column\n";
} else {
    echo "auto_pump_stop_at column already exists\n";
}

$conn->close();
echo "Done.\n";
