<?php
require 'db_connect.php';
$res = $conn->query("SELECT identifier, status, last_seen, ip_address FROM hardware_recognition");
while($row = $res->fetch_assoc()) {
    echo "ID: " . $row['identifier'] . " | Status: " . $row['status'] . " | Last Seen: " . $row['last_seen'] . " | IP: " . $row['ip_address'] . "\n";
}
?>
