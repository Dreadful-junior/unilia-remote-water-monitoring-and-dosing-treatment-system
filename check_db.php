<?php
require 'db_connect.php';

echo "<h2>Database Status Check</h2>";

// Check if tables exist
$tables = ['users', 'password_resets', 'sensor_data'];
foreach ($tables as $table) {
    $result = $conn->query("SHOW TABLES LIKE '$table'");
    if ($result->num_rows > 0) {
        echo "<span style='color:green'>✓ Table '$table' exists.</span><br>";
        
        $count_res = $conn->query("SELECT COUNT(*) as cnt FROM $table");
        $count = $count_res->fetch_assoc()['cnt'];
        echo " - Rows: $count<br>";
    } else {
        echo "<span style='color:red'>✗ Table '$table' MISSING!</span><br>";
    }
}

// Check users
$users_res = $conn->query("SELECT email, fullname FROM users LIMIT 10");
echo "<h3>Registered Emails (First 10):</h3><ul>";
while($u = $users_res->fetch_assoc()) {
    echo "<li>" . htmlspecialchars($u['email']) . " (" . htmlspecialchars($u['fullname']) . ")</li>";
}
echo "</ul>";

// Check recent resets
$resets_res = $conn->query("SELECT email, expires FROM password_resets ORDER BY expires DESC LIMIT 5");
echo "<h3>Recent Reset Requests:</h3><ul>";
while($r = $resets_res->fetch_assoc()) {
    echo "<li>" . htmlspecialchars($r['email']) . " - Expires: " . $r['expires'] . "</li>";
}
echo "</ul>";
?>
