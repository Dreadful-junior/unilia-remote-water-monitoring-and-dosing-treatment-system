<?php
require 'db_connect.php';

$sql = "CREATE TABLE IF NOT EXISTS hardware_recognition (
    id INT AUTO_INCREMENT PRIMARY KEY,
    component_name VARCHAR(100) NOT NULL,
    identifier VARCHAR(50) UNIQUE NOT NULL,
    status ENUM('online', 'offline', 'not_detected') DEFAULT 'not_detected',
    last_seen TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";

if ($conn->query($sql)) {
    echo "Table hardware_recognition created successfully.\n";
    
    $components = [
        ['Turbidity Sensor', 'turbidity_sensor'],
        ['TDS Sensor', 'tds_sensor'],
        ['Temperature Sensor', 'temp_sensor'],
        ['Ultrasonic Sensor', 'ultrasonic_sensor'],
        ['Dosing Pump', 'dosing_pump']
    ];
    
    foreach ($components as $c) {
        $name = $c[0];
        $id = $c[1];
        $conn->query("INSERT IGNORE INTO hardware_recognition (component_name, identifier) VALUES ('$name', '$id')");
    }
    echo "Default components initialized.\n";
} else {
    echo "Error creating table: " . $conn->error . "\n";
}
?>
