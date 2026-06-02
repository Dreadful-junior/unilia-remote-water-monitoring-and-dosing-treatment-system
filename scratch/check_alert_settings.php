<?php
require 'db_connect.php';
$res = $conn->query("SELECT * FROM alert_settings WHERE id=1");
echo json_encode($res->fetch_assoc(), JSON_PRETTY_PRINT);
?>
