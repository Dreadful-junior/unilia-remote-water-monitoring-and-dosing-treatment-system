<?php
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT); // Enable error reporting

include 'db_connect.php';

echo "<h2>Database Setup Status</h2>";
echo "<ul>";

// 1. Create Users Table
$sql_users = "CREATE TABLE IF NOT EXISTS users (
    id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    fullname VARCHAR(100) NOT NULL,
    email VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role VARCHAR(50) DEFAULT 'technician',
    last_login DATETIME NULL,
    reg_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)";

try {
    $conn->query($sql_users);
    echo "<li style='color: green;'>Table 'users' checked/created successfully.</li>";
} catch (Exception $e) {
    echo "<li style='color: red;'>Error checking/creating 'users' table: " . $e->getMessage() . "</li>";
}

// 2. Create Sensor Data Table (for Historical Data)
$sql_sensors = "CREATE TABLE IF NOT EXISTS sensor_data (
    id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    turbidity FLOAT NOT NULL,
    tds FLOAT NOT NULL,
    temperature FLOAT NOT NULL,
    recorded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";

try {
    $conn->query($sql_sensors);
    echo "<li style='color: green;'>Table 'sensor_data' checked/created successfully.</li>";
} catch (Exception $e) {
    echo "<li style='color: red;'>Error checking/creating 'sensor_data' table: " . $e->getMessage() . "</li>";
}

// 3. Create Activity Logs Table
$sql_logs = "CREATE TABLE IF NOT EXISTS activity_logs (
    id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT(6) UNSIGNED,
    action VARCHAR(50) NOT NULL,
    details TEXT,
    ip_address VARCHAR(45),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";

try {
    $conn->query($sql_logs);
    echo "<li style='color: green;'>Table 'activity_logs' checked/created successfully.</li>";
} catch (Exception $e) {
    echo "<li style='color: red;'>Error checking/creating 'activity_logs' table: " . $e->getMessage() . "</li>";
}

// 4. Create Generated Reports Table
$sql_reports = "CREATE TABLE IF NOT EXISTS generated_reports (
    id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    filename VARCHAR(255) NOT NULL,
    type VARCHAR(50) NOT NULL,
    format VARCHAR(10) NOT NULL,
    start_date DATETIME NOT NULL,
    end_date DATETIME NOT NULL,
    generated_by INT(6) UNSIGNED,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (generated_by) REFERENCES users(id) ON DELETE SET NULL
)";

try {
    $conn->query($sql_reports);
    echo "<li style='color: green;'>Table 'generated_reports' checked/created successfully.</li>";
} catch (Exception $e) {
    // If foreign key fails, try without it
    $sql_reports_no_fk = "CREATE TABLE IF NOT EXISTS generated_reports (
        id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        filename VARCHAR(255) NOT NULL,
        type VARCHAR(50) NOT NULL,
        format VARCHAR(10) NOT NULL,
        start_date DATETIME NOT NULL,
        end_date DATETIME NOT NULL,
        generated_by INT(6) UNSIGNED,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )";
    $conn->query($sql_reports_no_fk);
    echo "<li style='color: green;'>Table 'generated_reports' checked/created successfully (without foreign key).</li>";
}

// 5. Insert sample sensor data if table is empty
$check_data = $conn->query("SELECT COUNT(*) as count FROM sensor_data");
$row = $check_data->fetch_assoc();
if ($row['count'] == 0) {
    // Insert sample data for the last 7 days
    $sample_data = [];
    for ($i = 6; $i >= 0; $i--) {
        $date = date('Y-m-d H:i:s', strtotime("-$i days"));
        for ($j = 0; $j < 24; $j++) {
            $hour = date('Y-m-d H:i:s', strtotime($date . " +$j hours"));
            $sample_data[] = [
                'turbidity' => round(rand(10, 50) / 10, 2),
                'tds' => rand(200, 600),
                'temperature' => round(rand(180, 280) / 10, 1),
                'recorded_at' => $hour
            ];
        }
    }

    $stmt = $conn->prepare("INSERT INTO sensor_data (turbidity, tds, temperature, recorded_at) VALUES (?, ?, ?, ?)");
    foreach ($sample_data as $data) {
        $stmt->bind_param("ddds", $data['turbidity'], $data['tds'], $data['temperature'], $data['recorded_at']);
        $stmt->execute();
    }
    $stmt->close();
    echo "<li style='color: green;'>Sample sensor data inserted successfully.</li>";
}

// 6. Create Password Resets Table
$sql_password_resets = "CREATE TABLE IF NOT EXISTS password_resets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) NOT NULL,
    token VARCHAR(255) NOT NULL UNIQUE,
    expires DATETIME NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_token (token),
    INDEX idx_email (email)
)";

try {
    $conn->query($sql_password_resets);
    echo "<li style='color: green;'>Table 'password_resets' checked/created successfully.</li>";
} catch (Exception $e) {
    echo "<li style='color: red;'>Error checking/creating 'password_resets' table: " . $e->getMessage() . "</li>";
}

// 7. Create Alerts Table
$sql_alerts = "CREATE TABLE IF NOT EXISTS alerts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    type VARCHAR(50) NOT NULL,
    severity VARCHAR(20) NOT NULL,
    message TEXT NOT NULL,
    sensor_value FLOAT,
    threshold_value VARCHAR(50),
    is_read TINYINT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_severity (severity),
    INDEX idx_created (created_at),
    INDEX idx_read (is_read)
)";

