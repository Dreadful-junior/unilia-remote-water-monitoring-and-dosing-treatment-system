<?php
require 'db_connect.php';
$res = $conn->query("DESC hardware_recognition");
$cols = [];
while($row = $res->fetch_assoc()) {
    $cols[] = $row['Field'];
}
echo implode(", ", $cols);
?>
