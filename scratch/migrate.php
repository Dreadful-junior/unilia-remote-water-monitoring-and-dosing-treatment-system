<?php
require 'db_connect.php';
$conn->query("ALTER TABLE treatment_logs ADD COLUMN IF NOT EXISTS trigger_reason VARCHAR(255) DEFAULT NULL AFTER log_type");
echo "Done\n";
