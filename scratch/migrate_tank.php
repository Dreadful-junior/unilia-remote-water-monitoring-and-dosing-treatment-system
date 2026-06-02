<?php
require 'db_connect.php';
$conn->query("ALTER TABLE treatment_settings ADD COLUMN IF NOT EXISTS tank_height_cm FLOAT DEFAULT 30.0");
$conn->query("ALTER TABLE treatment_settings ADD COLUMN IF NOT EXISTS tank_min_cm FLOAT DEFAULT 5.0");
echo "Columns added successfully.\n";
