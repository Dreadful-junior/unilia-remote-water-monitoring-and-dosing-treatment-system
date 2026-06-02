<?php
require 'db_connect.php';
$res = $conn->query("SELECT id, email, role, is_approved, is_verified FROM users");
echo "--- User Table ---\n";
while($row = $res->fetch_assoc()) {
    print_r($row);
}
