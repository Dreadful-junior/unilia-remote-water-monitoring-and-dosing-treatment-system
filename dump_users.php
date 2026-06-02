<?php
require 'db_connect.php';
$res = $conn->query("SELECT email FROM users");
while($row = $res->fetch_assoc()) {
    echo "[" . $row['email'] . "]\n";
}
?>
