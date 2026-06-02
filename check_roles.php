<?php
require 'db_connect.php';
$res = $conn->query("SELECT DISTINCT role FROM users");
while ($row = $res->fetch_assoc()) {
    echo $row['role'] . "\n";
}
?>