try {
    $conn->query($sql_alerts);
    echo "<li style='color: green;'>Table 'alerts' checked/created successfully.</li>";
} catch (Exception $e) {
    echo "<li style='color: red;'>Error checking/creating 'alerts' table: " . $e->getMessage() . "</li>";
}

// 8. Create Treatment History Table
$sql_treatment = "CREATE TABLE IF NOT EXISTS treatment_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    cycle_number INT NOT NULL,
    volume FLOAT NOT NULL,
    duration INT NOT NULL,
    flow_rate FLOAT NOT NULL,
    log_type ENUM('auto', 'manual') DEFAULT 'auto',
    voltage FLOAT DEFAULT 24.2,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_created (created_at)
)";

try {
    $conn->query($sql_treatment);
    echo "<li style='color: green;'>Table 'treatment_logs' checked/created successfully (History).</li>";

    // Insert sample treatment data if empty
    $check_treatment = $conn->query("SELECT COUNT(*) as count FROM treatment_logs");
    $tr_row = $check_treatment->fetch_assoc();
    if ($tr_row['count'] == 0) {
        $sample_treatments = [
            [402, 250, 45, 5.5, 'auto', 24.2, date('Y-m-d H:i:s', strtotime('-1 hour'))],
            [401, 250, 42, 5.6, 'auto', 24.1, date('Y-m-d H:i:s', strtotime('-3 hours'))],
            [400, 1200, 180, 6.7, 'manual', 24.3, date('Y-m-d H:i:s', strtotime('-5 hours'))]
        ];

        $tr_stmt = $conn->prepare("INSERT INTO treatment_logs (cycle_number, volume, duration, flow_rate, log_type, voltage, created_at) VALUES (?, ?, ?, ?, ?, ?, ?)");
        foreach ($sample_treatments as $tr) {
            $tr_stmt->bind_param("ididsss", $tr[0], $tr[1], $tr[2], $tr[3], $tr[4], $tr[5], $tr[6]);
            $tr_stmt->execute();
        }
        $tr_stmt->close();
        echo "<li style='color: green;'>Sample treatment history inserted successfully.</li>";
    }
} catch (Exception $e) {
    echo "<li style='color: red;'>Error checking/creating 'treatment_logs' table: " . $e->getMessage() . "</li>";
}

// 8. Add ph and chlorine columns to sensor_data if they don't exist
try {
    $check_ph = $conn->query("SHOW COLUMNS FROM sensor_data LIKE 'ph'");
    if ($check_ph->num_rows == 0) {
        $conn->query("ALTER TABLE sensor_data ADD COLUMN ph FLOAT NULL AFTER temperature");
        echo "<li style='color: green;'>Added 'ph' column to sensor_data table.</li>";
    }

    $check_chlorine = $conn->query("SHOW COLUMNS FROM sensor_data LIKE 'chlorine'");
    if ($check_chlorine->num_rows == 0) {
        $conn->query("ALTER TABLE sensor_data ADD COLUMN chlorine FLOAT NULL AFTER ph");
        echo "<li style='color: green;'>Added 'chlorine' column to sensor_data table.</li>";
    }

    $check_distance = $conn->query("SHOW COLUMNS FROM sensor_data LIKE 'distance_cm'");
    if ($check_distance->num_rows == 0) {
        $conn->query("ALTER TABLE sensor_data ADD COLUMN distance_cm FLOAT NULL AFTER chlorine");
        echo "<li style='color: green;'>Added 'distance_cm' column to sensor_data table.</li>";
    }

    $check_water = $conn->query("SHOW COLUMNS FROM sensor_data LIKE 'water_level'");
    if ($check_water->num_rows == 0) {
        $conn->query("ALTER TABLE sensor_data ADD COLUMN water_level FLOAT NULL DEFAULT 0.0 AFTER distance_cm");
        echo "<li style='color: green;'>Added 'water_level' column to sensor_data table.</li>";
    }

    // 9. Insert sample alerts if table is empty
    $check_alerts = $conn->query("SELECT COUNT(*) as count FROM alerts");
    $alert_row = $check_alerts->fetch_assoc();
    if ($alert_row['count'] == 0) {
        $sample_alerts = [
            ['system', 'info', 'System successfully initialized and sensors calibrated.', 0, 'N/A'],
            ['turbidity', 'warning', 'Slightly high turbidity detected (4.2 NTU). Filtering increased.', 4.2, '5.0'],
            ['pump', 'success', 'Dosing pump activated automatically for water treatment.', 1, '1'],
            ['tds', 'critical', 'High TDS reading detected (850 PPM). System check recommended.', 850, '500'],
            ['system', 'info', 'Water storage level at 75% capacity.', 75, '100']
        ];

        $alert_stmt = $conn->prepare("INSERT INTO alerts (type, severity, message, sensor_value, threshold_value) VALUES (?, ?, ?, ?, ?)");
        foreach ($sample_alerts as $alert) {
            $alert_stmt->bind_param("sssds", $alert[0], $alert[1], $alert[2], $alert[3], $alert[4]);
            $alert_stmt->execute();
        }
        $alert_stmt->close();
        echo "<li style='color: green;'>Sample alerts inserted successfully.</li>";
    }
} catch (Exception $e) {
    echo "<li style='color: orange;'>Note: Database alteration or sample insertion issue: " . $e->getMessage() . "</li>";
}

echo "</ul>";
echo "<p>Setup complete. You can now <a href='signup.php'>Go to Sign Up</a> or <a href='reports.php'>Generate Reports</a>.</p>";

$conn->close();
?>
