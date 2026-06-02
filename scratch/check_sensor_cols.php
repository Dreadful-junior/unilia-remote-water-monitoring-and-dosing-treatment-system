<?php
require 'db_connect.php';
$res = $conn->query("DESC sensor_data");
$cols = [];
while($row = $res->fetch_assoc()) {
    $cols[] = $row['Field'];
}
echo implode(", ", $cols);
?>
