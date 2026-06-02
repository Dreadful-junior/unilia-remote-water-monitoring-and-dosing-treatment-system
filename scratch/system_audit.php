<?php
require 'db_connect.php';

echo "TABLES:\n";
$res = $conn->query("SHOW TABLES");
while($row = $res->fetch_row()) {
    $table = $row[0];
    $count = $conn->query("SELECT COUNT(*) FROM $table")->fetch_row()[0];
    echo "- $table: $count entries\n";
}

echo "\nUSERS:\n";
$res = $conn->query("SELECT id, username, role, email FROM users");
while($row = $res->fetch_assoc()) {
    echo "- ID: {$row['id']}, User: {$row['username']}, Role: {$row['role']}, Email: {$row['email']}\n";
}

echo "\nHARDWARE:\n";
$res = $conn->query("SELECT id, component_name, identifier, status FROM hardware_recognition");
while($row = $res->fetch_assoc()) {
    echo "- ID: {$row['id']}, Name: {$row['component_name']}, ID: {$row['identifier']}, Status: {$row['status']}\n";
}
?>
