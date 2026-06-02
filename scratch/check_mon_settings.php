<?php
require 'db_connect.php';
$res = $conn->query("SHOW COLUMNS FROM monitoring_settings");
while($row = $res->fetch_assoc()) {
    print_r($row);
}
