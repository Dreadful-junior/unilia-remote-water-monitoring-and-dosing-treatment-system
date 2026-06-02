<?php
require 'db_connect.php';
echo "TREATMENT SETTINGS:\n";
$r = $conn->query("SELECT * FROM treatment_settings");
print_r($r->fetch_assoc());
echo "\nHARDWARE SETTINGS:\n";
$r = $conn->query("SELECT * FROM hardware_settings");
print_r($r->fetch_assoc());
echo "\nLOGS TODAY:\n";
$today = date('Y-m-d 00:00:00');
$r = $conn->query("SELECT SUM(volume) as total FROM treatment_logs WHERE created_at >= '$today'");
print_r($r->fetch_assoc());
