<?php
require 'db_connect.php';
$res = $conn->query("SELECT id, recorded_at FROM sensor_data ORDER BY id DESC LIMIT 5");
while($row = $res->fetch_assoc()) {
    echo "ID: " . $row['id'] . " | Time: " . $row['recorded_at'] . "\n";
}
?>
