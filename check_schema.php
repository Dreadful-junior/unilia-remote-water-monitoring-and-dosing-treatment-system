<?php
require 'db_connect.php';
$res = $conn->query("DESC hardware_settings");
$cols = [];
while($row = $res->fetch_assoc()) {
    $cols[] = $row;
}
echo json_encode($cols, JSON_PRETTY_PRINT);
?>
