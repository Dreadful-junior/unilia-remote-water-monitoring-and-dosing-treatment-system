<?php
require 'db_connect.php';
$conn->query("UPDATE users SET role = 'manager' WHERE role = 'admin'");
if ($conn->affected_rows > 0) {
    echo "Updated " . $conn->affected_rows . " users to 'manager'.\n";
} else {
    echo "No 'admin' users found to update.\n";
}
?>
