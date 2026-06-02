<?php
require 'db_connect.php';

// Update email column length
$conn->query("ALTER TABLE users MODIFY email VARCHAR(254) NOT NULL");

// Add verification columns
$conn->query("ALTER TABLE users ADD COLUMN IF NOT EXISTS is_verified TINYINT(1) DEFAULT 0");
$conn->query("ALTER TABLE users ADD COLUMN IF NOT EXISTS verification_token VARCHAR(255) DEFAULT NULL");

echo "Users table updated successfully.\n";
