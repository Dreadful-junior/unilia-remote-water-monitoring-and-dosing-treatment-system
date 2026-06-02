<?php
require 'db_connect.php';
$res = $conn->query("SELECT tank_height_cm, tank_min_cm FROM monitoring_settings WHERE id = 1");
print_r($res->fetch_assoc());
