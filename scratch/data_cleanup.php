<?php
require 'db_connect.php';

echo "Cleaning up dummy data (Pre-launch data before 2026-05-12)...\n";

$cutoff = '2026-05-12 00:00:00';

// 1. Sensor Data
$res = $conn->query("DELETE FROM sensor_data WHERE recorded_at < '$cutoff'");
echo "- Deleted " . $conn->affected_rows . " old sensor_data entries.\n";

// 2. Treatment Logs
$res = $conn->query("DELETE FROM treatment_logs WHERE created_at < '$cutoff'");
echo "- Deleted " . $conn->affected_rows . " old treatment_logs entries.\n";

// 3. Alerts
$res = $conn->query("DELETE FROM alerts WHERE created_at < '$cutoff'");
echo "- Deleted " . $conn->affected_rows . " old alerts entries.\n";

// 4. Activity Logs
$res = $conn->query("DELETE FROM activity_logs WHERE created_at < '$cutoff'");
echo "- Deleted " . $conn->affected_rows . " old activity_logs entries.\n";

// 5. Pump Commands (Clear history)
$res = $conn->query("DELETE FROM pump_commands WHERE status != 'pending'");
echo "- Cleared " . $conn->affected_rows . " processed pump_commands.\n";

// 6. Generated Reports
$res = $conn->query("DELETE FROM generated_reports");
echo "- Cleared " . $conn->affected_rows . " dummy reports.\n";

// 7. Reset IDs (Optional, but makes it cleaner)
$conn->query("ALTER TABLE sensor_data AUTO_INCREMENT = 1");
$conn->query("ALTER TABLE treatment_logs AUTO_INCREMENT = 1");
$conn->query("ALTER TABLE alerts AUTO_INCREMENT = 1");

echo "Cleanup complete! The system is now fresh and ready for professional use.\n";
?>
