<?php
require 'db_connect.php';
$conn->query("UPDATE users SET is_verified = 1 WHERE email IN ('felixsaiwala4@gmail.com', 'mwahimbaalinuswe@gmail.com')");
echo "Updated " . $conn->affected_rows . " users to VERIFIED status.";
?>
