<?php
include 'db_connect.php';

echo "<h2>Database Schema Fix</h2>";

// Check current table structure
echo "<h3>Current sensor_data table structure:</h3>";
$result = $conn->query("DESCRIBE sensor_data");
echo "<pre>";
while($row = $result->fetch_assoc()) {
    echo $row['Field'] . " - " . $row['Type'] . " - " . ($row['Null'] == 'YES' ? 'NULL' : 'NOT NULL') . "\n";
}
echo "</pre>";

// Add missing columns
$columns_to_add = [
    'ph' => 'FLOAT NULL AFTER temperature',
    'chlorine' => 'FLOAT NULL AFTER ph',
    'distance_cm' => 'FLOAT NULL AFTER chlorine',
    'water_level' => 'FLOAT NULL DEFAULT 0.0 AFTER distance_cm'
];

echo "<h3>Adding missing columns:</h3>";
foreach ($columns_to_add as $column => $definition) {
    $check = $conn->query("SHOW COLUMNS FROM sensor_data LIKE '$column'");
    if ($check && $check->num_rows === 0) {
        try {
            $conn->query("ALTER TABLE sensor_data ADD COLUMN $column $definition");
            echo "<p style='color: green;'>✓ Added column: $column</p>";
        } catch (Exception $e) {
            echo "<p style='color: red;'>✗ Error adding column $column: " . $e->getMessage() . "</p>";
        }
    } else {
        echo "<p style='color: blue;'>- Column $column already exists</p>";
    }
}

echo "<h3>Final table structure:</h3>";
$result = $conn->query("DESCRIBE sensor_data");
echo "<pre>";
while($row = $result->fetch_assoc()) {
    echo $row['Field'] . " - " . $row['Type'] . " - " . ($row['Null'] == 'YES' ? 'NULL' : 'NOT NULL') . "\n";
}
echo "</pre>";

$conn->close();
?>
