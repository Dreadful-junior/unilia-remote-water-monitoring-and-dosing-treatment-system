<?php
require 'db_connect.php';

// Add login attempt tracking columns
$conn->query("ALTER TABLE users ADD COLUMN IF NOT EXISTS login_attempts INT DEFAULT 0");
$conn->query("ALTER TABLE users ADD COLUMN IF NOT EXISTS last_attempt_time DATETIME DEFAULT NULL");

echo "Users table updated for login attempt tracking.\n";
