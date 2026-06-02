<?php
require 'db_connect.php';
$res = $conn->query('SELECT * FROM hardware_settings');
print_r($res->fetch_assoc());
?>
