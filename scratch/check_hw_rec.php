<?php
require 'db_connect.php';
$res = $conn->query("SHOW COLUMNS FROM hardware_recognition");
while($row = $res->fetch_assoc()) {
    print_r($row);
}
