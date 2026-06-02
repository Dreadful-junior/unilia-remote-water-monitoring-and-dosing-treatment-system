<?php
// Test script to check database schema and API
include 'db_connect.php';

echo "<h2>Database Schema Check</h2>";

// Check current table structure
echo "<h3>Current sensor_data table structure:</h3>";
$result = $conn->query("DESCRIBE sensor_data");
echo "<table border='1'><tr><th>Field</th><th>Type</th><th>Null</th><th>Default</th></tr>";
while($row = $result->fetch_assoc()) {
    echo "<tr><td>" . $row['Field'] . "</td><td>" . $row['Type'] . "</td><td>" . $row['Null'] . "</td><td>" . ($row['Default'] ?? 'NULL') . "</td></tr>";
}
echo "</table>";

// Test API call
echo "<h3>Testing API with sample data:</h3>";
$data = [
    'api_key' => 'your-secret-api-key-123',
    'temperature' => 25.5,
    'turbidity' => 5.2,
    'tds' => 150.0,
    'distance_cm' => 20.5,
    'water_level' => 75.0
];

$ch = curl_init('http://localhost/water%20system/api/receive.php');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "<p>HTTP Code: $http_code</p>";
echo "<p>Response: $response</p>";

$conn->close();
?>
