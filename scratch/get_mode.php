<?php
require 'db_connect.php';
$hw = $conn->query("SELECT operation_mode FROM hardware_settings WHERE id=1")->fetch_assoc();
echo "MODE:" . $hw['operation_mode'];
?>
