<?php
require 'db_connect.php';
$conn->query("UPDATE monitoring_settings SET turbidity_offset = -640, max_turbidity = 100 WHERE id = 1");
echo "Calibration updated: Offset -640, Max Turbidity 100";
?>
