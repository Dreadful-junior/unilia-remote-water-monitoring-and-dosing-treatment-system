<?php
require 'db_connect.php';
$res = $conn->query("SELECT tank_height_cm, tank_min_cm FROM monitoring_settings WHERE id = 1");
echo json_encode($res->fetch_assoc(), JSON_PRETTY_PRINT);
?>
