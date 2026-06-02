<?php
require 'db_connect.php';
$hw = $conn->query("SELECT operation_mode, maintenance_mode FROM hardware_settings WHERE id=1")->fetch_assoc();
$mon = $conn->query("SELECT max_turbidity FROM monitoring_settings WHERE id=1")->fetch_assoc();
$treat = $conn->query("SELECT auto_enabled FROM treatment_settings WHERE id=1")->fetch_assoc();
echo json_encode([
    'hardware' => $hw,
    'monitoring' => $mon,
    'treatment' => $treat
], JSON_PRETTY_PRINT);
?>
