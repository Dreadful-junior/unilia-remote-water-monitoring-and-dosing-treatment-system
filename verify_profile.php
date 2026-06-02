<?php
session_start();
$_SESSION['user_id'] = 1; // Assuming user with ID 1 exists
require 'db_connect.php';

// Prepare data for simulated update
$_POST['fullname'] = 'Dalitso Phiri';
$_POST['email'] = 'dalitso@unilia.mw';
$_POST['new_password'] = ''; // Keep same

// Execute update API logic (simplified for test)
ob_start();
chdir('api');
include 'user_update.php';
chdir('..');
$output = ob_get_clean();

echo "API Response: " . $output . "\n";

// Verify DB
$res = $conn->query("SELECT fullname, email FROM users WHERE id = 1");
$user = $res->fetch_assoc();
echo "Updated Fullname: " . $user['fullname'] . "\n";
echo "Updated Email: " . $user['email'] . "\n";
?>
