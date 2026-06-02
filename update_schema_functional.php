<?php
require 'db_connect.php';

// 1. Treatment Settings
$conn->query("ALTER TABLE treatment_settings ADD COLUMN IF NOT EXISTS pump_flow_rate FLOAT DEFAULT 50.0");

// 2. Hardware Settings
$conn->query("ALTER TABLE hardware_settings ADD COLUMN IF NOT EXISTS turbidity_multiplier FLOAT DEFAULT 1.0");
$conn->query("ALTER TABLE hardware_settings ADD COLUMN IF NOT EXISTS tds_multiplier FLOAT DEFAULT 1.0");
$conn->query("ALTER TABLE hardware_settings ADD COLUMN IF NOT EXISTS config_version INT DEFAULT 1");

// 3. Hardware Recognition
$conn->query("ALTER TABLE hardware_recognition ADD COLUMN IF NOT EXISTS is_enabled TINYINT DEFAULT 1");

echo "Database updates completed successfully.\n";
?>
