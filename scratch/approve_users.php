<?php
require 'db_connect.php';
$conn->query("UPDATE users SET is_approved = 1 WHERE is_verified = 1");
echo "All verified users approved.";
