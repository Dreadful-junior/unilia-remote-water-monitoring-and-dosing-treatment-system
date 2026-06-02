<?php
require 'db_connect.php';
$res = $conn->query("SELECT email, fullname, role FROM users");
while($row = $res->fetch_assoc()) {
    echo "- {$row['email']} ({$row['fullname']}) [{$row['role']}]\n";
}
?>
