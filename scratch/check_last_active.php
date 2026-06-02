<?php
require 'db_connect.php';
$res = $conn->query("SELECT recorded_at FROM sensor_data WHERE pump_status = 1 ORDER BY id DESC LIMIT 1");
$row = $res->fetch_assoc();
echo "Last Active Pump Record: " . ($row['recorded_at'] ?? 'Never') . "\n";
