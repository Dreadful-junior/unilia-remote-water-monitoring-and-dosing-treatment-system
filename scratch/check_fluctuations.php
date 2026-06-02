<?php
require 'db_connect.php';
$res = $conn->query("SELECT turbidity, tds, recorded_at FROM sensor_data ORDER BY recorded_at DESC LIMIT 10");
while($row = $res->fetch_assoc()) {
    echo "{$row['recorded_at']} - Turb: {$row['turbidity']}, TDS: {$row['tds']}\n";
}
?>
