<?php
require 'db_connect.php';
$res = $conn->query("SHOW COLUMNS FROM sensor_data");
while($row = $res->fetch_assoc()) {
    print_r($row);
}
