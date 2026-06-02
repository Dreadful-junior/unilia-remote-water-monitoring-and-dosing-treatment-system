<?php
require 'db_connect.php';
$conn->query("ALTER TABLE monitoring_settings ADD COLUMN IF NOT EXISTS tank_min_cm FLOAT DEFAULT 5.0");
echo "Column added to monitoring_settings.\n";
