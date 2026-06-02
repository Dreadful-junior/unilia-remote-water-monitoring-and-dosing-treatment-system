<?php
require 'db_connect.php';

$res = $conn->query("SELECT * FROM sensor_data ORDER BY recorded_at DESC LIMIT 1");
$latest = $res->fetch_assoc();

header('Content-Type: application/json');
echo json_encode($latest, JSON_PRETTY_PRINT);
?>
