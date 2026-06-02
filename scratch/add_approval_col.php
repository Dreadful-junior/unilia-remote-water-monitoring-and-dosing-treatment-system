<?php
require 'db_connect.php';
$conn->query("ALTER TABLE users ADD COLUMN IF NOT EXISTS is_approved TINYINT DEFAULT 0");
echo "Column added successfully.";
