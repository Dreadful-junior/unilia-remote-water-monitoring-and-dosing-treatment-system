<?php
require 'db_connect.php';
$check = $conn->query("SHOW COLUMNS FROM hardware_settings LIKE 'active_chemical'");
if ($check->num_rows == 0) {
    if ($conn->query("ALTER TABLE hardware_settings ADD COLUMN active_chemical VARCHAR(50) DEFAULT 'Chlorine' AFTER operation_mode")) {
        echo "Column active_chemical added successfully.";
    } else {
        echo "Error: " . $conn->error;
    }
} else {
    echo "Column already exists.";
}
?>
