<?php
require 'db_connect.php';
$conn->query("ALTER TABLE hardware_recognition ADD COLUMN IF NOT EXISTS maintenance_note TEXT DEFAULT NULL");
echo "Maintenance note column added.";
