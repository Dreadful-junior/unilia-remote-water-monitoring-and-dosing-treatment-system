<?php
require 'db_connect.php';

$hw = $conn->query("SELECT pump_command, pump_command_time, NOW() as current_db_time FROM hardware_settings WHERE id = 1")->fetch_assoc();
echo "--- Hardware Settings ---\n";
print_r($hw);

$data = $conn->query("SELECT pump_status, recorded_at FROM sensor_data ORDER BY id DESC LIMIT 1")->fetch_assoc();
echo "\n--- Latest Sensor Data ---\n";
print_r($data);

$rec = $conn->query("SELECT status, consecutive_faults FROM hardware_recognition WHERE identifier = 'dosing_pump'")->fetch_assoc();
echo "\n--- Hardware Recognition (Pump) ---\n";
print_r($rec);

$diff = time() - strtotime($hw['pump_command_time']);
echo "\nTime difference (PHP time() - command_time): $diff seconds\n";
echo "PHP time(): " . time() . " (" . date('Y-m-d H:i:s') . ")\n";
