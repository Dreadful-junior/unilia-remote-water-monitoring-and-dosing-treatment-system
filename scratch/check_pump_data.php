<?php
require 'db_connect.php';
$res = $conn->query("SELECT COUNT(*) as count FROM sensor_data WHERE pump_status = 1");
$row = $res->fetch_assoc();
echo "Active Pump Records: " . $row['count'] . "\n";

$res = $conn->query("SELECT COUNT(*) as count FROM sensor_data");
$row = $res->fetch_assoc();
echo "Total Records: " . $row['count'] . "\n";
