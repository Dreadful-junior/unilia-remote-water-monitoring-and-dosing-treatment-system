<?php
require 'db_connect.php';
$res = $conn->query("SELECT email, login_attempts, last_attempt_time FROM users WHERE email = 'admin@admin.com'");
print_r($res->fetch_assoc());
