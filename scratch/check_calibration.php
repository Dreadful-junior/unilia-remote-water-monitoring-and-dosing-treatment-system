<?php
require 'db_connect.php';
$res = $conn->query("SHOW TABLES LIKE 'sensor_calibration'");
echo "Table exists: " . ($res->num_rows > 0 ? "Yes" : "No") . "\n";
if ($res->num_rows > 0) {
    $res2 = $conn->query("SELECT * FROM sensor_calibration");
    while($row = $res2->fetch_assoc()) {
        print_r($row);
    }
}
