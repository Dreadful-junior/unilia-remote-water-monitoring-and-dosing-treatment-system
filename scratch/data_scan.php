<?php
require 'db_connect.php';

$tables = ['sensor_data', 'treatment_logs', 'alerts', 'system_logs', 'maintenance_logs'];
foreach ($tables as $table) {
    $res = $conn->query("SELECT COUNT(*) as count FROM $table");
    $count = $res ? $res->fetch_assoc()['count'] : "Table not found";
    echo "$table: $count\n";
    
    if ($count > 0 && $res) {
        $first = $conn->query("SELECT * FROM $table ORDER BY id ASC LIMIT 1")->fetch_assoc();
        echo "  First entry date: " . ($first['recorded_at'] ?? $first['created_at'] ?? 'N/A') . "\n";
    }
}
?>
