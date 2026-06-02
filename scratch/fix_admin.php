<?php
require 'db_connect.php';

// Find admin users
$res = $conn->query("SELECT id, email, fullname FROM users WHERE role = 'admin'");
while($user = $res->fetch_assoc()) {
    $id = $user['id'];
    echo "Verifying Admin: " . $user['fullname'] . " (" . $user['email'] . ")\n";
    $conn->query("UPDATE users SET is_verified = 1, verification_token = NULL WHERE id = $id");
}

// Also verify any user with 'admin' in email just in case
$res = $conn->query("SELECT id, email FROM users WHERE email LIKE '%admin%' AND is_verified = 0");
while($user = $res->fetch_assoc()) {
    $id = $user['id'];
    echo "Verifying Email-based Admin: " . $user['email'] . "\n";
    $conn->query("UPDATE users SET is_verified = 1, verification_token = NULL WHERE id = $id");
}

echo "Done.\n";
