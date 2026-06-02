<?php
require 'db_connect.php';
$res = $conn->query("SHOW COLUMNS FROM alert_settings");
while ($row = $res->fetch_assoc()) {
    echo $row['Field'] . "\n";
}
echo "----\n";
$res = $conn->query("SHOW COLUMNS FROM treatment_logs");
while ($row = $res->fetch_assoc()) {
    echo $row['Field'] . "\n";
}
