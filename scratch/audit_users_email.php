<?php
require 'db_connect.php';
$res = $conn->query("SELECT email, is_verified, account_status, role FROM users");
while($row = $res->fetch_assoc()) {
    echo "- {$row['email']}: Verified={$row['is_verified']}, Status={$row['account_status']}, Role={$row['role']}\n";
}
?>
