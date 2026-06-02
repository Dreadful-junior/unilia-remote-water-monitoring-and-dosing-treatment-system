<?php
require 'db_connect.php';
$conn->query("UPDATE monitoring_settings SET tank_height_cm = 21, tank_min_cm = 3 WHERE id = 1");
echo "Tank calibration updated: Height 21cm, Min Gap 3cm";
?>
