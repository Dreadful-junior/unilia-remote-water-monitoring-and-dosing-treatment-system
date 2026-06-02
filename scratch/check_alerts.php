<?php
require 'db_connect.php';
$res = $conn->query("SHOW COLUMNS FROM alerts LIKE 'severity'");
print_r($res->fetch_assoc());
