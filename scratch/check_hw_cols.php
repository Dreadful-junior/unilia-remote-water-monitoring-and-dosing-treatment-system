<?php
require 'db_connect.php';
$res = $conn->query("DESC hardware_settings");
$cols = [];
while($row = $res->fetch_assoc()) {
    $cols[] = $row['Field'];
}
echo implode(", ", $cols);
?>
