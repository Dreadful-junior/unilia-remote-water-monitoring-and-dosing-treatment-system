<?php
require 'db_connect.php';
$res = $conn->query('SELECT * FROM treatment_settings WHERE id = 1');
print_r($res->fetch_assoc());
